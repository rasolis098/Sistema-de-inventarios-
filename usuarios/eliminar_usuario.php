<?php
session_start();
include '../config/db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['mensaje_exito'] = "Usuario eliminado correctamente.";
    } catch (PDOException $e) {
        $_SESSION['mensaje_error'] = "Error al eliminar usuario: " . $e->getMessage();
    }
}
header("Location: index.php");
exit;