<?php
/**
 * PS Gaming Center — installer.
 * Visit this file once after upload. It creates config.php, all tables,
 * and your first ADMIN account, then locks itself.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$rootDir = __DIR__;
$configPath = $rootDir . '/config.php';
$lockPath = $rootDir . '/installed.lock';
$alreadyInstalled = file_exists($lockPath);

$errors = [];
$success = false;

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyInstalled) {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';

    $adminUsername = trim($_POST['admin_username'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';

    if ($dbName === '' || $dbUser === '') {
        $errors[] = 'Database name and user are required.';
    }
    if ($adminUsername === '' || strlen($adminPassword) < 6) {
        $errors[] = 'Admin username and a password (6+ characters) are required.';
    }

    if (empty($errors)) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = @mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);
        if (!$conn) {
            $errors[] = 'Could not connect to the database: ' . mysqli_connect_error();
        }
    }

    if (empty($errors)) {
        mysqli_set_charset($conn, 'utf8mb4');
        $schema = file_get_contents($rootDir . '/schema.sql');
        if ($schema === false) {
            $errors[] = 'Could not read schema.sql. Re-upload the project files.';
        } else {
            $statements = preg_split('/;\s*[\r\n]+/', $schema);
            $statements = array_filter(array_map('trim', $statements));
            foreach ($statements as $stmtSql) {
                if ($stmtSql === '') continue;
                if (!mysqli_query($conn, $stmtSql)) {
                    $errors[] = 'Schema error: ' . mysqli_error($conn) . ' (statement: ' . substr($stmtSql, 0, 80) . '...)';
                    break;
                }
            }
        }
    }

    if (empty($errors)) {
        $existing = mysqli_query($conn, "SELECT id FROM users WHERE username = '" . mysqli_real_escape_string($conn, $adminUsername) . "'");
        if ($existing && mysqli_num_rows($existing) > 0) {
            $errors[] = 'That admin username already exists in the database. Choose a different one, or delete installed.lock/config.php and start with a fresh database.';
        } else {
            $hash = password_hash($adminPassword, PASSWORD_BCRYPT);
            $role = 'ADMIN';
            $stmt = mysqli_prepare($conn, 'INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sss', $adminUsername, $hash, $role);
            if (!mysqli_stmt_execute($stmt)) {
                $errors[] = 'Could not create admin account: ' . mysqli_error($conn);
            }
        }
    }

    if (empty($errors)) {
        $configContents = "<?php\n\n"
            . "\$DB_HOST = " . var_export($dbHost, true) . ";\n"
            . "\$DB_NAME = " . var_export($dbName, true) . ";\n"
            . "\$DB_USER = " . var_export($dbUser, true) . ";\n"
            . "\$DB_PASS = " . var_export($dbPass, true) . ";\n\n"
            . "\$conn = mysqli_connect(\$DB_HOST, \$DB_USER, \$DB_PASS, \$DB_NAME);\n\n"
            . "if (!\$conn) {\n"
            . "    die('Database connection failed: ' . mysqli_connect_error());\n"
            . "}\n\n"
            . "mysqli_set_charset(\$conn, 'utf8mb4');\n";

        if (@file_put_contents($configPath, $configContents) === false) {
            $errors[] = 'Could not write config.php. Check that this folder is writable (chmod 755 or 775).';
        }
    }

    if (empty($errors)) {
        file_put_contents($lockPath, 'installed at ' . date('c'));
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PS Gaming Center — Installer</title>
<style>
  * { box-sizing: border-box; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #0f172a; color: #e2e8f0; margin: 0; padding: 40px 16px;
  }
  .wrap { max-width: 480px; margin: 0 auto; }
  h1 { font-size: 22px; margin-bottom: 4px; }
  p.sub { color: #94a3b8; margin-top: 0; margin-bottom: 28px; }
  .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 24px; margin-bottom: 20px; }
  .card h2 { font-size: 14px; text-transform: uppercase; letter-spacing: .04em; color: #38bdf8; margin-top: 0; }
  label { display: block; font-size: 13px; margin: 14px 0 6px; color: #cbd5e1; }
  input {
    width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #334155;
    background: #0f172a; color: #e2e8f0; font-size: 14px;
  }
  input:focus { outline: none; border-color: #38bdf8; }
  button {
    margin-top: 24px; width: 100%; padding: 12px; border-radius: 8px; border: none;
    background: #38bdf8; color: #0f172a; font-weight: 700; font-size: 15px; cursor: pointer;
  }
  button:hover { background: #0ea5e9; }
  .errors { background: #451a1a; border: 1px solid #7f1d1d; color: #fecaca; padding: 14px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
  .errors ul { margin: 6px 0 0; padding-left: 18px; }
  .success { background: #052e1f; border: 1px solid #14532d; color: #bbf7d0; padding: 18px; border-radius: 10px; font-size: 14px; line-height: 1.6; }
  .success code { background: #0f172a; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
  .warn { color: #fcd34d; font-weight: 600; }
  small { color: #64748b; }
</style>
</head>
<body>
<div class="wrap">
  <h1>🎮 PS Gaming Center</h1>
  <p class="sub">One-time setup.</p>

  <?php if ($alreadyInstalled): ?>
    <div class="card">
      <div class="success">
        Already installed.<br><br>
        To reinstall with a new database, delete <code>installed.lock</code> and
        <code>config.php</code>, then reload this page.<br><br>
        <span class="warn">For security, delete or rename install.php now.</span>
      </div>
    </div>
  <?php elseif ($success): ?>
    <div class="card">
      <div class="success">
        ✅ Install complete! Tables created and your admin account is ready.<br><br>
        <a href="login.php" style="color:#38bdf8">Go to login →</a><br><br>
        <span class="warn">Delete or rename install.php now — leaving it live is a security risk.</span>
      </div>
    </div>
  <?php else: ?>
    <?php if ($errors): ?>
      <div class="errors">
        <strong>Please fix the following:</strong>
        <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="card">
        <h2>Database</h2>
        <label>Host</label>
        <input type="text" name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>" required>
        <label>Database name</label>
        <input type="text" name="db_name" value="<?= h($_POST['db_name'] ?? '') ?>" required>
        <label>Database user</label>
        <input type="text" name="db_user" value="<?= h($_POST['db_user'] ?? '') ?>" required>
        <label>Database password</label>
        <input type="password" name="db_pass" value="<?= h($_POST['db_pass'] ?? '') ?>">
      </div>

      <div class="card">
        <h2>Admin account</h2>
        <label>Username</label>
        <input type="text" name="admin_username" value="<?= h($_POST['admin_username'] ?? '') ?>" required>
        <label>Password (6+ characters)</label>
        <input type="password" name="admin_password" required minlength="6">
      </div>

      <button type="submit">Install</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
