<?php
session_start();
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $rol = $_POST['rol'];
    $id_empleado = $_POST['id_empleado'];
    $activo = isset($_POST['activo']) ? 1 : 0;

    // Hashear la contraseña por seguridad
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol, id_empleado, activo) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $email, $password_hash, $rol, $id_empleado, $activo]);
        $_SESSION['mensaje_exito'] = "Usuario agregado correctamente.";
    } catch (PDOException $e) {
        $_SESSION['mensaje_error'] = "Error al agregar usuario: " . $e->getMessage();
    }
    header("Location: index.php");
    exit;
}