<?php
// includes/mailer.php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Envía un correo vía SMTP usando PHPMailer.
 *
 * @param array $opts
 *  - to           : string|array  (obligatorio)  Ej: 'destino@dominio.com' o ['a@x.com','b@y.com']
 *  - subject      : string        (obligatorio)
 *  - html         : string        (opcional)     Cuerpo HTML
 *  - text         : string        (opcional)     Cuerpo texto plano (fallback). Si no se provee, se genera desde HTML.
 *  - attachments  : array         (opcional)     Rutas locales de archivos ['path/uno.pdf','/tmp/dos.png']
 *  - cc           : string|array  (opcional)
 *  - bcc          : string|array  (opcional)
 *  - reply_to     : string|array  (opcional)     Ej: 'soporte@dominio.com' o ['email','Nombre']
 *  - from         : string        (opcional)     Sobrescribe MAIL_FROM
 *  - from_name    : string        (opcional)     Sobrescribe MAIL_FROM_NAME
 *  - priority     : int           (opcional)     1 alta, 3 normal, 5 baja
 *
 * @return array ['ok' => bool, 'error' => string|null, 'debug' => string|null]
 */
function sendMail(array $opts): array
{
  $mail = new PHPMailer(true);

  // Nivel de depuración (0 en prod). Pon 2 si quieres ver el diálogo SMTP en pruebas.
  $mail->SMTPDebug = 0;
  $debugBuffer = '';

  try {
    // SMTP
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->Port = SMTP_PORT;
    $mail->SMTPSecure = SMTP_SECURE; // 'ssl' o 'tls'
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);

    // (Opcional) Capturar debug en buffer si activas SMTPDebug
    if ($mail->SMTPDebug > 0) {
      $mail->Debugoutput = function ($str, $level) use (&$debugBuffer) {
        $debugBuffer .= "[$level] $str\n";
      };
    }

    // From
    $from = $opts['from'] ?? MAIL_FROM;
    $fromName = $opts['from_name'] ?? MAIL_FROM_NAME;
    $mail->setFrom($from, $fromName);

    // To (acepta string o array)
    $to = $opts['to'] ?? null;
    if (!$to) {
      throw new Exception('Parámetro "to" es obligatorio.');
    }
    foreach ((array) $to as $addr) {
      // permitir ['email','Nombre']
      if (is_array($addr)) {
        $mail->addAddress($addr[0], $addr[1] ?? '');
      } else {
        $mail->addAddress($addr);
      }
    }

    // CC / BCC
    foreach ((array) ($opts['cc'] ?? []) as $addr) {
      $mail->addCC($addr);
    }
    foreach ((array) ($opts['bcc'] ?? []) as $addr) {
      $mail->addBCC($addr);
    }

    // Reply-To
    if (!empty($opts['reply_to'])) {
      $rt = $opts['reply_to'];
      if (is_array($rt))
        $mail->addReplyTo($rt[0], $rt[1] ?? '');
      else
        $mail->addReplyTo($rt);
    }

    // Prioridad
    if (!empty($opts['priority'])) {
      $mail->Priority = (int) $opts['priority']; // 1 alta, 3 normal, 5 baja
    }

    // Asunto y cuerpo
    $mail->Subject = (string) ($opts['subject'] ?? '');

    $html = (string) ($opts['html'] ?? '');
    $text = (string) ($opts['text'] ?? '');

    if ($html === '' && $text === '') {
      throw new Exception('Debes incluir "html" o "text" para el cuerpo del correo.');
    }

    if ($html !== '') {
      $mail->Body = $html;
      // Si no nos pasan texto plano, generarlo del HTML
      if ($text === '') {
        $text = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));
      }
    }
    $mail->AltBody = $text;

    // Adjuntos
    foreach ((array) ($opts['attachments'] ?? []) as $path) {
      if (is_string($path) && $path !== '' && file_exists($path)) {
        $mail->addAttachment($path);
      }
    }

    // Enviar
    $mail->send();
    return ['ok' => true, 'error' => null, 'debug' => $debugBuffer ?: null];

  } catch (Exception $e) {
    return ['ok' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage(), 'debug' => $debugBuffer ?: null];
  }
}
