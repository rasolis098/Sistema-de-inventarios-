<?php
session_start();
include '../config/db.php';
include '../includes/header.php';

// Redirigir si no hay sesión iniciada
if (!isset($_SESSION['usuario_id'])) {
    header("Location: /login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$error_db = '';

try {
    // 1. Obtener la información del usuario logueado
    $stmt_user = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt_user->execute([$usuario_id]);
    $usuario = $stmt_user->fetch();

    // 2. Obtener estadísticas de COTIZACIONES del usuario
    $stmt_stats_cot = $pdo->prepare("
        SELECT
            COUNT(*) as total_cotizaciones,
            SUM(total) as monto_total_cotizado,
            SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) as cotizaciones_aprobadas
        FROM cotizaciones
        WHERE usuario_id = ?
    ");
    $stmt_stats_cot->execute([$usuario_id]);
    $stats_cot = $stmt_stats_cot->fetch();
    $tasa_conversion = (($stats_cot['total_cotizaciones'] ?? 0) > 0) ? round((($stats_cot['cotizaciones_aprobadas'] ?? 0) / $stats_cot['total_cotizaciones']) * 100, 1) : 0;

    // 3. OBTENER ESTADÍSTICAS DE VENTAS Y PROSPECTOS (NUEVO)
    $stmt_stats_ventas = $pdo->prepare("SELECT COUNT(*) as total_ventas, SUM(total) as monto_total_ventas FROM ventas WHERE usuario_id = ?");
    $stmt_stats_ventas->execute([$usuario_id]);
    $stats_ventas = $stmt_stats_ventas->fetch();

    $stmt_stats_prospectos = $pdo->prepare("SELECT COUNT(*) as total_prospectos FROM prospectos WHERE asignado_a = ?");
    $stmt_stats_prospectos->execute([$usuario_id]);
    $stats_prospectos = $stmt_stats_prospectos->fetch();

    // 4. Obtener la lista de cotizaciones, ventas y prospectos del usuario
    $stmt_cotizaciones = $pdo->prepare("SELECT c.id, c.fecha, cl.nombre AS cliente_nombre, c.total, c.estado FROM cotizaciones c JOIN clientes cl ON c.cliente_id = cl.id WHERE c.usuario_id = ? ORDER BY c.fecha DESC LIMIT 10");
    $stmt_cotizaciones->execute([$usuario_id]);
    $cotizaciones = $stmt_cotizaciones->fetchAll();

    $stmt_ventas = $pdo->prepare("SELECT v.id, v.fecha_venta, cl.nombre AS cliente_nombre, v.total FROM ventas v JOIN clientes cl ON v.cliente_id = cl.id WHERE v.usuario_id = ? ORDER BY v.fecha_venta DESC LIMIT 10");
    $stmt_ventas->execute([$usuario_id]);
    $ventas = $stmt_ventas->fetchAll();

    $stmt_prospectos = $pdo->prepare("SELECT id, nombre, estado, fecha_registro FROM prospectos WHERE asignado_a = ? ORDER BY fecha_registro DESC LIMIT 10");
    $stmt_prospectos->execute([$usuario_id]);
    $prospectos = $stmt_prospectos->fetchAll();

} catch (PDOException $e) {
    $error_db = "Error al cargar los datos del perfil: " . $e->getMessage();
}

$inicial_usuario = $usuario['nombre'] ? strtoupper(substr($usuario['nombre'], 0, 1)) : '?';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - <?= htmlspecialchars($usuario['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd; --light-bg: #f8f9fa; --border-color: #dee2e6;
            --card-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        body { background-color: var(--light-bg); }
        .profile-avatar {
            width: 100px; height: 100px; background: linear-gradient(135deg, var(--primary-color), #6f42c1);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 3rem; font-weight: 700; margin: 0 auto 1rem;
        }
        .stats-card { border: 1px solid var(--border-color); border-radius: 0.75rem; }
        .section-title { font-weight: 600; font-size: 1.2rem; color: #343a40; margin-bottom: 1.5rem; }
        .nav-pills .nav-link.active { background-color: var(--primary-color); }
        .nav-pills .nav-link { color: #495057; }
    </style>
</head>
<body>
<div class="container my-4">

    <?php if ($error_db): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_db) ?></div>
    <?php else: ?>
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="profile-avatar"><?= htmlspecialchars($inicial_usuario) ?></div>
                    <h4 class="card-title"><?= htmlspecialchars($usuario['nombre']) ?></h4>
                    <p class="text-muted"><?= htmlspecialchars($usuario['email']) ?></p>
                    <span class="badge bg-success mb-3"><?= htmlspecialchars(ucfirst($usuario['rol'])) ?></span>
                    <div>
                        <a href="#" class="btn btn-primary btn-sm"><i class="fas fa-edit me-1"></i>Editar Perfil</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <h5 class="section-title">Mi Rendimiento</h5>
            <div class="row">
                <div class="col-md-4 mb-3"><div class="card stats-card h-100"><div class="card-body"><h6 class="text-muted">Cotizaciones</h6><h3 class="fw-bold"><?= $stats_cot['total_cotizaciones'] ?? 0 ?></h3></div></div></div>
                <div class="col-md-4 mb-3"><div class="card stats-card h-100"><div class="card-body"><h6 class="text-muted">Ventas Realizadas</h6><h3 class="fw-bold"><?= $stats_ventas['total_ventas'] ?? 0 ?></h3></div></div></div>
                <div class="col-md-4 mb-3"><div class="card stats-card h-100"><div class="card-body"><h6 class="text-muted">Prospectos Asignados</h6><h3 class="fw-bold"><?= $stats_prospectos['total_prospectos'] ?? 0 ?></h3></div></div></div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <ul class="nav nav-pills card-header-pills">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#cotizacionesTab">Mis Cotizaciones</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#ventasTab">Mis Ventas</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#prospectosTab">Mis Prospectos</a></li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="cotizacionesTab">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>ID</th><th>Fecha</th><th>Cliente</th><th class="text-end">Total</th><th>Estado</th></tr></thead>
                            <tbody>
                                <?php foreach ($cotizaciones as $item): ?>
                                <tr>
                                    <td><a href="/cotizaciones/ver.php?id=<?= $item['id'] ?>">#<?= $item['id'] ?></a></td>
                                    <td><?= date('d/m/Y', strtotime($item['fecha'])) ?></td>
                                    <td><?= htmlspecialchars($item['cliente_nombre']) ?></td>
                                    <td class="text-end fw-bold">$<?= number_format($item['total'], 2) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars(ucfirst($item['estado'])) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($cotizaciones)): ?><tr><td colspan="5" class="text-center text-muted py-4">No hay cotizaciones recientes.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="ventasTab">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>ID</th><th>Fecha</th><th>Cliente</th><th class="text-end">Total</th></tr></thead>
                            <tbody>
                                <?php foreach ($ventas as $item): ?>
                                <tr>
                                    <td><a href="/ventas/ver.php?id=<?= $item['id'] ?>">#<?= $item['id'] ?></a></td>
                                    <td><?= date('d/m/Y', strtotime($item['fecha_venta'])) ?></td>
                                    <td><?= htmlspecialchars($item['cliente_nombre']) ?></td>
                                    <td class="text-end fw-bold">$<?= number_format($item['total'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($ventas)): ?><tr><td colspan="4" class="text-center text-muted py-4">No hay ventas recientes.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="prospectosTab">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>ID</th><th>Nombre</th><th>Estado</th><th>Fecha Registro</th></tr></thead>
                            <tbody>
                                <?php foreach ($prospectos as $item): ?>
                                <tr>
                                    <td><a href="/prospectos/ver.php?id=<?= $item['id'] ?>">#<?= $item['id'] ?></a></td>
                                    <td><?= htmlspecialchars($item['nombre']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars(ucfirst($item['estado'])) ?></span></td>
                                    <td><?= date('d/m/Y', strtotime($item['fecha_registro'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($prospectos)): ?><tr><td colspan="4" class="text-center text-muted py-4">No hay prospectos recientes.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>