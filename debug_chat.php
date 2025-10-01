<?php
// debug_chat.php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    die("Necesitas iniciar sesión");
}

echo "<h3>Debug del Sistema de Chat</h3>";

try {
    $usuario_id = $_SESSION['usuario_id'];
    
    // 1. Verificar tabla de mensajes
    echo "<h4>1. Verificación de Tabla de Mensajes</h4>";
    $tableExists = $pdo->query("SHOW TABLES LIKE 'mensajes'")->rowCount() > 0;
    echo "Tabla 'mensajes' existe: " . ($tableExists ? "SÍ" : "NO") . "<br>";
    
    if ($tableExists) {
        // Mostrar estructura de la tabla
        $structure = $pdo->query("DESCRIBE mensajes")->fetchAll(PDO::FETCH_ASSOC);
        echo "Estructura de la tabla:<br>";
        echo "<table border='1'><tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Key</th></tr>";
        foreach ($structure as $field) {
            echo "<tr><td>{$field['Field']}</td><td>{$field['Type']}</td><td>{$field['Null']}</td><td>{$field['Key']}</td></tr>";
        }
        echo "</table>";
        
        // Contar mensajes
        $messageCount = $pdo->query("SELECT COUNT(*) as total FROM mensajes")->fetch(PDO::FETCH_ASSOC);
        echo "Total de mensajes en la base de datos: " . $messageCount['total'] . "<br>";
    }
    
    // 2. Verificar usuarios disponibles para chat
    echo "<h4>2. Usuarios Disponibles para Chat</h4>";
    $sql = "SELECT id, nombre, correo_usuario as email, activo 
            FROM usuarios 
            WHERE id != ? AND activo = 1 
            ORDER BY nombre";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Usuarios encontrados: " . count($users) . "<br>";
    foreach ($users as $user) {
        echo " - {$user['nombre']} (ID: {$user['id']})<br>";
    }
    
    // 3. Probar consulta de mensajes con el primer usuario
    echo "<h4>3. Prueba de Consulta de Mensajes</h4>";
    if (count($users) > 0) {
        $receptor_id = $users[0]['id'];
        
        // Probar la consulta exacta que usa el chat
        $sql = "SELECT m.*, u.nombre as emisor_nombre 
                FROM mensajes m 
                INNER JOIN usuarios u ON m.emisor_id = u.id 
                WHERE (m.emisor_id = ? AND m.receptor_id = ?) 
                   OR (m.emisor_id = ? AND m.receptor_id = ?) 
                ORDER BY m.fecha_envio ASC 
                LIMIT 50";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id, $receptor_id, $receptor_id, $usuario_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Mensajes encontrados con usuario ID {$receptor_id}: " . count($messages) . "<br>";
        
        if (count($messages) > 0) {
            echo "Ejemplo de mensajes:<br>";
            echo "<table border='1'><tr><th>ID</th><th>Emisor</th><th>Receptor</th><th>Mensaje</th><th>Fecha</th><th>Leído</th></tr>";
            foreach (array_slice($messages, 0, 5) as $msg) { // Mostrar solo 5
                echo "<tr>";
                echo "<td>{$msg['id']}</td>";
                echo "<td>{$msg['emisor_nombre']} ({$msg['emisor_id']})</td>";
                echo "<td>{$msg['receptor_id']}</td>";
                echo "<td>" . substr($msg['mensaje'], 0, 50) . "...</td>";
                echo "<td>{$msg['fecha_envio']}</td>";
                echo "<td>{$msg['leido']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "No hay mensajes. Probando inserción...<br>";
            
            // Insertar un mensaje de prueba
            $testMessage = "Este es un mensaje de prueba - " . date('Y-m-d H:i:s');
            $insertSql = "INSERT INTO mensajes (emisor_id, receptor_id, mensaje, fecha_envio) VALUES (?, ?, ?, NOW())";
            $insertStmt = $pdo->prepare($insertSql);
            $insertResult = $insertStmt->execute([$usuario_id, $receptor_id, $testMessage]);
            
            echo "Inserción de mensaje de prueba: " . ($insertResult ? "ÉXITO" : "FALLÓ") . "<br>";
            
            // Verificar si se insertó
            if ($insertResult) {
                $newMessages = $stmt->execute([$usuario_id, $receptor_id, $receptor_id, $usuario_id]);
                $messagesAfter = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "Mensajes después de inserción: " . count($messagesAfter) . "<br>";
            }
        }
    }
    
    // 4. Verificar respuesta JSON del endpoint
    echo "<h4>4. Prueba del Endpoint Chat</h4>";
    echo "<button onclick=\"testChatEndpoint()\">Probar Endpoint Chat</button>";
    echo "<div id='endpointResult'></div>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<script>
function testChatEndpoint() {
    fetch('chat_functions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_users'
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('endpointResult').innerHTML = 
            '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
    })
    .catch(error => {
        document.getElementById('endpointResult').innerHTML = 'Error: ' + error;
    });
}
</script>