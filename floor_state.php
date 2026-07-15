<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
require_once __DIR__ . '/includes/flash.php';

ensure_station_pricing_schema($conn);
$settings = get_site_settings($conn);

function station_active_orders_total(mysqli $conn, int $stationId): float
{
    $stmt = mysqli_prepare($conn, 'SELECT COALESCE(SUM(price * quantity), 0) AS total FROM active_orders WHERE station_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $stationId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return (float) $row['total'];
}

function station_elapsed_label(array $station): string
{
    if (empty($station['session_start'])) {
        return 'Free';
    }

    $nowMs = (int) round(microtime(true) * 1000);
    $startMs = (int) $station['session_start'];
    $pausedMs = isset($station['paused_duration_ms']) ? (int) $station['paused_duration_ms'] : 0;
    if (!empty($station['pause_started_at'])) {
        $pausedMs += max(0, $nowMs - (int) $station['pause_started_at']);
    }

    $elapsedSec = max(0, (int) floor(($nowMs - $startMs - $pausedMs) / 1000));
    $h = (int) floor($elapsedSec / 3600);
    $m = (int) floor(($elapsedSec % 3600) / 60);
    $s = $elapsedSec % 60;
    $parts = [];
    if ($h) {
        $parts[] = $h . 'h';
    }
    $parts[] = $m . 'm';
    $parts[] = $s . 's';

    $statusLabel = $station['status'] === 'PAUSED' ? 'Paused' : 'Busy';
    return $statusLabel . ' ' . implode(' ', $parts);
}

$zoneId = isset($_GET['zone']) ? (int) $_GET['zone'] : 0;
$stations = [];
if ($zoneId > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM stations WHERE zone_id = ? ORDER BY name ASC');
    mysqli_stmt_bind_param($stmt, 'i', $zoneId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $stationId = (int) $row['id'];
        $isActive = in_array($row['status'], ['BUSY', 'PAUSED'], true);
        $sessionStart = isset($row['session_start']) ? (int) $row['session_start'] : 0;

        $currentRate = isset($row['current_rate_per_hour']) && (float) $row['current_rate_per_hour'] > 0
            ? (float) $row['current_rate_per_hour']
            : station_price_for_player_count($row, isset($row['current_player_count']) && (int) $row['current_player_count'] > 0 ? (int) $row['current_player_count'] : 2);

        $currentBudget = 0.0;
        $warningLabel = '';
        if ($isActive && $sessionStart) {
            $nowMs = (int) round(microtime(true) * 1000);
            $pausedMs = isset($row['paused_duration_ms']) ? (int) $row['paused_duration_ms'] : 0;
            if (!empty($row['pause_started_at'])) {
                $pausedMs += max(0, $nowMs - (int) $row['pause_started_at']);
            }
            $elapsedMinutes = max(0, (int) floor(($nowMs - $sessionStart - $pausedMs) / 60000));
            $sessionFee = isset($row['current_session_fee']) ? (float) $row['current_session_fee'] : 0.0;
            $currentBudget = ($currentRate * ($elapsedMinutes / 60)) + station_active_orders_total($conn, $stationId) + $sessionFee;
            $budgetThreshold = (float) ($settings['session_budget_warning'] ?? 0);
            $durationThreshold = (int) ($settings['session_duration_warning_minutes'] ?? 0);
            if ($budgetThreshold > 0 && $currentBudget >= $budgetThreshold) {
                $warningLabel = 'BUDGET ALERT';
            }
            if ($durationThreshold > 0 && $elapsedMinutes >= $durationThreshold) {
                $warningLabel = trim($warningLabel ? $warningLabel . ' / ' : '') . 'TIME ALERT';
            }
        }

        $stations[] = [
            'id' => $stationId,
            'status' => $row['status'],
            'session_start' => $sessionStart,
            'pause_started_at' => isset($row['pause_started_at']) ? (int) $row['pause_started_at'] : 0,
            'paused_duration_ms' => isset($row['paused_duration_ms']) ? (int) $row['paused_duration_ms'] : 0,
            'elapsed_label' => $isActive ? station_elapsed_label($row) : 'Free',
            'current_budget' => round($currentBudget, 2),
            'current_rate_per_hour' => round($currentRate, 2),
            'current_player_count' => isset($row['current_player_count']) ? (int) $row['current_player_count'] : 0,
            'warning_label' => $warningLabel,
        ];
    }
}

header('Content-Type: application/json');
echo json_encode(['stations' => $stations]);
