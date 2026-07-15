<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
$user = require_admin();
$page = 'admin';
$pageTitle = 'Admin Panel';
ensure_station_pricing_schema($conn);
$settings = get_site_settings($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'settings') {
    $siteName = trim($_POST['site_name'] ?? '');
    $currencySymbol = trim($_POST['currency_symbol'] ?? '$');
    $lowStockThreshold = max(0, (int) ($_POST['low_stock_threshold'] ?? 3));
    $sessionStartFee = (float) ($_POST['session_start_fee'] ?? 1);
    $budgetWarning = (float) ($_POST['session_budget_warning'] ?? 0);
    $durationWarning = max(0, (int) ($_POST['session_duration_warning_minutes'] ?? 0));

    $stmt = mysqli_prepare($conn, 'UPDATE site_settings SET site_name = ?, currency_symbol = ?, low_stock_threshold = ?, session_start_fee = ?, session_budget_warning = ?, session_duration_warning_minutes = ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'ssdddii', $siteName, $currencySymbol, $lowStockThreshold, $sessionStartFee, $budgetWarning, $durationWarning, (int) $settings['id']);
    mysqli_stmt_execute($stmt);
    $settings = get_site_settings($conn);
    flash_set('success', 'Admin settings updated.');
    redirect('admin.php');
}

function formatMoney(float $amount): string
{
    return h($settings['currency_symbol'] ?? '$') . number_format($amount, 2);
}

// Station summary counts
$stationCounts = ['busy' => 0, 'paused' => 0, 'free' => 0];
$stmt = mysqli_prepare($conn, 'SELECT status, COUNT(*) AS total FROM stations GROUP BY status');
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $status = strtolower($row['status']);
    $stationCounts[$status] = (int) $row['total'];
}
$stationCounts['total'] = $stationCounts['busy'] + $stationCounts['paused'] + $stationCounts['free'];

// Live revenue estimate
$openRevenue = 0.0;
$liveSessions = 0;
$stmt = mysqli_prepare($conn, 'SELECT s.id, s.status, s.session_start, s.current_rate_per_hour, s.pause_started_at, s.paused_duration_ms, COALESCE(a.total, 0) AS snack_total
    FROM stations s
    LEFT JOIN (
      SELECT station_id, SUM(price * quantity) AS total
      FROM active_orders
      GROUP BY station_id
    ) a ON a.station_id = s.id
    WHERE s.status IN (\'BUSY\', \'PAUSED\')');
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $liveSessions++;
    $elapsedMinutes = 0;
    if (!empty($row['session_start'])) {
        $nowMs = (int) round(microtime(true) * 1000);
        $startMs = (int) $row['session_start'];
        $pausedMs = (int) ($row['paused_duration_ms'] ?? 0);
        if (!empty($row['pause_started_at'])) {
            $pausedMs += max(0, $nowMs - (int) $row['pause_started_at']);
        }
        $elapsedMinutes = max(0, (int) floor(max(0, $nowMs - $startMs - $pausedMs) / 60000));
    }
    $rate = (float) ($row['current_rate_per_hour'] ?? 0);
    $openRevenue += ($rate * ($elapsedMinutes / 60)) + (float) $row['snack_total'];
}

// Revenue trend over last 7 days
$trendDays = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $trendDays[$day] = 0.0;
}
$stmt = mysqli_prepare($conn, "SELECT DATE(ended_at) AS ended_date, COALESCE(SUM(total_price), 0) AS total FROM sessions WHERE ended_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(ended_at)");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $trendDays[$row['ended_date']] = (float) $row['total'];
}
$todayRevenue = $trendDays[date('Y-m-d')];

// Session duration buckets
$durationBuckets = ['<30' => 0, '30-60' => 0, '60-90' => 0, '90+' => 0];
$stmt = mysqli_prepare($conn, 'SELECT duration_minutes FROM sessions WHERE ended_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)');
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $duration = (int) $row['duration_minutes'];
    if ($duration < 30) {
        $durationBuckets['<30']++;
    } elseif ($duration < 60) {
        $durationBuckets['30-60']++;
    } elseif ($duration < 90) {
        $durationBuckets['60-90']++;
    } else {
        $durationBuckets['90+']++;
    }
}
$durationTotal = array_sum($durationBuckets);

