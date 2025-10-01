<?php
// correo/index.php - VERSIÓN FINAL
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/db.php';

// Si el usuario no ha iniciado sesión en el sistema, lo expulsamos al login principal.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

// ⚠️ CAMBIO CLAVE: Verificamos si las credenciales del correo existen en la sesión.
// Si no existen, lo enviamos a la página de conexión para que ingrese su contraseña.
if (!isset($_SESSION['imap_pass'])) {
    header('Location: conectar_correo.php');
    exit;
}

// Obtenemos el nombre del usuario desde la base de datos
$stmt = $pdo->prepare("SELECT nombre FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
$nombre_usuario = $usuario['nombre'] ?? 'Usuario';

// Usamos las credenciales guardadas en la sesión para conectar con IMAP
$imap_user = $_SESSION['imap_user'];
$imap_pass = $_SESSION['imap_pass'];
$imap_host = '{svgs441.serverneubox.com.mx:993/imap/ssl/novalidate-cert}INBOX';

// El resto del código es idéntico al anterior...
$inbox = @imap_open($imap_host, $imap_user, $imap_pass);

function decode_mime_header($text) {
    // ... (la función decode_mime_header no cambia)
    $elements = imap_mime_header_decode($text);
    $decoded = '';
    foreach ($elements as $element) {
        $charset = strtoupper($element->charset);
        $part = $element->text;
        if ($charset !== 'DEFAULT' && $charset !== 'UTF-8') {
            $part = mb_convert_encoding($part, 'UTF-8', $charset);
        }
        $decoded .= $part;
    }
    return $decoded;
}

// ... (toda la lógica de paginación y manejo de correos no cambia)
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;

if (!$inbox) {
    $emails = [];
    $total_emails = 0;
    $total_pages = 0;
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_selected']) && !empty($_POST['selected_emails'])) {
        foreach ($_POST['selected_emails'] as $msg_num) {
            imap_delete($inbox, intval($msg_num));
        }
        imap_expunge($inbox);
        header("Location: " . $_SERVER['PHP_SELF'] . "?page=" . $page);
        exit;
    }
    $emails = imap_search($inbox, 'ALL');
    $total_emails = $emails ? count($emails) : 0;
    $total_pages = ceil($total_emails / $per_page);
}

$offset = ($page - 1) * $per_page;

function pagination_block($current, $total, $block_size = 5) {
    $start = floor(($current - 1) / $block_size) * $block_size + 1;
    $end = min($start + $block_size - 1, $total);
    return [$start, $end];
}
[$block_start, $block_end] = pagination_block($page, $total_pages);

