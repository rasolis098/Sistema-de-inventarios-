<?php
session_start();
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $rol = $_POST['rol'];
    $id_empleado = $_POST['id_empleado'];
    $activo = isset($_POST['activo']) ? 1 : 0;

    try {
        if (!empty($password)) {
            // Si se proporcionó una nueva contraseña, la actualizamos
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, password_hash = ?, rol = ?, id_empleado = ?, activo = ? WHERE id = ?");
            $stmt->execute([$nombre, $email, $password_hash, $rol, $id_empleado, $activo, $id]);
        } else {
            // Si no, actualizamos todo excepto la contraseña
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ?, id_empleado = ?, activo = ? WHERE id = ?");
            $stmt->execute([$nombre, $email, $rol, $id_empleado, $activo, $id]);
        }
        $_SESSION['mensaje_exito'] = "Usuario actualizado correctamente.";
    } catch (PDOException $e) {
        $_SESSION['mensaje_error'] = "Error al actualizar usuario: " . $e->getMessage();
    }
    header("Location: index.php");
    exit;
}