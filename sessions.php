<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
$user = require_login();
$page = 'sessions';
$pageTitle = 'Session History';

$limit = 50;
$sessions = [];
$res = mysqli_query($conn, "SELECT s.*, u.username AS closed_by_name FROM sessions s
                             LEFT JOIN users u ON u.id = s.closed_by
                             ORDER BY s.ended_at DESC LIMIT $limit");
while ($row = mysqli_fetch_assoc($res)) { $sessions[] = $row; }

$totalsRow = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS c, COALESCE(SUM(total_price),0) AS revenue FROM sessions'));

include __DIR__ . '/includes/header.php';
?>

<h1>Session History</h1>
<?php flash_render(); ?>

<div class="stats-row">
  <div class="stat-card">
    <div class="stat-value"><?= (int) $totalsRow['c'] ?></div>
    <div class="stat-label">Total Sessions</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= number_format((float) $totalsRow['revenue'], 2) ?></div>
    <div class="stat-label">Total Revenue</div>
  </div>
</div>

<table class="data-table">
  <thead>
    <tr><th>Station</th><th>Started</th><th>Ended</th><th>Duration</th><th>Session</th><th>Snacks</th><th>Total</th><th>Receipt</th><th>Notes</th><th>Closed by</th><th></th></tr>
  </thead>
  <tbody>
    <?php foreach ($sessions as $s): ?>
    <tr>
      <td><?= h($s['station_name']) ?></td>
      <td><?= h($s['started_at']) ?></td>
      <td><?= h($s['ended_at']) ?></td>
      <td><?= (int) $s['duration_minutes'] ?> min</td>
      <td><?= number_format($s['session_price'], 2) ?></td>
      <td><?= number_format($s['snacks_total'], 2) ?></td>
      <td><strong><?= number_format($s['total_price'], 2) ?></strong></td>
      <td><?= h($s['receipt_number'] ?? '—') ?></td>
      <td><?= h($s['notes'] ?? '—') ?></td>
      <td><?= h($s['closed_by_name'] ?? '—') ?></td>
      <td><a href="bill.php?session_id=<?= (int) $s['id'] ?>" class="btn btn-sm btn-outline">View Bill</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($sessions)): ?>
      <tr><td colspan="11" class="muted">No sessions recorded yet.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<?php include __DIR__ . '/includes/footer.php'; ?>
