<?php
/**
 * Endpoint de autenticación
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

// Validar datos
if (empty($data['email']) || empty($data['password'])) {
    jsonResponse(['error' => 'Credenciales incompletas'], 400);
}

// Buscar usuario
$user = fetchOne("SELECT * FROM usuarios WHERE email = ?", [$data['email']]);

if (!$user || !password_verify($data['password'], $user['password_hash'])) {
    jsonResponse(['error' => 'Credenciales inválidas'], 401);
}

// Crear token de sesión
$sessionToken = bin2hex(random_bytes(32));

// Actualizar última conexión
executeQuery("UPDATE usuarios SET last_login = NOW() WHERE id = ?", [$user['id']]);

jsonResponse([
    'token' => $sessionToken,
    'user' => [
        'id' => $user['id'],
        'nombre' => $user['nombre'],
        'rol' => $user['rol'],
        'rut' => $user['rut']
    ]
]);
