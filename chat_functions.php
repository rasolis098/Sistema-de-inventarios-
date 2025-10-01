<?php
session_start();
require_once 'config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$action = $_POST['action'] ?? '';

// Para debugging - quitar en producción
error_log("Chat Action: " . $action . ", User ID: " . $usuario_id);

try {
    switch ($action) {
        case 'get_users':
            // Obtener todos los usuarios excepto el actual
            $stmt = $pdo->prepare("SELECT id, nombre, email as email FROM usuarios WHERE id != ? ORDER BY nombre");
            $stmt->execute([$usuario_id]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Agregar conteo de mensajes no leídos
            foreach ($users as &$user) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as mensajes_sin_leer FROM mensajes WHERE emisor_id = ? AND receptor_id = ? AND leido = 0");
                $stmt->execute([$user['id'], $usuario_id]);
                $unread = $stmt->fetch(PDO::FETCH_ASSOC);
                $user['mensajes_sin_leer'] = $unread['mensajes_sin_leer'] ?? 0;
            }
            
            echo json_encode(['success' => true, 'users' => $users]);
            break;
            
        case 'get_messages':
            $user_id = $_POST['receptor_id'] ?? 0;
            
            error_log("Getting messages for user: " . $user_id . " from user: " . $usuario_id);
            
            if (!$user_id) {
                echo json_encode(['success' => false, 'error' => 'ID de usuario no válido']);
                break;
            }
            
            // CORRECCIÓN: Obtener mensajes entre los dos usuarios
            $stmt = $pdo->prepare("
                SELECT m.*, 
                       u.nombre as emisor_nombre,
                       CASE 
                         WHEN m.emisor_id = ? THEN 'own'
                         ELSE 'other'
                       END as message_type
                FROM mensajes m 
                INNER JOIN usuarios u ON m.emisor_id = u.id 
                WHERE (m.emisor_id = ? AND m.receptor_id = ?) 
                   OR (m.emisor_id = ? AND m.receptor_id = ?) 
                ORDER BY m.fecha_envio ASC
            ");
            $stmt->execute([$usuario_id, $usuario_id, $user_id, $user_id, $usuario_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Messages found: " . count($messages));
            
            echo json_encode([
                'success' => true, 
                'messages' => $messages,
                'debug' => [
                    'user_id' => $user_id,
                    'usuario_id' => $usuario_id,
                    'message_count' => count($messages)
                ]
            ]);
            break;
            
        case 'send_message':
            $receptor_id = $_POST['receptor_id'] ?? 0;
            $mensaje = trim($_POST['mensaje'] ?? '');
            
            error_log("Sending message to: " . $receptor_id . ", message: " . $mensaje);
            
            if (empty($mensaje)) {
                echo json_encode(['success' => false, 'error' => 'Mensaje vacío']);
                break;
            }
            
            if (!$receptor_id) {
                echo json_encode(['success' => false, 'error' => 'Receptor no válido']);
                break;
            }
            
            $stmt = $pdo->prepare("INSERT INTO mensajes (emisor_id, receptor_id, mensaje, fecha_envio) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$usuario_id, $receptor_id, $mensaje]);
            
            echo json_encode(['success' => true, 'message_id' => $pdo->lastInsertId()]);
            break;
            
        case 'mark_as_read':
            $emisor_id = $_POST['emisor_id'] ?? 0;
            
            $stmt = $pdo->prepare("UPDATE mensajes SET leido = 1 WHERE emisor_id = ? AND receptor_id = ? AND leido = 0");
            $stmt->execute([$emisor_id, $usuario_id]);
            
            echo json_encode(['success' => true, 'marked' => $stmt->rowCount()]);
            break;
            
        case 'get_unread_count':
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM mensajes WHERE receptor_id = ? AND leido = 0");
            $stmt->execute([$usuario_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'count' => $result['count'] ?? 0]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida: ' . $action]);
    }
} catch (PDOException $e) {
    error_log("Database error in chat_functions: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}

// Función para debug
function debug_data($data) {
    error_log(print_r($data, true));
}
?>