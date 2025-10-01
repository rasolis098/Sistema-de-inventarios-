<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

$stmt = $pdo->prepare("SELECT nombre, correo_usuario, correo_password FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    die("Usuario no encontrado en la base de datos.");
}

$nombre_usuario = $usuario['nombre'];
$imap_user = $usuario['correo_usuario'];
$imap_pass = $usuario['correo_password'];

// Cambia la carpeta IMAP a Sent (puede variar según servidor, prueba '{...}Sent' o '{...}Sent Items')
$imap_host = '{svgs441.serverneubox.com.mx:993/imap/ssl/novalidate-cert}Sent';

$inbox = @imap_open($imap_host, $imap_user, $imap_pass);

function decode_mime_header($text) {
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

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$emails = $inbox ? imap_search($inbox, 'ALL') : [];
$total_emails = $emails ? count($emails) : 0;
$total_pages = ceil($total_emails / $per_page);
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
  <title>Enviados - Correo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body, html {
      height: 100%;
      margin: 0;
    }
    #sidebar {
      min-height: 100vh;
    }
    .name {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    table {
      min-width: 600px;
    }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
  <div class="container-fluid">
    <button class="btn btn-primary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar">
      <i class="bi bi-list"></i>
    </button>
    <a class="navbar-brand ms-2" href="#">Correo</a>
  </div>
</nav>

<div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasSidebar" aria-labelledby="offcanvasSidebarLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offcanvasSidebarLabel">Menú</h5>
    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
  </div>
  <div class="offcanvas-body d-flex flex-column justify-content-between p-0">
    <div>
      <div class="user-info text-center p-3 border-bottom">
        <i class="bi bi-person-circle fs-1 text-secondary"></i>
        <div class="name fw-semibold mt-2 fs-5 text-truncate" title="<?= htmlspecialchars($nombre_usuario) ?>">
          <?= htmlspecialchars($nombre_usuario) ?>
        </div>
      </div>

      <nav class="nav flex-column">
        <a href="index.php" class="nav-link"><i class="bi bi-inbox me-2"></i> Bandeja de Entrada</a>
        <a href="?q=UNSEEN" class="nav-link"><i class="bi bi-eye-slash me-2"></i> No leídos</a>
        <a href="?q=SEEN" class="nav-link"><i class="bi bi-eye me-2"></i> Leídos</a>
        <a href="?q=FLAGGED" class="nav-link"><i class="bi bi-star me-2"></i> Destacados</a>
        <a href="enviados.php" class="nav-link active"><i class="bi bi-send me-2"></i> Enviados</a>
        <a href="papelera.php" class="nav-link"><i class="bi bi-trash me-2"></i> Papelera</a>
        <a href="redactar.php" class="btn btn-danger w-100 mt-3">
          <i class="bi bi-pencil me-1"></i> Redactar
        </a>
      </nav>
    </div>

    <div class="p-3 border-top">
      <form action="logout.php" method="POST">
        <button type="submit" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center">
          <i class="bi bi-box-arrow-right me-2"></i> Cerrar sesión
        </button>
      </form>
    </div>
  </div>
</div>

<div class="d-flex">
  <!-- Sidebar para escritorio -->
  <nav id="sidebar" class="d-none d-lg-flex flex-column bg-white border-end p-3" style="width: 250px;">
    <div class="user-info text-center mb-4">
      <i class="bi bi-person-circle fs-1 text-secondary"></i>
      <div class="name fw-semibold mt-2 fs-5 text-truncate" title="<?= htmlspecialchars($nombre_usuario) ?>">
        <?= htmlspecialchars($nombre_usuario) ?>
      </div>
    </div>
    <nav class="nav flex-column">
      <a href="index.php" class="nav-link"><i class="bi bi-inbox me-2"></i> Bandeja de Entrada</a>
      <a href="?q=UNSEEN" class="nav-link"><i class="bi bi-eye-slash me-2"></i> No leídos</a>
      <a href="?q=SEEN" class="nav-link"><i class="bi bi-eye me-2"></i> Leídos</a>
      <a href="?q=FLAGGED" class="nav-link"><i class="bi bi-star me-2"></i> Destacados</a>
      <a href="enviados.php" class="nav-link active"><i class="bi bi-send me-2"></i> Enviados</a>
      <a href="papelera.php" class="nav-link"><i class="bi bi-trash me-2"></i> Papelera</a>
      <a href="redactar.php" class="btn btn-danger mt-3 w-100">
        <i class="bi bi-pencil me-1"></i> Redactar
      </a>
    </nav>
    <div class="mt-auto pt-3 border-top">
      <form action="logout.php" method="POST">
        <button type="submit" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center">
          <i class="bi bi-box-arrow-right me-2"></i> Cerrar sesión
        </button>
      </form>
    </div>
  </nav>

  <!-- Contenido principal -->
  <main class="flex-grow-1 p-3" style="min-width: 0;">
    <h1 class="mb-4">Correos Enviados</h1>

    <?php if (!$inbox): ?>
      <div class="alert alert-danger"><strong>Error:</strong> <?= htmlspecialchars(imap_last_error()) ?></div>
    <?php elseif (!$emails): ?>
      <div class="alert alert-info">No hay correos enviados.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Para</th>
              <th>Asunto</th>
              <th class="text-end">Fecha</th>
              <th class="text-center">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($emails, $offset, $per_page) as $num_msg): ?>
              <?php
                $header = imap_headerinfo($inbox, $num_msg);
                // Obtener destinatarios: to
                $to_addresses = [];
                if (!empty($header->to)) {
                  foreach ($header->to as $to) {
                    $to_addresses[] = $to->mailbox . '@' . $to->host;
                  }
                }
                $para = implode(', ', $to_addresses);
                $asunto = decode_mime_header($header->subject ?? '(Sin asunto)');
                $fecha = date('d/m/Y H:i', strtotime($header->date));
              ?>
              <tr>
                <td><?= htmlspecialchars($para) ?></td>
                <td>
                  <a href="ver.php?id=<?= $num_msg ?>" class="text-decoration-none fw-semibold">
                    <?= htmlspecialchars($asunto) ?>
                  </a>
                </td>
                <td class="text-end"><?= $fecha ?></td>
                <td class="text-center">
                  <a href="ver.php?id=<?= $num_msg ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-eye"></i> Ver
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <nav aria-label="Paginación">
        <ul class="pagination justify-content-center">
          <?php if ($block_start > 1): ?>
            <li class="page-item">
              <a class="page-link" href="?page=<?= $block_start - 1 ?>" aria-label="Anterior">
                <span aria-hidden="true">&laquo;</span>
              </a>
            </li>
          <?php endif; ?>

          <?php for ($i = $block_start; $i <= $block_end; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
              <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>

          <?php if ($block_end < $total_pages): ?>
            <li class="page-item">
              <a class="page-link" href="?page=<?= $block_end + 1 ?>" aria-label="Siguiente">
                <span aria-hidden="true">&raquo;</span>
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </nav>
    <?php endif; ?>

  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
