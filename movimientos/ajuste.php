<?php
include '../config/db.php';
include '../includes/header.php';

// Obtener productos y almacenes
$productos = $pdo->query("SELECT id, nombre FROM productos ORDER BY nombre")->fetchAll();
$almacenes = $pdo->query("SELECT id, nombre FROM almacenes ORDER BY nombre")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $almacen_id = $_POST['almacen_id'] ?? null;
    $producto_id = $_POST['producto_id'] ?? null;
    $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 0;
    $motivo = $_POST['motivo'] ?? '';

    if ($almacen_id && $producto_id && $cantidad !== 0) {
        try {
            $pdo->beginTransaction();

            // 1. Insertar en tabla de movimientos
            $stmt = $pdo->prepare("INSERT INTO movimientos (tipo, motivo, almacen_origen_id) VALUES ('ajuste', ?, ?)");
            $stmt->execute([$motivo, $almacen_id]);
            $movimiento_id = $pdo->lastInsertId();

            // 2. Insertar en detalles del movimiento
            $stmt2 = $pdo->prepare("INSERT INTO movimiento_detalles (movimiento_id, producto_id, cantidad) VALUES (?, ?, ?)");
            $stmt2->execute([$movimiento_id, $producto_id, $cantidad]);

            // 3. Actualizar stock
            $check = $pdo->prepare("SELECT stock FROM stock_almacenes WHERE producto_id = ? AND almacen_id = ?");
            $check->execute([$producto_id, $almacen_id]);

            if ($check->rowCount() > 0) {
                $update = $pdo->prepare("UPDATE stock_almacenes SET stock = stock + ? WHERE producto_id = ? AND almacen_id = ?");
                $update->execute([$cantidad, $producto_id, $almacen_id]);
            } else {
                $insert = $pdo->prepare("INSERT INTO stock_almacenes (producto_id, almacen_id, stock) VALUES (?, ?, ?)");
                $insert->execute([$producto_id, $almacen_id, $cantidad]);
            }

            $pdo->commit();
            echo "<div class='alert alert-success'>Ajuste realizado correctamente.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<div class='alert alert-danger'>Error al realizar el ajuste: {$e->getMessage()}</div>";
        }
    } else {
        echo "<div class='alert alert-warning'>Todos los campos son obligatorios y la cantidad no puede ser cero.</div>";
    }
}
?>

<div class="container mt-4">
    <h3>Ajuste de Inventario</h3>
    <form method="post">
        <div class="mb-3">
            <label for="almacen_id">Almacén</label>
            <select name="almacen_id" class="form-select" required>
                <option value="">Selecciona un almacén</option>
                <?php foreach ($almacenes as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="producto_id">Producto</label>
            <select name="producto_id" class="form-select" required>
                <option value="">Selecciona un producto</option>
                <?php foreach ($productos as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="cantidad">Cantidad (+ entrada / - salida)</label>
            <input type="number" name="cantidad" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="motivo">Motivo del ajuste</label>
            <textarea name="motivo" class="form-control" required></textarea>
        </div>

        <button type="submit" class="btn btn-warning">Guardar Ajuste</button>
        <a href="index.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
