<?php
// correo/conectar_correo.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/db.php';

// Si el usuario no ha iniciado sesión en el sistema principal, lo expulsamos.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

$error_mensaje = '';

// Si el formulario se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_ingresada = $_POST['password'] ?? '';
    
    // Obtenemos el hash de la contraseña desde la BD para verificar
    $stmt = $pdo->prepare("SELECT email, password_hash FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificamos si la contraseña ingresada coincide con el hash guardado
    if ($usuario && password_verify($password_ingresada, $usuario['password_hash'])) {
        // ¡Éxito! La contraseña es correcta.
        // Guardamos las credenciales de correo en la sesión.
        $_SESSION['imap_user'] = $usuario['email']; // Usamos el email como usuario IMAP
        $_SESSION['imap_pass'] = $password_ingresada; // Guardamos la contraseña ingresada
        
        // Redirigimos a la bandeja de entrada
        header('Location: index.php');
        exit;
    } else {
        $error_mensaje = "La contraseña es incorrecta. Inténtalo de nuevo.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Conectar al Correo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center vh-100 bg-light">
    <div class="card shadow p-4" style="width: 100%; max-width: 450px;">
        <h4 class="mb-3 text-center">Acceso al Módulo de Correo</h4>
        <p class="text-center text-muted mb-4">Por seguridad, por favor confirma tu contraseña para continuar.</p>
        
        <?php if ($error_mensaje): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_mensaje) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <input name="password" id="password" type="password" class="form-control" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary w-100">Desbloquear Correo</button>
        </form>
        <div class="text-center mt-3">
            <a href="/index.php">Volver al Dashboard</a>
        </div>
    </div>
</body>
</html>