<?php
session_start();
include '../config/db.php';
include '../includes/header.php';

// Validar ID
$prospecto_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$prospecto_id) {
    $_SESSION['mensaje_error'] = "ID de prospecto no proporcionado o inválido.";
    header("Location: index.php");
    exit;
}

$error_db = '';
$prospecto = null;
$seguimientos = [];

try {
    // Obtener los datos del prospecto y el nombre del usuario asignado
    $stmt = $pdo->prepare("
        SELECT p.*, u.nombre AS vendedor_nombre
        FROM prospectos p
        LEFT JOIN usuarios u ON p.asignado_a = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$prospecto_id]);
    $prospecto = $stmt->fetch();

    if (!$prospecto) {
        $_SESSION['mensaje_error'] = "Prospecto no encontrado.";
        header("Location: index.php");
        exit;
    }

    // Obtener el historial de seguimiento (asumiendo que tienes una tabla `prospecto_seguimientos`)
    $stmt_seguimiento = $pdo->prepare("
        SELECT s.*, u.nombre as usuario_nombre
        FROM prospecto_seguimientos s
        JOIN usuarios u ON s.usuario_id = u.id
        WHERE s.prospecto_id = ?
        ORDER BY s.fecha_seguimiento DESC
    ");
    $stmt_seguimiento->execute([$prospecto_id]);
    $seguimientos = $stmt_seguimiento->fetchAll();

} catch (PDOException $e) {
    $error_db = "Error al cargar los datos del prospecto. Asegúrate de que la tabla 'prospecto_seguimientos' exista si deseas usar esa funcionalidad.";
    // No detenemos la ejecución, para poder mostrar la info básica del prospecto.
}

// Mapeo de estados a clases de CSS
$estados_clase = [
    'nuevo' => 'secondary',
    'contactado' => 'info text-dark',
    'interesado' => 'success',
    'no interesado' => 'danger'
];
$clase_badge = $estados_clase[strtolower($prospecto['estado'])] ?? 'light';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Prospecto - <?= htmlspecialchars($prospecto['nombre']) ?></title>
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
        .section-title { font-weight: 600; font-size: 1.1rem; color: #495057; margin-bottom: 1.5rem; }
        .info-label { color: #6c757d; font-size: 0.9rem; }
        .info-value { font-weight: 500; }
        .timeline { list-style: none; padding: 0; position: relative; }
        .timeline:before { content: ''; position: absolute; top: 0; bottom: 0; width: 2px; background: var(--border-color); left: 20px; margin-left: -1.5px; }
        .timeline-item { margin-bottom: 20px; position: relative; }
        .timeline-icon { position: absolute; left: 20px; top: 0; margin-left: -12px; width: 24px; height: 24px; border-radius: 50%; background: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; z-index: 1; }
        .timeline-content { margin-left: 60px; background: var(--light-bg); border-radius: .5rem; padding: 15px; }
    </style>
</head>
<body>
<div class="container my-4">

    <?php if ($error_db): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($error_db) ?></div>
    <?php endif; ?>

    <div class="main-card">
        <div class="card-header-custom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h1 class="page-title mb-0"><i class="fas fa-user-tag me-2"></i>Perfil del Prospecto</h1>
                    <p class="text-muted mb-0"><?= htmlspecialchars($prospecto['nombre']) ?></p>
                </div>
                <div class="text-end">
                    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Volver</a>
                    <a href="editar.php?id=<?= $prospecto['id'] ?>" class="btn btn-warning btn-sm"><i class="fas fa-edit me-1"></i>Editar</a>
                    <a href="agregar_seguimiento.php?prospecto_id=<?= $prospecto['id'] ?>" class="btn btn-info btn-sm text-white"><i class="fas fa-plus me-1"></i>Añadir Nota</a>
                </div>
            </div>
        </div>

        <div class="card-body p-4">
            <div class="row">
                <div class="col-lg-5">
                    <section>
                        <h5 class="section-title">Información de Contacto</h5>
                        <p class="mb-2"><strong class="info-label">Email:</strong> <span class="info-value"><?= htmlspecialchars($prospecto['email'] ?? 'No especificado') ?></span></p>
                        <p class="mb-2"><strong class="info-label">Teléfono:</strong> <span class="info-value"><?= htmlspecialchars($prospecto['telefono'] ?? 'No especificado') ?></span></p>
                    </section>
                    <hr class="my-4">
                    <section>
                        <h5 class="section-title">Estado y Asignación</h5>
                        <p class="mb-2"><strong class="info-label">Estado actual:</strong> <span class="badge text-bg-<?= $clase_badge ?>"><?= htmlspecialchars(ucfirst($prospecto['estado'])) ?></span></p>
                        <p class="mb-2"><strong class="info-label">Vendedor Asignado:</strong> <span class="info-value"><?= htmlspecialchars($prospecto['vendedor_nombre'] ?? 'Sin asignar') ?></span></p>
                        <p class="mb-2"><strong class="info-label">Fecha de Registro:</strong> <span class="info-value"><?= date('d/m/Y H:i', strtotime($prospecto['fecha_registro'])) ?></span></p>
                    </section>
                    <hr class="my-4">
                     <section>
                        <h5 class="section-title">Notas Generales</h5>
                        <p class="info-value fst-italic"><?= !empty($prospecto['notas']) ? nl2br(htmlspecialchars($prospecto['notas'])) : 'No hay notas generales.' ?></p>
                    </section>
                </div>

                <div class="col-lg-7">
                    <h5 class="section-title">Historial de Seguimiento</h5>
                    <?php if (empty($seguimientos)): ?>
                        <div class="text-center text-muted p-4 border rounded">
                            <i class="fas fa-history fa-2x mb-2"></i>
                            <p>No hay notas de seguimiento para este prospecto.</p>
                        </div>
                    <?php else: ?>
                        <ul class="timeline">
                            <?php foreach ($seguimientos as $nota): ?>
                            <li class="timeline-item">
                                <div class="timeline-icon"><i class="fas fa-comment"></i></div>
                                <div class="timeline-content">
                                    <p class="mb-1"><?= nl2br(htmlspecialchars($nota['nota'])) ?></p>
                                    <small class="text-muted">
                                        Por <strong><?= htmlspecialchars($nota['usuario_nombre']) ?></strong> el <?= date('d/m/Y H:i', strtotime($nota['fecha_seguimiento'])) ?>
                                    </small>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php include '../includes/footer.php'; ?>