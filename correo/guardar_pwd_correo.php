<?php
session_start();
require_once '../config/db.php';

// Redirección de seguridad si el usuario no está logueado o el método no es POST
if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

// Validar que la contraseña no esté vacía
if (isset($_POST['mail_password']) && !empty($_POST['mail_password'])) {
    $password = $_POST['mail_password'];
    $usuario_id = $_SESSION['usuario_id'];

    // --- NOTA DE SEGURIDAD ---
    // En una aplicación real, deberías ENCRIPTAR esta contraseña antes de guardarla.
    // Por simplicidad en este ejemplo, se guarda directamente.
    // ¡NO USAR ESTO EN PRODUCCIÓN SIN ENCRIPTACIÓN!

    try {
        $stmt = $pdo->prepare("UPDATE usuarios SET mail_password = ? WHERE id = ?");
        $stmt->execute([$password, $usuario_id]);

        // Redirigir de vuelta a la URL original para que se recargue y muestre el correo
        if (isset($_POST['redirect_url']) && !empty($_POST['redirect_url'])) {
            header("Location: " . $_POST['redirect_url']);
            exit();
        } else {
            header("Location: index.php"); // Redirección de respaldo
            exit();
        }

    } catch (PDOException $e) {
        // En caso de error en la base de datos, muestra un mensaje
        die("Error al guardar la contraseña: " . $e->getMessage());
    }

} else {
    // Si la contraseña está vacía, simplemente redirige de vuelta
    if (isset($_POST['redirect_url'])) {
        header("Location: " . $_POST['redirect_url']);
        exit();
    } else {
        header("Location: index.php");
        exit();
    }
}
?>