if ($emails) rsort($emails);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Bandeja de Entrada</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { margin: 0; height: 100vh; display: flex; overflow: hidden; background-color: #f5f7fa; }
        #sidebar { width: 250px; background-color: #fff; border-right: 1px solid #ddd; display: flex; flex-direction: column; justify-content: space-between; padding: 1rem 0; }
        #sidebar .user-info { text-align: center; margin-bottom: 1rem; }
        #sidebar .user-info i { font-size: 2rem; color: #6c757d; }
        #sidebar .user-info .name { font-weight: 600; margin-top: 0.5rem; }
        .nav-link { padding: 0.6rem 1.5rem; color: #555; font-weight: 500; }
        .nav-link:hover, .nav-link.active { background-color: #e8f0fe; color: #1967d2; }
        .btn-redactar { margin: 1rem 1.5rem; font-weight: 600; }
        #main-content { flex: 1; display: flex; flex-direction: column; }
        #topbar { height: 56px; background-color: #fff; border-bottom: 1px solid #ddd; padding: 0 1rem; font-weight: 500; font-size: 1.1rem; display: flex; align-items: center; color: #202124; }
        #email-list { flex: 1; overflow-y: auto; background-color: #fff; padding: 0; }
        table { width: 100%; border-collapse: collapse; }
        thead th { background-color: #f1f3f4; padding: 0.75rem 1.5rem; font-size: 0.85rem; color: #5f6368; text-transform: uppercase; white-space: nowrap; }
        tbody tr:hover { background-color: #f8fbff; }
        tbody td { padding: 0.75rem 1.5rem; vertical-align: middle; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .subject { max-width: 100%; width: auto; }
        .date { width: 140px; text-align: right; font-size: 0.85rem; color: #6c757d; }
        .actions { width: 100px; text-align: center; }
        .btn-view { background-color: #1a73e8; color: white; font-size: 0.8rem; padding: 0.3rem 0.7rem; border: none; border-radius: 20px; text-decoration: none; }
        .btn-view:hover { background-color: #155ab6; color: white; }
        .pagination { justify-content: center; margin: 1rem 0; }
    </style>
</head>
<body>
    <nav id="sidebar">
        <div>
            <div class="user-info">
                <i class="bi bi-person-circle"></i>
                <div class="name"><?= htmlspecialchars($nombre_usuario) ?></div>
            </div>
            <a href="/index.php" class="nav-link">
                <i class="bi bi-house-door me-2"></i> Inicio Sistema
            </a>
            <a href="index.php" class="nav-link active"><i class="bi bi-inbox me-2"></i> Bandeja de Entrada</a>
            <a href="enviados.php" class="nav-link"><i class="bi bi-send me-2"></i> Enviados</a>
            <a href="papelera.php" class="nav-link"><i class="bi bi-trash me-2"></i> Papelera</a>
            <a href="redactar.php" class="btn btn-primary btn-redactar">
                <i class="bi bi-pencil me-1"></i> Redactar
            </a>
        </div>
        <div class="px-3">
            <form action="/logout.php" method="POST">
                <input type="hidden" name="redirect" value="/login.php">
                <button type="submit" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-box-arrow-right me-2"></i> Cerrar sesión
                </button>
            </form>
        </div>
    </nav>
    
    <div id="main-content">
        <header id="topbar">Bandeja de Entrada</header>
        <section id="email-list">
            <?php if (!$inbox): ?>
                <div class="alert alert-danger m-4"><strong>Error de Conexión:</strong> No se pudo conectar al servidor de correo. (<?= htmlspecialchars(imap_last_error()) ?>)</div>
            <?php elseif (!$emails): ?>
                <div class="alert alert-info m-4">No hay correos en la bandeja de entrada.</div>
            <?php else: ?>
                <form method="POST" onsubmit="return confirm('¿Seguro que quieres eliminar los correos seleccionados?')">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all"></th>
                                <th>De</th>
                                <th class="subject">Asunto</th>
                                <th class="date">Fecha</th>
                                <th class="actions">Ver</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($emails, $offset, $per_page) as $msg_num): ?>
                                <?php
                                    $header = imap_headerinfo($inbox, $msg_num);
                                    $from_info = $header->from[0];
                                    $remitente = isset($from_info->personal) ? decode_mime_header($from_info->personal) : $from_info->mailbox;
                                    $asunto = isset($header->subject) ? decode_mime_header($header->subject) : '(Sin asunto)';
                                    $fecha = date('d/m/Y H:i', strtotime($header->date));
                                ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_emails[]" value="<?= $msg_num ?>"></td>
                                    <td><?= htmlspecialchars($remitente) ?></td>
                                    <td class="subject">
                                        <a href="ver.php?id=<?= $msg_num ?>" class="text-dark text-decoration-none fw-medium">
                                            <?= htmlspecialchars($asunto) ?>
                                        </a>
                                    </td>
                                    <td class="date"><?= $fecha ?></td>
                                    <td class="actions">
                                        <a href="ver.php?id=<?= $msg_num ?>" class="btn-view"><i class="bi bi-eye"></i> Ver</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="m-3">
                        <button type="submit" name="delete_selected" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Eliminar seleccionados
                        </button>
                    </div>
                </form>
                <nav>
                    <ul class="pagination">
                        <?php if ($block_start > 1): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $block_start - 1 ?>">&laquo;</a></li>
                        <?php endif; ?>
                        <?php for ($i = $block_start; $i <= $block_end; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($block_end < $total_pages): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $block_end + 1 ?>">&raquo;</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            <?php if ($inbox) imap_close($inbox); ?>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="selected_emails[]"]');
            for (const cb of checkboxes) {
                cb.checked = this.checked;
            }
        });
    </script>
</body>
</html>