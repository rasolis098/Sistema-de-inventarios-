<?php
include '../config/db.php';
include '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Movimientos de Inventario</h2>
    <a href="entrada.php" class="btn btn-success mb-2">Registrar Entrada</a>
    <a href="salida.php" class="btn btn-danger mb-2">Registrar Salida</a>
    <a href="transferencia.php" class="btn btn-primary mb-2">Registrar Transferencia</a>
    <a href="ajuste.php" class="btn btn-warning mb-2">Registrar Ajuste</a>

    <table id="tablaMovimientos" class="table table-striped table-bordered" style="width:100%">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Productos</th>
                <th>Almacén Origen</th>
                <th>Destino</th>
                <th>Fecha</th>
                <th>Comentario</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = "
                SELECT 
                    m.id,
                    m.tipo,
                    m.motivo,
                    m.fecha,
                    a1.nombre AS almacen_origen,
                    a2.nombre AS almacen_destino,
                    c.nombre AS cliente,
                    GROUP_CONCAT(CONCAT(p.nombre, ' x ', md.cantidad) ORDER BY p.nombre SEPARATOR '<br>') AS productos
                FROM movimientos m
                LEFT JOIN almacenes a1 ON m.almacen_origen_id = a1.id
                LEFT JOIN almacenes a2 ON m.almacen_destino_id = a2.id
                LEFT JOIN clientes c ON m.cliente_id = c.id
                LEFT JOIN movimiento_detalles md ON m.id = md.movimiento_id
                LEFT JOIN productos p ON md.producto_id = p.id
                GROUP BY m.id, m.tipo, m.motivo, m.fecha, a1.nombre, a2.nombre, c.nombre
                ORDER BY m.fecha DESC
            ";

            $stmt = $pdo->query($sql);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $tipo = ucfirst($row['tipo']);
                $productos = $row['productos'] ?: '—';
                $origen = $row['almacen_origen'] ?: 'N/A';

                // destino depende del tipo de movimiento
                if ($row['tipo'] === 'salida' && !empty($row['cliente'])) {
                    $destino = htmlspecialchars($row['cliente']);
                } else {
                    $destino = $row['almacen_destino'] ?: 'N/A';
                }

                $fecha = date("d/m/Y H:i", strtotime($row['fecha']));
                $motivo = htmlspecialchars($row['motivo'] ?? '');

                echo "<tr>
                    <td>{$tipo}</td>
                    <td>{$productos}</td>
                    <td>{$origen}</td>
                    <td>{$destino}</td>
                    <td>{$fecha}</td>
                    <td>{$motivo}</td>
                </tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#tablaMovimientos').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        order: [[4, 'desc']]
    });
});
</script>
