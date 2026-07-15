<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
$user = require_login();
$page = 'reservations';
$pageTitle = 'Reservations';
ensure_station_pricing_schema($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'add') {
    $stationId = (int) ($_POST['station_id'] ?? 0);
    $customerName = trim($_POST['customer_name'] ?? '');
    $startAt = trim($_POST['start_at'] ?? '');
    $endAt = trim($_POST['end_at'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? 'PENDING';

    if ($stationId <= 0 || $customerName === '' || $startAt === '' || $endAt === '') {
        flash_set('error', 'Please provide a station, customer, and booking time range.');
    } else {
        $stmt = mysqli_prepare($conn, 'INSERT INTO reservations (station_id, customer_name, start_at, end_at, notes, status) VALUES (?, ?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'isssss', $stationId, $customerName, $startAt, $endAt, $notes, $status);
        mysqli_stmt_execute($stmt);
        log_audit_event($conn, $user['id'], 'reservation_created', 'Created reservation for ' . $customerName, 'reservation', mysqli_insert_id($conn));
        flash_set('success', 'Reservation created.');
    }
    redirect('reservations.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'update_status') {
    $id = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? 'PENDING';
    $stmt = mysqli_prepare($conn, 'UPDATE reservations SET status = ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'si', $status, $id);
    mysqli_stmt_execute($stmt);
    log_audit_event($conn, $user['id'], 'reservation_updated', 'Reservation status updated', 'reservation', $id);
    flash_set('success', 'Reservation updated.');
    redirect('reservations.php');
}

$stations = [];
$res = mysqli_query($conn, 'SELECT * FROM stations ORDER BY name ASC');
while ($row = mysqli_fetch_assoc($res)) { $stations[] = $row; }

$reservations = [];
$res = mysqli_query($conn, 'SELECT r.*, s.name AS station_name FROM reservations r JOIN stations s ON s.id = r.station_id ORDER BY r.start_at ASC');
while ($row = mysqli_fetch_assoc($res)) { $reservations[] = $row; }

include __DIR__ . '/includes/header.php';
?>

<h1>Reservations</h1>
<?php flash_render(); ?>

<div class="card">
  <h2>Add Reservation</h2>
  <form method="POST">
    <input type="hidden" name="form" value="add">
    <label>Customer</label>
    <input type="text" name="customer_name" required>
    <label>Station</label>
    <select name="station_id" required>
      <?php foreach ($stations as $station): ?>
        <option value="<?= (int) $station['id'] ?>"><?= h($station['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <label>Start</label>
    <input type="datetime-local" name="start_at" required>
    <label>End</label>
    <input type="datetime-local" name="end_at" required>
    <label>Status</label>
    <select name="status">
      <option value="PENDING">Pending</option>
      <option value="CONFIRMED">Confirmed</option>
      <option value="CANCELLED">Cancelled</option>
    </select>
    <label>Notes</label>
    <textarea name="notes" rows="3"></textarea>
    <button type="submit" class="btn btn-primary">Save Reservation</button>
  </form>
</div>

<h2 class="mt">Upcoming Reservations</h2>
<table class="data-table">
  <thead><tr><th>Customer</th><th>Station</th><th>Start</th><th>End</th><th>Status</th><th>Notes</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($reservations as $reservation): ?>
    <tr>
      <td><?= h($reservation['customer_name']) ?></td>
      <td><?= h($reservation['station_name']) ?></td>
      <td><?= h($reservation['start_at']) ?></td>
      <td><?= h($reservation['end_at']) ?></td>
      <td><?= h($reservation['status']) ?></td>
      <td><?= h($reservation['notes']) ?></td>
      <td>
        <form method="POST" style="display:inline">
          <input type="hidden" name="form" value="update_status">
          <input type="hidden" name="id" value="<?= (int) $reservation['id'] ?>">
          <select name="status" onchange="this.form.submit()">
            <option value="PENDING" <?= $reservation['status'] === 'PENDING' ? 'selected' : '' ?>>Pending</option>
            <option value="CONFIRMED" <?= $reservation['status'] === 'CONFIRMED' ? 'selected' : '' ?>>Confirmed</option>
            <option value="CANCELLED" <?= $reservation['status'] === 'CANCELLED' ? 'selected' : '' ?>>Cancelled</option>
          </select>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($reservations)): ?>
      <tr><td colspan="7" class="muted">No reservations yet.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<?php include __DIR__ . '/includes/footer.php'; ?>
