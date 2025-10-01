<?php

session_start();

include '../config/db.php';

include '../includes/header.php';



$error_db = '';

$pedidos = [];

$stats = [

    'total' => 0, 'pendientes' => 0, 'procesando' => 0,

    'completados' => 0, 'total_ventas' => 0

];



try {

    // Consulta de estad铆sticas optimizada

    $stats_query = "

        SELECT

            COUNT(*) as total,

            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,

            SUM(CASE WHEN estado = 'procesando' THEN 1 ELSE 0 END) as procesando,

            SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,

            COALESCE(SUM(CASE WHEN estado = 'completado' THEN total ELSE 0 END), 0) as total_ventas

        FROM pedidos

    ";

    $stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);



    // Consulta principal de pedidos (sin cambios)

    $sql = "SELECT p.id, c.nombre AS cliente, p.fecha, p.total, p.estado, p.fecha_entrega

            FROM pedidos p 

            JOIN clientes c ON p.cliente_id = c.id

            ORDER BY p.fecha DESC";

    $pedidos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);



} catch (PDOException $e) {

    $error_db = "Error al conectar con la base de datos: " . $e->getMessage();

}

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gesti贸n de Pedidos - Sistema de Inventario</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

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

        .stats-card { border: 1px solid var(--border-color); border-radius: 0.75rem; transition: all 0.2s ease; }

        .stats-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }

        .table thead th { background-color: #f8f9fa; }

        .badge-pendiente { background-color: #fff3cd; color: #664d03; }

        .badge-procesando { background-color: #cff4fc; color: #055160; }

        .badge-completado { background-color: #d1e7dd; color: #0f5132; }

        .badge-cancelado { background-color: #f8d7da; color: #842029; }

    </style>

</head>

<body>

<div class="container-fluid my-4">



    <?php if ($error_db): ?>

        <div class="alert alert-danger"><?= htmlspecialchars($error_db) ?></div>

    <?php else: ?>

    <div class="main-card">

        <div class="card-header-custom">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                <div>

                    <h1 class="page-title mb-0"><i class="fas fa-truck-fast me-2"></i>Gesti贸n de Pedidos</h1>

                    <p class="text-muted mb-0">Administra y monitorea todos los pedidos del sistema</p>

                </div>

                <a href="agregar.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Nuevo Pedido</a>

            </div>

        </div>

        <div class="card-body p-4">

            <section class="mb-4">

                <div class="row">

                    <div class="col-lg col-md-6 mb-3"><div class="card stats-card h-100"><div class="card-body text-center"><h6 class="text-muted">Total Pedidos</h6><h3 class="fw-bold"><?= $stats['total'] ?></h3></div></div></div>

                    <div class="col-lg col-md-6 mb-3"><div class="card stats-card h-100"><div class="card-body text-center"><h6 class="text-muted">Pendientes</h6><h3 class="fw-bold text-warning"><?= $stats['pendientes'] ?></h3></div></div></div>

                    <div class="col-lg col-md-6 mb-3"><div class="card stats-card h-100"><div class="card-body text-center"><h6 class="text-muted">En Proceso</h6><h3 class="fw-bold text-info"><?= $stats['procesando'] ?></h3></div></div></div>

                    <div class="col-lg col-md-6 mb-3"><div class="card stats-card h-100"><div class="card-body text-center"><h6 class="text-muted">Completados</h6><h3 class="fw-bold text-success"><?= $stats['completados'] ?></h3></div></div></div>

                    <div class="col-lg col-md-6 mb-3"><div class="card stats-card h-100"><div class="card-body text-center"><h6 class="text-muted">Ventas (Completados)</h6><h3 class="fw-bold">$<?= number_format($stats['total_ventas'], 2) ?></h3></div></div></div>

                </div>

            </section>

            

            <hr class="my-4">

            

            <section>

                <div class="mb-4 p-3 border rounded bg-light">

                    <div class="row align-items-end g-3">

                        <div class="col-md-3">

                            <label class="form-label fw-semibold">Estado del pedido</label>

                            <select class="form-select" id="filtroEstado">

                                <option value="">Todos los estados</option>

                                <option value="pendiente">Pendiente</option>

                                <option value="procesando">Procesando</option>

                                <option value="completado">Completado</option>

                                <option value="cancelado">Cancelado</option>

                            </select>

                        </div>

                        <div class="col-md-3">

                            <label class="form-label fw-semibold">Buscar cliente</label>

                            <input type="text" class="form-control" id="filtroCliente" placeholder="Nombre del cliente">

                        </div>

                        <div class="col-md-2">

                            <label class="form-label fw-semibold">Fecha desde</label>

                            <input type="date" class="form-control" id="filtroFechaDesde">

                        </div>

                        <div class="col-md-2">

                            <label class="form-label fw-semibold">Fecha hasta</label>

                            <input type="date" class="form-control" id="filtroFechaHasta">

                        </div>

                        <div class="col-md-2">

                            <button class="btn btn-secondary w-100" onclick="resetearFiltros()">

                                <i class="fas fa-sync-alt me-1"></i>Limpiar

                            </button>

                        </div>

                    </div>

                </div>

                

                <div class="table-responsive">

                    <table id="tablaPedidos" class="table table-hover" style="width:100%">

                        <thead class="table-light">

                            <tr>

                                <th>ID</th>

                                <th>Cliente</th>

                                <th>Fecha Pedido</th>

                                <th>Fecha Entrega</th>

                                <th class="text-end">Total</th>

                                <th>Estado</th>

                                <th class="text-center">Acciones</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($pedidos as $pedido): ?>

                            <tr>

                                <td><span class="badge bg-dark">#<?= $pedido['id'] ?></span></td>

                                <td class="fw-bold"><?= htmlspecialchars($pedido['cliente']) ?></td>

                                <td><?= date("d/m/Y", strtotime($pedido['fecha'])) ?></td>

                                <td><?= $pedido['fecha_entrega'] ? date("d/m/Y", strtotime($pedido['fecha_entrega'])) : '<span class="text-muted">N/A</span>' ?></td>

                                <td class="text-end fw-bold text-success">$<?= number_format($pedido['total'], 2) ?></td>

                                <td>

                                    <span class="badge badge-<?= strtolower($pedido['estado']) ?>">

                                        <?= ucfirst($pedido['estado']) ?>

                                    </span>

                                </td>

                                <td class="text-center">

                                    <a href="ver_pedido.php?id=<?= $pedido['id'] ?>" class="btn btn-info btn-sm text-white" title="Ver detalle"><i class="fas fa-eye"></i></a>

                                    <a href="editar_pedido.php?id=<?= $pedido['id'] ?>" class="btn btn-warning btn-sm" title="Editar pedido"><i class="fas fa-edit"></i></a>

                                </td>

                            </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        </div>

    </div>

    <?php endif; ?>

</div>



<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>

$(document).ready(function(){

    // L脫GICA DE FILTROS RESTAURADA Y ADAPTADA

    $.fn.dataTable.ext.search.push(

        function(settings, data, dataIndex) {

            var min = $('#filtroFechaDesde').val();

            var max = $('#filtroFechaHasta').val();

            var date = new Date(data[2].split('/').reverse().join('-')); // Columna de Fecha Pedido



            if (

                (min === "" && max === "") ||

                (min === "" && date <= new Date(max)) ||

                (min <= date && max === "") ||

                (min <= date && date <= new Date(max))

            ) {

                return true;

            }

            return false;

        }

    );



    var table = $('#tablaPedidos').DataTable({

        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },

        dom: 'rt<"row mt-3"<"col-md-6"i><"col-md-6"p>>',

        pageLength: 10,

        responsive: true,

        order: [[0, 'desc']]

    });

    

    // Aplicar filtros individuales al escribir o cambiar

    $('#filtroCliente').on('keyup', function() {

        table.column(1).search(this.value).draw();

    });



    $('#filtroEstado').on('change', function() {

        table.column(5).search(this.value).draw();

    });



    // Filtros de fecha necesitan redibujar la tabla

    $('#filtroFechaDesde, #filtroFechaHasta').on('change', function() {

        table.draw();

    });

});



function resetearFiltros() {

    $('#filtroEstado, #filtroCliente, #filtroFechaDesde, #filtroFechaHasta').val('');

    $('#tablaPedidos').DataTable().search('').columns().search('').draw();

}

</script>



</body>

</html>

<?php include '../includes/footer.php'; ?> ?>