// Low stock alerts
$lowStockItems = [];
$stmt = mysqli_prepare($conn, 'SELECT * FROM snacks WHERE stock <= ? ORDER BY stock ASC, name ASC LIMIT 6');
$threshold = max(0, (int) ($settings['low_stock_threshold'] ?? 3));
mysqli_stmt_bind_param($stmt, 'i', $threshold);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $lowStockItems[] = $row;
}

// Top snack orders
$topSnackSales = [];
$stmt = mysqli_prepare($conn, 'SELECT snack_name, SUM(quantity) AS sold, SUM(price * quantity) AS revenue FROM active_orders GROUP BY snack_name ORDER BY sold DESC LIMIT 6');
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $topSnackSales[] = $row;
}

// Recent closed sessions
$recentSessions = [];
$stmt = mysqli_prepare($conn, 'SELECT s.*, u.username AS closed_by_name FROM sessions s LEFT JOIN users u ON u.id = s.closed_by ORDER BY s.ended_at DESC LIMIT 5');
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $recentSessions[] = $row;
}

// Recent audit events
$recentAudit = [];
$stmt = mysqli_prepare($conn, 'SELECT a.*, u.username FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 6');
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $recentAudit[] = $row;
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-head admin-hero">
  <div>
    <h1>Admin Control Center</h1>
    <p class="muted small">A single overview for live floor health, revenue, stock alerts, and fast controls.</p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-outline btn-sm" onclick="window.location.reload();">Refresh</button>
    <a href="dashboard.php" class="btn btn-primary btn-sm">Live floor</a>
    <a href="stations.php" class="btn btn-primary btn-sm">Stations</a>
    <a href="snacks.php" class="btn btn-primary btn-sm">Snacks</a>
  </div>
</div>

<?php flash_render(); ?>

<div class="admin-summary-grid">
  <div class="summary-card summary-primary">
    <span class="summary-label">Live revenue estimate</span>
    <strong><?= formatMoney($openRevenue) ?></strong>
    <span class="summary-note"><?= h($liveSessions) ?> active sessions</span>
  </div>
  <div class="summary-card summary-accent">
    <span class="summary-label">Today's closed revenue</span>
    <strong><?= formatMoney($todayRevenue) ?></strong>
    <span class="summary-note">Last 7 days trend</span>
  </div>
  <div class="summary-card summary-neutral">
    <span class="summary-label">Stations</span>
    <strong><?= h($stationCounts['total']) ?> total</strong>
    <span class="summary-note"><?= h($stationCounts['busy']) ?> busy · <?= h($stationCounts['paused']) ?> paused · <?= h($stationCounts['free']) ?> free</span>
  </div>
  <div class="summary-card summary-warning">
    <span class="summary-label">Low stock alerts</span>
    <strong><?= h(count($lowStockItems)) ?> items</strong>
    <span class="summary-note">Threshold <?= h($threshold) ?> units</span>
  </div>
</div>

