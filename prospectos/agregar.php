<?php
// Iniciar sesión para manejar mensajes y obtener usuarios
session_start();

include '../config/db.php';
include '../includes/header.php';

$mensaje_error = '';
$usuarios = [];

try {
    // Obtener la lista de usuarios (vendedores) para el menú desplegable
    $stmt_usuarios = $pdo->query("SELECT id, nombre FROM usuarios WHERE activo = 1 ORDER BY nombre");
    $usuarios = $stmt_usuarios->fetchAll();
} catch (PDOException $e) {
    $mensaje_error = "Error al cargar la lista de vendedores.";
    error_log($e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $email = $_POST['email'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $estado = $_POST['estado'] ?? 'nuevo';
    $notas = $_POST['notas'] ?? '';
    // Capturar el ID del usuario asignado desde el nuevo campo del formulario
    $usuario_asignado_id = $_POST['asignado_a'] ?? null;

    if (trim($nombre) === '' || empty($usuario_asignado_id)) {
        $mensaje_error = "El nombre y el vendedor asignado son obligatorios.";
    } else {
        try {
            // Añadir `asignado_a` a la consulta de inserción
            $stmt = $pdo->prepare("INSERT INTO prospectos (nombre, email, telefono, estado, notas, asignado_a) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $email, $telefono, $estado, $notas, $usuario_asignado_id]);
            
            $_SESSION['mensaje_exito'] = "Prospecto agregado y asignado correctamente.";
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            error_log("Error al agregar prospecto: " . $e->getMessage());
            $mensaje_error = "Error al agregar el prospecto.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Prospecto - Sistema de Inventario</title>
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
        .section-title { font-weight: 600; font-size: 1.1rem; color: #495057; margin-bottom: 1.5rem; display: flex; align-items: center; border-bottom: 2px solid var(--primary-color); padding-bottom: 0.5rem;}
        .section-title .fas { color: var(--primary-color); margin-right: 0.75rem; }
    </style>
</head>
<body>
<div class="container my-4">
    <form method="post" id="prospectoForm">
        <div class="main-card">
            <div class="card-header-custom">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title mb-0"><i class="fas fa-user-plus me-2"></i>Agregar Prospecto</h1>
                        <p class="text-muted mb-0">Complete la información del cliente potencial</p>
                    </div>
                    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Volver</a>
                </div>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($mensaje_error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($mensaje_error) ?></div>
                <?php endif; ?>

                <section class="mb-4">
                    <h5 class="section-title"><i class="fas fa-user"></i>Información del Prospecto</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" class="form-control" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estado Inicial</label>
                            <select name="estado" class="form-select">
                                <option value="nuevo" selected>Nuevo</option>
                                <option value="contactado">Contactado</option>
                                <option value="interesado">Interesado</option>
                                <option value="no interesado">No interesado</option>
                            </select>
                        </div>
                    </div>
                </section>
                <hr class="my-4">
                <section>
                    <h5 class="section-title"><i class="fas fa-user-tie"></i>Asignación y Notas</h5>
                     <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="asignado_a" class="form-label">Asignar a Vendedor <span class="text-danger">*</span></label>
                            <select name="asignado_a" id="asignado_a" class="form-select" required>
                                <option value="">-- Selecciona un usuario --</option>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?= $usuario['id'] ?>"><?= htmlspecialchars($usuario['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                             <label class="form-label">Notas Adicionales</label>
                            <textarea name="notas" class="form-control" rows="4"><?= htmlspecialchars($_POST['notas'] ?? '') ?></textarea>
                        </div>
                    </div>
                </section>
            </div>
            <div class="card-footer text-end p-3">
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Guardar Prospecto</button>
            </div>
        </div>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php include '../includes/footer.php'; ?>