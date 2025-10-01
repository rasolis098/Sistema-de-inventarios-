<?php
// buscar.php - Colocar este archivo en la raíz del sitio (inv.dialexander.com/buscar.php)

// Habilitar reporte de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Establecer cabeceras para respuesta JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar solicitudes OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Incluir la configuración de la base de datos
$configPath = __DIR__ . '/config/db.php';
if (!file_exists($configPath)) {
    echo json_encode(['success' => false, 'message' => 'Archivo de configuración no encontrado: ' . $configPath]);
    exit;
}

include $configPath;

// Verificar si la conexión a la base de datos se estableció correctamente
if (!isset($pdo)) {
    echo json_encode(['success' => false, 'message' => 'Error: Conexión a la base de datos no inicializada']);
    exit;
}

// Función para obtener el contenido de la solicitud
function getRequestData() {
    $input = [];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Para application/json
        if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            $json = file_get_contents('php://input');
            $input = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['error' => 'JSON inválido: ' . json_last_error_msg()];
            }
        } 
        // Para form-data/x-www-form-urlencoded tradicional
        else {
            $input = $_POST;
        }
    } 
    // Para solicitudes GET (útil para pruebas)
    else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $input = $_GET;
    }
    
    return $input;
}

// Obtener datos de la solicitud
$input = getRequestData();

// Verificar si se recibió una consulta
if (!isset($input['query']) || empty(trim($input['query']))) {
    echo json_encode(['success' => false, 'message' => 'Consulta vacía']);
    exit;
}

$query = trim($input['query']);
$results = [];

try {
    // Buscar en productos
    $stmt = $pdo->prepare("
        SELECT id, codigo, nombre, descripcion, 
               COALESCE((
                   SELECT SUM(stock) 
                   FROM stock_almacenes 
                   WHERE producto_id = productos.id
               ), 0) as stock_total
        FROM productos 
        WHERE (codigo LIKE :query OR nombre LIKE :query OR descripcion LIKE :query) 
          AND activo = 1
        LIMIT 5
    ");
    $stmt->execute(['query' => "%$query%"]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($productos as $producto) {
        $results[] = [
            'type' => 'product',
            'title' => $producto['nombre'],
            'description' => "Código: {$producto['codigo']} | Stock: {$producto['stock_total']}",
            'url' => "https://inv.dialexander.com/productos/editar.php?id={$producto['id']}",
            'icon' => 'fas fa-box'
        ];
    }
    
    // Buscar en clientes
    $stmt = $pdo->prepare("
        SELECT id, nombre, telefono, email 
        FROM clientes 
        WHERE nombre LIKE :query OR telefono LIKE :query OR email LIKE :query
        LIMIT 5
    ");
    $stmt->execute(['query' => "%$query%"]);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($clientes as $cliente) {
        $results[] = [
            'type' => 'client',
            'title' => $cliente['nombre'],
            'description' => "Tel: {$cliente['telefono']} | Email: {$cliente['email']}",
            'url' => "https://inv.dialexander.com/clientes/editar.php?id={$cliente['id']}",
            'icon' => 'fas fa-user'
        ];
    }
    
    // Buscar en cotizaciones
    $stmt = $pdo->prepare("
        SELECT c.id, c.codigo, c.fecha, cl.nombre as cliente, c.total
        FROM cotizaciones c
        LEFT JOIN clientes cl ON c.cliente_id = cl.id
        WHERE c.codigo LIKE :query OR cl.nombre LIKE :query
        ORDER BY c.fecha DESC
        LIMIT 5
    ");
    $stmt->execute(['query' => "%$query%"]);
    $cotizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($cotizaciones as $cotizacion) {
        $fecha = date('d/m/Y', strtotime($cotizacion['fecha']));
        $results[] = [
            'type' => 'quote',
            'title' => "Cotización #{$cotizacion['codigo']}",
            'description' => "Cliente: {$cotizacion['cliente']} | Total: $" . number_format($cotizacion['total'], 2),
            'url' => "https://inv.dialexander.com/cotizaciones/ver.php?id={$cotizacion['id']}",
            'icon' => 'fas fa-file-invoice-dollar'
        ];
    }
    
    // Buscar en stock por almacén
    $stmt = $pdo->prepare("
        SELECT p.id, p.nombre, p.codigo, a.nombre as almacen, sa.stock
        FROM stock_almacenes sa
        INNER JOIN productos p ON sa.producto_id = p.id
        INNER JOIN almacenes a ON sa.almacen_id = a.id
        WHERE p.nombre LIKE :query OR p.codigo LIKE :query OR a.nombre LIKE :query
        ORDER BY sa.stock DESC
        LIMIT 5
    ");
    $stmt->execute(['query' => "%$query%"]);
    $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($stocks as $stock) {
        $results[] = [
            'type' => 'stock',
            'title' => $stock['nombre'],
            'description' => "Almacén: {$stock['almacen']} | Stock: {$stock['stock']}",
            'url' => "https://inv.dialexander.com/productos/editar.php?id={$stock['id']}",
            'icon' => 'fas fa-warehouse'
        ];
    }
    
    // Devolver resultados
    echo json_encode(['success' => true, 'results' => $results]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error general: ' . $e->getMessage()]);
}