<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
$user = require_login();
ensure_station_pricing_schema($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$stationId = (int) ($_POST['station_id'] ?? 0);
$action = $_POST['action'] ?? '';
$returnZone = (int) ($_POST['return_zone'] ?? 0);
$returnStationId = (int) ($_POST['return_station_id'] ?? 0);
$redirectTo = 'dashboard.php' . ($returnZone ? '?zone=' . $returnZone : '');
if ($returnStationId > 0) {
    $redirectTo = 'station_session.php?station_id=' . $returnStationId;
}

$stmt = mysqli_prepare($conn, 'SELECT * FROM stations WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $stationId);
mysqli_stmt_execute($stmt);
$station = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$station) {
    flash_set('error', 'Station not found.');
    redirect($redirectTo);
}

if ($action === 'start') {
    if ($station['status'] !== 'FREE') {
        flash_set('error', 'That station is not available to start a new session.');
        redirect($redirectTo);
    }

    $playerCount = (int) ($_POST['player_count'] ?? 0);
    if ($playerCount !== 2 && $playerCount !== 4) {
        flash_set('error', 'Please choose 2 or 4 players.');
        redirect($redirectTo);
    }

    $rate = station_price_for_player_count($station, $playerCount);
    $settings = get_site_settings($conn);
    $sessionFee = session_start_fee($settings);
    $now = (int) round(microtime(true) * 1000);
    $stmt = mysqli_prepare($conn, "UPDATE stations SET status = 'BUSY', session_start = ?, current_player_count = ?, current_rate_per_hour = ?, current_session_fee = ?, pause_started_at = NULL, paused_duration_ms = 0 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'iiddi', $now, $playerCount, $rate, $sessionFee, $stationId);
    mysqli_stmt_execute($stmt);
    log_audit_event($conn, $user['id'], 'session_started', 'Started session at ' . $station['name'] . ' for ' . $playerCount . ' players at ' . number_format($rate, 2) . '/hr plus ' . number_format($sessionFee, 2) . ' session fee', 'station', $stationId);
    flash_set('success', 'Session started on ' . $station['name'] . ' for ' . $playerCount . ' players at ' . number_format($rate, 2) . '/hr (+' . number_format($sessionFee, 2) . ' fee).');
} elseif ($action === 'pause') {
    if ($station['status'] !== 'BUSY' || !$station['session_start']) {
        flash_set('error', 'There is no active session to pause.');
        redirect($redirectTo);
    }
    if ($station['pause_started_at']) {
        flash_set('error', 'This session is already paused.');
        redirect($redirectTo);
    }
    $now = (int) round(microtime(true) * 1000);
    $stmt = mysqli_prepare($conn, "UPDATE stations SET status = 'PAUSED', pause_started_at = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $now, $stationId);
    mysqli_stmt_execute($stmt);
    log_audit_event($conn, $user['id'], 'session_paused', 'Paused session at ' . $station['name'], 'station', $stationId);
    flash_set('success', 'Session paused.');
} elseif ($action === 'resume') {
    if ($station['status'] !== 'PAUSED' || !$station['pause_started_at']) {
        flash_set('error', 'There is no paused session to resume.');
        redirect($redirectTo);
    }
    $now = (int) round(microtime(true) * 1000);
    $pausedDuration = max(0, $now - (int) $station['pause_started_at']);
    $stmt = mysqli_prepare($conn, "UPDATE stations SET status = 'BUSY', paused_duration_ms = paused_duration_ms + ?, pause_started_at = NULL WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $pausedDuration, $stationId);
    mysqli_stmt_execute($stmt);
    log_audit_event($conn, $user['id'], 'session_resumed', 'Resumed session at ' . $station['name'], 'station', $stationId);
    flash_set('success', 'Session resumed.');
} elseif ($action === 'end') {
    if (!in_array($station['status'], ['BUSY', 'PAUSED'], true) || !$station['session_start']) {
        flash_set('error', 'That station has no active session to end.');
        redirect($redirectTo);
    }

    $now = (int) round(microtime(true) * 1000);
    $startMs = (int) $station['session_start'];
    $pausedMs = isset($station['paused_duration_ms']) ? (int) $station['paused_duration_ms'] : 0;
    if (!empty($station['pause_started_at'])) {
        $pausedMs += max(0, $now - (int) $station['pause_started_at']);
    }
    $durationMinutes = max(0, (int) round(max(0, $now - $startMs - $pausedMs) / 60000));
    $hours = $durationMinutes / 60;
    $playerCount = isset($station['current_player_count']) && (int) $station['current_player_count'] > 0 ? (int) $station['current_player_count'] : 2;
    $rate = isset($station['current_rate_per_hour']) && (float) $station['current_rate_per_hour'] > 0 ? (float) $station['current_rate_per_hour'] : station_price_for_player_count($station, $playerCount);
    $sessionFee = isset($station['current_session_fee']) ? (float) $station['current_session_fee'] : 0.0;
    $sessionPrice = round($hours * $rate, 2);
    $sessionNotes = trim((string) ($_POST['session_notes'] ?? ''));
    $receiptNumber = trim((string) ($_POST['receipt_number'] ?? ''));

    // Snapshot itemized snacks ordered during this session (active_orders gets cleared below).
    $snackItems = [];
    $stmt = mysqli_prepare($conn, 'SELECT snack_name, price, quantity FROM active_orders WHERE station_id = ? ORDER BY created_at ASC');
    mysqli_stmt_bind_param($stmt, 'i', $stationId);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    $snackTotal = 0.0;
    while ($row = mysqli_fetch_assoc($r)) {
        $lineTotal = (float) $row['price'] * (int) $row['quantity'];
        $snackTotal += $lineTotal;
        $snackItems[] = [
            'name' => $row['snack_name'],
            'price' => (float) $row['price'],
            'quantity' => (int) $row['quantity'],
            'line_total' => round($lineTotal, 2),
        ];
    }
    $snacksSnapshot = json_encode($snackItems);

    $totalPrice = $sessionPrice + $sessionFee + $snackTotal;
    $startedAt = date('Y-m-d H:i:s', (int) ($startMs / 1000));
    $endedAt = date('Y-m-d H:i:s', (int) ($now / 1000));
    $newSessionId = 0;

    $conn->begin_transaction();
    try {
        $stmt = mysqli_prepare($conn, 'INSERT INTO sessions
            (station_id, station_name, started_at, ended_at, duration_minutes, session_price, pricing_rate_per_hour, session_fee, player_count, snacks_total, total_price, notes, receipt_number, snacks_snapshot, closed_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param(
            $stmt,
            'isssidddiddsssi',
            $stationId,
            $station['name'],
            $startedAt,
            $endedAt,
            $durationMinutes,
            $sessionPrice,
            $rate,
            $sessionFee,
            $playerCount,
            $snackTotal,
            $totalPrice,
            $sessionNotes,
            $receiptNumber,
            $snacksSnapshot,
            $user['id']
        );
        mysqli_stmt_execute($stmt);
        $newSessionId = (int) mysqli_insert_id($conn);

        $stmt = mysqli_prepare($conn, 'DELETE FROM active_orders WHERE station_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $stationId);
        mysqli_stmt_execute($stmt);

        $stmt = mysqli_prepare($conn, "UPDATE stations SET status = 'FREE', session_start = NULL, current_player_count = NULL, current_rate_per_hour = NULL, pause_started_at = NULL, paused_duration_ms = 0, current_session_notes = NULL, current_session_fee = 0 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $stationId);
        mysqli_stmt_execute($stmt);

        $conn->commit();
        log_audit_event($conn, $user['id'], 'session_ended', 'Ended session at ' . $station['name'] . ' for ' . $playerCount . ' players', 'station', $stationId);
        flash_set('success', 'Session ended. Total: ' . number_format($totalPrice, 2) . ' (' . $durationMinutes . ' min).');
        if ($newSessionId > 0) {
            redirect('bill.php?session_id=' . $newSessionId);
        }
    } catch (Throwable $e) {
        $conn->rollback();
        flash_set('error', 'Could not end session: ' . $e->getMessage());
    }
} elseif ($action === 'position') {
    // Drag-to-reposition (admin only), called via the small JS helper.
    if (!is_admin()) {
        flash_set('error', 'Only admins can move stations.');
        redirect($redirectTo);
    }
    $posX = (int) ($_POST['pos_x'] ?? 0);
    $posY = (int) ($_POST['pos_y'] ?? 0);
    $stmt = mysqli_prepare($conn, 'UPDATE stations SET pos_x = ?, pos_y = ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'iii', $posX, $posY, $stationId);
    mysqli_stmt_execute($stmt);
    echo json_encode(['status' => 'ok']);
    exit;
} else {
    flash_set('error', 'Unknown action.');
}

redirect($redirectTo);
