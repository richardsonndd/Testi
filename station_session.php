<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
$user = require_login();
$page = 'dashboard';
$pageTitle = 'Station Session';
ensure_station_pricing_schema($conn);
$settings = get_site_settings($conn);

$stationId = (int) ($_GET['station_id'] ?? 0);
if ($stationId <= 0) {
    redirect('dashboard.php');
}

$stmt = mysqli_prepare($conn, 'SELECT * FROM stations WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $stationId);
mysqli_stmt_execute($stmt);
$station = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$station) {
    flash_set('error', 'Station not found.');
    redirect('dashboard.php');
}

function station_active_orders_total(mysqli $conn, int $stationId): float
{
    $stmt = mysqli_prepare($conn, 'SELECT COALESCE(SUM(price * quantity), 0) AS total FROM active_orders WHERE station_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $stationId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return (float) $row['total'];
}

$isActive = $station['status'] !== 'FREE';
$isPaused = $station['status'] === 'PAUSED';
$stationPrice2 = station_price_for_player_count($station, 2);
$stationPrice4 = station_price_for_player_count($station, 4);
$defaultStartRate = isset($station['default_start_price_per_hour']) && (float) $station['default_start_price_per_hour'] > 0
    ? (float) $station['default_start_price_per_hour']
    : max($stationPrice2, $stationPrice4);
$currentRate = isset($station['current_rate_per_hour']) && (float) $station['current_rate_per_hour'] > 0
    ? (float) $station['current_rate_per_hour']
    : $defaultStartRate;
$snackTotal = $isActive ? station_active_orders_total($conn, $stationId) : 0;
$currentSessionFee = $isActive && isset($station['current_session_fee']) ? (float) $station['current_session_fee'] : 0.0;
$upcomingSessionFee = session_start_fee($settings);
$sessionWarning = '';
$elapsedMinutes = 0;
$currentBudget = 0.0;
$pauseStartedAt = isset($station['pause_started_at']) ? (int) $station['pause_started_at'] : 0;
$pausedDurationMs = isset($station['paused_duration_ms']) ? (int) $station['paused_duration_ms'] : 0;
if ($isActive && $station['session_start']) {
    $nowMs = (int) round(microtime(true) * 1000);
    $startMs = (int) $station['session_start'];
    $pausedMs = $pausedDurationMs;
    if ($isPaused && $pauseStartedAt) {
        $pausedMs += max(0, $nowMs - $pauseStartedAt);
    }
    $elapsedMinutes = max(0, (int) round(max(0, $nowMs - $startMs - $pausedMs) / 60000));
    $currentBudget = ($currentRate * ($elapsedMinutes / 60)) + $snackTotal + $currentSessionFee;
    if ((float) ($settings['session_budget_warning'] ?? 0) > 0 && $currentBudget >= (float) $settings['session_budget_warning']) {
        $sessionWarning = 'Budget warning: total has reached ' . h($settings['currency_symbol'] ?? '$') . number_format($currentBudget, 2) . '.';
    }
    if ((int) ($settings['session_duration_warning_minutes'] ?? 0) > 0 && $elapsedMinutes >= (int) $settings['session_duration_warning_minutes']) {
        $sessionWarning = trim($sessionWarning . ' Duration warning: session has hit ' . $elapsedMinutes . ' minutes.');
    }
}
$snacks = [];
$res = mysqli_query($conn, 'SELECT * FROM snacks WHERE stock > 0 ORDER BY name ASC');
while ($row = mysqli_fetch_assoc($res)) { $snacks[] = $row; }
$activeOrders = [];
if ($isActive) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM active_orders WHERE station_id = ? ORDER BY created_at ASC');
    mysqli_stmt_bind_param($stmt, 'i', $stationId);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($r)) { $activeOrders[] = $row; }
}
$auditEvents = [];
if ($stationId > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT a.*, u.username FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.entity_type = ? AND a.entity_id = ? ORDER BY a.created_at DESC LIMIT 10');
    $entityType = 'station';
    mysqli_stmt_bind_param($stmt, 'si', $entityType, $stationId);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($r)) { $auditEvents[] = $row; }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1><?= h($station['name']) ?></h1>
    <p class="muted small">Manage this station session from one dedicated page.</p>
  </div>
  <a href="dashboard.php" class="btn btn-outline btn-sm">Back to Floor</a>
</div>

<?php flash_render(); ?>

