<?php
// ---- PASO 1: LLAMAR A TODOS LOS ARCHIVOS NECESARIOS ----
session_start();
require_once '../config/db.php';
require_once '../includes/helpers.php';
require_once '../includes/header.php'; // <--- Aquí se incluye la parte superior (menú, CSS, etc.)

// ---- LÓGICA DE LA PÁGINA ----
$error_db = '';
$pedidos = [];

try {
    // Consulta para obtener todos los pedidos con sus detalles
    $sql = "SELECT p.id, c.nombre AS cliente_nombre, u.nombre AS usuario_nombre, p.fecha, p.total, p.estado
            FROM pedidos p
            JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            ORDER BY p.id DESC";
    $pedidos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error de base de datos: " . $e->getMessage());
    $error_db = "Ocurrió un error al cargar los datos.";
}
?>

<div class="container-fluid my-4">

    <?php if ($error_db): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_db) ?></div>
    <?php else: ?>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h1 class="h4 mb-0"><i class="fas fa-truck-fast me-2 text-primary"></i>Gestión de Pedidos</h1>
            <a href="agregar.php" class="btn btn-primary btn-sm fw-semibold"><i class="fas fa-plus me-1"></i>Nuevo Pedido</a>
        </div>

        <div class="card-body">
            <div class="mb-4 p-3 border rounded bg-light">
                <div class="row align-items-end g-3">
                    <div class="col-md-3"><label class="form-label fw-semibold">Estado</label><select class="form-select form-select-sm" id="filtroEstado"><option value="">Todos</option><option value="pendiente">Pendiente</option><option value="procesando">Procesando</option><option value="completado">Completado</option><option value="cancelado">Cancelado</option></select></div>
                    <div class="col-md-3"><label class="form-label fw-semibold">Cliente</label><input type="text" class="form-control form-control-sm" id="filtroCliente" placeholder="Buscar cliente..."></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Rango de Fechas</label><div class="input-group"><input type="date" class="form-control form-control-sm" id="filtroFechaDesde"><input type="date" class="form-control form-control-sm" id="filtroFechaHasta"></div></div>
                    <div class="col-md-2"><button class="btn btn-outline-secondary btn-sm w-100" id="resetearFiltros"><i class="fas fa-sync-alt me-1"></i>Limpiar</button></div>
                </div>
            </div>

            <div class="table-responsive">
                <table id="tablaPedidos" class="table table-hover align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Vendedor</th>
                            <th>Fecha Pedido</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $pedido): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($pedido['id']) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($pedido['cliente_nombre']) ?></td>
                            <td><?= htmlspecialchars($pedido['usuario_nombre'] ?? 'N/A') ?></td>
                            <td><?= date("d/m/Y", strtotime($pedido['fecha'])) ?></td>
                            <td class="text-center">
                                <span class="badge <?= getEstadoBadge($pedido['estado']) ?> p-2">
                                    <?= ucfirst(htmlspecialchars($pedido['estado'] ?? 'Sin estado')) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <a href="ver_pedido.php?id=<?= $pedido['id'] ?>" class="btn btn-info btn-sm text-white" title="Ver Detalle"><i class="fas fa-eye"></i></a>
                                    <a href="editar_pedido.php?id=<?= $pedido['id'] ?>" class="btn btn-warning btn-sm" title="Editar Pedido"><i class="fas fa-edit"></i></a>
                                    <a href="eliminar_pedido.php?id=<?= $pedido['id'] ?>" class="btn btn-danger btn-sm" title="Eliminar Pedido" onclick="return confirm('¿Eliminar pedido #<?= htmlspecialchars($pedido['id']) ?>?');"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; // <--- Aquí se incluye la parte inferior (scripts, cierre de body/html) ?>