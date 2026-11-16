<?php
mysqli_report(MYSQLI_REPORT_OFF);

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "portfolio_samuel";

$connect = mysqli_connect($host, $user, $pass);

if (!$connect) {
    die("Database connection failed. Please start MySQL and try again.");
}

mysqli_query($connect, "CREATE DATABASE IF NOT EXISTS `$dbname`");
mysqli_select_db($connect, $dbname);

mysqli_query($connect, "CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
