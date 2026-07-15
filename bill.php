<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
$user = require_login();
ensure_station_pricing_schema($conn);
$settings = get_site_settings($conn);
$currency = $settings['currency_symbol'] ?? '$';
$siteName = $settings['site_name'] ?? 'PS Gaming Center';

$sessionId = (int) ($_GET['session_id'] ?? 0);
if ($sessionId <= 0) {
    redirect('sessions.php');
}

$stmt = mysqli_prepare($conn, 'SELECT s.*, u.username AS closed_by_name FROM sessions s LEFT JOIN users u ON u.id = s.closed_by WHERE s.id = ?');
mysqli_stmt_bind_param($stmt, 'i', $sessionId);
mysqli_stmt_execute($stmt);
$session = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$session) {
    flash_set('error', 'Bill not found.');
    redirect('sessions.php');
}

$snackItems = [];
if (!empty($session['snacks_snapshot'])) {
    $decoded = json_decode($session['snacks_snapshot'], true);
    if (is_array($decoded)) {
        $snackItems = $decoded;
    }
}

$durationMinutes = (int) $session['duration_minutes'];
$durationLabel = sprintf('%dh %02dm', intdiv($durationMinutes, 60), $durationMinutes % 60);
$sessionFee = isset($session['session_fee']) ? (float) $session['session_fee'] : 0.0;
$billNumber = 'INV-' . str_pad((string) $session['id'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bill <?= h($billNumber) ?> — <?= h($siteName) ?></title>
<style>
  :root { color-scheme: light; }
  * { box-sizing: border-box; }
  body {
    font-family: 'Courier New', Courier, monospace;
    background: #e9edf1;
    margin: 0;
    padding: 32px 16px;
    color: #1a1a1a;
  }
  .toolbar {
    max-width: 480px;
    margin: 0 auto 16px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
  }
  .btn {
    font-family: system-ui, -apple-system, sans-serif;
    font-size: 14px;
    font-weight: 600;
    padding: 10px 18px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
  }
  .btn-primary { background: #2563eb; color: #fff; }
  .btn-outline { background: #fff; color: #1a1a1a; border: 1px solid #c7ccd4; }
  .bill {
    max-width: 480px;
    margin: 0 auto;
    background: #fff;
    padding: 28px 26px;
    border-radius: 10px;
    box-shadow: 0 6px 24px rgba(0,0,0,0.10);
  }
  .bill-header {
    text-align: center;
    border-bottom: 2px dashed #c7ccd4;
    padding-bottom: 14px;
    margin-bottom: 14px;
  }
  .bill-header h1 {
    font-size: 20px;
    margin: 0 0 4px;
    letter-spacing: 0.5px;
  }
  .bill-header .muted { color: #666; font-size: 12px; }
  .bill-meta {
    font-size: 13px;
    margin-bottom: 14px;
    border-bottom: 1px dashed #c7ccd4;
    padding-bottom: 14px;
  }
  .bill-meta div { display: flex; justify-content: space-between; margin-bottom: 4px; }
  .bill-meta span:first-child { color: #555; }
  .bill-section-title {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #555;
    margin: 14px 0 6px;
  }
  table.line-items { width: 100%; border-collapse: collapse; font-size: 13px; }
  table.line-items td { padding: 3px 0; }
  table.line-items td.amt { text-align: right; white-space: nowrap; }
  table.line-items td.qty { color: #666; padding-left: 6px; white-space: nowrap; }
  .totals { margin-top: 12px; border-top: 1px dashed #c7ccd4; padding-top: 10px; font-size: 13px; }
  .totals div { display: flex; justify-content: space-between; margin-bottom: 4px; }
  .totals .grand {
    font-size: 17px;
    font-weight: 700;
    border-top: 2px solid #1a1a1a;
    padding-top: 8px;
    margin-top: 8px;
  }
  .bill-footer {
    text-align: center;
    margin-top: 20px;
    padding-top: 14px;
    border-top: 2px dashed #c7ccd4;
    font-size: 12px;
    color: #666;
  }
  .notes-box {
    margin-top: 12px;
    font-size: 12px;
    color: #444;
    background: #f4f5f7;
    padding: 8px 10px;
    border-radius: 6px;
  }
  @media print {
    body { background: #fff; padding: 0; }
    .toolbar { display: none; }
    .bill { box-shadow: none; max-width: 100%; }
  }
</style>
</head>
<body>

<div class="toolbar">
  <a href="sessions.php" class="btn btn-outline">Back to History</a>
  <button class="btn btn-primary" onclick="window.print()">Print Bill</button>
</div>

<div class="bill">
  <div class="bill-header">
    <h1><?= h($siteName) ?></h1>
    <div class="muted">Session Receipt</div>
  </div>

  <div class="bill-meta">
    <div><span>Bill No.</span><strong><?= h($billNumber) ?></strong></div>
    <?php if (!empty($session['receipt_number'])): ?>
      <div><span>Receipt #</span><strong><?= h($session['receipt_number']) ?></strong></div>
    <?php endif; ?>
    <div><span>Station</span><strong><?= h($session['station_name']) ?></strong></div>
    <div><span>Players</span><strong><?= (int) ($session['player_count'] ?? 0) ?: '—' ?></strong></div>
    <div><span>Started</span><strong><?= h($session['started_at']) ?></strong></div>
    <div><span>Ended</span><strong><?= h($session['ended_at']) ?></strong></div>
    <div><span>Duration</span><strong><?= h($durationLabel) ?></strong></div>
    <div><span>Rate</span><strong><?= number_format((float) $session['pricing_rate_per_hour'], 2) ?><?= h($currency) ?>/hr</strong></div>
    <div><span>Closed by</span><strong><?= h($session['closed_by_name'] ?? '—') ?></strong></div>
  </div>

  <div class="bill-section-title">Charges</div>
  <table class="line-items">
    <tr>
      <td>Station time (<?= h($durationLabel) ?> @ <?= number_format((float) $session['pricing_rate_per_hour'], 2) ?><?= h($currency) ?>/hr)</td>
      <td class="amt"><?= number_format((float) $session['session_price'], 2) ?><?= h($currency) ?></td>
    </tr>
    <?php if ($sessionFee > 0): ?>
    <tr>
      <td>Session fee</td>
      <td class="amt">€<?= number_format($sessionFee, 2) ?></td>
    </tr>
    <?php endif; ?>
    <?php foreach ($snackItems as $item): ?>
      <tr>
        <td><?= h($item['name']) ?><span class="qty">x<?= (int) $item['quantity'] ?></span></td>
        <td class="amt"><?= number_format((float) $item['line_total'], 2) ?><?= h($currency) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <div class="totals">
    <div><span>Station time</span><span><?= number_format((float) $session['session_price'], 2) ?><?= h($currency) ?></span></div>
    <?php if ($sessionFee > 0): ?>
    <div><span>Session fee</span><span>€<?= number_format($sessionFee, 2) ?></span></div>
    <?php endif; ?>
    <div><span>Snacks</span><span><?= number_format((float) $session['snacks_total'], 2) ?><?= h($currency) ?></span></div>
    <div class="grand"><span>Total</span><span><?= number_format((float) $session['total_price'], 2) ?><?= h($currency) ?></span></div>
  </div>

  <?php if (!empty($session['notes'])): ?>
    <div class="notes-box"><strong>Notes:</strong> <?= h($session['notes']) ?></div>
  <?php endif; ?>

  <div class="bill-footer">
    Thank you! · <?= h(date('Y-m-d H:i', strtotime($session['ended_at']))) ?>
  </div>
</div>

<script>
  // Uncomment to auto-open print dialog on load:
  // window.addEventListener('load', () => window.print());
</script>

</body>
</html>
