<?php
include '../config/db.php';
include '../includes/header.php';

// Obtener productos y almacenes para los select
$productos = $pdo->query("SELECT id, nombre FROM productos WHERE activo = 1 ORDER BY nombre")->fetchAll();
$almacenes = $pdo->query("SELECT id, nombre FROM almacenes ORDER BY nombre")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $producto_id = $_POST['producto_id'] ?? null;
    $almacen_id = $_POST['almacen_id'] ?? null;
    $cantidad = $_POST['cantidad'] ?? 0;
    $motivo = $_POST['motivo'] ?? '';

    // Validar campos obligatorios
    if (!$producto_id || !$almacen_id || $cantidad <= 0) {
        echo "<div class='alert alert-danger'>Por favor completa todos los campos obligatorios y asegúrate que la cantidad sea mayor a cero.</div>";
    } else {
        try {
            // Insertar movimiento (tipo = 'entrada', almacen_destino_id = almacen seleccionado)
            $stmt = $pdo->prepare("INSERT INTO movimientos (tipo, almacen_destino_id, motivo) VALUES (?, ?, ?)");
            $stmt->execute(['entrada', $almacen_id, $motivo]);
            $movimiento_id = $pdo->lastInsertId();

            // Insertar detalle del movimiento
            $stmtDetalle = $pdo->prepare("INSERT INTO movimiento_detalles (movimiento_id, producto_id, cantidad) VALUES (?, ?, ?)");
            $stmtDetalle->execute([$movimiento_id, $producto_id, $cantidad]);

            // Actualizar existencias
            $check = $pdo->prepare("SELECT cantidad FROM existencias WHERE producto_id = ? AND almacen_id = ?");
            $check->execute([$producto_id, $almacen_id]);

            if ($row = $check->fetch()) {
                $nuevaCantidad = $row['cantidad'] + $cantidad;
                $update = $pdo->prepare("UPDATE existencias SET cantidad = ? WHERE producto_id = ? AND almacen_id = ?");
                $update->execute([$nuevaCantidad, $producto_id, $almacen_id]);
            } else {
                $insert = $pdo->prepare("INSERT INTO existencias (producto_id, almacen_id, cantidad) VALUES (?, ?, ?)");
                $insert->execute([$producto_id, $almacen_id, $cantidad]);
            }

            echo "<div class='alert alert-success'>Entrada registrada correctamente.</div>";
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>Error al registrar la entrada: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>

<div class="container mt-4">
    <h3>Registrar Entrada de Producto</h3>
    <form method="post" action="">
        <div class="mb-3">
            <label for="producto_id" class="form-label">Producto <span class="text-danger">*</span></label>
            <select name="producto_id" id="producto_id" class="form-select" required>
                <option value="">-- Seleccionar producto --</option>
                <?php foreach ($productos as $producto): ?>
                    <option value="<?= $producto['id'] ?>"><?= htmlspecialchars($producto['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="almacen_id" class="form-label">Almacén <span class="text-danger">*</span></label>
            <select name="almacen_id" id="almacen_id" class="form-select" required>
                <option value="">-- Seleccionar almacén --</option>
                <?php foreach ($almacenes as $almacen): ?>
                    <option value="<?= $almacen['id'] ?>"><?= htmlspecialchars($almacen['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="cantidad" class="form-label">Cantidad <span class="text-danger">*</span></label>
            <input type="number" name="cantidad" id="cantidad" class="form-control" min="1" required>
        </div>

        <div class="mb-3">
            <label for="motivo" class="form-label">Motivo</label>
            <textarea name="motivo" id="motivo" class="form-control" rows="3" placeholder="Opcional"></textarea>
        </div>

        <button type="submit" class="btn btn-success">Registrar Entrada</button>
        <a href="index.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
