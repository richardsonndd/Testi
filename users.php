<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
$user = require_admin();
$page = 'users';
$pageTitle = 'Manage Staff';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'add') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = ($_POST['role'] ?? 'STAFF') === 'ADMIN' ? 'ADMIN' : 'STAFF';

    if ($username === '' || strlen($password) < 6) {
        flash_set('error', 'Username and a password of at least 6 characters are required.');
    } else {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE username = ?');
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
            flash_set('error', 'That username is already taken.');
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = mysqli_prepare($conn, 'INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sss', $username, $hash, $role);
            mysqli_stmt_execute($stmt);
            flash_set('success', 'Account "' . $username . '" created.');
        }
    }
    redirect('users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id === (int) $user['id']) {
        flash_set('error', "You can't delete your own account while logged in.");
    } else {
        $stmt = mysqli_prepare($conn, 'DELETE FROM users WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        flash_set('success', 'Account deleted.');
    }
    redirect('users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'reset_password') {
    $id = (int) ($_POST['id'] ?? 0);
    $password = $_POST['password'] ?? '';
    if (strlen($password) < 6) {
        flash_set('error', 'New password must be at least 6 characters.');
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = mysqli_prepare($conn, 'UPDATE users SET password_hash = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'si', $hash, $id);
        mysqli_stmt_execute($stmt);
        flash_set('success', 'Password updated.');
    }
    redirect('users.php');
}

$users = [];
$res = mysqli_query($conn, 'SELECT * FROM users ORDER BY role ASC, username ASC');
while ($row = mysqli_fetch_assoc($res)) { $users[] = $row; }

include __DIR__ . '/includes/header.php';
?>

<h1>Manage Staff</h1>
<?php flash_render(); ?>

<div class="card">
  <h2>Add Account</h2>
  <form method="POST">
    <input type="hidden" name="form" value="add">
    <label>Username</label>
    <input type="text" name="username" required>
    <label>Password</label>
    <input type="password" name="password" required minlength="6">
    <label>Role</label>
    <select name="role">
      <option value="STAFF">Staff</option>
      <option value="ADMIN">Admin</option>
    </select>
    <button type="submit" class="btn btn-primary">Create Account</button>
  </form>
</div>

<h2 class="mt">All Accounts</h2>
<table class="data-table">
  <thead><tr><th>Username</th><th>Role</th><th>Created</th><th>Reset Password</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><?= h($u['username']) ?></td>
      <td><span class="badge <?= $u['role'] === 'ADMIN' ? 'badge-busy' : 'badge-free' ?>"><?= h($u['role']) ?></span></td>
      <td><?= h($u['created_at']) ?></td>
      <td>
        <form method="POST" class="inline-form">
          <input type="hidden" name="form" value="reset_password">
          <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <input type="password" name="password" placeholder="New password" minlength="6" style="width:140px">
          <button type="submit" class="btn btn-sm btn-outline">Update</button>
        </form>
      </td>
      <td>
        <?php if ((int) $u['id'] !== (int) $user['id']): ?>
        <form method="POST" onsubmit="return confirm('Delete this account?')">
          <input type="hidden" name="form" value="delete">
          <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </form>
        <?php else: ?>
          <span class="muted small">(you)</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php include __DIR__ . '/includes/footer.php'; ?>
