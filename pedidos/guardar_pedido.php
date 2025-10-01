<?php
// 1. INICIAR SESIÓN OBLIGATORIAMENTE AL PRINCIPIO
session_start();

// Activar reporte de errores para depuración
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. VERIFICAR QUE EL USUARIO ESTÉ LOGUEADO
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403); // Código de error 'Prohibido'
    die("Acceso no permitido. Debes iniciar sesión.");
}

// Cargar dependencias
require_once '../config/db.php';
require_once 'funciones.php'; // Asumimos que aquí está `enviarCorreoPedidoPendiente`

// Validar que el método sea POST y que los datos esenciales existan
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['cliente_id']) || empty($_POST['productos'])) {
    die('Error: Faltan datos obligatorios para procesar el pedido.');
}

// --- RECOLECCIÓN Y VALIDACIÓN DE DATOS ---
$cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
$fecha_entrega = $_POST['fecha_entrega'] ?? date('Y-m-d');
$observaciones = filter_input(INPUT_POST, 'observaciones', FILTER_SANITIZE_SPECIAL_CHARS);
$productos_recibidos = $_POST['productos'];

// 3. OBTENER EL ID DEL USUARIO DESDE LA SESIÓN
$usuario_id = $_SESSION['usuario_id'];

// --- LÓGICA DE NEGOCIO Y SEGURIDAD ---
try {
    // Iniciar transacción para asegurar la integridad de los datos
    $pdo->beginTransaction();

    // 4. RECALCULAR TOTALES EN EL SERVIDOR (PASO DE SEGURIDAD CRÍTICO)
    $total_recalculado = 0;
    $detalles_validados = [];
    
    // Preparar una consulta para obtener el precio real desde la BD
    $stmt_producto = $pdo->prepare("SELECT precio_venta FROM productos WHERE id = ?");

    foreach ($productos_recibidos as $producto) {
        $producto_id = filter_var($producto['id'], FILTER_VALIDATE_INT);
        $cantidad = filter_var($producto['cantidad'], FILTER_VALIDATE_FLOAT);

        if (!$producto_id || $cantidad <= 0) continue;

        // Obtener el precio real desde la base de datos, ignorando el enviado por el cliente
        $stmt_producto->execute([$producto_id]);
        $precio_unitario_real = $stmt_producto->fetchColumn();

        if ($precio_unitario_real === false) {
            throw new Exception("El producto con ID {$producto_id} no fue encontrado en la base de datos.");
        }
        
        // Usar los porcentajes de IVA y descuento del formulario (esto es aceptable)
        $iva_porcentaje = filter_var($producto['iva'], FILTER_VALIDATE_FLOAT, ['options' => ['default' => 0]]);
        $descuento_porcentaje = filter_var($producto['descuento'], FILTER_VALIDATE_FLOAT, ['options' => ['default' => 0]]);
        
        // Calcular el importe de la línea en el servidor
        $subtotal_linea = $precio_unitario_real * $cantidad;
        $monto_iva = $subtotal_linea * ($iva_porcentaje / 100);
        $monto_descuento = $subtotal_linea * ($descuento_porcentaje / 100);
        $importe_recalculado = $subtotal_linea + $monto_iva - $monto_descuento;

        $total_recalculado += $importe_recalculado;

        $detalles_validados[] = [
            'producto_id' => $producto_id,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio_unitario_real,
            'iva' => $iva_porcentaje, 
            'descuento' => $descuento_porcentaje,
            'importe' => $importe_recalculado
        ];
    }

    if (empty($detalles_validados)) {
        throw new Exception('No se han especificado productos válidos.');
    }

    // 5. INSERTAR EL PEDIDO PRINCIPAL (INCLUYENDO `usuario_id`)
    $stmt_pedido = $pdo->prepare(
        "INSERT INTO pedidos (cliente_id, usuario_id, fecha, fecha_entrega, total, observaciones) 
         VALUES (?, ?, CURDATE(), ?, ?, ?)"
    );
    // Se pasa el $usuario_id de la sesión y el $total_recalculado del servidor
    $stmt_pedido->execute([$cliente_id, $usuario_id, $fecha_entrega, $total_recalculado, $observaciones]);
    $pedido_id = $pdo->lastInsertId();

    // 6. INSERTAR LOS DETALLES DEL PEDIDO
    $stmt_detalle = $pdo->prepare(
        "INSERT INTO pedido_detalles (pedido_id, producto_id, cantidad, precio_unitario, iva, descuento, importe) 
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($detalles_validados as $detalle) {
        $stmt_detalle->execute([
            $pedido_id,
            $detalle['producto_id'],
            $detalle['cantidad'],
            $detalle['precio_unitario'],
            $detalle['iva'],
            $detalle['descuento'],
            $detalle['importe']
        ]);
    }

    // Si todo fue correcto, se confirman los cambios
    $pdo->commit();

    // Envío de correo (se llama solo una vez)
    enviarCorreoPedidoPendiente($pdo, $pedido_id, $cliente_id, $fecha_entrega, $total_recalculado);

    // Redirigir a la página de visualización del pedido
    header("Location: ver_pedido.php?id=" . $pedido_id);
    exit;

} catch (Exception $e) {
    // Si ocurre un error, se revierten todos los cambios
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Registrar el error para el administrador y mostrar un mensaje genérico al usuario
    error_log("Error al guardar el pedido: " . $e->getMessage());
    die("Ocurrió un error al procesar tu pedido. Por favor, intenta de nuevo más tarde.");
}
?>