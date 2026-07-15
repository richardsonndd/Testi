<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
$user = require_admin();
$page = 'stations';
$pageTitle = 'Manage Stations';
ensure_station_pricing_schema($conn);

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'add') {
    $name = trim($_POST['name'] ?? '');
    $consoleType = trim($_POST['console_type'] ?? 'PS5');
    $price2 = (float) ($_POST['price_per_hour_2'] ?? 2.50);
    $price4 = (float) ($_POST['price_per_hour_4'] ?? 4.00);
    $zoneId = (int) ($_POST['zone_id'] ?? 0);

    if ($name === '' || $zoneId <= 0) {
        flash_set('error', 'Station name and zone are required.');
    } else {
        // Stagger new stations across a simple grid so they don't overlap by default.
        $countRow = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS c FROM stations'));
        $index = (int) $countRow['c'];
        $posX = 20 + ($index % 5) * 140;
        $posY = 20 + intdiv($index, 5) * 140;

        $stmt = mysqli_prepare($conn, 'INSERT INTO stations (name, console_type, price_per_hour, price_per_hour_2, price_per_hour_4, zone_id, pos_x, pos_y) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'ssdddiii', $name, $consoleType, $price2, $price2, $price4, $zoneId, $posX, $posY);
        mysqli_stmt_execute($stmt);
        flash_set('success', 'Station "' . $name . '" added.');
    }
    redirect('stations.php');
}

// Handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'edit') {
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $consoleType = trim($_POST['console_type'] ?? 'PS5');
    $price2 = (float) ($_POST['price_per_hour_2'] ?? 0);
    $price4 = (float) ($_POST['price_per_hour_4'] ?? 0);
    $zoneId = (int) ($_POST['zone_id'] ?? 0);

    if ($name === '' || $zoneId <= 0) {
        flash_set('error', 'Station name and zone are required.');
    } else {
        $stmt = mysqli_prepare($conn, 'UPDATE stations SET name = ?, console_type = ?, price_per_hour = ?, price_per_hour_2 = ?, price_per_hour_4 = ?, zone_id = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'ssdddii', $name, $consoleType, $price2, $price2, $price4, $zoneId, $id);
        mysqli_stmt_execute($stmt);
        flash_set('success', 'Station updated.');
    }
    redirect('stations.php');
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = mysqli_prepare($conn, 'DELETE FROM stations WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    flash_set('success', 'Station deleted.');
    redirect('stations.php');
}

// Handle add zone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'add_zone') {
    $name = trim($_POST['zone_name'] ?? '');
    if ($name !== '') {
        $countRow = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS c FROM zones'));
        $stmt = mysqli_prepare($conn, 'INSERT INTO zones (name, sort_order) VALUES (?, ?)');
        $sortOrder = (int) $countRow['c'];
        mysqli_stmt_bind_param($stmt, 'si', $name, $sortOrder);
        mysqli_stmt_execute($stmt);
        flash_set('success', 'Zone "' . $name . '" added.');
    }
    redirect('stations.php');
}

$zones = [];
$res = mysqli_query($conn, 'SELECT * FROM zones ORDER BY sort_order ASC, name ASC');
while ($row = mysqli_fetch_assoc($res)) { $zones[] = $row; }

$stations = [];
$res = mysqli_query($conn, 'SELECT s.*, z.name AS zone_name FROM stations s JOIN zones z ON z.id = s.zone_id ORDER BY z.sort_order ASC, s.name ASC');
while ($row = mysqli_fetch_assoc($res)) { $stations[] = $row; }

include __DIR__ . '/includes/header.php';
?>

<h1>Manage Stations</h1>
<?php flash_render(); ?>

<div class="grid-2">
  <div class="card">
    <h2>Add Station</h2>
    <form method="POST">
      <input type="hidden" name="form" value="add">
      <label>Name</label>
      <input type="text" name="name" required placeholder="e.g. PS5 - 1">

      <label>Console Type</label>
      <input type="text" name="console_type" value="PS5">

      <label>2-player price / hour</label>
      <input type="number" step="0.01" name="price_per_hour_2" value="2.50" required>

      <label>4-player price / hour</label>
      <input type="number" step="0.01" name="price_per_hour_4" value="4.00" required>

      <label>Zone</label>
      <select name="zone_id" required>
        <?php foreach ($zones as $zone): ?>
          <option value="<?= (int) $zone['id'] ?>"><?= h($zone['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="btn btn-primary">Add Station</button>
    </form>
  </div>

  <div class="card">
    <h2>Add Zone</h2>
    <form method="POST">
      <input type="hidden" name="form" value="add_zone">
      <label>Zone name</label>
      <input type="text" name="zone_name" required placeholder="e.g. VIP Room">
      <button type="submit" class="btn btn-outline">Add Zone</button>
    </form>
  </div>
</div>

<h2 class="mt">All Stations</h2>
<table class="data-table">
  <thead>
    <tr><th>Name</th><th>Type</th><th>Zone</th><th>2P</th><th>4P</th><th>Status</th><th></th></tr>
  </thead>
  <tbody>
    <?php foreach ($stations as $s): ?>
    <tr>
      <form method="POST">
        <input type="hidden" name="form" value="edit">
        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
        <td><input type="text" name="name" value="<?= h($s['name']) ?>" required></td>
        <td><input type="text" name="console_type" value="<?= h($s['console_type']) ?>"></td>
        <td>
          <select name="zone_id">
            <?php foreach ($zones as $zone): ?>
              <option value="<?= (int) $zone['id'] ?>" <?= $zone['id'] == $s['zone_id'] ? 'selected' : '' ?>><?= h($zone['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td><input type="number" step="0.01" name="price_per_hour_2" value="<?= h($s['price_per_hour_2']) ?>" style="width:70px"></td>
        <td><input type="number" step="0.01" name="price_per_hour_4" value="<?= h($s['price_per_hour_4']) ?>" style="width:70px"></td>
        <td><span class="badge <?= $s['status'] === 'BUSY' ? 'badge-busy' : 'badge-free' ?>"><?= h($s['status']) ?></span></td>
        <td class="actions">
          <button type="submit" class="btn btn-sm btn-outline">Save</button>
      </form>
      <form method="POST" onsubmit="return confirm('Delete this station?')" style="display:inline">
          <input type="hidden" name="form" value="delete">
          <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </td>
      </form>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($stations)): ?>
      <tr><td colspan="7" class="muted">No stations yet.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<?php include __DIR__ . '/includes/footer.php'; ?>
