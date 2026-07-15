<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
$user = require_admin();
$page = 'snacks';
$pageTitle = 'Manage Snacks';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'add') {
    $name = trim($_POST['name'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock'] ?? 0);

    if ($name === '') {
        flash_set('error', 'Snack name is required.');
    } else {
        $stmt = mysqli_prepare($conn, 'INSERT INTO snacks (name, price, stock) VALUES (?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'sdi', $name, $price, $stock);
        mysqli_stmt_execute($stmt);
        log_audit_event($conn, $user['id'], 'snack_added', 'Added snack ' . $name, 'snack', mysqli_insert_id($conn));
        flash_set('success', 'Snack "' . $name . '" added.');
    }
    redirect('snacks.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'edit') {
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock'] ?? 0);

    if ($name === '') {
        flash_set('error', 'Snack name is required.');
    } else {
        $stmt = mysqli_prepare($conn, 'UPDATE snacks SET name = ?, price = ?, stock = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'sdii', $name, $price, $stock, $id);
        mysqli_stmt_execute($stmt);
        log_audit_event($conn, $user['id'], 'snack_updated', 'Updated snack ' . $name, 'snack', $id);
        flash_set('success', 'Snack updated.');
    }
    redirect('snacks.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = mysqli_prepare($conn, 'DELETE FROM snacks WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    log_audit_event($conn, $user['id'], 'snack_deleted', 'Deleted snack', 'snack', $id);
    flash_set('success', 'Snack deleted.');
    redirect('snacks.php');
}

$snacks = [];
$res = mysqli_query($conn, 'SELECT * FROM snacks ORDER BY name ASC');
while ($row = mysqli_fetch_assoc($res)) { $snacks[] = $row; }

include __DIR__ . '/includes/header.php';
?>

<h1>Manage Snacks</h1>
<?php flash_render(); ?>

<div class="card">
  <h2>Add Snack</h2>
  <form method="POST">
    <input type="hidden" name="form" value="add">
    <label>Name</label>
    <input type="text" name="name" required placeholder="e.g. Cola">
    <label>Price</label>
    <input type="number" step="0.01" name="price" value="0" required>
    <label>Stock</label>
    <input type="number" name="stock" value="0" required>
    <button type="submit" class="btn btn-primary">Add Snack</button>
  </form>
</div>

<h2 class="mt">All Snacks</h2>
<table class="data-table">
  <thead><tr><th>Name</th><th>Price</th><th>Stock</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($snacks as $s): ?>
    <tr>
      <form method="POST">
        <input type="hidden" name="form" value="edit">
        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
        <td><input type="text" name="name" value="<?= h($s['name']) ?>" required></td>
        <td><input type="number" step="0.01" name="price" value="<?= h($s['price']) ?>" style="width:90px"></td>
        <td><input type="number" name="stock" value="<?= (int) $s['stock'] ?>" style="width:80px"></td>
        <td class="actions">
          <button type="submit" class="btn btn-sm btn-outline">Save</button>
      </form>
      <form method="POST" onsubmit="return confirm('Delete this snack?')" style="display:inline">
          <input type="hidden" name="form" value="delete">
          <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </td>
      </form>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($snacks)): ?>
      <tr><td colspan="4" class="muted">No snacks yet.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<?php include __DIR__ . '/includes/footer.php'; ?>
