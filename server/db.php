<?php
require_once __DIR__ . '/config.php';

function db_connect(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $conn->set_charset(DB_CHARSET);
    // Data waktu proyek menggunakan WIB (UTC+7).
    $conn->query("SET time_zone = '+07:00'");
    return $conn;
}
