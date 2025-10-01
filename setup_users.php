<?php
// setup_users.php
session_start();
require_once 'config/db.php';

// Solo permitir acceso si es administrador o en desarrollo
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_id'] != 1) {
    die("Acceso denegado");
}

try {
    // Verificar si la tabla usuarios existe y tiene datos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['total'] == 0) {
        // Insertar usuarios de ejemplo
        $usuarios = [
            ['nombre' => 'Admin Sistema', 'email' => 'admin@empresa.com', 'password' => password_hash('admin123', PASSWORD_DEFAULT), 'activo' => 1],
            ['nombre' => 'Ana García', 'email' => 'ana@empresa.com', 'password' => password_hash('ana123', PASSWORD_DEFAULT), 'activo' => 1],
            ['nombre' => 'Carlos López', 'email' => 'carlos@empresa.com', 'password' => password_hash('carlos123', PASSWORD_DEFAULT), 'activo' => 1],
            ['nombre' => 'María Rodríguez', 'email' => 'maria@empresa.com', 'password' => password_hash('maria123', PASSWORD_DEFAULT), 'activo' => 1],
            ['nombre' => 'Pedro Martínez', 'email' => 'pedro@empresa.com', 'password' => password_hash('pedro123', PASSWORD_DEFAULT), 'activo' => 1]
        ];
        
        $sql = "INSERT INTO usuarios (nombre, email, password, activo, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        
        foreach ($usuarios as $usuario) {
            $stmt->execute([$usuario['nombre'], $usuario['email'], $usuario['password'], $usuario['activo']]);
        }
        
        echo "Usuarios insertados correctamente: " . count($usuarios) . " usuarios creados.";
    } else {
        echo "Ya existen usuarios en la base de datos. Total: " . $result['total'];
    }
    
    // Mostrar lista de usuarios
    echo "<h3>Usuarios en el sistema:</h3>";
    $stmt = $pdo->query("SELECT id, nombre, email, activo FROM usuarios ORDER BY id");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Email</th><th>Activo</th></tr>";
    foreach ($usuarios as $usuario) {
        echo "<tr>";
        echo "<td>{$usuario['id']}</td>";
        echo "<td>{$usuario['nombre']}</td>";
        echo "<td>{$usuario['email']}</td>";
        echo "<td>" . ($usuario['activo'] ? 'Sí' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>