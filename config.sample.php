<?php
/**
 * PS Gaming Center config.
 * install.php generates config.php automatically — don't edit this sample on a live site.
 */

$DB_HOST = 'localhost';
$DB_NAME = 'psgc';
$DB_USER = 'psgc_user';
$DB_PASS = 'CHANGE_ME';

$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');
