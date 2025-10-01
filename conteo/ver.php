<?php
// Habilitar mostrar errores para debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../config/db.php';
include '../includes/header.php';

// Verificar si se proporcionó un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger'>ID de cotización no válido.</div>";
    include '../includes/footer.php';
    exit;
}

$id = intval($_GET['id']);

try {
    // Primero verificar las columnas disponibles en la tabla cotizaciones
    $stmt = $pdo->prepare("SHOW COLUMNS FROM cotizaciones");
    $stmt->execute();
    $columnas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Construir la consulta dinámicamente basada en las columnas disponibles
    $campos = ['c.id', 'c.fecha', 'c.total', 'c.estado', 'cl.nombre AS cliente'];
    
    // Agregar campos opcionales si existen en la tabla
    if (in_array('notas', $columnas)) {
        $campos[] = 'c.notas';
    }
    if (in_array('validez', $columnas)) {
        $campos[] = 'c.validez';
    }
    
    // Campos del cliente
    $campos = array_merge($campos, ['cl.email', 'cl.telefono', 'cl.direccion']);
    
    $sql = "SELECT " . implode(', ', $campos) . " 
            FROM cotizaciones c
            JOIN clientes cl ON c.cliente_id = cl.id
            WHERE c.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $cotizacion = $stmt->fetch();

    if (!$cotizacion) {
        echo "<div class='alert alert-danger'>Cotización no encontrada.</div>";
        include '../includes/footer.php';
        exit;
    }

    // Obtener productos cotizados
    $stmt = $pdo->prepare("
        SELECT cd.*, p.nombre, p.codigo, p.descripcion
        FROM cotizacion_detalles cd
        JOIN productos p ON cd.producto_id = p.id
        WHERE cd.cotizacion_id = ?
    ");
    $stmt->execute([$id]);
    $detalles = $stmt->fetchAll();

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error al cargar los datos: " . htmlspecialchars($e->getMessage()) . "</div>";
    include '../includes/footer.php';
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Cotización - Sistema de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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
        
        .info-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 25px;
            border: var(--card-border);
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 25px;
            border: var(--card-border);
            overflow: hidden;
        }
        
        .section-title {
            color: var(--secondary-color);
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 12px;
            color: var(--primary-color);
            background: rgba(52, 152, 219, 0.1);
            width: 40px;
            height: 40px;
            line-height: 40px;
            text-align: center;
            border-radius: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-group {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid var(--primary-color);
        }
        
        .info-label {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 8px;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 1.1rem;
            color: #334155;
            font-weight: 500;
        }
        
        .table th {
            background-color: var(--secondary-color);
            color: white;
            font-weight: 600;
            padding: 15px;
            text-align: center;
            border: none;
        }
        
        .table td {
            padding: 15px;
            vertical-align: middle;
            border-color: #e2e8f0;
        }
        
        .table tbody tr:hover {
            background-color: #f8fafc;
        }
        
        .badge-status {
            font-size: 0.9rem;
            padding: 0.6rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            border-radius: 8px;
            padding: 12px 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .total-row {
            background-color: #f8fafc;
            font-weight: 700;
        }
        
        .total-row td {
            border-top: 2px solid var(--secondary-color);
            font-size: 1.1rem;
        }
        
        .product-code {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
        }
        
        .product-description {
            font-size: 0.9rem;
            color: #64748b;
            margin-top: 5px;
        }
        
        .notes-section {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            border-left: 4px solid var(--info-color);
        }
        
        .notes-label {
            font-weight: 600;
            color: var(--info-color);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .notes-label i {
            margin-right: 10px;
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .table {
                font-size: 0.85rem;
            }
        }

        /* Estilos para impresión */
        @media print {
            body {
                background-color: white;
                padding: 0;
            }
            
            .page-header, .info-card, .table-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .btn {
                display: none;
            }
            
            .action-buttons {
                display: none;
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
                    <h1 class="h3 fw-bold mb-0">
                        <i class="fas fa-file-invoice-dollar me-2 text-primary"></i>Detalle de Cotización
                    </h1>
                    <p class="text-muted mb-0">Información completa de la cotización #<?= $cotizacion['id'] ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver al Listado
                    </a>
                </div>
            </div>
        </div>

        <!-- Información de la Cotización -->
        <div class="info-card">
            <h4 class="section-title">
                <i class="fas fa-info-circle"></i>Información de la Cotización
            </h4>
            
            <div class="info-grid">
                <div class="info-group">
                    <div class="info-label">Número de Cotización</div>
                    <div class="info-value">#<?= $cotizacion['id'] ?></div>
                </div>
                
                <div class="info-group">
                    <div class="info-label">Fecha de Emisión</div>
                    <div class="info-value">
                        <i class="fas fa-calendar me-2 text-muted"></i><?= date('d/m/Y', strtotime($cotizacion['fecha'])) ?>
                    </div>
                </div>
                
                <div class="info-group">
                    <div class="info-label">Estado</div>
                    <div class="info-value">
                        <span class="badge-status bg-<?= 
                            $cotizacion['estado'] == 'aceptada' ? 'success' :
                            ($cotizacion['estado'] == 'rechazada' ? 'danger' : 'warning') ?>">
                            <i class="fas fa-<?= 
                                $cotizacion['estado'] == 'aceptada' ? 'check-circle' :
                                ($cotizacion['estado'] == 'rechazada' ? 'times-circle' : 'clock') ?> me-1"></i>
                            <?= ucfirst($cotizacion['estado']) ?>
                        </span>
                    </div>
                </div>
                
                <?php if (isset($cotizacion['validez']) && !empty($cotizacion['validez'])): ?>
                <div class="info-group">
                    <div class="info-label">Validez de la Oferta</div>
                    <div class="info-value">
                        <i class="fas fa-clock me-2 text-muted"></i><?= $cotizacion['validez'] ?> días
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Información del Cliente -->
        <div class="info-card">
            <h4 class="section-title">
                <i class="fas fa-user"></i>Información del Cliente
            </h4>
            
            <div class="info-grid">
                <div class="info-group">
                    <div class="info-label">Cliente</div>
                    <div class="info-value">
                        <i class="fas fa-user-tie me-2 text-muted"></i><?= htmlspecialchars($cotizacion['cliente']) ?>
                    </div>
                </div>
                
                <?php if (!empty($cotizacion['email'])): ?>
                <div class="info-group">
                    <div class="info-label">Email</div>
                    <div class="info-value">
                        <i class="fas fa-envelope me-2 text-muted"></i><?= htmlspecialchars($cotizacion['email']) ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($cotizacion['telefono'])): ?>
                <div class="info-group">
                    <div class="info-label">Teléfono</div>
                    <div class="info-value">
                        <i class="fas fa-phone me-2 text-muted"></i><?= htmlspecialchars($cotizacion['telefono']) ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($cotizacion['direccion'])): ?>
                <div class="info-group">
                    <div class="info-label">Dirección</div>
                    <div class="info-value">
                        <i class="fas fa-map-marker-alt me-2 text-muted"></i><?= htmlspecialchars($cotizacion['direccion']) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Productos Cotizados -->
        <div class="table-container">
            <div class="p-4">
                <h4 class="section-title">
                    <i class="fas fa-boxes"></i>Productos Cotizados
                </h4>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="35%">Producto</th>
                                <th width="10%" class="text-center">Cantidad</th>
                                <th width="15%" class="text-end">Precio Unitario</th>
                                <th width="15%" class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $total = 0; ?>
                            <?php foreach ($detalles as $index => $d): ?>
                                <?php
                                    $subtotal = $d['cantidad'] * $d['precio_unitario'];
                                    $total += $subtotal;
                                ?>
                                <tr>
                                    <td class="text-center"><?= $index + 1 ?></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($d['nombre']) ?></div>
                                        <?php if (!empty($d['codigo'])): ?>
                                        <div class="product-code">
                                            <i class="fas fa-barcode me-1"></i>Código: <?= htmlspecialchars($d['codigo']) ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($d['descripcion'])): ?>
                                        <div class="product-description">
                                            <?= htmlspecialchars($d['descripcion']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= $d['cantidad'] ?></td>
                                    <td class="text-end">$<?= number_format($d['precio_unitario'], 2) ?></td>
                                    <td class="text-end fw-bold">$<?= number_format($subtotal, 2) ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="4" class="text-end">Total</td>
                                <td class="text-end">$<?= number_format($total, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Notas Adicionales - Solo si existe la columna y tiene valor -->
        <?php if (isset($cotizacion['notas']) && !empty($cotizacion['notas'])): ?>
        <div class="info-card">
            <h4 class="section-title">
                <i class="fas fa-sticky-note"></i>Notas Adicionales
            </h4>
            
            <div class="notes-section">
                <div class="notes-label">
                    <i class="fas fa-info-circle"></i>Observaciones
                </div>
                <p><?= nl2br(htmlspecialchars($cotizacion['notas'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Botones de Acción -->
        <div class="action-buttons">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Volver al Listado
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print me-2"></i>Imprimir Cotización
            </button>
            <?php if ($cotizacion['estado'] == 'pendiente'): ?>
            <a href="editar.php?id=<?= $id ?>" class="btn btn-warning">
                <i class="fas fa-edit me-2"></i>Editar Cotización
            </a>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>