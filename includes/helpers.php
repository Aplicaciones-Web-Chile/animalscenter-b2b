<?php
/**
 * Funciones helper reutilizables para toda la aplicación
 */

/**
 * Limpia un RUT eliminando puntos y guión
 *
 * @param string $rut RUT con formato (ej: 12.345.678-9)
 * @return string RUT sin formato (ej: 123456789)
 */
function limpiarRut($rut)
{
    return str_replace(['.', '-'], '', $rut);
}

/**
 * Formatea un RUT para mostrar con puntos y guión
 *
 * @param string $rut RUT sin formato (ej: 123456789)
 * @return string RUT con formato (ej: 12.345.678-9)
 */
function formatearRut($rut)
{
    if (empty($rut))
        return '';

    // Primero limpiar por si acaso ya tiene formato
    $rut = limpiarRut($rut);

    // Extraer dígito verificador
    $dv = substr($rut, -1);
    $numero = substr($rut, 0, -1);

    // Formatear con puntos de miles
    $numero = number_format($numero, 0, '', '.');

    return $numero . '-' . $dv;
}

/**
 * Sanea un valor de entrada para prevenir XSS
 *
 * @param string $input Valor de entrada a sanear
 * @return string Valor saneado
 */
function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Verifica si una cadena está vacía
 *
 * @param string $value Valor a verificar
 * @return bool True si está vacío
 */
function isEmpty($value)
{
    return trim($value) === '';
}

/**
 * Genera un mensaje flash para mostrar en la siguiente solicitud
 *
 * @param string $type Tipo de mensaje (success, error, warning, info)
 * @param string $message Contenido del mensaje
 */
function setFlashMessage($type, $message)
{
    startSession();
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Obtiene y borra el mensaje flash almacenado
 *
 * @return array|null Mensaje flash o null si no hay
 */
function getFlashMessage()
{
    startSession();
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Muestra el mensaje flash formateado para Bootstrap
 */
function displayFlashMessage()
{
    $flashMessage = getFlashMessage();
    if ($flashMessage) {
        $type = $flashMessage['type'];
        $message = $flashMessage['message'];

        echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                {$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
    }
}

/**
 * Redirige a una URL específica
 *
 * @param string $url URL de destino
 */
function redirect($url)
{
    header("Location: $url");
    exit;
}

/**
 * Genera un token CSRF para formularios
 *
 * @return string Token CSRF
 */
function generateCsrfToken()
{
    startSession();
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * Verifica si un token CSRF es válido
 *
 * @param string $token Token a verificar
 * @param bool $regenerate Si es true, regenera el token después de validarlo
 * @return bool True si el token es válido
 */
function validateCsrfToken($token, $regenerate = false)
{
    startSession();
    if (!isset($_SESSION['csrf_token'])) {
        // Si no hay token, creamos uno nuevo
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return false;
    }

    $valid = hash_equals($_SESSION['csrf_token'], $token);

    // Regenerar token después de su uso solo si se solicita
    if ($regenerate && $valid) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $valid;
}

/**
 * Formatea una fecha y hora
 *
 * @param string $datetime Fecha y hora en formato MySQL (YYYY-MM-DD HH:MM:SS)
 * @param string $format Formato deseado (default: d/m/Y H:i)
 * @return string Fecha formateada
 */
function formatDateTime($datetime, $format = 'd/m/Y H:i')
{
    $date = new DateTime($datetime);
    return $date->format($format);
}

/**
 * Formatea un valor numérico como moneda ($)
 *
 * @param float $amount Cantidad a formatear
 * @return string Cantidad formateada ($X.XXX)
 */
function formatCurrency($amount)
{
    if (!is_numeric($amount)) {
        return $amount;
    }

    return '$' . number_format($amount, 0, ',', '.');
}

/**
 * Obtiene la URL base de la aplicación
 *
 * @return string URL base
 */
function getBaseUrl()
{
    return rtrim(APP_URL, '/');
}

/**
 * Verifica si una petición es AJAX
 *
 * @return bool True si es una petición AJAX
 */
function isAjaxRequest()
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Envía una respuesta JSON y termina la ejecución
 *
 * @param mixed $data Datos a convertir a JSON
 * @param int $statusCode Código de estado HTTP
 */
function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Muestra un enlace de paginación
 *
 * @param int $currentPage Página actual
 * @param int $totalPages Total de páginas
 * @param string $baseUrl URL base para los enlaces
 */
function paginationLinks($currentPage, $totalPages, $baseUrl)
{
    echo '<nav aria-label="Paginación"><ul class="pagination">';

    // Enlace anterior
    if ($currentPage > 1) {
        echo '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage - 1) . '">&laquo; Anterior</a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link">&laquo; Anterior</span></li>';
    }

    // Números de página
    $start = max(1, $currentPage - 2);
    $end = min($start + 4, $totalPages);

    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) {
            echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            echo '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
        }
    }

    // Enlace siguiente
    if ($currentPage < $totalPages) {
        echo '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage + 1) . '">Siguiente &raquo;</a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link">Siguiente &raquo;</span></li>';
    }

    echo '</ul></nav>';
}

// Helpers funciones de cache
function syncLogStart(PDO $pdo, string $endpoint, string $syncType, ?string $sinceParam): int
{
    $sql = "INSERT INTO api_sync_log (endpoint, sync_type, since_param, started_at, status)
            VALUES (:endpoint, :sync_type, :since_param, NOW(), 'ok')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':endpoint' => $endpoint,
        ':sync_type' => $syncType,
        ':since_param' => $sinceParam
    ]);
    return (int) $pdo->lastInsertId();
}

