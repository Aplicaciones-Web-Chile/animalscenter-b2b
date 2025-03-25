<?php
/**
 * Script para actualizar o crear usuarios de prueba
 * Este script actualiza o crea los usuarios de prueba con las credenciales especificadas
 */

// Establecer variables de entorno manualmente
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_NAME'] = 'b2b_database';
$_ENV['DB_USER'] = 'root';
$_ENV['DB_PASS'] = '';
$_ENV['DB_PORT'] = '3306';
$_ENV['APP_ENV'] = 'development';

// Cargar dependencias
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

// Definir usuarios de prueba
$usuarios = [
    [
        'nombre' => 'Administrador',
        'email' => 'admin@animalscenter.cl',
        'password' => 'AdminB2B123',
        'rol' => 'admin',
        'rut' => '11.111.111-1'
    ],
    [
        'nombre' => 'Proveedor Ejemplo',
        'email' => 'proveedor@ejemplo.com',
        'password' => 'ProveedorAC2024',
        'rol' => 'proveedor',
        'rut' => '22.222.222-2'
    ]
];

// Función para actualizar o crear usuario
function actualizarOCrearUsuario($usuario) {
    try {
        // Verificar si el usuario existe por email o por RUT
        $usuarioExistentePorEmail = fetchOne("SELECT * FROM usuarios WHERE email = ?", [$usuario['email']]);
        $usuarioExistentePorRut = fetchOne("SELECT * FROM usuarios WHERE rut = ?", [$usuario['rut']]);
        
        // Hash de la contraseña
        $passwordHash = password_hash($usuario['password'], PASSWORD_DEFAULT);
        
        // Si existe por email, actualizamos ese usuario
        if ($usuarioExistentePorEmail) {
            $sql = "UPDATE usuarios SET 
                    nombre = ?, 
                    password_hash = ?, 
                    rol = ? 
                    WHERE email = ?";
                    
            $params = [
                $usuario['nombre'],
                $passwordHash,
                $usuario['rol'],
                $usuario['email']
            ];
            
            // Si el RUT es diferente y no pertenece a otro usuario, actualizarlo también
            if ($usuarioExistentePorEmail['rut'] !== $usuario['rut']) {
                if (!$usuarioExistentePorRut || $usuarioExistentePorRut['email'] === $usuario['email']) {
                    $sql = "UPDATE usuarios SET 
                            nombre = ?, 
                            password_hash = ?, 
                            rol = ?,
                            rut = ? 
                            WHERE email = ?";
                    $params = [
                        $usuario['nombre'],
                        $passwordHash,
                        $usuario['rol'],
                        $usuario['rut'],
                        $usuario['email']
                    ];
                } else {
                    echo "⚠️ No se pudo actualizar el RUT para '{$usuario['email']}' porque ya está asignado a otro usuario.\n";
                }
            }
            
            if (executeQuery($sql, $params)) {
                echo "✅ Usuario '{$usuario['email']}' actualizado correctamente.\n";
            } else {
                echo "❌ Error al actualizar el usuario '{$usuario['email']}'.\n";
            }
        }
        // Si existe por RUT pero no por email, actualizamos ese usuario
        else if ($usuarioExistentePorRut) {
            $sql = "UPDATE usuarios SET 
                    nombre = ?, 
                    email = ?,
                    password_hash = ?, 
                    rol = ? 
                    WHERE rut = ?";
                    
            $params = [
                $usuario['nombre'],
                $usuario['email'],
                $passwordHash,
                $usuario['rol'],
                $usuario['rut']
            ];
            
            if (executeQuery($sql, $params)) {
                echo "✅ Usuario con RUT '{$usuario['rut']}' actualizado correctamente.\n";
            } else {
                echo "❌ Error al actualizar el usuario con RUT '{$usuario['rut']}'.\n";
            }
        }
        // Si no existe ni por email ni por RUT, lo creamos
        else {
            $sql = "INSERT INTO usuarios (nombre, email, password_hash, rol, rut) 
                    VALUES (?, ?, ?, ?, ?)";
                    
            $params = [
                $usuario['nombre'],
                $usuario['email'],
                $passwordHash,
                $usuario['rol'],
                $usuario['rut']
            ];
            
            if (executeQuery($sql, $params)) {
                echo "✅ Usuario '{$usuario['email']}' creado correctamente.\n";
            } else {
                echo "❌ Error al crear el usuario '{$usuario['email']}'.\n";
            }
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

// Procesar cada usuario
echo "Iniciando actualización de usuarios de prueba...\n";
echo "=============================================\n";

foreach ($usuarios as $usuario) {
    actualizarOCrearUsuario($usuario);
}

echo "=============================================\n";
echo "Proceso completado.\n";
