<?php
include '../config/db.php';
include '../includes/header.php';

// Función para verificar si una columna existe en una tabla
function columnaExiste($pdo, $tabla, $columna) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM $tabla LIKE ?");
        $stmt->execute([$columna]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Verificar estructura de la base de datos
$tieneStock = columnaExiste($pdo, 'productos', 'stock');
$tieneAlmacenId = columnaExiste($pdo, 'productos', 'almacen_id');

// Obtener estadísticas básicas
$total_almacenes = $pdo->query("SELECT COUNT(*) as total FROM almacenes")->fetch()['total'];
$ultimo_almacen_result = $pdo->query("SELECT nombre FROM almacenes ORDER BY created_at DESC LIMIT 1")->fetch();
$ultimo_almacen = $ultimo_almacen_result ? $ultimo_almacen_result['nombre'] : 'N/A';

// Obtener estadísticas adicionales si es posible
$total_productos = 0;
$total_stock = 0;

if ($tieneAlmacenId && $tieneStock) {
    $stats = $pdo->query("
        SELECT 
            COUNT(p.id) as total_productos,
            COALESCE(SUM(p.stock), 0) as total_stock
        FROM productos p
        WHERE p.almacen_id IS NOT NULL
    ")->fetch();
    $total_productos = $stats['total_productos'];
    $total_stock = $stats['total_stock'];
}

// Obtener almacenes
if ($tieneAlmacenId && $tieneStock) {
    $stmt = $pdo->query("
        SELECT a.*, 
               COUNT(p.id) as total_productos,
               COALESCE(SUM(p.stock), 0) as total_stock
        FROM almacenes a 
        LEFT JOIN productos p ON a.id = p.almacen_id 
        GROUP BY a.id 
        ORDER BY a.created_at DESC
    ");
} else {
    $stmt = $pdo->query("SELECT * FROM almacenes ORDER BY created_at DESC");
}

$almacenes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Almacenes - Sistema de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #17a2b8;
            --light-bg: #f8f9fa;
            --card-border: 1px solid #e2e8f0;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px 0;
            color: #444;
        }
        
        .page-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 25px;
            border: var(--card-border);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: all 0.3s ease;
            border: var(--card-border);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
        }
        
        .stat-card:nth-child(1)::before { background: var(--secondary-color); }
        .stat-card:nth-child(2)::before { background: var(--info-color); }
        .stat-card:nth-child(3)::before { background: var(--success-color); }
        .stat-card:nth-child(4)::before { background: var(--primary-color); }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 15px;
            width: 60px;
            height: 60px;
            line-height: 60px;
            border-radius: 12px;
            display: inline-block;
        }
        
        .stat-card:nth-child(1) .stat-icon { 
            background: rgba(44, 62, 80, 0.1);
            color: var(--secondary-color);
        }
        .stat-card:nth-child(2) .stat-icon { 
            background: rgba(23, 162, 184, 0.1);
            color: var(--info-color);
        }
        .stat-card:nth-child(3) .stat-icon { 
            background: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
        }
        .stat-card:nth-child(4) .stat-icon { 
            background: rgba(52, 152, 219, 0.1);
            color: var(--primary-color);
        }
        
        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .progress-bar-mini {
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-value {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 20px;
            border: var(--card-border);
        }
        
        .table-header {
            background: white;
            padding: 20px;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table th {
            background-color: var(--secondary-color);
            color: white;
            font-weight: 500;
            padding: 15px;
            font-size: 0.9rem;
        }
        
        .table td {
            padding: 15px;
            vertical-align: middle;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .btn-action {
            border-radius: 6px;
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .btn-action:hover {
            transform: scale(1.1);
        }
        
        .badge {
            font-weight: 500;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .badge-secondary {
            background-color: rgba(108, 117, 125, 0.15);
            color: #6c757d;
        }
        
        .badge-success {
            background-color: rgba(39, 174, 96, 0.15);
            color: #27ae60;
        }
        
        .badge-info {
            background-color: rgba(23, 162, 184, 0.15);
            color: #17a2b8;
        }
        
        .badge-warning {
            background-color: rgba(243, 156, 18, 0.15);
            color: #f39c12;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
        }
        
        .info-alert {
            background: rgba(23, 162, 184, 0.1);
            border: 1px solid var(--info-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .almacen-name {
            display: flex;
            align-items: center;
            font-weight: 600;
            color: var(--secondary-color);
        }
        
        .almacen-meta {
            font-size: 0.85rem;
            color: #64748b;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .table-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .export-buttons {
                width: 100%;
                justify-content: space-between;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
            
            .btn-action {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h3 fw-bold"><i class="fas fa-warehouse me-2 text-primary"></i>Gestión de Almacenes</h1>
                    <p class="mb-0 text-muted">Administra y monitorea todos los almacenes del sistema</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="agregar.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Nuevo Almacén
                    </a>
                </div>
            </div>
        </div>

        <!-- Alerta informativa -->
        <?php if (!$tieneAlmacenId): ?>
        <div class="info-alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-info-circle me-3 fa-2x" style="color: var(--info-color);"></i>
                <div>
                    <h5 class="mb-1">Configuración requerida</h5>
                    <p class="mb-0">La relación entre productos y almacenes no está configurada completamente.</p>
                    <a href="../productos/index.php" class="btn btn-sm btn-outline-info mt-2">
                        <i class="fas fa-cog me-1"></i>Configurar productos
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-warehouse"></i>
                </div>
                <div class="stat-number"><?php echo $total_almacenes; ?></div>
                <div class="stat-label">Total Almacenes</div>
                <div class="progress-bar-mini">
                    <div class="progress-value bg-secondary" style="width: 100%"></div>
                </div>
            </div>
            
            <?php if ($tieneAlmacenId): ?>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-box"></i>
                </div>
                <div class="stat-number"><?php echo $total_productos; ?></div>
                <div class="stat-label">Productos Totales</div>
                <div class="progress-bar-mini">
                    <div class="progress-value bg-info" style="width: 100%"></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-cubes"></i>
                </div>
                <div class="stat-number"><?php echo $total_stock; ?></div>
                <div class="stat-label">Stock Total</div>
                <div class="progress-bar-mini">
                    <div class="progress-value bg-success" style="width: 100%"></div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-number"><?php echo $ultimo_almacen; ?></div>
                <div class="stat-label">Último Almacén</div>
                <div class="progress-bar-mini">
                    <div class="progress-value bg-primary" style="width: 100%"></div>
                </div>
            </div>
        </div>

        <!-- Tabla de Almacenes -->
        <div class="table-container">
            <div class="table-header">
                <h5 class="mb-0 fw-semibold"><i class="fas fa-list me-2 text-primary"></i>Lista de Almacenes</h5>
                <div class="export-buttons">
                    <span class="badge bg-primary rounded-pill"><?= count($almacenes) ?> registros</span>
                </div>
            </div>
            
            <div class="table-responsive">
                <table id="tablaAlmacenes" class="table table-hover">
                    <thead>
                        <tr>
                            <th>Almacén</th>
                            <th>Ubicación</th>
                            <th>Responsable</th>
                            <?php if ($tieneAlmacenId): ?>
                            <th>Productos</th>
                            <?php if ($tieneStock): ?>
                            <th>Stock</th>
                            <?php endif; ?>
                            <?php endif; ?>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($almacenes as $almacen): ?>
                        <tr>
                            <td>
                                <div class="almacen-name">
                                    <i class="fas fa-warehouse me-2 text-primary"></i>
                                    <?php echo htmlspecialchars($almacen['nombre']); ?>
                                </div>
                                <div class="almacen-meta">
                                    ID: <?php echo $almacen['id']; ?> · 
                                    Creado: <?php echo date('d/m/Y', strtotime($almacen['created_at'])); ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-secondary">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?php echo htmlspecialchars($almacen['ubicacion']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($almacen['responsable']); ?>
                                </span>
                            </td>
                            <?php if ($tieneAlmacenId): ?>
                            <td>
                                <span class="badge badge-warning">
                                    <i class="fas fa-box me-1"></i>
                                    <?php echo isset($almacen['total_productos']) ? $almacen['total_productos'] : '0'; ?>
                                </span>
                            </td>
                            <?php if ($tieneStock): ?>
                            <td>
                                <span class="badge badge-success">
                                    <i class="fas fa-cubes me-1"></i>
                                    <?php echo isset($almacen['total_stock']) ? $almacen['total_stock'] : '0'; ?>
                                </span>
                            </td>
                            <?php endif; ?>
                            <?php endif; ?>
                            <td>
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle me-1"></i>Activo
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="editar.php?id=<?php echo $almacen['id']; ?>" class="btn btn-warning btn-action" title="Editar almacén">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="productos.php?id=<?php echo $almacen['id']; ?>" class="btn btn-info btn-action" title="Ver productos">
                                        <i class="fas fa-boxes"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger btn-action" 
                                            onclick="confirmarEliminacion(<?php echo $almacen['id']; ?>, '<?php echo htmlspecialchars($almacen['nombre']); ?>')" 
                                            title="Eliminar almacén">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación de Eliminación -->
    <div class="modal fade" id="modalEliminar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas eliminar el almacén <strong id="nombreAlmacenEliminar"></strong>?</p>
                    <p class="text-danger"><small>Esta acción no se puede deshacer y podría afectar a los productos asociados.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <a href="#" class="btn btn-danger" id="btnConfirmarEliminar">
                        <i class="fas fa-trash me-1"></i>Eliminar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script>
    $(document).ready(function() {
        // Inicializar DataTable
        var table = $('#tablaAlmacenes').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            },
            dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
            pageLength: 10,
            responsive: true,
            order: [[0, 'asc']],
            columnDefs: [
                { responsivePriority: 1, targets: 0 },
                { responsivePriority: 2, targets: -1 },
                { orderable: false, targets: -1 }
            ]
        });
    });

    function confirmarEliminacion(id, nombre) {
        $('#nombreAlmacenEliminar').text(nombre);
        $('#btnConfirmarEliminar').attr('href', 'eliminar.php?id=' + id);
        
        var modal = new bootstrap.Modal(document.getElementById('modalEliminar'));
        modal.show();
    }
    </script>
</body>
</html>