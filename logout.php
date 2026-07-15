<?php
require_once __DIR__ . '/includes/auth.php';

unset($_SESSION['user']);
session_destroy();
redirect('login.php');
