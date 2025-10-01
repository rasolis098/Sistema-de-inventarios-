<?php
// config/db.php
$host = 'localhost';
$dbname = 'dialexan_inventariosystem';
$username = 'dialexan';
$password = '././2_2/8-$/';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error de conexion: " . $e->getMessage());
}
?>