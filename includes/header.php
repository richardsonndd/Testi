<?php
/** @var array|null $user set by including page before this include */
$user = current_user();
$page = $page ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php
$siteSettings = get_site_settings($conn);
$siteName = $siteSettings['site_name'] ?? 'PS Gaming Center';
?>
<title><?= isset($pageTitle) ? h($pageTitle) . ' — ' : '' ?><?= h($siteName) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php if ($user): ?>
<div class="page-shell">
  <aside class="sidebar" id="siteSidebar" aria-label="Main navigation">
    <div class="sidebar-header">
      <div>
        <a href="dashboard.php" class="brand sidebar-brand">🎮 <?= h($siteName) ?></a>
        <p class="sidebar-tag muted small">Menuja e aksesit të shpejtë</p>
      </div>
      <button id="sidebarClose" class="btn btn-sm btn-outline">✕</button>
    </div>
    <nav class="sidebar-nav">
      <a href="dashboard.php" class="sidebar-link <?= $page === 'dashboard' ? 'active' : '' ?>">Paneli</a>
      <?php if ($user['role'] === 'ADMIN'): ?>
      <a href="admin.php" class="sidebar-link <?= $page === 'admin' ? 'active' : '' ?>">Admin</a>
      <?php endif; ?>
      <a href="stations.php" class="sidebar-link <?= $page === 'stations' ? 'active' : '' ?>">Stacionet</a>
      <a href="snacks.php" class="sidebar-link <?= $page === 'snacks' ? 'active' : '' ?>">Snack-et</a>
      <a href="reservations.php" class="sidebar-link <?= $page === 'reservations' ? 'active' : '' ?>">Rezervimet</a>
      <a href="sessions.php" class="sidebar-link <?= $page === 'sessions' ? 'active' : '' ?>">Historia</a>
      <?php if ($user['role'] === 'ADMIN'): ?>
      <a href="audit.php" class="sidebar-link <?= $page === 'audit' ? 'active' : '' ?>">Auditimi</a>
      <a href="users.php" class="sidebar-link <?= $page === 'users' ? 'active' : '' ?>">Stafi</a>
      <?php endif; ?>
    </nav>
  </aside>
  <div class="content-shell">
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <header class="topbar">
      <div class="topbar-inner">
        <button id="sidebarToggle" class="btn btn-sm btn-outline">☰ Menu</button>
        <a href="dashboard.php" class="brand">🎮 <?= h($siteName) ?></a>
        <div class="topbar-user">
          <span><?= h($user['username']) ?> <small>(<?= h($user['role']) ?>)</small></span>
          <a href="logout.php" class="btn btn-sm btn-outline">Dil</a>
        </div>
      </div>
    </header>
<?php endif; ?>
<main class="container">
