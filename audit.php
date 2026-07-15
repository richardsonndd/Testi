<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
$user = require_admin();
$page = 'audit';
$pageTitle = 'Audit Log';
ensure_station_pricing_schema($conn);

$entries = [];
$res = mysqli_query($conn, 'SELECT a.*, u.username FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 100');
while ($row = mysqli_fetch_assoc($res)) { $entries[] = $row; }

include __DIR__ . '/includes/header.php';
?>

<h1>Audit Log</h1>
<?php flash_render(); ?>

<table class="data-table">
  <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>Entity</th></tr></thead>
  <tbody>
    <?php foreach ($entries as $entry): ?>
    <tr>
      <td><?= h($entry['created_at']) ?></td>
      <td><?= h($entry['username'] ?? 'System') ?></td>
      <td><?= h($entry['action']) ?></td>
      <td><?= h($entry['details']) ?></td>
      <td><?= h($entry['entity_type'] ?? '—') ?> #<?= (int) ($entry['entity_id'] ?? 0) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($entries)): ?>
      <tr><td colspan="5" class="muted">No audit entries yet.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<?php include __DIR__ . '/includes/footer.php'; ?>
