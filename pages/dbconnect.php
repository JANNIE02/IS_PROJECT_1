<?php
// db_connect.php
$DB_HOST = 'localhost';
$DB_PORT = '5432';              
$DB_NAME = 'food_connect';
$DB_USER = 'postgres';
$DB_PASS = 'Mokoro111*';                  

$dsn = "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME}";

try {
    $conn = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}
?>