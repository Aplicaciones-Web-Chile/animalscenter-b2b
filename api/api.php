<?php
/**
 * Controlador principal de la API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Configurar manejo de errores
set_error_handler(function($severity, $message, $file, $line) {
    jsonResponse(['error' => $message], 500);
});

// Validar IP permitida
if (!isAllowedIP()) {
    jsonResponse(['error' => 'Acceso no autorizado'], 403);
}

// Obtener ruta de la solicitud
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$endpoint = str_replace('/api/', '', $requestUri);

// Enrutamiento básico
switch ($endpoint) {
    case 'login':
        require 'auth.php';
        break;
    case 'productos':
        require 'productos.php';
        break;
    case 'ventas':
        require 'ventas.php';
        break;
    case 'facturas':
        require 'facturas.php';
        break;
    default:
        jsonResponse(['error' => 'Endpoint no encontrado'], 404);
}
