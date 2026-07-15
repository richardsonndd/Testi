<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
$user = require_login();
redirect('dashboard.php');