function syncLogFinish(PDO $pdo, int $logId, string $status, int $upserted, int $deleted = 0, ?string $errorMsg = null): void
{
    $sql = "UPDATE api_sync_log
               SET finished_at = NOW(),
                   status = :status,
                   items_upserted = :upserted,
                   items_deleted = :deleted,
                   error_message = :error
             WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':status' => $status,
        ':upserted' => $upserted,
        ':deleted' => $deleted,
        ':error' => $errorMsg,
        ':id' => $logId
    ]);
}

function fechaDMYtoYMD(string $dmy): string
{
    $dt = DateTime::createFromFormat('d/m/Y', $dmy);
    if (!$dt)
        $dt = new DateTime($dmy);
    return $dt->format('Y-m-d');
}

function isTodayOrAfter(string $ymd): bool
{
    $today = (new DateTime('today'))->format('Y-m-d');
    return $ymd >= $today;
}

/**
 * Canonicaliza un conjunto de proveedores:
 * - string cast
 * - trim
 * - elimina duplicados vacíos
 * - ordena asc
 * - devuelve [array_normalizado, json, sha256]
 */
function canonicalizarKprvList(array $kprvList): array
{
    $norm = array_values(array_filter(array_map(function ($x) {
        $s = trim((string) $x);
        return $s === '' ? null : $s;
    }, $kprvList)));
    $norm = array_values(array_unique($norm));
    sort($norm, SORT_STRING);

    $json = json_encode($norm, JSON_UNESCAPED_UNICODE);
    $hash = hash('sha256', $json);

    return [$norm, $json, $hash];
}

/**
 * Convierte números “latam” (coma decimal y/o punto de miles) a formato “en” (punto decimal).
 * Devuelve string normalizado (ej. "0.57") listo para DECIMAL en MySQL.
 */
function normalizarNumeroLatam($valor, ?int $scale = null): string
{
    $s = trim((string) $valor);
    $s = str_replace([" ", "\u{00A0}"], "", $s);
    if (str_contains($s, '.') && str_contains($s, ',')) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (str_contains($s, ',')) {
        $s = str_replace(',', '.', $s);
    }
    if (!is_numeric($s))
        return '0';
    return $scale !== null ? number_format((float) $s, $scale, '.', '') : $s;
}