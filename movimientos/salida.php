<?php
include '../config/db.php';
include '../includes/header.php';

$almacenes = $pdo->query("SELECT id, nombre FROM almacenes")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $producto_id = $_POST['producto_id'] ?? null;
    $almacen_origen_id = $_POST['almacen_origen_id'] ?? null;
    $cantidad = (int)($_POST['cantidad'] ?? 0);
    $motivo = $_POST['motivo'] ?? '';

    if (!$producto_id || !$almacen_origen_id || $cantidad <= 0) {
        echo "<div class='alert alert-danger'>Por favor, complete todos los campos correctamente.</div>";
    } else {
        $stmt = $pdo->prepare("SELECT stock FROM stock_almacenes WHERE producto_id = ? AND almacen_id = ?");
        $stmt->execute([$producto_id, $almacen_origen_id]);
        $existencia = $stmt->fetch();

        if (!$existencia || $existencia['stock'] < $cantidad) {
            echo "<div class='alert alert-danger'>No hay suficiente stock para esta salida.</div>";
        } else {
            $stmt = $pdo->prepare("INSERT INTO movimientos (tipo, producto_id, cantidad, almacen_origen_id, almacen_destino_id, motivo) VALUES ('salida', ?, ?, ?, NULL, ?)");
            $stmt->execute([$producto_id, $cantidad, $almacen_origen_id, $motivo]);

            $stmt = $pdo->prepare("UPDATE stock_almacenes SET stock = stock - ? WHERE producto_id = ? AND almacen_id = ?");
            $stmt->execute([$cantidad, $producto_id, $almacen_origen_id]);

            echo "<div class='alert alert-success'>Salida registrada correctamente.</div>";
        }
    }
}
?>

<div class="container mt-4">
    <h3>Registrar Salida</h3>
    <form method="post" action="">
        <div class="mb-3">
            <label>Buscar Producto</label>
            <input type="text" id="buscar_producto" class="form-control" placeholder="Escriba el nombre del producto">
            <input type="hidden" name="producto_id" id="producto_id" required>
        </div>
        <div class="mb-3">
            <label>Almacén Origen</label>
            <select name="almacen_origen_id" class="form-select" required>
                <option value="">Seleccione un almacén</option>
                <?php foreach ($almacenes as $alm): ?>
                    <option value="<?= $alm['id'] ?>"><?= htmlspecialchars($alm['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label>Cantidad</label>
            <input type="number" name="cantidad" class="form-control" min="1" required>
        </div>
        <div class="mb-3">
            <label>Motivo</label>
            <textarea name="motivo" class="form-control"></textarea>
        </div>
        <button type="submit" class="btn btn-danger">Registrar Salida</button>
        <a href="index.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<!-- jQuery y jQuery UI -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
$(function() {
    $("#buscar_producto").autocomplete({
        source: "buscar_productos.php",
        minLength: 2,
        select: function(event, ui) {
            $("#producto_id").val(ui.item.id);
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
