<?php
session_start();
require_once '../config/db.php';

// --- INICIO DEL "GUARDIÁN" DE SEGURIDAD ---
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}
if (!isset($_SESSION['imap_pass'])) {
    header('Location: conectar_correo.php');
    exit;
}
// --- FIN DEL "GUARDIÁN" ---

$error_message = null;
$inbox = null;
$msg_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$msg_id) {
    http_response_code(400);
    $error_message = "ID del mensaje no especificado.";
} else {
    $imap_user = $_SESSION['imap_user'];
    $imap_pass = $_SESSION['imap_pass'];
    $imap_host = '{mail.dialexander.com:993/imap/ssl/novalidate-cert}INBOX';

    $inbox = @imap_open($imap_host, $imap_user, $imap_pass);

    if (!$inbox) {
        $error_message = "Error de conexión: No se pudo conectar al servidor de correo. Detalle: " . (imap_last_error() ?: 'Error desconocido');
    }
}

// --- SECCIÓN DE FUNCIONES MEJORADAS ---

/**
 * Decodifica encabezados de correo que pueden estar en diferentes formatos (MIME).
 */
function decode_mime_header($text) {
    if (empty($text)) return '';
    $elements = imap_mime_header_decode($text);
    $decoded = '';
    foreach ($elements as $element) {
        $charset = ($element->charset === 'default') ? 'UTF-8' : $element->charset;
        $decoded .= @iconv($charset, 'UTF-8//TRANSLIT', $element->text) ?: $element->text;
    }
    return $decoded;
}

/**
 * Decodifica el cuerpo de un mensaje según su tipo de codificación (Base64, Quoted-Printable).
 */
function decode_body($body, $encoding) {
    switch ($encoding) {
        case 3: return base64_decode($body);
        case 4: return quoted_printable_decode($body);
        default: return $body;
    }
}

/**
 * Función recursiva que explora la estructura de un correo para encontrar el cuerpo y los adjuntos.
 */
function parse_email_parts($inbox, $msg_id, $parts, &$html_body, &$plain_body, &$attachments, $prefix = '') {
    foreach ($parts as $index => $part) {
        $part_number = $prefix . ($index + 1);
        $disposition = (isset($part->disposition)) ? strtoupper($part->disposition) : null;

        // --- Búsqueda de Adjuntos ---
        $is_attachment = ($disposition === 'ATTACHMENT' || $disposition === 'INLINE');
        $filename = '';
        if ($is_attachment) {
            $params = array_merge(
                isset($part->dparameters) ? $part->dparameters : [],
                isset($part->parameters) ? $part->parameters : []
            );
            foreach ($params as $param) {
                if (strtoupper($param->attribute) == 'FILENAME' || strtoupper($param->attribute) == 'NAME') {
                    $filename = decode_mime_header($param->value);
                    break;
                }
            }
            $attachments[] = [
                'part' => $part_number,
                'filename' => $filename ?: "adjunto_{$part_number}",
            ];
        }
        // --- Búsqueda del Cuerpo ---
        elseif (isset($part->subtype)) {
            $subtype = strtoupper($part->subtype);
            if ($subtype == 'HTML' && !$html_body) { // Solo guarda el primer HTML que encuentre
                $html_body = decode_body(imap_fetchbody($inbox, $msg_id, $part_number), $part->encoding);
            } elseif ($subtype == 'PLAIN' && !$plain_body) {
                $plain_body = decode_body(imap_fetchbody($inbox, $msg_id, $part_number), $part->encoding);
            }
        }

        // Recorre las sub-partes si existen
        if (isset($part->parts)) {
            parse_email_parts($inbox, $msg_id, $part->parts, $html_body, $plain_body, $attachments, $part_number . '.');
        }
    }
}

/**
 * Función principal que orquesta la extracción del contenido del correo.
 */
function get_email_content($inbox, $msg_id) {
    $structure = imap_fetchstructure($inbox, $msg_id);
    $html_body = '';
    $plain_body = '';
    $attachments = [];

    if (isset($structure->parts) && count($structure->parts)) {
        // Correo multipart, se usa la función recursiva
        parse_email_parts($inbox, $msg_id, $structure->parts, $html_body, $plain_body, $attachments);
    } else {
        // Correo simple, sin partes
        $body_content = imap_body($inbox, $msg_id);
        $plain_body = decode_body($body_content, $structure->encoding);
    }

    $final_body = !empty($html_body) 
        ? $html_body 
        : (!empty($plain_body) ? nl2br(htmlspecialchars($plain_body)) : 'No se encontró contenido visible en este correo.');

    return ['body' => $final_body, 'attachments' => $attachments];
}

