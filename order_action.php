<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$stationId = (int) ($_POST['station_id'] ?? 0);
$snackId = (int) ($_POST['snack_id'] ?? 0);
$quantity = max(1, (int) ($_POST['quantity'] ?? 1));
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

if (!$station || $station['status'] !== 'BUSY') {
    flash_set('error', 'That station has no active session.');
    redirect($redirectTo);
}

$stmt = mysqli_prepare($conn, 'SELECT * FROM snacks WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $snackId);
mysqli_stmt_execute($stmt);
$snack = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$snack) {
    flash_set('error', 'Snack not found.');
    redirect($redirectTo);
}
if ((int) $snack['stock'] < $quantity) {
    flash_set('error', 'Not enough stock for ' . $snack['name'] . '.');
    redirect($redirectTo);
}

$conn->begin_transaction();
try {
    $stmt = mysqli_prepare($conn, 'INSERT INTO active_orders (station_id, snack_id, snack_name, price, quantity) VALUES (?, ?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'iisdi', $stationId, $snackId, $snack['name'], $snack['price'], $quantity);
    mysqli_stmt_execute($stmt);

    $stmt = mysqli_prepare($conn, 'UPDATE snacks SET stock = stock - ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $quantity, $snackId);
    mysqli_stmt_execute($stmt);

    $conn->commit();
    flash_set('success', 'Added ' . $quantity . 'x ' . $snack['name'] . ' to ' . $station['name'] . '.');
} catch (Throwable $e) {
    $conn->rollback();
    flash_set('error', 'Could not add snack: ' . $e->getMessage());
}

redirect($redirectTo);
