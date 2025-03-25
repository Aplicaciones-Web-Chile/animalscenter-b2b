<?php
/**
 * Funciones auxiliares globales
 * 
 * Colección de funciones de utilidad disponibles en toda la aplicación
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

/**
 * Obtiene el valor de una variable de entorno
 * 
 * @param string $key Clave de la variable
 * @param mixed $default Valor por defecto
 * @return mixed Valor de la variable o valor por defecto
 */
function env($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

/**
 * Obtiene una configuración de la aplicación
 * 
 * @param string $key Clave de configuración
 * @param mixed $default Valor por defecto
 * @return mixed Valor de configuración o valor por defecto
 */
function config($key, $default = null) {
    $app = App\Core\App::getInstance();
    return $app->getConfig($key, $default);
}

/**
 * Genera una URL base
 * 
 * @param string $path Ruta relativa
 * @return string URL completa
 */
function url($path = '') {
    $baseUrl = env('APP_URL', 'http://localhost');
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

/**
 * Genera una URL de asset
 * 
 * @param string $path Ruta relativa al asset
 * @return string URL completa del asset
 */
function asset($path) {
    return url('assets/' . ltrim($path, '/'));
}

/**
 * Redirecciona a una URL
 * 
 * @param string $url URL de destino
 * @param int $statusCode Código de estado HTTP
 * @return void
 */
function redirect($url, $statusCode = 302) {
    header('Location: ' . $url, true, $statusCode);
    exit;
}

/**
 * Formatea una fecha en formato legible
 * 
 * @param string $date Fecha en formato Y-m-d o Y-m-d H:i:s
 * @param string $format Formato de salida
 * @return string Fecha formateada
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) {
        return '';
    }
    
    $dateTime = new DateTime($date);
    return $dateTime->format($format);
}

/**
 * Formatea un número como moneda
 * 
 * @param float $amount Cantidad
 * @param string $symbol Símbolo de moneda
 * @param int $decimals Número de decimales
 * @return string Cantidad formateada
 */
function formatCurrency($amount, $symbol = '$', $decimals = 0) {
    return $symbol . ' ' . number_format((float) $amount, $decimals, ',', '.');
}

/**
 * Sanea un string para prevenir XSS
 * 
 * @param string $string String a sanear
 * @return string String saneado
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Verifica si el usuario está autenticado
 * 
 * @return bool True si está autenticado
 */
function isAuthenticated() {
    $session = new App\Core\Session();
    return $session->isAuthenticated();
}

/**
 * Obtiene el usuario autenticado
 * 
 * @return array|null Datos del usuario o null
 */
function currentUser() {
    $session = new App\Core\Session();
    return $session->getUser();
}

/**
 * Genera un token CSRF
 * 
 * @return string Token CSRF
 */
function csrfToken() {
    $session = new App\Core\Session();
    
    if (!$session->has('csrf_token')) {
        $token = bin2hex(random_bytes(32));
        $session->set('csrf_token', $token);
    }
    
    return $session->get('csrf_token');
}

/**
 * Genera un campo de formulario oculto con el token CSRF
 * 
 * @return string Campo HTML
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

/**
 * Verifica si un token CSRF es válido
 * 
 * @param string $token Token a verificar
 * @return bool True si es válido
 */
function verifyCsrfToken($token) {
    $session = new App\Core\Session();
    return $token === $session->get('csrf_token');
}

/**
 * Trunca un texto a una longitud máxima
 * 
 * @param string $text Texto a truncar
 * @param int $length Longitud máxima
 * @param string $append Texto a añadir al final
 * @return string Texto truncado
 */
function truncate($text, $length = 100, $append = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    $text = substr($text, 0, $length);
    return rtrim($text) . $append;
}

/**
 * Genera un slug a partir de un texto
 * 
 * @param string $text Texto a convertir
 * @return string Slug generado
 */
function slugify($text) {
    // Reemplazar caracteres especiales
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    
    // Transliterar
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    
    // Eliminar caracteres no deseados
    $text = preg_replace('~[^-\w]+~', '', $text);
    
    // Eliminar guiones extra
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    
    // Convertir a minúsculas
    $text = strtolower($text);
    
    return $text;
}

/**
 * Valida un RUT chileno
 * 
 * @param string $rut RUT a validar
 * @return bool True si es válido
 */
function validarRut($rut) {
    // Limpiar y formatear RUT
    $rut = preg_replace('/[^k0-9]/i', '', $rut);
    $dv = substr($rut, -1);
    $numero = substr($rut, 0, strlen($rut) - 1);
    
    // Calcular dígito verificador
    $i = 2;
    $suma = 0;
    
    foreach (array_reverse(str_split($numero)) as $v) {
        if ($i == 8) {
            $i = 2;
        }
        
        $suma += $v * $i;
        $i++;
    }
    
    $dvr = 11 - ($suma % 11);
    
    if ($dvr == 11) {
        $dvr = 0;
    }
    
    if ($dvr == 10) {
        $dvr = 'K';
    }
    
    return strtoupper($dv) == strtoupper($dvr);
}

/**
 * Formatea un RUT chileno
 * 
 * @param string $rut RUT a formatear
 * @return string RUT formateado
 */
function formatRut($rut) {
    $rut = preg_replace('/[^k0-9]/i', '', $rut);
    $dv = substr($rut, -1);
    $numero = substr($rut, 0, strlen($rut) - 1);
    
    $numero = number_format($numero, 0, '', '.');
    
    return $numero . '-' . $dv;
}

/**
 * Obtiene el nombre del mes en español
 * 
 * @param int $month Número de mes (1-12)
 * @return string Nombre del mes
 */
function nombreMes($month) {
    $meses = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre'
    ];
    
    return $meses[(int) $month] ?? '';
}

/**
 * Verifica si una cadena está vacía (null, '', '0')
 * 
 * @param mixed $value Valor a verificar
 * @return bool True si está vacío
 */
function isEmpty($value) {
    return $value === null || $value === '' || $value === '0';
}

/**
 * Genera un identificador único
 * 
 * @return string Identificador único
 */
function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}

/**
 * Devuelve un mensaje de error de validación
 * 
 * @param array $errors Errores de validación
 * @param string $field Campo a obtener error
 * @return string Mensaje de error o cadena vacía
 */
function errorMessage($errors, $field) {
    return $errors[$field] ?? '';
}

/**
 * Devuelve una clase CSS si hay un error de validación
 * 
 * @param array $errors Errores de validación
 * @param string $field Campo a verificar
 * @param string $class Clase CSS a devolver
 * @return string Clase CSS o cadena vacía
 */
function errorClass($errors, $field, $class = 'is-invalid') {
    return isset($errors[$field]) ? $class : '';
}

/**
 * Genera un log en el archivo de registros
 * 
 * @param string $message Mensaje a registrar
 * @param string $level Nivel de log (info, warning, error)
 * @return void
 */
function logMessage($message, $level = 'info') {
    $logFile = APP_ROOT . '/storage/logs/' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    
    // Crear directorio si no existe
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    // Formatear mensaje
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    
    // Escribir en archivo
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}