// --- Lógica principal de la página ---
if (!$error_message && $inbox) {
    $emails = imap_search($inbox, 'ALL') ?: [];
    rsort($emails);

    if (!in_array($msg_id, $emails)) {
        $error_message = "El mensaje con ID $msg_id no fue encontrado en el buzón.";
    } else {
        $current_index = array_search($msg_id, $emails);
        $prev_id = ($current_index < count($emails) - 1) ? $emails[$current_index + 1] : null;
        $next_id = ($current_index > 0) ? $emails[$current_index - 1] : null;

        $header = imap_headerinfo($inbox, $msg_id);
        $from_info = $header->from[0];
        $from_name = isset($from_info->personal) ? decode_mime_header($from_info->personal) : '';
        $from_email = $from_info->mailbox . '@' . $from_info->host;
        $subject = isset($header->subject) ? decode_mime_header($header->subject) : '(Sin asunto)';
        $date = date('d/m/Y H:i', strtotime($header->date));

        // Llamada a la nueva función unificada
        $content = get_email_content($inbox, $msg_id);
        $body = $content['body'];
        $attachments = $content['attachments'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title><?= isset($subject) ? htmlspecialchars($subject) : 'Ver Correo' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
    body { background-color: #f5f7fa; font-family: 'Roboto', sans-serif; padding: 1.5rem 1rem 3rem; min-height: 100vh; display: flex; flex-direction: column; align-items: center; }
    .container-email { width: 100%; max-width: 900px; background: #fff; border-radius: 10px; box-shadow: 0 3px 10px rgb(0 0 0 / 0.12); padding: 2rem 2.5rem; display: flex; flex-direction: column; gap: 1.25rem; }
    .email-header { border-bottom: 1px solid #ddd; }
    .email-header h1 { font-weight: 700; font-size: 1.8rem; margin-bottom: 0.2rem; color: #202124; }
    .email-meta { font-size: 0.9rem; color: #5f6368; display: flex; flex-wrap: wrap; gap: 1rem; }
    .email-body { overflow-y: auto; max-height: 65vh; font-size: 1rem; color: #202124; line-height: 1.5; border-radius: 6px; word-wrap: break-word; }
    .email-body iframe { width: 100%; height: 60vh; border: none; }
    .nav-buttons { display: flex; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; }
    .btn-nav { flex: 1; min-width: 120px; font-weight: 600; }
    .attachments { margin-top: 1rem; }
    .attachment-link { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.3rem 0.6rem; background-color: #e8f0fe; border-radius: 5px; text-decoration: none; color: #1967d2; font-weight: 500; margin-right: 0.5rem; margin-bottom: 0.5rem; }
  </style>
</head>
<body>
<div class="container-email" role="main">
<?php if ($error_message): ?>
    <div class="alert alert-danger" role="alert">
        <h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> Ocurrió un Error</h4>
        <p><?= htmlspecialchars($error_message) ?></p>
        <hr>
        <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Volver a la bandeja de entrada</a>
    </div>
<?php else: ?>
    <nav class="nav-buttons" aria-label="Navegación de correos">
      <a href="ver.php?id=<?= $prev_id ?>" class="btn btn-outline-primary btn-nav <?= $prev_id === null ? 'disabled' : '' ?>" aria-label="Correo anterior"><i class="bi bi-arrow-left"></i> Anterior</a>
      <a href="index.php" class="btn btn-secondary btn-nav" aria-label="Volver a la bandeja de entrada"><i class="bi bi-envelope"></i> Bandeja</a>
      <a href="ver.php?id=<?= $next_id ?>" class="btn btn-outline-primary btn-nav <?= $next_id === null ? 'disabled' : '' ?>" aria-label="Correo siguiente">Siguiente <i class="bi bi-arrow-right"></i></a>
    </nav>
    <header class="email-header">
      <h1><?= htmlspecialchars($subject) ?></h1>
      <div class="email-meta">
        <span><strong>De:</strong> <?= htmlspecialchars($from_name ?: $from_email) ?> (<?= htmlspecialchars($from_email) ?>)</span>
        <span><strong>Fecha:</strong> <?= htmlspecialchars($date) ?></span>
      </div>
    </header>
    <article class="email-body" tabindex="0" aria-label="Contenido del correo">
        <?php
            // Se muestra el contenido en un iframe para aislar estilos y scripts del correo.
            echo '<iframe srcdoc="' . htmlspecialchars($body) . '"></iframe>';
        ?>
    </article>
    <?php if (!empty($attachments)): ?>
      <div class="attachments">
        <h5>Adjuntos:</h5>
        <?php foreach ($attachments as $att): ?>
          <a class="attachment-link" target="_blank" href="descargar.php?id=<?= $msg_id ?>&part=<?= $att['part'] ?>&filename=<?= urlencode($att['filename']) ?>"><i class="bi bi-paperclip"></i> <?= htmlspecialchars($att['filename']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="d-flex flex-wrap gap-2 mt-4">
      <a href="responder.php?id=<?= $msg_id ?>" class="btn btn-success"><i class="bi bi-reply-fill"></i> Responder</a>
      <form action="eliminar.php" method="POST" onsubmit="return confirm('¿Estás seguro de eliminar este mensaje?');" style="display: inline;">
        <input type="hidden" name="msg_id" value="<?= $msg_id ?>">
        <button type="submit" class="btn btn-danger"><i class="bi bi-trash3-fill"></i> Eliminar</button>
      </form>
    </div>
<?php endif; ?>
</div>
<?php if ($inbox) imap_close($inbox); ?>
</body>
</html>

