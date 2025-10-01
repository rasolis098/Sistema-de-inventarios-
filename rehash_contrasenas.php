<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';

// Obtener todos los usuarios
$stmt = $pdo->query("SELECT id, correo_password FROM usuarios");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($usuarios as $usuario) {
    $id = $usuario['id'];
    $pass_bd = $usuario['correo_password'];

    // Detectar si el valor NO parece un hash bcrypt/argon
    if (strlen($pass_bd) < 50 || substr($pass_bd, 0, 4) !== '$2y$') {
        // Aquí asumimos que es texto plano o md5
        // Si sospechas que es MD5, comprueba así:
        // $es_md5 = preg_match('/^[a-f0-9]{32}$/', $pass_bd);

        // Pedir la contraseña original en texto plano de alguna fuente
        // Aquí suponemos que en la BD estaba en texto plano:
        $password_plano = $pass_bd;

        // Hashear con bcrypt
        $nuevo_hash = password_hash($password_plano, PASSWORD_DEFAULT);

        // Guardar en la BD
        $update = $pdo->prepare("UPDATE usuarios SET correo_password = ? WHERE id = ?");
        $update->execute([$nuevo_hash, $id]);

        echo "Usuario ID $id re-hasheado.\n";
    } else {
        echo "Usuario ID $id ya tiene hash válido, se omite.\n";
    }
}

echo "Proceso finalizado.\n";
