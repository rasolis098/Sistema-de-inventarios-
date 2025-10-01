<?php
// Mover session_start() al inicio para manejar mensajes.
session_start();

include '../config/db.php';
include '../includes/header.php';

// Verificar si hay mensajes de sesión
$mensaje_exito = $_SESSION['mensaje_exito'] ?? '';
unset($_SESSION['mensaje_exito']);

$mensaje_error = $_SESSION['mensaje_error'] ?? '';
unset($_SESSION['mensaje_error']);

$estadoFiltro = $_GET['estado'] ?? '';
$prospectos = [];
$total_prospectos = $nuevos = $contactados = $interesados = $no_interesados = 0;
$error_db = '';

try {
    // Consulta principal de prospectos
    if ($estadoFiltro && in_array($estadoFiltro, ['nuevo','contactado','interesado','no interesado'])) {
        $stmt = $pdo->prepare("SELECT * FROM prospectos WHERE estado = ? ORDER BY fecha_registro DESC");
        $stmt->execute([$estadoFiltro]);
    } else {
        $stmt = $pdo->query("SELECT * FROM prospectos ORDER BY fecha_registro DESC");
    }
    $prospectos = $stmt->fetchAll();

    // Obtener estadísticas en una sola consulta para mayor eficiencia
    $stats_query = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'nuevo' THEN 1 ELSE 0 END) as nuevos,
            SUM(CASE WHEN estado = 'contactado' THEN 1 ELSE 0 END) as contactados,
            SUM(CASE WHEN estado = 'interesado' THEN 1 ELSE 0 END) as interesados,
            SUM(CASE WHEN estado = 'no interesado' THEN 1 ELSE 0 END) as no_interesados
        FROM prospectos
    ";
    $stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);
    
    $total_prospectos = $stats['total'] ?? 0;
    $nuevos = $stats['nuevos'] ?? 0;
    $contactados = $stats['contactados'] ?? 0;
    $interesados = $stats['interesados'] ?? 0;
    $no_interesados = $stats['no_interesados'] ?? 0;

} catch (PDOException $e) {
    error_log("Error en la página de prospectos: " . $e->getMessage());
    $error_db = "Error al conectar con la base de datos de prospectos. Verifique que la tabla 'prospectos' exista.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Prospectos - Sistema de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --light-bg: #f8f9fa;
            --border-color: #dee2e6;
            --card-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        body { background-color: var(--light-bg); font-family: 'Inter', sans-serif; color: #343a40; }
        .main-card { background-color: white; border-radius: 0.75rem; border: 1px solid var(--border-color); box-shadow: var(--card-shadow); overflow: hidden; }
        .card-header-custom { background-color: #fff; color: #212529; padding: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .page-title { font-weight: 700; font-size: 1.75rem; }
        .stats-card { border: 1px solid var(--border-color); border-radius: 0.75rem; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .stats-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .table thead th { background-color: #f8f9fa; }
        .table td, .table th { vertical-align: middle; }
        .badge { font-weight: 500; padding: .4em .8em; border-radius: 20px; font-size: 0.8rem; }
        .badge-nuevo { background-color: #e9ecef; color: #495057; }
        .badge-contactado { background-color: #cfe2ff; color: #0d6efd; }
        .badge-interesado { background-color: #d1e7dd; color: #155724; }
        .badge-no-interesado { background-color: #f8d7da; color: #842029; }
    </style>
</head>
<body>

    <div class="container-fluid my-4">

        <?php if ($error_db): ?>
            <div class="alert alert-danger">
                <h4><i class="fas fa-database me-2"></i>Error de Base de Datos</h4>
                <p><?= htmlspecialchars($error_db) ?></p>
            </div>
        <?php else: ?>

        <div class="main-card">
            <div class="card-header-custom">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h1 class="page-title mb-0"><i class="fas fa-user-plus me-2"></i>Gestión de Prospectos</h1>
                        <p class="text-muted mb-0">Administra y realiza seguimiento a tus clientes potenciales</p>
                    </div>
                    <div class="text-end">
                        <a href="agregar.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Nuevo Prospecto</a>
                    </div>
                </div>
            </div>

            <div class="card-body p-4">
                
                <?php if ($mensaje_exito): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($mensaje_exito) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($mensaje_error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($mensaje_error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <section class="mb-4">
                    <div class="row">
                        <div class="col-lg col-md-4 col-6 mb-3"><a href="index.php" class="text-decoration-none"><div class="card stats-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted fw-normal">Total</h6><h3 class="fw-bold mb-0"><?= $total_prospectos ?></h3></div><div class="fs-2 text-primary opacity-50"><i class="fas fa-users"></i></div></div></div></a></div>
                        <div class="col-lg col-md-4 col-6 mb-3"><a href="?estado=nuevo" class="text-decoration-none"><div class="card stats-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted fw-normal">Nuevos</h6><h3 class="fw-bold mb-0"><?= $nuevos ?></h3></div><div class="fs-2 text-secondary opacity-50"><i class="fas fa-star"></i></div></div></div></a></div>
                        <div class="col-lg col-md-4 col-6 mb-3"><a href="?estado=contactado" class="text-decoration-none"><div class="card stats-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted fw-normal">Contactados</h6><h3 class="fw-bold mb-0"><?= $contactados ?></h3></div><div class="fs-2 text-info opacity-50"><i class="fas fa-phone"></i></div></div></div></a></div>
                        <div class="col-lg col-md-6 col-6 mb-3"><a href="?estado=interesado" class="text-decoration-none"><div class="card stats-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted fw-normal">Interesados</h6><h3 class="fw-bold mb-0"><?= $interesados ?></h3></div><div class="fs-2 text-success opacity-50"><i class="fas fa-thumbs-up"></i></div></div></div></a></div>
                        <div class="col-lg col-md-6 col-12 mb-3"><a href="?estado=no interesado" class="text-decoration-none"><div class="card stats-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted fw-normal">No Interesados</h6><h3 class="fw-bold mb-0"><?= $no_interesados ?></h3></div><div class="fs-2 text-danger opacity-50"><i class="fas fa-thumbs-down"></i></div></div></div></a></div>
                    </div>
                </section>

                <hr class="my-4">

                <section>
                    <div class="d-flex justify-content-end mb-3">
                        <input type="text" id="customSearch" class="form-control" style="width: 250px;" placeholder="Buscar prospecto...">
                    </div>
                    <div class="table-responsive">
                        <table id="tablaProspectos" class="table table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Contacto</th>
                                    <th>Estado</th>
                                    <th>Notas</th>
                                    <th>Fecha Registro</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prospectos as $p): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($p['nombre']) ?></div>
                                        <small class="text-muted">ID: <?= $p['id'] ?></small>
                                    </td>
                                    <td>
                                        <div><i class="fas fa-envelope me-2 text-muted"></i><small><?= htmlspecialchars($p['email']) ?></small></div>
                                        <?php if (!empty($p['telefono'])): ?>
                                            <div><i class="fas fa-phone me-2 text-muted"></i><small><?= htmlspecialchars($p['telefono']) ?></small></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= str_replace(' ', '-', $p['estado']) ?>"><?= ucfirst($p['estado']) ?></span>
                                    </td>
                                    <td title="<?= htmlspecialchars($p['notas']) ?>">
                                        <small><?= !empty($p['notas']) ? htmlspecialchars(mb_strimwidth($p['notas'], 0, 50, '...')) : '<span class="text-muted">--</span>' ?></small>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($p['fecha_registro'])) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <a href="editar.php?id=<?= $p['id'] ?>" class="btn btn-warning btn-sm" title="Editar"><i class="fas fa-edit"></i></a>
                                        <a href="seguimiento.php?id=<?= $p['id'] ?>" class="btn btn-info btn-sm" title="Ver Seguimiento"><i class="fas fa-history"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

            </div>
        </div>
        <?php endif; // Fin del else para el error de DB ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            const table = $('#tablaProspectos').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
                pageLength: 10,
                responsive: true,
                order: [[4, 'desc']],
                columnDefs: [
                    { orderable: false, targets: [5] }, // Desactivar orden en columna de acciones
                    { searchable: false, targets: [4, 5] } // Desactivar búsqueda en fecha y acciones
                ],
                dom: 'rt<"row mt-3"<"col-md-6"i><"col-md-6"p>>', // Ocultar buscador por defecto
            });

            $('#customSearch').on('keyup', function() {
                table.search(this.value).draw();
            });
        });
    </script>
</body>
</html>
<?php include '../includes/footer.php'; ?>