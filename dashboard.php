<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
$user = require_login();
$page = 'dashboard';
$pageTitle = 'Dashboard';
ensure_station_pricing_schema($conn);
$settings = get_site_settings($conn);

// Load zones
$zones = [];
$res = mysqli_query($conn, 'SELECT * FROM zones ORDER BY sort_order ASC, name ASC');
while ($row = mysqli_fetch_assoc($res)) {
    $zones[] = $row;
}
$activeZoneId = (int) ($_GET['zone'] ?? ($zones[0]['id'] ?? 0));

// Load stations for the active zone
$stations = [];
$stmt = mysqli_prepare($conn, 'SELECT * FROM stations WHERE zone_id = ? ORDER BY name ASC');
mysqli_stmt_bind_param($stmt, 'i', $activeZoneId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $stations[] = $row;
}

// Snacks (for the add snack mini-form per busy station)
$snacks = [];
$res = mysqli_query($conn, 'SELECT * FROM snacks WHERE stock > 0 ORDER BY name ASC');
while ($row = mysqli_fetch_assoc($res)) {
    $snacks[] = $row;
}

function station_active_orders_total(mysqli $conn, int $stationId): float
{
    $stmt = mysqli_prepare($conn, 'SELECT COALESCE(SUM(price * quantity), 0) AS total FROM active_orders WHERE station_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $stationId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return (float) $row['total'];
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Dashboard</h1>
    <p class="muted small">Live floor view, station status, and session budgets.</p>
  </div>
  <div class="page-actions">
    <div class="zone-tabs">
      <?php foreach ($zones as $zone): ?>
        <a href="?zone=<?= (int) $zone['id'] ?>"
           class="zone-tab <?= $zone['id'] == $activeZoneId ? 'active' : '' ?>">
          <?= h($zone['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if (is_admin()): ?>
      <button type="button" id="toggleEditModeBtn" class="btn btn-outline btn-sm">Edit Floor</button>
    <?php endif; ?>
  </div>
</div>

<?php flash_render(); ?>

<?php if (is_admin()): ?>
  <p class="muted small">Toggle editing to reposition stations. Click a station to open its management modal when edit mode is off.</p>
<?php endif; ?>

<div class="floor-canvas" id="floorCanvas"
     data-zone-id="<?= (int) $activeZoneId ?>"
     style="width: <?= (int) ($zones[array_search($activeZoneId, array_column($zones, 'id'))]['width'] ?? 900) ?>px;
            height: <?= (int) ($zones[array_search($activeZoneId, array_column($zones, 'id'))]['height'] ?? 600) ?>px;">
  <?php if (empty($stations)): ?>
    <div class="empty-hint">No stations in this zone yet. <?php if (is_admin()): ?><a href="stations.php">Add one</a>.<?php endif; ?></div>
  <?php endif; ?>

  <?php foreach ($stations as $station): ?>
    <?php
      $isActive = in_array($station['status'], ['BUSY', 'PAUSED'], true);
      $isPaused = $station['status'] === 'PAUSED';
      $elapsedMin = 0;
      $stationBudget = 0.0;
      $stationWarning = '';
      if ($isActive && $station['session_start']) {
          $nowMs = (int) round(microtime(true) * 1000);
          $startMs = (int) $station['session_start'];
          $pausedMs = isset($station['paused_duration_ms']) ? (int) $station['paused_duration_ms'] : 0;
          if ($isPaused && !empty($station['pause_started_at'])) {
              $pausedMs += max(0, $nowMs - (int) $station['pause_started_at']);
          }
          $elapsedMin = (int) floor(max(0, $nowMs - $startMs - $pausedMs) / 60000);
          $currentRate = isset($station['current_rate_per_hour']) && (float) $station['current_rate_per_hour'] > 0
              ? (float) $station['current_rate_per_hour']
              : station_price_for_player_count($station, isset($station['current_player_count']) && (int) $station['current_player_count'] > 0 ? (int) $station['current_player_count'] : 2);
          $tileSessionFee = isset($station['current_session_fee']) ? (float) $station['current_session_fee'] : 0.0;
          $stationBudget = ($currentRate * ($elapsedMin / 60)) + station_active_orders_total($conn, (int) $station['id']) + $tileSessionFee;
          $warningBudgetThreshold = (float) ($settings['session_budget_warning'] ?? 0);
          $warningDurationThreshold = (int) ($settings['session_duration_warning_minutes'] ?? 0);
          if ($warningBudgetThreshold > 0 && $stationBudget >= $warningBudgetThreshold) {
              $stationWarning = 'BUDGET ALERT';
          }
          if ($warningDurationThreshold > 0 && $elapsedMin >= $warningDurationThreshold) {
              $stationWarning = trim($stationWarning ? $stationWarning . ' / ' : '') . 'TIME ALERT';
          }
      }
      $stationPrice2 = station_price_for_player_count($station, 2);
      $stationPrice4 = station_price_for_player_count($station, 4);
    ?>
    <div class="station <?= $isPaused ? 'station-paused' : ($isActive ? 'station-busy' : 'station-free') ?> <?= is_admin() ? 'draggable' : '' ?>"
         style="left: <?= (int) $station['pos_x'] ?>px; top: <?= (int) $station['pos_y'] ?>px;"
         data-station-id="<?= (int) $station['id'] ?>"
         onclick="openStationSessionPage(<?= (int) $station['id'] ?>, event)">
      <div class="station-icon">🎮</div>
      <div class="station-name"><?= h($station['name']) ?></div>
      <div class="station-status-badge" data-status-badge><?= $isActive ? ($isPaused ? 'Paused' : ($elapsedMin . ' min')) : 'Free' ?></div>
      <?php if ($stationWarning): ?>
        <div class="station-alert-badge"><?= h($stationWarning) ?></div>
      <?php endif; ?>
      <?php if ($isActive): ?>
        <div class="station-budget" data-budget-value><?= h($settings['currency_symbol'] ?? '$') ?><?= number_format($stationBudget, 2) ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php foreach ($stations as $station): ?>
  <?php
    $isActive = in_array($station['status'], ['BUSY', 'PAUSED'], true);
    $snackTotal = $isActive ? station_active_orders_total($conn, (int) $station['id']) : 0;
    $activeOrders = [];
    if ($isActive) {
        $stmt = mysqli_prepare($conn, 'SELECT * FROM active_orders WHERE station_id = ? ORDER BY created_at ASC');
        mysqli_stmt_bind_param($stmt, 'i', $station['id']);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($r)) { $activeOrders[] = $row; }
    }
    $stationPrice2 = station_price_for_player_count($station, 2);
    $stationPrice4 = station_price_for_player_count($station, 4);
    $currentPlayerCount = isset($station['current_player_count']) && (int) $station['current_player_count'] > 0 ? (int) $station['current_player_count'] : null;
    $currentRate = isset($station['current_rate_per_hour']) && (float) $station['current_rate_per_hour'] > 0 ? (float) $station['current_rate_per_hour'] : null;
    $modalSessionFee = $isActive && isset($station['current_session_fee']) ? (float) $station['current_session_fee'] : 0.0;
    $budgetWarning = '';
    $durationWarning = '';
    $currentBudget = 0.0;
    if ($isActive && $station['session_start'] && $currentRate !== null) {
        $currentBudget = ($currentRate * ($modalElapsedMin / 60)) + $snackTotal + $modalSessionFee;
        $warningBudgetThreshold = (float) ($settings['session_budget_warning'] ?? 0);
        $warningDurationThreshold = (int) ($settings['session_duration_warning_minutes'] ?? 0);
        if ($warningBudgetThreshold > 0 && $currentBudget >= $warningBudgetThreshold) {
            $budgetWarning = 'Budget warning: total has reached ' . h($settings['currency_symbol'] ?? '$') . number_format($currentBudget, 2) . '.';
        }
        if ($warningDurationThreshold > 0 && $elapsedMin >= $warningDurationThreshold) {
            $durationWarning = 'Duration warning: session has hit ' . $elapsedMin . ' minutes.';
        }
    }
  ?>
  <div class="station-modal-backdrop" id="modal-<?= (int) $station['id'] ?>" style="display:none;">
    <div class="station-modal" role="dialog" aria-modal="true">
      <div class="station-modal-head">
        <div>
          <h2><?= h($station['name']) ?> <small>(<?= h($station['console_type']) ?>)</small></h2>
          <p class="muted">Station details, live session info, and snack orders</p>
        </div>
        <button type="button" class="btn-close" onclick="closeStationModal(<?= (int) $station['id'] ?>)">&times;</button>
      </div>

      <div class="station-modal-grid">
        <div class="station-modal-panel">
          <div class="station-info-card">
            <h3>Session status</h3>
            <div class="station-status-pill <?= $isPaused ? 'paused' : ($isActive ? 'busy' : 'free') ?>"><?= $isPaused ? 'Paused' : ($isActive ? 'Busy' : 'Free') ?></div>
            <div class="session-warning" data-session-warning>
              <?php if ($budgetWarning || $durationWarning): ?>
                <div class="alert alert-error"><?= h(trim($budgetWarning . ' ' . $durationWarning)) ?></div>
              <?php endif; ?>
            </div>
            <?php if ($isActive): ?>
              <p><strong>Elapsed:</strong> <span class="live-timer" data-start="<?= (int) $station['session_start'] ?>" data-paused-duration="<?= (int) $station['paused_duration_ms'] ?>" data-pause-start="<?= (int) $station['pause_started_at'] ?>"></span></p>
              <p><strong>Player count:</strong> <?= $currentPlayerCount !== null ? (int) $currentPlayerCount . ' players' : '—' ?></p>
              <p><strong>Current rate:</strong> <?= $currentRate !== null ? number_format($currentRate, 2) : number_format($stationPrice2, 2) ?>/hr</p>
              <p><strong>Session fee:</strong> €<?= number_format($modalSessionFee, 2) ?></p>
              <p><strong>Current budget:</strong> <span class="station-budget-value" data-budget-value><?= h($settings['currency_symbol'] ?? '$') ?><?= number_format($currentBudget, 2) ?></span></p>
            <?php else: ?>
              <p class="muted">Choose the player count below to start a new session.</p>
            <?php endif; ?>
          </div>

          <div class="station-info-card">
            <h3>Pricing by player count</h3>
            <div class="pricing-grid">
              <div class="price-box">
                <span>2 players</span>
                <strong><?= number_format($stationPrice2, 2) ?>/hr</strong>
              </div>
              <div class="price-box">
                <span>4 players</span>
                <strong><?= number_format($stationPrice4, 2) ?>/hr</strong>
              </div>
            </div>
            <p class="muted small">A €<?= number_format(session_start_fee($settings), 2) ?> session fee is added automatically when the session starts.</p>
          </div>

          <?php if ($isActive): ?>
            <div class="station-info-card">
              <h3>Session notes & receipt</h3>
              <label>Notes</label>
              <textarea name="session_notes_<?= (int) $station['id'] ?>" rows="3" form="session-form-<?= (int) $station['id'] ?>"></textarea>
              <label>Receipt number</label>
              <input type="text" name="receipt_number_<?= (int) $station['id'] ?>" form="session-form-<?= (int) $station['id'] ?>" placeholder="Optional">
            </div>

            <?php if ($activeOrders): ?>
              <div class="station-info-card">
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
              </div>
            <?php endif; ?>

            <?php if ($isActive && $snacks): ?>
            <form method="POST" action="order_action.php" class="modal-form">
              <input type="hidden" name="station_id" value="<?= (int) $station['id'] ?>">
              <input type="hidden" name="return_zone" value="<?= (int) $activeZoneId ?>">
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

            <form method="POST" action="station_action.php" class="modal-form" id="session-form-<?= (int) $station['id'] ?>" style="margin-top:12px;">
              <input type="hidden" name="station_id" value="<?= (int) $station['id'] ?>">
              <input type="hidden" name="return_zone" value="<?= (int) $activeZoneId ?>">
              <input type="hidden" name="action" value="end">
              <input type="hidden" name="session_notes" value="">
              <input type="hidden" name="receipt_number" value="">
              <button type="submit" class="btn btn-danger btn-block" onclick="return confirm('End this session?')">End Session</button>
            </form>
            <script>
              (function(){
                var form = document.getElementById('session-form-<?= (int) $station['id'] ?>');
                if (!form) return;
                form.addEventListener('submit', function () {
                  var notes = document.querySelector('textarea[name="session_notes_<?= (int) $station['id'] ?>"]').value;
                  var receipt = document.querySelector('input[name="receipt_number_<?= (int) $station['id'] ?>"]').value;
                  form.querySelector('input[name="session_notes"]').value = notes;
                  form.querySelector('input[name="receipt_number"]').value = receipt;
                });
              })();
            </script>
          <?php else: ?>
            <form method="POST" action="station_action.php" class="modal-form">
              <input type="hidden" name="station_id" value="<?= (int) $station['id'] ?>">
              <input type="hidden" name="return_zone" value="<?= (int) $activeZoneId ?>">
              <input type="hidden" name="action" value="start">
              <label>Player count</label>
              <select name="player_count" required>
                <option value="2">2 players</option>
                <option value="4">4 players</option>
              </select>
              <button type="submit" class="btn btn-primary btn-block">Start Session</button>
            </form>
          <?php endif; ?>
        </div>

        <div class="station-modal-panel">
          <div class="station-info-card">
            <h3>Station details</h3>
            <p><strong>Console:</strong> <?= h($station['console_type']) ?></p>
            <p><strong>Zone:</strong> <?= h($station['zone_id']) ?></p>
            <p><strong>Session start fee:</strong> €<?= number_format(session_start_fee($settings), 2) ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<script src="assets/js/floorplan.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
