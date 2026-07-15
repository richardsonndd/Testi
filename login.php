<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Ju lutemi vendosni si emrin e përdoruesit ashtu edhe fjalëkalimin.';
    } else {
        $stmt = mysqli_prepare($conn, 'SELECT * FROM users WHERE username = ?');
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        if ($row && password_verify($password, $row['password_hash'])) {
            $_SESSION['user'] = [
                'id' => $row['id'],
                'username' => $row['username'],
                'role' => $row['role'],
            ];
            redirect('index.php');
        } else {
            $error = 'Emri i përdoruesit ose fjalëkalimi është i pasaktë.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Identifikohu — PS Gaming Center</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
  <form method="POST" class="auth-card">
    <h1>🎮 PS Gaming Center</h1>
    <p class="muted">Identifikohu për të vazhduar</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <label>Emri i përdoruesit</label>
    <input type="text" name="username" required autofocus value="<?= h($_POST['username'] ?? '') ?>">

    <label>Fjalëkalimi</label>
    <input type="password" name="password" required>

    <button type="submit" class="btn btn-primary btn-block">Identifikohu</button>
  </form>
</body>
</html>
