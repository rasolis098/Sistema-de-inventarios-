<?php
// check_my_users.php
session_start();
require_once 'config/db.php';

echo "<h3>Diagnóstico del Sistema - Tu Estructura</h3>";

try {
    // Verificar conexión
    echo "<p><strong>Conexión a BD:</strong> OK</p>";
    
    // Verificar tabla de usuarios
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'usuarios'")->rowCount();
    echo "<p><strong>Tabla 'usuarios' existe:</strong> " . ($tableCheck ? 'Sí' : 'No') . "</p>";
    
    if ($tableCheck) {
        // Verificar estructura de la tabla
        $structure = $pdo->query("DESCRIBE usuarios")->fetchAll(PDO::FETCH_ASSOC);
        echo "<p><strong>Campos en tabla usuarios:</strong></p>";
        echo "<ul>";
        foreach ($structure as $field) {
            echo "<li>{$field['Field']} ({$field['Type']})</li>";
        }
        echo "</ul>";
        
        // Contar usuarios
        $userCount = $pdo->query("SELECT COUNT(*) as total FROM usuarios")->fetch(PDO::FETCH_ASSOC);
        echo "<p><strong>Total de usuarios:</strong> {$userCount['total']}</p>";
        
        // Mostrar usuarios activos
        $users = $pdo->query("SELECT id, nombre, correo_usuario, activo FROM usuarios WHERE activo = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        echo "<p><strong>Usuarios activos (para chat):</strong> " . count($users) . "</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Activo</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['nombre']}</td>";
            echo "<td>{$user['correo_usuario']}</td>";
            echo "<td>" . ($user['activo'] ? 'Sí' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Mostrar usuario actual de sesión
        if (isset($_SESSION['usuario_id'])) {
            $currentUser = $pdo->prepare("SELECT id, nombre, correo_usuario FROM usuarios WHERE id = ?");
            $currentUser->execute([$_SESSION['usuario_id']]);
            $user = $currentUser->fetch(PDO::FETCH_ASSOC);
            echo "<p><strong>Usuario en sesión:</strong> {$user['nombre']} ({$user['correo_usuario']})</p>";
        }
    }
    
    // Verificar tabla de mensajes
    $messagesTable = $pdo->query("SHOW TABLES LIKE 'mensajes'")->rowCount();
    echo "<p><strong>Tabla 'mensajes' existe:</strong> " . ($messagesTable ? 'Sí' : 'No') . "</p>";
    
    if ($messagesTable) {
        $messageCount = $pdo->query("SELECT COUNT(*) as total FROM mensajes")->fetch(PDO::FETCH_ASSOC);
        echo "<p><strong>Total de mensajes:</strong> {$messageCount['total']}</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h4>Probar consulta de chat:</h4>";

try {
    if (isset($_SESSION['usuario_id'])) {
        $sql = "SELECT id, nombre, correo_usuario as email, activo
                FROM usuarios 
                WHERE id != ? AND activo = 1 
                ORDER BY nombre";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['usuario_id']]);
        $chatUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Usuarios disponibles para chat:</strong> " . count($chatUsers) . "</p>";
        if (count($chatUsers) > 0) {
            echo "<ul>";
            foreach ($chatUsers as $user) {
                echo "<li>{$user['nombre']} ({$user['email']})</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: orange;'>No hay otros usuarios activos para chatear</p>";
        }
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error en consulta de chat: " . $e->getMessage() . "</p>";
}
?>