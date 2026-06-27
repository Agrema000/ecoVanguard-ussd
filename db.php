<?php
$host     = "localhost";
$db_name  = "ecovanguard_db";
$username = "root";
$password = ""; // XAMPP default password is blank

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    // Set error mode so we can catch bugs quickly
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If connection fails, stop everything and show why
    die("Database Connection Failed: " . $e->getMessage());
}
?>