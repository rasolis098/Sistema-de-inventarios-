<?php
session_start();
require_once '../config/db.php';
require_once '../includes/header.php';

// Constante para el IVA. Facilita el mantenimiento si cambia en el futuro.
define('IVA_PORCENTAJE', 16.0);

// Validar ID del pedido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['mensaje_error'] = "ID de pedido inválido.";
    header('Location: index.php');
    exit;
}

$pedido_id = (int)$_GET['id'];
$error_db = '';
$pedido = null;
$detalles = [];
$historial = [];

try {
    // Obtener datos principales del pedido
    $sql = "SELECT p.*, c.nombre AS cliente_nombre, c.direccion, c.telefono, c.email, u.nombre AS vendedor_nombre
            FROM pedidos p 
            JOIN clientes c ON p.cliente_id = c.id 
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            WHERE p.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        $_SESSION['mensaje_error'] = "Pedido no encontrado.";
        header('Location: index.php');
        exit;
    }

    // Obtener detalles del pedido (productos)
    $sql_detalles = "SELECT pd.*, pr.nombre, pr.codigo 
                       FROM pedido_detalles pd 
                       JOIN productos pr ON pd.producto_id = pr.id 
                       WHERE pd.pedido_id = ?";
    $stmt_detalles = $pdo->prepare($sql_detalles);
    $stmt_detalles->execute([$pedido_id]);
    $detalles = $stmt_detalles->fetchAll(PDO::FETCH_ASSOC);

    // Obtener historial de estados del pedido
    $sql_historial = "SELECT pe.*, u.nombre as usuario_nombre 
                        FROM pedido_estados pe 
                        LEFT JOIN usuarios u ON pe.usuario_id = u.id 
                        WHERE pe.pedido_id = ? ORDER BY pe.fecha DESC";
    $stmt_historial = $pdo->prepare($sql_historial);
    $stmt_historial->execute([$pedido_id]);
    $historial = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);

    // --- LÓGICA DE CÁLCULO CENTRALIZADA Y CORREGIDA ---
    // Se recalculan todos los totales desde los detalles para garantizar consistencia.
    $subtotal_general = 0;
    $descuento_total_monto = 0;
    $iva_total_monto = 0;

    if (!empty($detalles)) {
        foreach ($detalles as $item) {
            // 1. Subtotal por línea (Cantidad * Precio Unitario)
            $linea_subtotal = (float)$item['precio_unitario'] * (float)$item['cantidad'];
            
            // 2. Monto del descuento para la línea
            $linea_monto_descuento = $linea_subtotal * ((float)$item['descuento'] / 100);
            
            // 3. Base imponible (subtotal menos descuento) sobre la cual se calcula el IVA.
            $base_para_iva = $linea_subtotal - $linea_monto_descuento;
            
            // 4. Monto del IVA para la línea (usando el 16% fijo)
            $linea_monto_iva = $base_para_iva * (IVA_PORCENTAJE / 100);

            // 5. Acumular totales generales
            $subtotal_general += $linea_subtotal;
            $descuento_total_monto += $linea_monto_descuento;
            $iva_total_monto += $linea_monto_iva;
        }
    }

    // 6. Cálculo del total final basado en los valores recalculados.
    $total_calculado = $subtotal_general - $descuento_total_monto + $iva_total_monto;

} catch (PDOException $e) {
    $error_db = "Error al cargar los datos del pedido: " . $e->getMessage();
}

