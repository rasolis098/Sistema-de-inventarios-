<?php
// login.php - Versión Final y Corregida ("Proyecto Hyperion")
// -------------------------------------------------------------------------
// INICIO DE TU LÓGICA PHP ORIGINAL (INTEGRADA SIN MODIFICACIONES)
// -------------------------------------------------------------------------
session_start();

// 1. Redirigir si el usuario ya ha iniciado sesión
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

// 2. Incluir la configuración de la base de datos
// ¡IMPORTANTE! Asegúrate de que la ruta a tu archivo de conexión es correcta.
require_once __DIR__ . '/config/db.php';

// 3. Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validar que los campos no estén vacíos
    if (empty($email) || empty($password)) {
        $error = "Por favor, completa todos los campos.";
    } else {
        try {
            // 4. Preparar y ejecutar la consulta a la base de datos
            $stmt = $pdo->prepare("
                SELECT id, nombre, email, password_hash, activo, id_empleado
                FROM usuarios 
                WHERE email = ? AND activo = 1
            ");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 5. Verificar la contraseña y el usuario
            if ($usuario && password_verify($password, $usuario['password_hash'])) {
                // Login Exitoso: Crear variables de sesión
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['empleado_id'] = $usuario['id_empleado'];
                
                // 6. (Opcional) Registrar el acceso en la tabla de logs
                try {
                    $stmt_log = $pdo->prepare("
                        INSERT INTO logs_acceso (usuario_id, fecha_acceso, ip) 
                        VALUES (?, NOW(), ?)
                    ");
                    $stmt_log->execute([
                        $usuario['id'], 
                        $_SERVER['REMOTE_ADDR'] ?? 'Desconocida'
                    ]);
                } catch (PDOException $e) {
                    // Si falla el log, no detener el login. Solo registrar el error.
                    error_log("Error al registrar log de acceso: " . $e->getMessage());
                }
                
                // 7. Redirigir al panel principal
                header('Location: index.php');
                exit;
            } else {
                // Error de credenciales
                $error = "Credenciales incorrectas o usuario inactivo.";
            }
        } catch (PDOException $e) {
            // Error de conexión a la base de datos
            $error = "Error crítico de conexión. Contacte al administrador.";
            error_log("Error en login: " . $e->getMessage());
        }
    }
}
// -------------------------------------------------------------------------
// FIN DE LA LÓGICA PHP
// -------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema | Inventario Hyperion</title>
    
    <!-- Dependencias (Bootstrap, Font Awesome, Google Fonts) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-main: #0a0a14;
            --card-glass: rgba(15, 20, 40, 0.6);
            --border-color: rgba(0, 190, 255, 0.2);
            --accent-primary: #00beff;
            --accent-glow: rgba(0, 190, 255, 0.5);
            --text-primary: #e6f7ff;
            --text-secondary: #a0c8e0;
            --font-display: 'Orbitron', sans-serif;
            --font-body: 'Roboto', sans-serif;
        }

        body {
            font-family: var(--font-body);
            background-color: var(--bg-main);
            color: var(--text-primary);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow: hidden;
            position: relative;
        }

        /* --- Fondo de rejilla animada --- */
        #animated-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background-image: 
                linear-gradient(rgba(0, 190, 255, 0.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 190, 255, 0.1) 1px, transparent 1px);
            background-size: 40px 40px;
            animation: move-grid 20s linear infinite;
        }

        #animated-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle, transparent, var(--bg-main) 70%);
        }

        @keyframes move-grid {
            from { background-position: 0 0; }
            to { background-position: 80px 80px; }
        }

        /* --- Tarjeta de Login (Glassmorphism) --- */
        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 2.5rem;
            background: var(--card-glass);
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            animation: float-in 1s ease-out forwards;
        }

        @keyframes float-in {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header .icon {
            font-size: 3rem;
            color: var(--accent-primary);
            margin-bottom: 0.5rem;
            text-shadow: 0 0 15px var(--accent-glow);
        }
        .login-header h2 {
            font-family: var(--font-display);
            font-weight: 700;
            letter-spacing: 2px;
        }
        .login-header p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        /* --- Estilos de los campos de entrada --- */
        .input-wrapper {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .input-wrapper .icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            transition: color 0.3s ease;
        }
        .form-control {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            height: 50px;
            border-radius: 8px;
            padding-left: 45px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-control:focus {
            background: rgba(0, 0, 0, 0.2);
            color: var(--text-primary);
            border-color: var(--accent-primary);
            box-shadow: 0 0 15px var(--accent-glow);
        }
        .form-control:focus ~ .icon {
            color: var(--accent-primary);
        }
        .form-control::placeholder {
            color: var(--text-secondary);
        }

        /* --- Botón de Login --- */
        .btn-submit {
            background: var(--accent-primary);
            border: none;
            color: var(--bg-main);
            padding: 0.8rem;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 1px;
            border-radius: 8px;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 0 10px var(--accent-glow);
        }
        .btn-submit:hover {
            background: var(--accent-primary);
            color: var(--bg-main);
            transform: scale(1.05);
            box-shadow: 0 0 25px var(--accent-glow);
        }

        /* --- Mensaje de Error --- */
        .alert-custom {
            background-color: rgba(255, 50, 50, 0.1);
            border: 1px solid rgba(255, 50, 50, 0.3);
            color: #ffc0c0;
            font-size: 0.9rem;
            border-radius: 8px;
            padding: 0.75rem 1rem;
        }
    </style>
</head>
<body>
    <div id="animated-bg"></div>

    <main class="login-card">
        <header class="login-header">
            <div class="icon"><i class="fas fa-cubes"></i></div>
            <h2>Inventario</h2>
            <p>Acceso Autorizado al Sistema</p>
        </header>

        <!-- El bloque de error de PHP se mostrará aquí si existe la variable $error -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-custom text-center mb-3">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm" action="login.php" novalidate>
            <div class="input-wrapper">
                <i class="fas fa-envelope icon"></i>
                <input type="email" class="form-control" id="email" name="email" placeholder="Correo Electrónico" required autofocus>
            </div>
            
            <div class="input-wrapper">
                <i class="fas fa-lock icon"></i>
                <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required>
            </div>
            
            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-submit">
                    <span class="btn-text">Iniciar Sesión</span>
                </button>
            </div>
        </form>
    </main>

    <script>
        // Script para el estado de carga del botón (UX)
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = `
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                <span class="ms-1">Verificando...</span>`;
        });
    </script>
</body>
</html>