<div class="grid-3">
  <div class="card chart-card">
    <div class="chart-card-header">
      <h2>Status breakdown</h2>
      <span class="muted">Live station distribution</span>
    </div>
    <div class="status-breakdown">
      <?php foreach (['busy' => 'Busy', 'paused' => 'Paused', 'free' => 'Free'] as $key => $label): ?>
        <?php $count = $stationCounts[$key]; $percent = $stationCounts['total'] ? round($count / $stationCounts['total'] * 100) : 0; ?>
        <div class="status-row">
          <div class="status-label"><?= h($label) ?></div>
          <div class="status-bar-wrapper">
            <div class="status-bar status-bar-<?= h($key) ?>" style="width: <?= h($percent) ?>%;"></div>
          </div>
          <div class="status-value"><?= h($count) ?> (<?= h($percent) ?>%)</div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card chart-card">
    <div class="chart-card-header">
      <h2>Revenue trend</h2>
      <span class="muted">7-day closed revenue</span>
    </div>
    <div class="bar-chart">
      <?php $maxTrend = max($trendDays) ?: 1; ?>
      <?php foreach ($trendDays as $day => $value): ?>
        <?php $height = (int) round(($value / $maxTrend) * 100); ?>
        <div class="bar-group" title="<?= h($day) ?>: <?= formatMoney($value) ?>">
          <div class="bar" style="height: <?= h($height) ?>%;"></div>
          <span class="bar-label"><?= h(date('D', strtotime($day))) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card chart-card">
    <div class="chart-card-header">
      <h2>Session duration</h2>
      <span class="muted">Last 30 days</span>
    </div>
    <div class="distribution-grid">
      <?php foreach ($durationBuckets as $label => $count): ?>
        <?php $percent = $durationTotal ? round($count / $durationTotal * 100) : 0; ?>
        <div class="distribution-row">
          <span><?= h($label) ?></span>
          <div class="distribution-bar-wrapper">
            <div class="distribution-bar" style="width: <?= h($percent) ?>%;"></div>
          </div>
          <strong><?= h($count) ?></strong>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="grid-2 admin-main-grid">
  <div class="card">
    <div class="panel-header">
      <div>
        <h2>Operational settings</h2>
        <span class="muted">Manage defaults, alerts, and live pricing behavior.</span>
      </div>
    </div>
    <form method="POST" class="admin-settings-form">
      <input type="hidden" name="form" value="settings">
      <div class="settings-grid">
        <div class="settings-panel">
          <h3>General</h3>
          <label>Site name</label>
          <input type="text" name="site_name" value="<?= h($settings['site_name'] ?? 'PS Gaming Center') ?>">
          <label>Currency symbol</label>
          <input type="text" name="currency_symbol" value="<?= h($settings['currency_symbol'] ?? '$') ?>" maxlength="5">
          <label>Session start fee (€, added automatically)</label>
          <input type="number" step="0.01" name="session_start_fee" value="<?= number_format(session_start_fee($settings), 2, '.', '') ?>">
        </div>
        <div class="settings-panel">
          <h3>Warnings</h3>
          <label>Budget warning threshold</label>
          <input type="number" step="0.01" name="session_budget_warning" value="<?= number_format((float) ($settings['session_budget_warning'] ?? 0), 2, '.', '') ?>">
          <label>Session duration warning</label>
          <input type="number" name="session_duration_warning_minutes" value="<?= (int) ($settings['session_duration_warning_minutes'] ?? 0) ?>">
          <label>Low stock threshold</label>
          <input type="number" name="low_stock_threshold" value="<?= (int) ($settings['low_stock_threshold'] ?? 3) ?>">
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
  </div>

  <div class="card">
    <div class="panel-header">
      <div>
        <h2>Quick actions</h2>
        <span class="muted">Fast navigation and operational shortcuts.</span>
      </div>
    </div>
    <div class="admin-actions">
      <button type="button" class="btn btn-sm btn-outline" onclick="window.location.reload();">Refresh</button>
      <a href="dashboard.php" class="btn btn-sm btn-primary">Live floor</a>
      <a href="stations.php" class="btn btn-sm btn-primary">Station manager</a>
      <a href="snacks.php" class="btn btn-sm btn-primary">Snack inventory</a>
      <a href="sessions.php" class="btn btn-sm btn-outline">Session history</a>
      <a href="audit.php" class="btn btn-sm btn-outline">Audit log</a>
    </div>

    <div class="panel-divider"></div>
    <div class="activity-block">
      <h3>Low stock items</h3>
      <?php if ($lowStockItems): ?>
        <ul class="mini-list">
          <?php foreach ($lowStockItems as $snack): ?>
            <li><?= h($snack['name']) ?> — <strong><?= (int) $snack['stock'] ?></strong></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="muted">No low-stock items at the moment.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="grid-2 admin-main-grid">
  <div class="card">
    <div class="panel-header">
      <div>
        <h2>Recent session history</h2>
        <span class="muted">Latest closed sessions.</span>
      </div>
    </div>
    <table class="data-table admin-table">
      <thead>
        <tr><th>Station</th><th>Ended</th><th>Total</th><th>Duration</th><th>Closed by</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentSessions as $session): ?>
          <tr>
            <td><?= h($session['station_name']) ?></td>
            <td><?= h($session['ended_at']) ?></td>
            <td><?= formatMoney((float) $session['total_price']) ?></td>
            <td><?= h((int) $session['duration_minutes']) ?> min</td>
            <td><?= h($session['closed_by_name'] ?: 'System') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <div class="panel-header">
      <div>
        <h2>Recent audit events</h2>
        <span class="muted">Recent changes tracked by the system.</span>
      </div>
    </div>
    <div class="audit-list">
      <?php if ($recentAudit): ?>
        <?php foreach ($recentAudit as $event): ?>
          <div class="audit-row">
            <div>
              <strong><?= h($event['username'] ?: 'System') ?></strong>
              <span class="muted"><?= h($event['created_at']) ?></span>
            </div>
            <div><?= h($event['action']) ?><?php if (!empty($event['details'])): ?> — <?= h($event['details']) ?><?php endif; ?></div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="muted">No recent audit activity found.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