// Función auxiliar para los colores de estado
function getEstadoClass($estado) {
    switch (strtolower($estado)) {
        case 'completado': return 'success';
        case 'procesando': return 'info';
        case 'cancelado': return 'danger';
        case 'pendiente': default: return 'warning';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Pedido #<?= htmlspecialchars($pedido_id) ?></title>
    <style>
        .header-status { border-left: 5px solid; }
        .header-status.border-success { border-color: var(--bs-success); }
        .header-status.border-info { border-color: var(--bs-info); }
        .header-status.border-warning { border-color: var(--bs-warning); }
        .header-status.border-danger { border-color: var(--bs-danger); }
        .timeline { list-style: none; padding: 0; position: relative; }
        .timeline:before { content: ''; position: absolute; top: 0; bottom: 0; width: 2px; background: #e9ecef; left: 2rem; }
        .timeline-item { margin-bottom: 20px; position: relative; }
        .timeline-item .timeline-icon { position: absolute; left: 2rem; transform: translateX(-50%); background-color: white; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border: 2px solid #e9ecef; }
        .timeline-item .timeline-content { margin-left: 4.5rem; background: #f8f9fa; padding: 1rem; border-radius: .5rem; }
    </style>
</head>
<body>
<div class="container my-4">

    <?php if ($error_db): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_db) ?></div>
    <?php elseif (!$pedido): ?>
        <div class="alert alert-warning">No se encontró información del pedido.</div>
    <?php else: ?>
    <div class="card shadow-sm">
        <div class="card-header bg-light p-3 header-status border-<?= getEstadoClass($pedido['estado']) ?>">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h1 class="h4 mb-0"><i class="fas fa-receipt me-2"></i>Detalle de Pedido #<?= htmlspecialchars($pedido_id) ?></h1>
                    <span class="badge text-bg-<?= getEstadoClass($pedido['estado']) ?> fs-6"><?= ucfirst(htmlspecialchars($pedido['estado'])) ?></span>
                </div>
                <div class="text-end">
                    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Volver</a>
                    <a href="editar_pedido.php?id=<?= $pedido_id ?>" class="btn btn-warning btn-sm"><i class="fas fa-edit me-1"></i>Editar</a>
                    <a href="imprimir_pedido.php?id=<?= $pedido_id ?>" class="btn btn-info btn-sm text-white" target="_blank"><i class="fas fa-print me-1"></i>Imprimir</a>
                    <?php if (strtolower($pedido['estado']) !== 'completado' && strtolower($pedido['estado']) !== 'cancelado'): ?>
                        <a href="completar_pedido.php?id=<?= $pedido_id ?>" class="btn btn-success btn-sm" onclick="return confirm('¿Estás seguro de que quieres marcar este pedido como completado?')">
                            <i class="fas fa-check-circle me-1"></i>Completar Pedido
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="mb-4">
                        <h5 class="fw-bold border-bottom pb-2 mb-3">Información del Cliente</h5>
                        <p class="mb-1"><strong>Nombre:</strong> <?= htmlspecialchars($pedido['cliente_nombre']) ?></p>
                        <p class="mb-1"><strong>Teléfono:</strong> <?= htmlspecialchars($pedido['telefono'] ?? 'N/A') ?></p>
                        <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($pedido['email'] ?? 'N/A') ?></p>
                        <p class="mb-1"><strong>Dirección:</strong> <?= htmlspecialchars($pedido['direccion'] ?? 'N/A') ?></p>
                    </div>
                    <div class="mb-4">
                        <h5 class="fw-bold border-bottom pb-2 mb-3">Detalles del Pedido</h5>
                        <p class="mb-1"><strong>Fecha Pedido:</strong> <?= date("d/m/Y", strtotime($pedido['fecha'])) ?></p>
                        <p class="mb-1"><strong>Fecha Entrega:</strong> <?= $pedido['fecha_entrega'] ? date("d/m/Y", strtotime($pedido['fecha_entrega'])) : 'No especificada' ?></p>
                        <p class="mb-1"><strong>Vendedor:</strong> <?= htmlspecialchars($pedido['vendedor_nombre'] ?? 'N/A') ?></p>
                    </div>

                    <?php if (!empty($historial)): ?>
                    <div class="mb-4">
                        <h5 class="fw-bold border-bottom pb-2 mb-3">Historial de Estados</h5>
                        <ul class="timeline">
                            <?php foreach($historial as $h): ?>
                            <li class="timeline-item">
                                <div class="timeline-icon text-primary"><i class="fas fa-info-circle"></i></div>
                                <div class="timeline-content">
                                    <strong class="d-block text-primary"><?= ucfirst(htmlspecialchars($h['estado'])) ?></strong>
                                    <small class="text-muted"><i class="fas fa-calendar-alt me-1"></i><?= date("d/m/Y H:i", strtotime($h['fecha'])) ?> por <?= htmlspecialchars($h['usuario_nombre'] ?? 'Sistema') ?></small>
                                    <?php if(!empty($h['comentario'])): ?>
                                        <p class="mb-0 mt-1 fst-italic">"<?= htmlspecialchars($h['comentario']) ?>"</p>
                                    <?php endif; ?>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-8">
                    <h5 class="fw-bold border-bottom pb-2 mb-3">Productos del Pedido</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-center">Cant.</th>
                                    <th class="text-end">P. Unit.</th>
                                    <th class="text-center">IVA (%)</th>
                                    <th class="text-center">Desc. (%)</th>
                                    <th class="text-end">Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($detalles)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted fst-italic py-3">No hay productos en este pedido.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($detalles as $item): ?>
                                        <?php
                                            // Recalcular los valores de la línea para mostrar con total consistencia
                                            $linea_subtotal = (float)$item['precio_unitario'] * (float)$item['cantidad'];
                                            $linea_monto_descuento = $linea_subtotal * ((float)$item['descuento'] / 100);
                                            $base_para_iva = $linea_subtotal - $linea_monto_descuento;
                                            $linea_monto_iva = $base_para_iva * (IVA_PORCENTAJE / 100);
                                            $importe_final_linea = $base_para_iva + $linea_monto_iva;
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($item['nombre']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($item['codigo']) ?></small>
                                            </td>
                                            <td class="text-center"><?= (float)$item['cantidad'] ?></td>
                                            <td class="text-end">$<?= number_format((float)$item['precio_unitario'], 2) ?></td>
                                            <td class="text-center"><?= number_format(IVA_PORCENTAJE, 2) ?>%</td>
                                            <td class="text-center"><?= (float)$item['descuento'] ?>%</td>
                                            <td class="text-end fw-bold">$<?= number_format($importe_final_linea, 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <hr>
                    <div class="row justify-content-end">
                        <div class="col-md-7 col-lg-6">
                            <div class="d-flex justify-content-between mb-1"><span>Subtotal:</span> <span>$<?= number_format($subtotal_general, 2) ?></span></div>
                            <div class="d-flex justify-content-between mb-1"><span>Descuento Total:</span> <span class="text-success">-$<?= number_format($descuento_total_monto, 2) ?></span></div>
                            <div class="d-flex justify-content-between mb-1"><span>IVA Total (<?= number_format(IVA_PORCENTAJE, 2) ?>%):</span> <span>$<?= number_format($iva_total_monto, 2) ?></span></div>
                            <div class="d-flex justify-content-between fw-bold fs-5 mt-2 pt-2 border-top">
                                <span>TOTAL:</span>
                                <span class="text-primary">$<?= number_format($total_calculado, 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>