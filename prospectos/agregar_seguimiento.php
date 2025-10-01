<?php
// 1. session_start() DEBE ser lo primero en el archivo.
session_start();

// 2. Incluir solo la configuración de la base de datos.
include '../config/db.php';

// Redirigir si no hay sesión iniciada
if (!isset($_SESSION['usuario_id'])) {
    header("Location: /login.php");
    exit;
}

// 3. Toda la lógica de la página va aquí, ANTES de cualquier HTML.
$prospecto_id = filter_input(INPUT_GET, 'prospecto_id', FILTER_VALIDATE_INT);
if (!$prospecto_id && isset($_POST['prospecto_id'])) {
    $prospecto_id = filter_input(INPUT_POST, 'prospecto_id', FILTER_VALIDATE_INT);
}

if (!$prospecto_id) {
    $_SESSION['mensaje_error'] = "ID de prospecto no proporcionado o inválido.";
    header("Location: index.php");
    exit;
}

$mensaje_error = '';
$prospecto_nombre = '';

try {
    $stmt = $pdo->prepare("SELECT nombre FROM prospectos WHERE id = ?");
    $stmt->execute([$prospecto_id]);
    $prospecto_nombre = $stmt->fetchColumn();

    if (!$prospecto_nombre) {
        $_SESSION['mensaje_error'] = "El prospecto no existe.";
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    die("Error al conectar con la base de datos: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nota = $_POST['nota'] ?? '';
    $usuario_id = $_SESSION['usuario_id'];

    if (empty(trim($nota))) {
        $mensaje_error = "La nota no puede estar vacía.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO prospecto_seguimientos (prospecto_id, usuario_id, nota) VALUES (?, ?, ?)");
            $stmt->execute([$prospecto_id, $usuario_id, $nota]);

            $_SESSION['mensaje_exito'] = "Nota de seguimiento agregada correctamente.";
            // Esta redirección ahora funcionará porque no se ha enviado HTML
            header("Location: ver.php?id=" . $prospecto_id);
            exit;
        } catch (PDOException $e) {
            $mensaje_error = "Error al guardar la nota: " . $e->getMessage();
        }
    }
}

// 4. AHORA, después de toda la lógica, incluimos el header que SÍ dibuja HTML.
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Seguimiento a Prospecto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd; --light-bg: #f8f9fa; --border-color: #dee2e6;
            --card-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        body { background-color: var(--light-bg); }
        .main-card { background-color: white; border-radius: 0.75rem; border: 1px solid var(--border-color); box-shadow: var(--card-shadow); overflow: hidden; }
        .card-header-custom { background-color: #fff; padding: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .page-title { font-weight: 700; font-size: 1.75rem; }
    </style>
</head>
<body>
<div class="container my-4">
    <div class="main-card">
        <div class="card-header-custom">
            <div>
                <h1 class="page-title mb-0"><i class="fas fa-history me-2"></i>Agregar Nota de Seguimiento</h1>
                <p class="text-muted mb-0">Para el prospecto: <strong><?= htmlspecialchars($prospecto_nombre) ?></strong></p>
            </div>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($mensaje_error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($mensaje_error) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="agregar_seguimiento.php?prospecto_id=<?= $prospecto_id ?>">
                <div class="mb-3">
                    <label for="nota" class="form-label fw-bold">Detalles del Seguimiento (Llamada, Correo, Visita, etc.)</label>
                    <textarea class="form-control" id="nota" name="nota" rows="6" required placeholder="Escribe aquí los detalles del contacto..."><?= htmlspecialchars($_POST['nota'] ?? '') ?></textarea>
                </div>
                <div class="text-end">
                    <a href="ver.php?id=<?= $prospecto_id ?>" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Guardar Nota
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>