<div class="grid-2">
  <div class="card">
    <h2>Session Control</h2>
    <div class="station-status-pill <?= $isPaused ? 'paused' : ($isActive ? 'busy' : 'free') ?>"><?= $isPaused ? 'Paused' : ($isActive ? 'Busy' : 'Free') ?></div>
    <?php if ($isActive): ?>
      <p><strong>Current rate:</strong> <?= number_format($currentRate, 2) ?><?= h($settings['currency_symbol'] ?? '$') ?>/hr</p>
      <p><strong>Session fee:</strong> €<?= number_format($currentSessionFee, 2) ?></p>
      <p><strong>Current budget:</strong> <?= h($settings['currency_symbol'] ?? '$') ?><?= number_format($currentBudget, 2) ?></p>
      <p><strong>Elapsed:</strong> <span class="live-timer" data-start="<?= (int) $station['session_start'] ?>" data-paused-duration="<?= $pausedDurationMs ?>" data-pause-start="<?= $pauseStartedAt ?>"></span></p>
      <?php if ($sessionWarning): ?>
        <div class="alert alert-error"><?= h($sessionWarning) ?></div>
      <?php endif; ?>
      <?php if ($isPaused): ?>
        <form method="POST" action="station_action.php" class="modal-form">
          <input type="hidden" name="station_id" value="<?= (int) $station['id'] ?>">
          <input type="hidden" name="action" value="resume">
          <input type="hidden" name="return_station_id" value="<?= (int) $station['id'] ?>">
          <button type="submit" class="btn btn-primary btn-block">Resume Session</button>
        </form>
      <?php else: ?>
        <form method="POST" action="station_action.php" class="modal-form">
          <input type="hidden" name="station_id" value="<?= (int) $station['id'] ?>">
          <input type="hidden" name="action" value="pause">
          <input type="hidden" name="return_station_id" value="<?= (int) $station['id'] ?>">
          <button type="submit" class="btn btn-warning btn-block" onclick="return confirm('Pause this session?')">Pause Session</button>
        </form>
      <?php endif; ?>
      <form method="POST" action="station_action.php" class="modal-form">
        <input type="hidden" name="station_id" value="<?= (int) $station['id'] ?>">
        <input type="hidden" name="action" value="end">
        <input type="hidden" name="return_station_id" value="<?= (int) $station['id'] ?>">
        <label>Notes</label>
        <textarea name="session_notes" rows="3"><?= h($station['current_session_notes'] ?? '') ?></textarea>
        <label>Receipt number</label>
        <input type="text" name="receipt_number" placeholder="Optional">
        <button type="submit" class="btn btn-danger btn-block" onclick="return confirm('End this session?')">End Session</button>
      </form>
    <?php else: ?>
      <form method="POST" action="station_action.php" class="modal-form">
        <input type="hidden" name="station_id" value="<?= (int) $station['id'] ?>">
        <input type="hidden" name="action" value="start">
        <input type="hidden" name="return_station_id" value="<?= (int) $station['id'] ?>">
        <label>Player count</label>
        <select name="player_count" required>
          <option value="2">2 players — <?= number_format($stationPrice2, 2) ?><?= h($settings['currency_symbol'] ?? '$') ?>/hr</option>
          <option value="4">4 players — <?= number_format($stationPrice4, 2) ?><?= h($settings['currency_symbol'] ?? '$') ?>/hr</option>
        </select>
        <p class="muted small">A €<?= number_format($upcomingSessionFee, 2) ?> session fee is added automatically when the session starts.</p>
        <button type="submit" class="btn btn-primary btn-block">Start Session</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Station Details</h2>
    <p><strong>Console:</strong> <?= h($station['console_type']) ?></p>
    <p><strong>2-player price:</strong> <?= number_format($stationPrice2, 2) ?><?= h($settings['currency_symbol'] ?? '$') ?>/hr</p>
    <p><strong>4-player price:</strong> <?= number_format($stationPrice4, 2) ?><?= h($settings['currency_symbol'] ?? '$') ?>/hr</p>
    <p><strong>Session start fee:</strong> €<?= number_format($upcomingSessionFee, 2) ?></p>

    <?php if ($isActive && $activeOrders): ?>
      <h3>Current snack charges</h3>
      <table class="mini-table">
        <?php foreach ($activeOrders as $o): ?>
          <tr>
            <td><?= h($o['snack_name']) ?> x<?= (int) $o['quantity'] ?></td>
            <td><?= number_format($o['price'] * $o['quantity'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      <p><strong>Snacks total:</strong> <?= number_format($snackTotal, 2) ?></p>
    <?php endif; ?>

    <?php if ($isActive && $snacks): ?>
      <form method="POST" action="order_action.php" class="modal-form">
        <input type="hidden" name="station_id" value="<?= (int) $station['id'] ?>">
        <input type="hidden" name="return_station_id" value="<?= (int) $station['id'] ?>">
        <label>Quick snack add</label>
        <select name="snack_id" required>
          <?php foreach ($snacks as $s): ?>
            <option value="<?= (int) $s['id'] ?>"><?= h($s['name']) ?> — <?= number_format($s['price'], 2) ?> (<?= (int) $s['stock'] ?> in stock)</option>
          <?php endforeach; ?>
        </select>
        <div class="form-row">
          <input type="number" name="quantity" value="1" min="1" style="width:90px">
          <button type="submit" class="btn btn-sm btn-outline">Add Snack</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($auditEvents): ?>
  <div class="card">
    <h2>Recent Station Activity</h2>
    <table class="mini-table">
      <thead>
        <tr>
          <th>Time</th>
          <th>User</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($auditEvents as $event): ?>
          <tr>
            <td><?= h($event['created_at']) ?></td>
            <td><?= h($event['username'] ?: 'System') ?></td>
            <td><?= h($event['action'] . ($event['details'] ? ' — ' . $event['details'] : '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<script>
(function () {
  function tick() {
    document.querySelectorAll('.live-timer[data-start]').forEach(function (el) {
      var startMs = parseInt(el.getAttribute('data-start'), 10);
      if (!startMs) return;
      var pausedDuration = parseInt(el.getAttribute('data-paused-duration') || '0', 10);
      var pauseStart = parseInt(el.getAttribute('data-pause-start') || '0', 10);
      if (pauseStart > 0) {
        pausedDuration += Math.max(0, Date.now() - pauseStart);
      }
      var elapsedSec = Math.max(0, Math.floor((Date.now() - startMs - pausedDuration) / 1000));
      var h = Math.floor(elapsedSec / 3600);
      var m = Math.floor((elapsedSec % 3600) / 60);
      var s = elapsedSec % 60;
      var parts = [];
      if (h) parts.push(h + 'h');
      parts.push(m + 'm');
      parts.push(s + 's');
      el.textContent = parts.join(' ');
    });
  }
  tick();
  setInterval(tick, 1000);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
