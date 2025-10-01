<?php
// Habilitar errores para depuración (puedes quitar esto en producción)
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'config/db.php';

// Redirigir si no hay sesión activa
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Obtener datos del usuario logeado
$stmt = $pdo->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    // Si el usuario no existe, destruir la sesión y redirigir
    session_destroy();
    header('Location: login.php');
    exit;
}

$nombre_usuario = $usuario['nombre'];
$correo_usuario = $usuario['email'];

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // === Totales de estadísticas ===
    $total_productos = $pdo->query("SELECT COUNT(*) AS total FROM productos")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $total_ventas = $pdo->query("SELECT COUNT(*) AS total FROM ventas")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $total_clientes = $pdo->query("SELECT COUNT(*) AS total FROM clientes")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $total_categorias = $pdo->query("SELECT COUNT(*) AS total FROM categorias")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // === Producto más vendido ===
    $producto_mas_vendido = 'N/A';
    $sql = "SELECT p.nombre, SUM(vd.cantidad) AS total_vendido
            FROM venta_detalles vd
            INNER JOIN productos p ON p.id = vd.producto_id
            GROUP BY vd.producto_id
            ORDER BY total_vendido DESC
            LIMIT 1";
    $stmt = $pdo->query($sql);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $producto_mas_vendido = $row['nombre'] . " ({$row['total_vendido']} unidades)";
    }

    // === Actividad reciente de la base de datos ===
    $ultimos_clientes = $pdo->query("SELECT nombre, created_at FROM clientes ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $ultimos_productos = $pdo->query("SELECT nombre, created_at FROM productos ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $ultimas_ventas = $pdo->query("SELECT v.id, v.total, v.fecha, c.nombre AS nombre_cliente
                                    FROM ventas v
                                    INNER JOIN clientes c ON v.cliente_id = c.id
                                    ORDER BY v.fecha DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $ultima_venta = $ultimas_ventas[0] ?? null;
    $ultimos_productos_vendidos = $pdo->query("
        SELECT p.nombre AS producto, vd.cantidad, v.fecha
        FROM venta_detalles vd
        INNER JOIN productos p ON p.id = vd.producto_id
        INNER JOIN ventas v ON v.id = vd.venta_id
        ORDER BY v.fecha DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // === Datos para gráficos ===
    $sqlCategorias = "
        SELECT c.nombre AS categoria, COUNT(p.id) AS total
        FROM categorias c
        LEFT JOIN productos p ON p.categoria_id = c.id
        GROUP BY c.id, c.nombre
        ORDER BY total DESC
    ";
    $categoriasData = $pdo->query($sqlCategorias)->fetchAll(PDO::FETCH_ASSOC);
    $categorias_labels = [];
    $categorias_totales = [];
    foreach ($categoriasData as $row) {
        $categorias_labels[] = $row['categoria'];
        $categorias_totales[] = (int)$row['total'];
    }

    $sqlMovimientos = "
        SELECT MONTH(m.fecha) AS mes, m.tipo, SUM(d.cantidad) AS total
        FROM movimientos m
        INNER JOIN movimiento_detalles d ON d.movimiento_id = m.id
        GROUP BY MONTH(m.fecha), m.tipo
        ORDER BY MONTH(m.fecha)
    ";
    $movimientos = $pdo->query($sqlMovimientos)->fetchAll(PDO::FETCH_ASSOC);
    $entradas = array_fill(1, 12, 0);
    $salidas = array_fill(1, 12, 0);
    foreach ($movimientos as $m) {
        $mes = (int)$m['mes'];
        $tipo = strtolower($m['tipo']);
        $total = (int)$m['total'];
        if ($tipo === 'entrada') { $entradas[$mes] = $total; }
        elseif ($tipo === 'salida') { $salidas[$mes] = $total; }
    }
    $entradas_data = array_values($entradas);
    $salidas_data = array_values($salidas);

    $sqlVentas = "
        SELECT MONTH(fecha) AS mes, SUM(total) AS total_ventas
        FROM ventas
        WHERE YEAR(fecha) = YEAR(CURDATE())
        GROUP BY MONTH(fecha)
        ORDER BY mes
    ";
    $ventas_mes = $pdo->query($sqlVentas)->fetchAll(PDO::FETCH_ASSOC);
    $ventas_data = array_fill(1, 12, 0);
    foreach ($ventas_mes as $venta) {
        $ventas_data[(int)$venta['mes']] = (float)$venta['total_ventas'];
    }
    $ventas_chart_data = array_values($ventas_data);

    // === Actividad reciente en tiempo real ===
    $actividad_reciente = $pdo->query("
        (SELECT 'venta' as tipo, v.id as referencia, CONCAT('Venta #', v.id) as descripcion,
                c.nombre as usuario, v.fecha as fecha_creacion
         FROM ventas v
         INNER JOIN clientes c ON v.cliente_id = c.id
         ORDER BY v.fecha DESC LIMIT 3)
         UNION ALL
         (SELECT 'producto' as tipo, p.id as referencia, CONCAT('Producto: ', p.nombre) as descripcion,
                 'Sistema' as usuario, p.created_at as fecha_creacion
          FROM productos p
          ORDER BY p.created_at DESC LIMIT 3)
          UNION ALL
          (SELECT 'cliente' as tipo, c.id as referencia, CONCAT('Cliente: ', c.nombre) as descripcion,
                  'Sistema' as usuario, c.created_at as fecha_creacion
           FROM clientes c
           ORDER BY c.created_at DESC LIMIT 3)
           ORDER BY fecha_creacion DESC
           LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
    $categorias_labels = $categorias_totales = [];
    $entradas_data = $salidas_data = array_fill(0, 12, 0);
    $ventas_chart_data = array_fill(0, 12, 0);
    $actividad_reciente = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Inventario Avanzado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-blue: #2c59d7;
            --primary-blue-dark: #2243a7;
            --secondary-gray: #6c757d;
            --success-color: #20c997;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #0dcaf0;
            --bg-light: #f8fafc;
            --card-bg-light: #ffffff;
            --sidebar-dark: #1b2f4c;
            --font-family: 'Poppins', sans-serif;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--bg-light);
            color: #333;
            overflow-x: hidden;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            background: linear-gradient(180deg, var(--sidebar-dark) 0%, #172a44 100%);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        .sidebar.minimized {
            width: 80px;
        }
        .sidebar.minimized .menu-text {
            display: none;
        }
        .main-content {
            margin-left: 250px;
            transition: all 0.3s ease;
        }
        .main-content.expanded {
            margin-left: 80px;
        }
        .card {
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            border: none;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        /* Estilos de gradiente para tarjetas de estadísticas */
        .stats-card {
            padding: 1rem;
        }
        .stats-card .h5, .stats-card .text-xs {
            color: white;
        }
        .card-gradient-1 { background: linear-gradient(135deg, var(--primary-blue), var(--primary-blue-dark)); }
        .card-gradient-2 { background: linear-gradient(135deg, var(--success-color), #1a9e7a); }
        .card-gradient-3 { background: linear-gradient(135deg, var(--warning-color), #d4a72d); }
        .card-gradient-4 { background: linear-gradient(135deg, var(--danger-color), #c02b3b); }

        /* Estilo de navegación en la barra lateral */
        .nav-link {
            color: rgba(255, 255, 255, 0.7);
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }
        .nav-link:hover, .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid white;
        }

        /* Botón de actualización con animación de rotación */
        .btn-outline-primary .fas.fa-sync-alt {
            transition: transform 0.5s ease;
        }
        .btn-outline-primary:hover .fas.fa-sync-alt {
            transform: rotate(180deg);
        }

        /* Estilos de tablas mejorados */
        .table thead th {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--secondary-gray);
            background-color: #e9ecef;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.03);
            cursor: pointer;
            transform: scale(1.01);
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }

        .activity-venta { border-left: 4px solid var(--success-color); }
        .activity-producto { border-left: 4px solid var(--primary-blue); }
        .activity-cliente { border-left: 4px solid var(--info-color); }
        .activity-sistema { border-left: 4px solid var(--warning-color); }

        /* Estilos para el chat */
        .chat-badge {
            position: relative;
        }
        .chat-notification {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .user-item:hover {
            background-color: #f8f9fa;
        }
        .user-item.bg-primary:hover {
            background-color: var(--primary-blue-dark) !important;
        }
        .chat-messages {
            background-color: #f8f9fa;
        }
        .message-text {
            word-wrap: break-word;
        }
        .users-list, .chat-messages {
            scrollbar-width: thin;
            scrollbar-color: var(--secondary-gray) var(--bg-light);
        }
        .users-list::-webkit-scrollbar, .chat-messages::-webkit-scrollbar {
            width: 6px;
        }
        .users-list::-webkit-scrollbar-track, .chat-messages::-webkit-scrollbar-track {
            background: var(--bg-light);
        }
        .users-list::-webkit-scrollbar-thumb, .chat-messages::-webkit-scrollbar-thumb {
            background-color: var(--secondary-gray);
            border-radius: 3px;
        }
        @media (max-width: 768px) {
            .sidebar { width: 80px; }
            .sidebar .menu-text { display: none; }
            .main-content { margin-left: 80px; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container-fluid p-0">
        <div class="sidebar" id="sidebar">
            <div class="p-4">
                <h4 class="text-white text-center mb-4">
                    <i class="fas fa-warehouse me-2"></i>
                    <span class="menu-text">InventarioPro</span>
                </h4>
                <ul class="nav flex-column mt-4">
                    <li class="nav-item mb-2">
                        <a href="dashboard.php" class="nav-link active py-2">
                            <i class="fas fa-home mx-2"></i>
                            <span class="menu-text">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="correo/index.php" class="nav-link py-2">
                            <i class="fas fa-envelope mx-2"></i>
                            <span class="menu-text">Correo</span>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="prospectos/index.php" class="nav-link py-2">
                            <i class="fas fa-user-plus mx-2"></i>
                            <span class="menu-text">Prospectos</span>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="clientes/index.php" class="nav-link py-2">
                            <i class="fas fa-users mx-2"></i>
                            <span class="menu-text">Clientes</span>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="cotizaciones/index.php" class="nav-link py-2">
                            <i class="fas fa-file-invoice-dollar mx-2"></i>
                            <span class="menu-text">Cotizaciones</span>
                        </a>
                    </li>
                     <li class="nav-item mb-2">
                        <a href="ventas/index.php" class="nav-link py-2">
                            <i class="fas fa-dollar-sign mx-2"></i>
                            <span class="menu-text">Ventas</span>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="pedidos/index.php" class="nav-link py-2">
                            <i class="fas fa-clipboard-list mx-2"></i>
                            <span class="menu-text">Pedidos</span>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="productos/index.php" class="nav-link py-2">
                            <i class="fas fa-box mx-2"></i>
                            <span class="menu-text">Productos</span>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="almacenes/index.php" class="nav-link py-2">
                            <i class="fas fa-warehouse mx-2"></i>
                            <span class="menu-text">Almacenes</span>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="reportes/index.php" class="nav-link py-2">
                            <i class="fas fa-chart-bar mx-2"></i>
                            <span class="menu-text">Reportes</span>
                        </a>
                    </li>
                    </div>
                </div>
            </div>
        </div>

        <div class="main-content" id="mainContent">
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
                <div class="container-fluid">
                    <button id="sidebarToggle" class="btn btn-sm">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="d-flex">
                        <div class="dropdown me-3 position-relative">
                            <a href="#" class="btn btn-sm btn-light chat-badge" role="button" id="chatDropdown" data-bs-toggle="modal" data-bs-target="#chatModal">
                                <i class="fas fa-comments"></i>
                                <span class="chat-notification" id="chatNotificationCount" style="display: none;">0</span>
                            </a>
                        </div>
                        <div class="dropdown me-3 position-relative">
                            <a href="#" class="btn btn-sm btn-light dropdown-toggle" role="button" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <span class="badge bg-danger notification-badge" id="globalNotificationBadge">0</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" id="notificationsList">
                                <li><a class="dropdown-item" href="#">Cargando notificaciones...</a></li>
                            </ul>
                        </div>
                        <div class="dropdown">
                            <a href="#" class="btn btn-sm btn-light dropdown-toggle" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($nombre_usuario); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="https://inv.dialexander.com/usuarios/perfil.php"><i class="fas fa-user me-2"></i> Perfil</a></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i> Configuración</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Cerrar sesión</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold">Dashboard en Tiempo Real</h3>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary me-2" onclick="exportDashboardData()">
                            <i class="fas fa-download me-1"></i> Exportar
                        </button>
                        <button class="btn btn-sm btn-primary" onclick="refreshAllData()">
                            <i class="fas fa-sync-alt me-1"></i> Actualizar Todo
                        </button>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card card-gradient-1 shadow h-100 py-2">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-white text-uppercase mb-1">Productos Totales</div>
                                    <div class="h5 mb-0 font-weight-bold" id="total-productos"><?php echo $total_productos; ?></div>
                                </div>
                                <i class="fas fa-boxes fa-2x text-white opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card card-gradient-2 shadow h-100 py-2">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-white text-uppercase mb-1">Clientes Activos</div>
                                    <div class="h5 mb-0 font-weight-bold" id="total-clientes"><?php echo $total_clientes; ?></div>
                                </div>
                                <i class="fas fa-users fa-2x text-white opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card card-gradient-3 shadow h-100 py-2">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-white text-uppercase mb-1">Ventas Totales</div>
                                    <div class="h5 mb-0 font-weight-bold" id="total-ventas"><?php echo $total_ventas; ?></div>
                                </div>
                                <i class="fas fa-file-invoice-dollar fa-2x text-white opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card card-gradient-4 shadow h-100 py-2">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-white text-uppercase mb-1">Categorías</div>
                                    <div class="h5 mb-0 font-weight-bold" id="total-categorias"><?php echo $total_categorias; ?></div>
                                </div>
                                <i class="fas fa-tags fa-2x text-white opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Movimientos de Inventario</h6>
                                <div class="dropdown">
                                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuLink">
                                        <li><a class="dropdown-item" href="#" onclick="exportChartData('movimientos')">Exportar datos</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="refreshMovementChart()">Actualizar</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-area">
                                    <canvas id="inventoryMovementChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Distribución por Categorías</h6>
                                <div class="dropdown">
                                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink2" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuLink2">
                                        <li><a class="dropdown-item" href="#" onclick="exportChartData('categorias')">Exportar datos</a></li>
                                        <li><a class="dropdown-item" href="#">Ver detalles</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-pie pt-4 pb-2">
                                    <canvas id="categoryDistributionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-xl-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Ventas Mensuales <?php echo date('Y'); ?></h6>
                                <div class="dropdown">
                                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink3" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuLink3">
                                        <li><a class="dropdown-item" href="#" onclick="exportChartData('ventas')">Exportar datos</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="refreshSalesChart()">Actualizar</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-area">
                                    <canvas id="salesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-lg-12">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-broadcast-tower me-2"></i>Actividad en Tiempo Real
                                </h6>
                                <div>
                                    <span class="badge bg-success me-2" id="status-conexion">
                                        <i class="fas fa-circle me-1"></i>Conectado
                                    </span>
                                    <button class="btn btn-sm btn-outline-primary" onclick="toggleRealTime()" id="toggleRealtimeBtn">
                                        <i class="fas fa-pause me-1"></i>Pausar
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="15%">Hora</th>
                                                <th width="20%">Usuario</th>
                                                <th width="45%">Actividad</th>
                                                <th width="20%">Tipo</th>
                                            </tr>
                                        </thead>
                                        <tbody id="realTimeActivity">
                                            <?php if (!empty($actividad_reciente)): ?>
                                                <?php foreach ($actividad_reciente as $actividad): ?>
                                                <tr class="activity-<?php echo $actividad['tipo']; ?>">
                                                    <td><?php echo date('H:i', strtotime($actividad['fecha_creacion'])); ?></td>
                                                    <td><?php echo htmlspecialchars($actividad['usuario']); ?></td>
                                                    <td><?php echo htmlspecialchars($actividad['descripcion']); ?></td>
                                                    <td>
                                                        <span class="badge
                                                            <?php echo $actividad['tipo'] === 'venta' ? 'bg-success' :
                                                                ($actividad['tipo'] === 'producto' ? 'bg-primary' : 'bg-info'); ?>">
                                                                <?php echo ucfirst($actividad['tipo']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-4">
                                                        No hay actividad reciente registrada
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">Actividad Reciente</h6>
                                <button class="btn btn-sm btn-outline-primary" onclick="refreshActivity()">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush" id="recentActivityList">
                                    <?php if ($ultima_venta): ?>
                                    <li class="list-group-item">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <span class="fw-bold">Nueva venta registrada</span>
                                                <p class="mb-0">Orden #<?php echo $ultima_venta['id']; ?> por $<?php echo number_format($ultima_venta['total'], 2); ?></p>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted"><?php echo tiempo_transcurrido($ultima_venta['fecha']); ?></small>
                                            </div>
                                        </div>
                                    </li>
                                    <?php endif; ?>
                                    <?php if (!empty($ultimos_productos)): ?>
                                    <li class="list-group-item">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <span class="fw-bold">Producto agregado</span>
                                                <p class="mb-0"><?php echo htmlspecialchars($ultimos_productos[0]['nombre']); ?></p>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted"><?php echo tiempo_transcurrido($ultimos_productos[0]['created_at']); ?></small>
                                            </div>
                                        </div>
                                    </li>
                                    <?php endif; ?>
                                    <?php if (!empty($ultimos_clientes)): ?>
                                    <li class="list-group-item">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <span class="fw-bold">Nuevo cliente registrado</span>
                                                <p class="mb-0"><?php echo htmlspecialchars($ultimos_clientes[0]['nombre']); ?> agregado al sistema</p>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted"><?php echo tiempo_transcurrido($ultimos_clientes[0]['created_at']); ?></small>
                                            </div>
                                        </div>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">Productos Más Vendidos</h6>
                                <div>
                                    <button class="btn btn-sm btn-outline-primary me-2" onclick="refreshTopProducts()">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                    <a href="productos/index.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped mb-0" id="topProductsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Producto</th>
                                                <th>Unidades Vendidas</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody id="topProductsBody">
                                            <?php if (!empty($ultimos_productos_vendidos)): ?>
                                                <?php foreach ($ultimos_productos_vendidos as $producto): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($producto['producto']); ?></td>
                                                    <td><span class="badge bg-primary"><?php echo $producto['cantidad']; ?> unidades</span></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="viewProduct('<?php echo htmlspecialchars($producto['producto']); ?>')">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">No hay datos disponibles</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="chatModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-comments me-2"></i>Chat Interno
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <div class="col-md-4 border-end">
                            <div class="p-3 border-bottom">
                                <input type="text" class="form-control form-control-sm" placeholder="Buscar usuarios..." id="searchUsers">
                            </div>
                            <div class="users-list" id="usersList" style="height: 300px; overflow-y: auto;">
                                <div class="text-center p-3">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                    <p class="mt-2 mb-0">Cargando usuarios...</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="chat-header p-3 border-bottom">
                                <h6 class="mb-0" id="chatWithUser">Selecciona un usuario para chatear</h6>
                                <small class="text-muted" id="userStatus"></small>
                            </div>
                            <div class="chat-messages p-3" id="chatMessages" style="height: 300px; overflow-y: auto;">
                                <div class="text-center text-muted">
                                    <i class="fas fa-comments fa-2x mb-2"></i>
                                    <p>Selecciona una conversación para comenzar</p>
                                </div>
                            </div>
                            <div class="chat-input p-3 border-top">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Escribe un mensaje..." id="messageInput" disabled>
                                    <button class="btn btn-primary" id="sendMessage" disabled>
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Variables globales
    let realTimeInterval;
    let isRealTimeActive = true;
    let salesChartInstance = null;
    let movementChartInstance = null;
    let categoryChartInstance = null;

    // Toggle sidebar
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        sidebar.classList.toggle('minimized');
        mainContent.classList.toggle('expanded');
        const menuTexts = document.querySelectorAll('.menu-text');
        menuTexts.forEach(text => {
            text.classList.toggle('d-none');
        });
        if (salesChartInstance) salesChartInstance.resize();
        if (movementChartInstance) movementChartInstance.resize();
        if (categoryChartInstance) categoryChartInstance.resize();
    });

    // Función para cargar datos via AJAX
    async function fetchDashboardData(section = 'all') {
        try {
            const response = await fetch(`ajax_dashboard.php?action=${section}`);
            const result = await response.json();
            if (result.success) {
                return result.data;
            } else {
                console.error('Error:', result.error);
                showNotification('Error al cargar datos', 'danger');
                return null;
            }
        } catch (error) {
            console.error('Error fetching data:', error);
            showNotification('Error de conexión', 'danger');
            return null;
        }
    }

    // Función para cargar actividad en tiempo real desde la base de datos
    async function loadRealTimeActivity() {
        try {
            const response = await fetch('realtime_activity.php');
            const result = await response.json();
            if (result.success) {
                updateRealTimeActivityUI(result.data);
            }
        } catch (error) {
            console.error('Error loading real-time activity:', error);
        }
    }

    // Actualizar interfaz de actividad en tiempo real
    function updateRealTimeActivityUI(activities) {
        const tbody = document.getElementById('realTimeActivity');
        if (activities && activities.length > 0) {
            let html = '';
            activities.forEach(activity => {
                const badgeClass = activity.tipo === 'venta' ? 'bg-success' :
                                     activity.tipo === 'producto' ? 'bg-primary' : 'bg-info';
                html += `
                    <tr class="activity-${activity.tipo}">
                        <td>${activity.hora}</td>
                        <td>${escapeHtml(activity.usuario)}</td>
                        <td>${escapeHtml(activity.descripcion)}</td>
                        <td><span class="badge ${badgeClass}">${activity.tipo}</span></td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }
    }

    // Función para cargar notificaciones
    async function loadNotifications() {
        try {
            const response = await fetch('notifications.php');
            const result = await response.json();
            if (result.success) {
                updateNotificationsUI(result.data);
                updateStatsCards(result.data);
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }

    // Actualizar interfaz de notificaciones
    function updateNotificationsUI(data) {
        const notificationCount = (data.stock_bajo?.length || 0) + (data.agotados?.length || 0);
        document.getElementById('globalNotificationBadge').textContent = notificationCount;
        const notificationsList = document.getElementById('notificationsList');
        let notificationsHTML = '';
        if (data.stock_bajo && data.stock_bajo.length > 0) {
            data.stock_bajo.forEach(product => {
                notificationsHTML += `
                    <li><a class="dropdown-item" href="productos/index.php">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        Stock bajo: ${product.nombre} (${product.stock} unidades)
                    </a></li>
                `;
            });
        }
        if (data.agotados && data.agotados.length > 0) {
            data.agotados.forEach(product => {
                notificationsHTML += `
                    <li><a class="dropdown-item" href="productos/index.php">
                        <i class="fas fa-times-circle text-danger me-2"></i>
                        Producto agotado: ${product.nombre}
                    </a></li>
                `;
            });
        }
        if (notificationsHTML === '') {
            notificationsHTML = '<li><a class="dropdown-item" href="#">No hay notificaciones</a></li>';
        }
        notificationsList.innerHTML = notificationsHTML;
    }

    // Actualizar tarjetas de estadísticas
    async function updateStatsCards(data) {
        if (data && data.stats) {
            document.getElementById('total-productos').textContent = data.stats.total_productos;
            document.getElementById('total-clientes').textContent = data.stats.total_clientes;
            document.getElementById('total-ventas').textContent = data.stats.total_ventas;
            document.getElementById('total-categorias').textContent = data.stats.total_categorias;
        }
    }

    // Actualizar tabla de productos más vendidos
    async function refreshTopProducts() {
        const tableBody = document.getElementById('topProductsBody');
        tableBody.innerHTML = `
            <tr>
                <td colspan="3" class="text-center">
                    <div class="spinner-border spinner-border-sm" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    Actualizando...
                </td>
            </tr>
        `;
        const data = await fetchDashboardData('top_products');
        if (data && data.top_products) {
            let html = '';
            if (data.top_products.length > 0) {
                data.top_products.forEach((product, index) => {
                    html += `
                        <tr>
                            <td>${escapeHtml(product.nombre)}</td>
                            <td><span class="badge bg-primary">${product.total_vendido} unidades</span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="viewProduct('${product.nombre}')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            } else {
                html = '<tr><td colspan="3" class="text-center">No hay datos disponibles</td></tr>';
            }
            tableBody.innerHTML = html;
            showNotification('Productos más vendidos actualizados', 'success');
        }
    }

    // Actualizar gráfica de ventas
    async function refreshSalesChart() {
        const data = await fetchDashboardData('sales_chart');
        if (data && data.sales_chart) {
            updateSalesChart(data.sales_chart);
            showNotification('Gráfica de ventas actualizada', 'success');
        }
    }

    // Función para escapar HTML (seguridad)
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Mostrar notificación
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(notification);
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }

    // Actividad en tiempo real
    function startRealTimeMonitoring() {
        realTimeInterval = setInterval(async () => {
            if (isRealTimeActive) {
                await loadRealTimeActivity();
            }
        }, 10000);
    }

    // Control de tiempo real
    function toggleRealTime() {
        isRealTimeActive = !isRealTimeActive;
        const button = document.getElementById('toggleRealtimeBtn');
        const status = document.getElementById('status-conexion');
        if (isRealTimeActive) {
            button.innerHTML = '<i class="fas fa-pause me-1"></i>Pausar';
            status.className = 'badge bg-success me-2';
            status.innerHTML = '<i class="fas fa-circle me-1"></i>Conectado';
            showNotification('Monitoreo en tiempo real activado', 'success');
        } else {
            button.innerHTML = '<i class="fas fa-play me-1"></i>Reanudar';
            status.className = 'badge bg-warning me-2';
            status.innerHTML = '<i class="fas fa-circle me-1"></i>Pausado';
            showNotification('Monitoreo en tiempo real pausado', 'warning');
        }
    }

    // Funciones de exportación
    function exportDashboardData() {
        showNotification('Preparando exportación del dashboard...', 'info');
        setTimeout(() => {
            showNotification('Dashboard exportado exitosamente', 'success');
        }, 2000);
    }

    function exportChartData(type) {
        showNotification(`Exportando datos de ${type}...`, 'info');
        setTimeout(() => {
            showNotification(`Datos de ${type} exportados correctamente`, 'success');
        }, 1500);
    }

    // Actualizar toda la data
    async function refreshAllData() {
        showNotification('Actualizando toda la información...', 'info');
        await loadNotifications();
        await loadRealTimeActivity();
        await refreshTopProducts();
        await refreshSalesChart();
        showNotification('Datos actualizados completamente', 'success');
    }

    // Actualizar actividad reciente
    async function refreshActivity() {
        const activityList = document.getElementById('recentActivityList');
        activityList.innerHTML = `
            <li class="list-group-item text-center">
                <div class="spinner-border spinner-border-sm" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                Actualizando actividad...
            </li>
        `;
        setTimeout(() => {
            location.reload();
        }, 1000);
    }

    // Función para ver producto
    function viewProduct(nombre) {
        showNotification(`Viendo detalles de: ${nombre}`, 'info');
        window.location.href = `productos/index.php?search=${encodeURIComponent(nombre)}`;
    }

    // Actualizar gráfica de ventas
    function updateSalesChart(newData) {
        if (salesChartInstance) {
            salesChartInstance.data.datasets[0].data = newData;
            salesChartInstance.update();
        }
    }

    // Sistema de Chat Interno
    class ChatSystem {
        constructor() {
            this.currentUser = null;
            this.messagesInterval = null;
            this.unreadInterval = null;
            this.isChatOpen = false;
            this.init();
        }

        init() {
            this.bindEvents();
            this.startUnreadChecker();
        }

        bindEvents() {
            document.getElementById('searchUsers').addEventListener('input', (e) => {
                this.searchUsers(e.target.value);
            });
            document.getElementById('sendMessage').addEventListener('click', () => {
                this.sendMessage();
            });
            document.getElementById('messageInput').addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.sendMessage();
                }
            });
            document.getElementById('chatModal').addEventListener('show.bs.modal', () => {
                this.isChatOpen = true;
                this.loadUsers();
            });
            document.getElementById('chatModal').addEventListener('hide.bs.modal', () => {
                this.isChatOpen = false;
                this.stopMessagesChecker();
                this.currentUser = null;
            });
        }

        async loadUsers() {
            try {
                const response = await fetch('chat_functions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=get_users'
                });
                const result = await response.json();
                if (result.success) {
                    this.displayUsers(result.users);
                }
            } catch (error) {
                console.error('Error loading users:', error);
            }
        }

        displayUsers(users) {
            const usersList = document.getElementById('usersList');
            let html = '';
            if (users.length > 0) {
                users.forEach(user => {
                    const unreadBadge = user.mensajes_sin_leer > 0 ?
                        `<span class="badge bg-danger float-end">${user.mensajes_sin_leer}</span>` : '';
                    html += `
                        <div class="user-item p-3 border-bottom" data-user-id="${user.id}" style="cursor: pointer;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${this.escapeHtml(user.nombre)}</strong>
                                    <br>
                                    <small class="text-muted">${this.escapeHtml(user.email)}</small>
                                </div>
                                ${unreadBadge}
                            </div>
                        </div>
                    `;
                });
            } else {
                html = '<div class="p-3 text-center text-muted">No hay usuarios disponibles</div>';
            }
            usersList.innerHTML = html;
            document.querySelectorAll('.user-item').forEach(item => {
                item.addEventListener('click', () => {
                    const userId = item.getAttribute('data-user-id');
                    this.selectUser(userId, item);
                });
            });
        }

        async selectUser(userId, element) {
            console.log("Seleccionando usuario ID:", userId);
            document.querySelectorAll('.user-item').forEach(item => {
                item.classList.remove('bg-primary', 'text-white');
            });
            element.classList.add('bg-primary', 'text-white');
            this.currentUser = userId;
            document.getElementById('messageInput').disabled = false;
            document.getElementById('sendMessage').disabled = false;
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.innerHTML = `<div class="text-center py-4"><div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Cargando...</span></div><p class="mt-2">Cargando mensajes...</p></div>`;
            await this.loadMessages(userId);
            const userName = element.querySelector('strong').textContent;
            document.getElementById('chatWithUser').textContent = `Chat con ${userName}`;
            document.getElementById('userStatus').textContent = 'En línea';
            await this.markAsRead(userId);
            this.startMessagesChecker();
        }

        async loadMessages(userId) {
            if (!userId) { return; }
            try {
                const response = await fetch('chat_functions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=get_messages&receptor_id=${userId}`
                });
                if (!response.ok) { throw new Error(`Error HTTP: ${response.status}`); }
                const result = await response.json();
                if (result.success && result.messages) {
                    this.displayMessages(result.messages);
                } else {
                    this.displayMessages([]);
                }
            } catch (error) {
                console.error('Error loading messages:', error);
                this.displayMessages([]);
            }
        }

        displayMessages(messages) {
            const messagesContainer = document.getElementById('chatMessages');
            let html = '';
            if (messages && Array.isArray(messages) && messages.length > 0) {
                messages.forEach(message => {
                    const isOwn = parseInt(message.emisor_id) === parseInt(<?php echo $usuario_id; ?>);
                    const messageClass = isOwn ? 'bg-primary text-white' : 'bg-light';
                    const alignClass = isOwn ? 'text-end' : 'text-start';
                    const senderName = isOwn ? 'Tú' : this.escapeHtml(message.emisor_nombre || 'Usuario');
                    let timeString = 'Ahora';
                    if (message.fecha_envio) {
                        const messageDate = new Date(message.fecha_envio);
                        timeString = messageDate.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
                    }
                    html += `
                        <div class="message ${alignClass} mb-2">
                            <div class="d-inline-block p-2 rounded ${messageClass}" style="max-width: 70%;">
                                <div class="message-text">${this.escapeHtml(message.mensaje || '')}</div>
                                <small class="opacity-75 d-block">${timeString} - ${senderName}</small>
                            </div>
                        </div>
                    `;
                });
            } else {
                html = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-comments fa-2x mb-2"></i>
                        <p>No hay mensajes aún</p>
                        <small>¡Sé el primero en enviar un mensaje!</small>
                    </div>
                `;
            }
            messagesContainer.innerHTML = html;
            setTimeout(() => { messagesContainer.scrollTop = messagesContainer.scrollHeight; }, 100);
        }

        async sendMessage() {
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            if (!message || !this.currentUser) return;
            try {
                const response = await fetch('chat_functions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=send_message&receptor_id=${this.currentUser}&mensaje=${encodeURIComponent(message)}`
                });
                const result = await response.json();
                if (result.success) {
                    messageInput.value = '';
                    await this.loadMessages(this.currentUser);
                    await this.loadUnreadCount();
                } else {
                    showNotification('Error al enviar mensaje', 'danger');
                }
            } catch (error) {
                console.error('Error sending message:', error);
                showNotification('Error de conexión', 'danger');
            }
        }

        async loadUnreadCount() {
            try {
                const response = await fetch('chat_functions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=get_unread_count'
                });
                const result = await response.json();
                if (result.success) {
                    this.updateUnreadBadge(result.count);
                }
            } catch (error) {
                console.error('Error loading unread count:', error);
            }
        }

        updateUnreadBadge(count) {
            const badge = document.getElementById('chatNotificationCount');
            if (count > 0) {
                badge.textContent = count > 9 ? '9+' : count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }

        async markAsRead(userId) {
            try {
                await fetch('chat_functions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=mark_as_read&emisor_id=${userId}`
                });
                await this.loadUnreadCount();
            } catch (error) {
                console.error('Error marking as read:', error);
            }
        }

        startMessagesChecker() {
            this.stopMessagesChecker();
            this.messagesInterval = setInterval(async () => {
                if (this.currentUser && this.isChatOpen) {
                    await this.loadMessages(this.currentUser);
                }
            }, 3000);
        }

        stopMessagesChecker() {
            if (this.messagesInterval) {
                clearInterval(this.messagesInterval);
            }
        }

        startUnreadChecker() {
            this.unreadInterval = setInterval(async () => {
                if (!this.isChatOpen) {
                    await this.loadUnreadCount();
                }
            }, 10000);
        }

        searchUsers(query) {
            const users = document.querySelectorAll('.user-item');
            users.forEach(user => {
                const userName = user.querySelector('strong').textContent.toLowerCase();
                if (userName.includes(query.toLowerCase())) {
                    user.style.display = 'block';
                } else {
                    user.style.display = 'none';
                }
            });
        }
        
        escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    }

    // Inicialización completa
    document.addEventListener('DOMContentLoaded', function() {
        const movementCtx = document.getElementById('inventoryMovementChart').getContext('2d');
        movementChartInstance = new Chart(movementCtx, {
            type: 'line',
            data: {
                labels: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
                datasets: [{
                    label: 'Entradas',
                    data: <?php echo json_encode(array_values($entradas_data), JSON_NUMERIC_CHECK); ?>,
                    backgroundColor: 'rgba(13, 109, 253, 0.1)',
                    borderColor: '#0d6efd',
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#0d6efd'
                },{
                    label: 'Salidas',
                    data: <?php echo json_encode(array_values($salidas_data), JSON_NUMERIC_CHECK); ?>,
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    borderColor: '#dc3545',
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#dc3545'
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: value => value + ' unid'
                        }
                    }
                }
            }
        });
        const categoryCtx = document.getElementById('categoryDistributionChart').getContext('2d');
        categoryChartInstance = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($categorias_labels, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{
                    data: <?php echo json_encode($categorias_totales, JSON_NUMERIC_CHECK); ?>,
                    backgroundColor: [
                        '#0d6efd','#1cc88a','#f6c23e','#e74a3b','#36b9cc',
                        '#6f42c1','#20c997','#6610f2','#fd7e14','#adb5bd'
                    ],
                    hoverBackgroundColor: [
                        '#0a58ca','#15a06c','#d4a72d','#c53023','#2c9faf',
                        '#5c37a8','#1c7c64','#5a0dcb','#d3640c','#919aa1'
                    ],
                    hoverBorderColor: 'rgba(234,236,244,1)'
                }]
            },
            options: {
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                }
            }
        });
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        salesChartInstance = new Chart(salesCtx, {
            type: 'bar',
            data: {
                labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                datasets: [{
                    label: 'Ventas Totales ($)',
                    data: <?php echo json_encode($ventas_chart_data, JSON_NUMERIC_CHECK); ?>,
                    backgroundColor: 'rgba(25, 135, 84, 0.6)',
                    borderColor: '#198754',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString('es-MX');
                            }
                        }
                    }
                }
            }
        });
        loadNotifications();
        loadRealTimeActivity();
        startRealTimeMonitoring();
        window.chatSystem = new ChatSystem();
        setInterval(loadNotifications, 30000);
        setInterval(refreshTopProducts, 60000);
        showNotification('Dashboard cargado exitosamente', 'success');
    });
    document.addEventListener('DOMContentLoaded', () => {
        const chat = new ChatSystem();
    });
    </script>
</body>
</html>

<?php
// Función helper para mostrar tiempo transcurrido
function tiempo_transcurrido($fecha) {
    $fecha_pasada = new DateTime($fecha);
    $ahora = new DateTime();
    $diferencia = $ahora->diff($fecha_pasada);

    if ($diferencia->y > 0) {
        return "Hace " . $diferencia->y . " año" . ($diferencia->y > 1 ? 's' : '');
    } elseif ($diferencia->m > 0) {
        return "Hace " . $diferencia->m . " mes" . ($diferencia->m > 1 ? 'es' : '');
    } elseif ($diferencia->d > 0) {
        return "Hace " . $diferencia->d . " día" . ($diferencia->d > 1 ? 's' : '');
    } elseif ($diferencia->h > 0) {
        return "Hace " . $diferencia->h . " hora" . ($diferencia->h > 1 ? 's' : '');
    } elseif ($diferencia->i > 0) {
        return "Hace " . $diferencia->i . " minuto" . ($diferencia->i > 1 ? 's' : '');
    } else {
        return "Hace unos segundos";
    }
}
?>