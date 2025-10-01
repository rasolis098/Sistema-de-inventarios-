<?php
/**
 * editar_pedido.php
 * Permite ver y modificar un pedido existente.
 * Contiene la lógica para mostrar el formulario y para procesar los cambios.
 */

// 1. INICIAR SESIÓN Y CARGAR LA BD. ESTO SIEMPRE VA PRIMERO.
session_start();
require_once '../config/db.php';

// Redirigir si el usuario no ha iniciado sesión.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit();
}

// 2. PROCESAR EL FORMULARIO SI ES UN ENVÍO POST.
// Toda esta sección se ejecuta ANTES de que se envíe cualquier HTML.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validar que el ID viene en la URL.
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        $_SESSION['mensaje_error'] = 'ID de pedido inválido durante el guardado.';
        header('Location: index.php');
        exit;
    }
    $pedido_id = (int)$_GET['id'];

    $pdo->beginTransaction();
    try {
        // Recoger datos del formulario.
        $fecha_entrega = $_POST['fecha_entrega'];
        $observaciones = $_POST['observaciones'] ?? '';
        $productos_post = $_POST['productos'] ?? [];

        if (empty($productos_post)) {
            throw new Exception("No se puede guardar un pedido sin productos.");
        }

        // Recalcular el total en el servidor por seguridad.
        $total_recalculado = 0;
        $stmt_precio = $pdo->prepare("SELECT precio_venta FROM productos WHERE id = ?");

        foreach ($productos_post as $item) {
            $producto_id = (int)($item['id'] ?? 0);
            $cantidad = (float)($item['cantidad'] ?? 0);

            if ($producto_id <= 0 || $cantidad <= 0) continue;

            $stmt_precio->execute([$producto_id]);
            $precio_unitario_real = $stmt_precio->fetchColumn();
            
            if ($precio_unitario_real === false) throw new Exception("Producto con ID $producto_id no encontrado.");

            $iva_pct = (float)($item['iva'] ?? 16);
            $desc_pct = (float)($item['descuento'] ?? 0);

            $subtotal = $precio_unitario_real * $cantidad;
            $monto_iva = $subtotal * ($iva_pct / 100);
            $monto_descuento = $subtotal * ($desc_pct / 100);
            $total_recalculado += $subtotal + $monto_iva - $monto_descuento;
        }

        // Actualizar el pedido principal.
        $sql_update_pedido = "UPDATE pedidos SET fecha_entrega = ?, total = ?, observaciones = ? WHERE id = ?";
        $pdo->prepare($sql_update_pedido)->execute([$fecha_entrega, $total_recalculado, $observaciones, $pedido_id]);

        // Borrar y re-insertar detalles.
        $pdo->prepare("DELETE FROM pedido_detalles WHERE pedido_id = ?")->execute([$pedido_id]);

        $sql_insert_detalle = "INSERT INTO pedido_detalles (pedido_id, producto_id, cantidad, precio_unitario, iva, descuento, importe) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert_detalle = $pdo->prepare($sql_insert_detalle);

        foreach ($productos_post as $item) {
            $producto_id = (int)($item['id'] ?? 0);
            $cantidad = (float)($item['cantidad'] ?? 0);
            if ($producto_id <= 0 || $cantidad <= 0) continue;

            $stmt_precio->execute([$producto_id]);
            $precio_unitario_real = $stmt_precio->fetchColumn();
            
            $iva_pct = (float)($item['iva'] ?? 16);
            $desc_pct = (float)($item['descuento'] ?? 0);
            
            $subtotal = $precio_unitario_real * $cantidad;
            $monto_iva = $subtotal * ($iva_pct / 100);
            $monto_descuento = $subtotal * ($desc_pct / 100);
            $importe = $subtotal + $monto_iva - $monto_descuento;

            $stmt_insert_detalle->execute([$pedido_id, $producto_id, $cantidad, $precio_unitario_real, $iva_pct, $desc_pct, $importe]);
        }
        
        $pdo->commit();
        $_SESSION['mensaje_exito'] = "Pedido #" . $pedido_id . " actualizado correctamente.";
        header("Location: ver_pedido.php?id=" . $pedido_id);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['mensaje_error'] = "Error al actualizar: " . $e->getMessage();
        header("Location: editar_pedido.php?id=" . $pedido_id);
        exit();
    }
}

