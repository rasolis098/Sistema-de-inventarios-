<?php
// /config/inicializar_db.php

// Muestra todos los errores de PHP (solo para depuración)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Incluir el archivo de conexión que define las variables ($host, $dbname...)
require_once __DIR__ . '/db.php';

echo "<h1>Inicializador de Base de Datos</h1>";

try {
    // 2. Usar las VARIABLES ($host, $dbname, etc.) de tu archivo db.php
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green;'>✅ Conexión a la base de datos '" . $dbname . "' exitosa.</p>";

    // 3. Definir el esquema de la base de datos en un array
    $schema = [
        'Productos' => "CREATE TABLE Productos (
            id_producto INT PRIMARY KEY AUTO_INCREMENT,
            codigo VARCHAR(50) NOT NULL UNIQUE,
            marca VARCHAR(100),
            nombre VARCHAR(255) NOT NULL,
            precio_unitario DECIMAL(10, 2) NOT NULL,
            stock INT NOT NULL DEFAULT 0
        );",

        'Clientes' => "CREATE TABLE Clientes (
            id_cliente INT PRIMARY KEY AUTO_INCREMENT,
            nombre_facturacion VARCHAR(255) NOT NULL,
            rfc VARCHAR(13),
            direccion_fiscal TEXT,
            nombre_destinatario VARCHAR(255) NOT NULL,
            empresa_envio VARCHAR(255),
            direccion_envio TEXT NOT NULL,
            telefono VARCHAR(15) NOT NULL,
            referencias TEXT
        );",

        'Empleados' => "CREATE TABLE Empleados (
            id_empleado INT PRIMARY KEY AUTO_INCREMENT,
            nombre_completo VARCHAR(200) NOT NULL,
            numero_empleado VARCHAR(20) UNIQUE
        );",
        
        'Ventas' => "CREATE TABLE Ventas (
            id_venta INT PRIMARY KEY AUTO_INCREMENT,
            id_cliente INT NOT NULL,
            id_empleado INT NOT NULL,
            fecha_venta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            subtotal DECIMAL(10, 2) NOT NULL,
            iva DECIMAL(10, 2) NOT NULL,
            total DECIMAL(10, 2) NOT NULL,
            estatus VARCHAR(50) DEFAULT 'Procesando',
            FOREIGN KEY (id_cliente) REFERENCES Clientes(id_cliente),
            FOREIGN KEY (id_empleado) REFERENCES Empleados(id_empleado)
        );",

        'VentasDetalle' => "CREATE TABLE VentasDetalle (
            id_detalle INT PRIMARY KEY AUTO_INCREMENT,
            id_venta INT NOT NULL,
            id_producto INT NOT NULL,
            cantidad INT NOT NULL,
            precio_unitario DECIMAL(10, 2) NOT NULL,
            importe DECIMAL(10, 2) NOT NULL,
            FOREIGN KEY (id_venta) REFERENCES Ventas(id_venta) ON DELETE CASCADE,
            FOREIGN KEY (id_producto) REFERENCES Productos(id_producto)
        );"
    ];

    // 4. Recorrer el schema y crear las tablas si no existen
    foreach ($schema as $tableName => $createQuery) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        
        if ($stmt->rowCount() == 0) {
            $pdo->exec($createQuery);
            echo "<p style='color:blue;'>   - Tabla `{$tableName}` creada exitosamente.</p>";
        } else {
            echo "<p style='color:orange;'>- Tabla `{$tableName}` ya existe, no se realizaron cambios.</p>";
        }
    }

    echo "<hr><p style='color:green; font-weight:bold;'>🎉 Proceso finalizado. La base de datos está lista.</p>";

} catch (PDOException $e) {
    die("<p style='color:red;'>❌ ERROR: No se pudo conectar o ejecutar la consulta. " . $e->getMessage() . "</p>");
}
?>