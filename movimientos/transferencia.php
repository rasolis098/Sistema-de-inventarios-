<?php
include '../config/db.php';
include '../includes/header.php';

// Obtener productos y almacenes
$productos = $pdo->query("SELECT id, nombre FROM productos ORDER BY nombre")->fetchAll();
$almacenes = $pdo->query("SELECT id, nombre FROM almacenes ORDER BY nombre")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $origen_id = $_POST['origen_id'];
    $destino_id = $_POST['destino_id'];
    $producto_id = $_POST['producto_id'];
    $cantidad = (int)$_POST['cantidad'];
    $motivo = $_POST['motivo'] ?? '';

    if ($origen_id == $destino_id) {
        echo "<div class='alert alert-warning'>El almacén origen y destino no pueden ser iguales.</div>";
    } elseif ($cantidad <= 0) {
        echo "<div class='alert alert-warning'>La cantidad debe ser mayor a cero.</div>";
    } else {
        try {
            $pdo->beginTransaction();

            // Registrar movimiento
            $stmt = $pdo->prepare("INSERT INTO movimientos (tipo, motivo, almacen_origen_id, almacen_destino_id) VALUES ('transferencia', ?, ?, ?)");
            $stmt->execute([$motivo, $origen_id, $destino_id]);
            $movimiento_id = $pdo->lastInsertId();

            // Registrar detalle del movimiento
            $stmt2 = $pdo->prepare("INSERT INTO movimiento_detalles (movimiento_id, producto_id, cantidad) VALUES (?, ?, ?)");
            $stmt2->execute([$movimiento_id, $producto_id, $cantidad]);

            // Verificar stock en origen
            $stmtStockOrigen = $pdo->prepare("SELECT stock FROM stock_almacenes WHERE producto_id = ? AND almacen_id = ?");
            $stmtStockOrigen->execute([$producto_id, $origen_id]);
            $origenStock = $stmtStockOrigen->fetchColumn();

            if ($origenStock === false || $origenStock < $cantidad) {
                throw new Exception("Stock insuficiente en almacén origen.");
            }

            // Actualizar stock origen
            $updateOrigen = $pdo->prepare("UPDATE stock_almacenes SET stock = stock - ? WHERE producto_id = ? AND almacen_id = ?");
            $updateOrigen->execute([$cantidad, $producto_id, $origen_id]);

            // Actualizar o insertar stock en destino
            $stmtStockDestino = $pdo->prepare("SELECT stock FROM stock_almacenes WHERE producto_id = ? AND almacen_id = ?");
            $stmtStockDestino->execute([$producto_id, $destino_id]);

            if ($stmtStockDestino->rowCount() > 0) {
                $updateDestino = $pdo->prepare("UPDATE stock_almacenes SET stock = stock + ? WHERE producto_id = ? AND almacen_id = ?");
                $updateDestino->execute([$cantidad, $producto_id, $destino_id]);
            } else {
                $insertDestino = $pdo->prepare("INSERT INTO stock_almacenes (producto_id, almacen_id, stock) VALUES (?, ?, ?)");
                $insertDestino->execute([$producto_id, $destino_id, $cantidad]);
            }

            $pdo->commit();
            echo "<div class='alert alert-success'>Transferencia registrada exitosamente.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<div class='alert alert-danger'>Error en la transferencia: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<div class="container mt-4">
    <h3>Transferencia de Inventario</h3>
    <form method="post">
        <div class="mb-3">
            <label>Almacén Origen</label>
            <select name="origen_id" class="form-select" required>
                <option value="">Selecciona</option>
                <?php foreach ($almacenes as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label>Almacén Destino</label>
            <select name="destino_id" class="form-select" required>
                <option value="">Selecciona</option>
                <?php foreach ($almacenes as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label>Producto</label>
            <select name="producto_id" class="form-select" required>
                <option value="">Selecciona</option>
                <?php foreach ($productos as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label>Cantidad a transferir</label>
            <input type="number" name="cantidad" class="form-control" min="1" required>
        </div>

        <div class="mb-3">
            <label>Motivo de la transferencia</label>
            <textarea name="motivo" class="form-control"></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Registrar Transferencia</button>
        <a href="index.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