// 3. SI NO ES UN POST, SE PREPARAN LOS DATOS PARA MOSTRAR EL FORMULARIO.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['mensaje_error'] = 'ID de pedido no válido.';
    header('Location: index.php');
    exit;
}
$pedido_id = (int)$_GET['id'];

try {
    $stmt_pedido = $pdo->prepare("SELECT p.*, c.nombre AS cliente_nombre FROM pedidos p JOIN clientes c ON p.cliente_id = c.id WHERE p.id = ?");
    $stmt_pedido->execute([$pedido_id]);
    $pedido = $stmt_pedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) { throw new Exception("Pedido no encontrado"); }

    $sql_detalles = "SELECT pd.*, pr.nombre, pr.codigo FROM pedido_detalles pd JOIN productos pr ON pd.producto_id = pr.id WHERE pd.pedido_id = ?";
    $stmt_detalles = $pdo->prepare($sql_detalles);
    $stmt_detalles->execute([$pedido_id]);
    $detalles = $stmt_detalles->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error al cargar los datos del pedido: " . $e->getMessage());
}

// 4. INCLUIR EL HEADER VISUAL ANTES DE EMPEZAR A ESCRIBIR EL HTML.
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<style>
    .ui-autocomplete { z-index: 1050; max-height: 250px; overflow-y: auto; }
    .producto-row { border-bottom: 1px solid #dee2e6; padding-bottom: 1rem; margin-bottom: 1rem; }
    .producto-row:last-child { border-bottom: none; }
    .importe-display { font-weight: 600; padding-top: 2.2rem; white-space: nowrap; }
</style>

<div class="container my-4">

    <?php if (isset($_SESSION['mensaje_error'])): ?>
        <div class="alert alert-danger" role="alert"><?= $_SESSION['mensaje_error']; unset($_SESSION['mensaje_error']); ?></div>
    <?php elseif (isset($_SESSION['mensaje_exito'])): ?>
        <div class="alert alert-success" role="alert"><?= $_SESSION['mensaje_exito']; unset($_SESSION['mensaje_exito']); ?></div>
    <?php endif; ?>

    <form method="POST" action="editar_pedido.php?id=<?= $pedido_id ?>" id="formPedido">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h1 class="h4 mb-0"><i class="fas fa-edit me-2"></i>Editar Pedido #<?= $pedido_id ?></h1>
                        <p class="mb-0 text-muted">Cliente: <strong><?= htmlspecialchars($pedido['cliente_nombre']) ?></strong></p>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-secondary btn-sm">Volver</a>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Actualizar Pedido</button>
                    </div>
                </div>
            </div>

            <div class="card-body p-4">
                <h5 class="mb-3 fw-bold">Detalles Generales</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Fecha de Entrega</label>
                        <input type="date" name="fecha_entrega" class="form-control" value="<?= htmlspecialchars(substr($pedido['fecha_entrega'], 0, 10)) ?>" required>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="1"><?= htmlspecialchars($pedido['observaciones']) ?></textarea>
                    </div>
                </div>
                <hr class="my-4">
                <h5 class="mb-3 fw-bold">Productos</h5>
                <div id="productos-container">
                    <?php foreach ($detalles as $i => $d): ?>
                        <div class="producto-row" data-index="<?= $i ?>">
                            <div class="row align-items-end g-2">
                                <div class="col-md-4">
                                    <label class="form-label small">Producto</label>
                                    <input type="hidden" name="productos[<?= $i ?>][id]" class="producto-id" value="<?= $d['producto_id'] ?>">
                                    <input type="text" class="form-control form-control-sm producto-autocomplete" value="<?= htmlspecialchars($d['codigo'] . ' - ' . $d['nombre']) ?>" required>
                                </div>
                                <div class="col-md-1"><label class="form-label small">Cant.</label><input type="number" name="productos[<?= $i ?>][cantidad]" class="form-control form-control-sm cantidad" min="0.01" step="any" value="<?= (float)$d['cantidad'] ?>" required></div>
                                <div class="col-md-2"><label class="form-label small">Precio</label><input type="text" name="productos[<?= $i ?>][precio_unitario_ignorado]" class="form-control form-control-sm precio" value="<?= number_format((float)$d['precio_unitario'], 2, '.', '') ?>" readonly></div>
                                <div class="col-md-1"><label class="form-label small">IVA(%)</label><input type="number" name="productos[<?= $i ?>][iva]" class="form-control form-control-sm iva" min="0" value="<?= (float)$d['iva'] ?>"></div>
                                <div class="col-md-1"><label class="form-label small">Desc(%)</label><input type="number" name="productos[<?= $i ?>][descuento]" class="form-control form-control-sm descuento" min="0" value="<?= (float)$d['descuento'] ?>"></div>
                                <div class="col-md-2 text-end"><label class="form-label small d-block">Importe</label><div class="importe-display">$0.00</div></div>
                                <div class="col-md-1 text-center pt-3"><button type="button" class="btn btn-danger btn-sm" onclick="eliminarProducto(this)">&times;</button></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-success btn-sm mt-3" onclick="agregarProducto()"><i class="fas fa-plus me-1"></i>Agregar Producto</button>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
let rowIndex = <?= count($detalles) ?>;

function inicializarAutocomplete(selector) {
    $(selector).autocomplete({
        source: 'buscar_productos.php',
        minLength: 2,
        select: function(event, ui) {
            event.preventDefault(); // Prevenir que el valor por defecto (label) se ponga en el input
            const row = $(this).closest('.producto-row');
            row.find('.producto-id').val(ui.item.id);
            $(this).val(ui.item.label); // Poner el label (ej. "COD1 - Producto 1")
            row.find('.precio').val(parseFloat(ui.item.precio).toFixed(2));
            calcularTotales();
        }
    });
}

function agregarProducto() {
    const container = $("#productos-container");
    let newIndex = rowIndex++;
    
    const newRowHTML = `
        <div class="producto-row" data-index="${newIndex}">
            <div class="row align-items-end g-2">
                <div class="col-md-4">
                    <label class="form-label small">Producto</label>
                    <input type="hidden" name="productos[${newIndex}][id]" class="producto-id">
                    <input type="text" class="form-control form-control-sm producto-autocomplete" placeholder="Busca por código o nombre" required>
                </div>
                <div class="col-md-1"><label class="form-label small">Cant.</label><input type="number" name="productos[${newIndex}][cantidad]" class="form-control form-control-sm cantidad" value="1" min="0.01" step="any" required></div>
                <div class="col-md-2"><label class="form-label small">Precio</label><input type="text" name="productos[${newIndex}][precio_unitario_ignorado]" class="form-control form-control-sm precio" value="0.00" readonly></div>
                <div class="col-md-1"><label class="form-label small">IVA(%)</label><input type="number" name="productos[${newIndex}][iva]" class="form-control form-control-sm iva" value="16" min="0"></div>
                <div class="col-md-1"><label class="form-label small">Desc(%)</label><input type="number" name="productos[${newIndex}][descuento]" class="form-control form-control-sm descuento" value="0" min="0"></div>
                <div class="col-md-2 text-end"><label class="form-label small d-block">Importe</label><div class="importe-display">$0.00</div></div>
                <div class="col-md-1 text-center pt-3"><button type="button" class="btn btn-danger btn-sm" onclick="eliminarProducto(this)">&times;</button></div>
            </div>
        </div>`;
    
    container.append(newRowHTML);
    inicializarAutocomplete(`.producto-row[data-index="${newIndex}"] .producto-autocomplete`);
}

function eliminarProducto(button) {
    if ($('.producto-row').length > 0) {
        $(button).closest('.producto-row').remove();
        calcularTotales();
    } else {
        alert('El pedido no puede quedar sin productos.');
    }
}

function calcularTotales() {
    $('.producto-row').each(function() {
        const row = $(this);
        const cantidad = parseFloat(row.find('.cantidad').val()) || 0;
        const precio = parseFloat(row.find('.precio').val()) || 0;
        const ivaPct = parseFloat(row.find('.iva').val()) || 0;
        const descPct = parseFloat(row.find('.descuento').val()) || 0;
        
        const subtotal = cantidad * precio;
        const montoDescuento = subtotal * (descPct / 100);
        const subtotalConDesc = subtotal - montoDescuento;
        const montoIva = subtotalConDesc * (ivaPct / 100);
        const importe = subtotalConDesc + montoIva;
        
        row.find('.importe-display').text(`$${importe.toFixed(2)}`);
    });
}

// Event delegation para manejar cambios en filas nuevas y existentes.
$('#productos-container').on('input', '.cantidad, .iva, .descuento', function() {
    calcularTotales();
});

// Inicializar todo al cargar la página.
$(document).ready(function() {
    inicializarAutocomplete('.producto-autocomplete');
    calcularTotales();
});
</script>

<?php
// Se incluye el pie de página (cierre de body, html, etc.).
require_once '../includes/footer.php';
?>