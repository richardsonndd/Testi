<?php

$DB_HOST = 'sql210.byethost7.com';
$DB_NAME = 'b7_42247357_lenti';
$DB_USER = 'b7_42247357';
$DB_PASS = 'NITi1995';

$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');
