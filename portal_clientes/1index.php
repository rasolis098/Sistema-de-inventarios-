<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valor = trim($_POST['valor'] ?? '');

    if (filter_var($valor, FILTER_VALIDATE_EMAIL)) {
        header("Location: pedidos.php?email=" . urlencode($valor));
        exit;
    } else {
        header("Location: pedidos.php?codigo=" . urlencode($valor));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso Privado - Portal de Clientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', sans-serif;
        }
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #0d6efd;
        }
        .btn-primary {
            background-color: #0d6efd;
            border: none;
        }
        .brand-header {
            font-weight: 600;
            font-size: 1.2rem;
            color: #0d6efd;
        }
        .subtext {
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="text-center mb-4">
                <div class="brand-header">Distribuidora Industrial Alexander</div>
                <div class="subtext">Acceso privado para clientes registrados</div>
            </div>
            <div class="card p-4">
                <h5 class="text-center mb-4">Portal de Clientes</h5>
                <form method="post">
                    <div class="mb-3">
                        <label for="valor" class="form-label">Correo electrónico o código de cliente</label>
                        <input type="text" class="form-control" id="valor" name="valor" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                </form>
            </div>
            <p class="text-center mt-4 text-muted small">© 2025 Distribuidora Industrial Alexander</p>
        </div>
    </div>
</div>
</body>
</html>
