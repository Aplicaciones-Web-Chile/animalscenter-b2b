<?php
// includes/alerts.php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once APP_ROOT . '/includes/mail_helper.php'; // tu wrapper de PHPMailer

// Destinatarios fijos para alertas
const ALERT_RECIPIENTS = ['bryan@aplicacionesweb.cl', 'juan@aplicacionesweb.cl'];

/**
 * Envía un correo de alerta cuando una sync falla.
 *
 * @param string     $tituloCorto   Ej: "sync_full_productos" o "sync_full_proveedores"
 * @param \Throwable $e             Excepción capturada
 * @param array      $extra         Contexto útil (clave => valor)
 */
function notifySyncFailure(string $tituloCorto, Throwable $e, array $extra = []): void
{
  $when = (new DateTime())->format('Y-m-d H:i:s');
  $host = php_uname('n');
  $uri = $_SERVER['REQUEST_URI'] ?? 'CLI';
  $file = $e->getFile();
  $line = $e->getLine();
  $msg = $e->getMessage();
  $trace = nl2br(htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8'));

  // Normaliza extras para mostrar
  $extrasHtml = '';
  if (!empty($extra)) {
    $rows = '';
    foreach ($extra as $k => $v) {
      if (is_array($v)) {
        $val = htmlspecialchars(json_encode($v, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
      } else {
        $val = htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
      }
      $rows .= "<tr><th align='left' style='padding:6px;border:1px solid #eee;'>{$k}</th><td style='padding:6px;border:1px solid #eee;'>{$val}</td></tr>";
    }
    $extrasHtml = "<table cellpadding='0' cellspacing='0' style='border-collapse:collapse;margin-top:12px;'>{$rows}</table>";
  }

  $subject = "[B2B][ALERTA] Falla {$tituloCorto}: {$msg}";
  $html = <<<HTML
        <div style="font-family:system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color:#222;">
          <h2 style="margin:0 0 10px 0;">Falla en {$tituloCorto}</h2>
          <p>Se ha detectado un error durante la ejecución.</p>

          <table cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
            <tr><th align="left" style="padding:6px;border:1px solid #eee;">Fecha/Hora</th><td style="padding:6px;border:1px solid #eee;">{$when}</td></tr>
            <tr><th align="left" style="padding:6px;border:1px solid #eee;">Host</th><td style="padding:6px;border:1px solid #eee;">{$host}</td></tr>
            <tr><th align="left" style="padding:6px;border:1px solid #eee;">Script</th><td style="padding:6px;border:1px solid #eee;">{$uri}</td></tr>
            <tr><th align="left" style="padding:6px;border:1px solid #eee;">Archivo</th><td style="padding:6px;border:1px solid #eee;">{$file}:{$line}</td></tr>
            <tr><th align="left" style="padding:6px;border:1px solid #eee;">Mensaje</th><td style="padding:6px;border:1px solid #eee;">{$msg}</td></tr>
          </table>

          {$extrasHtml}

          <h3 style="margin-top:18px;">Stack trace</h3>
          <pre style="background:#f7f7f7;border:1px solid #eee;padding:10px;white-space:pre-wrap;">{$trace}</pre>
        </div>
    HTML;

  // Alt body simple (texto)
  $alt = "Falla en {$tituloCorto}\n\n{$msg}\n\nArchivo: {$file}:{$line}\nFecha/Hora: {$when}\nHost: {$host}\n";

  try {
    sendMail([
      'to' => ALERT_RECIPIENTS,
      'subject' => $subject,
      'html' => $html,
      'text' => $alt,
    ]);
  } catch (\Throwable $mailEx) {
    // Evita que una falla enviando el mail derribe el proceso
    error_log("[notifySyncFailure][MAIL ERROR] " . $mailEx->getMessage());
  }
}
