<?php
/**
 * Script para corregir la configuración de sesiones
 * Este script modifica los archivos necesarios para unificar la configuración de sesiones
 */

echo "Corrigiendo configuración de sesiones...\n";

// 1. Modificar includes/session.php
$sessionFile = __DIR__ . '/../includes/session.php';
if (file_exists($sessionFile)) {
    $content = file_get_contents($sessionFile);
    
    // Buscar la función startSession
    $pattern = '/function\s+startSession\s*\(\s*\)\s*\{[^}]*\}/s';
    $replacement = "function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configurar opciones de sesión
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false, // Cambiar a true en producción
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        session_start();
        return true;
    }
    return false;
}";
    
    $newContent = preg_replace($pattern, $replacement, $content);
    
    if ($newContent !== $content) {
        file_put_contents($sessionFile, $newContent);
        echo "✅ Archivo includes/session.php actualizado correctamente.\n";
    } else {
        echo "⚠️ No se pudieron aplicar cambios a includes/session.php automáticamente.\n";
    }
} else {
    echo "❌ No se encontró el archivo includes/session.php\n";
}

// 2. Verificar que todos los archivos principales utilizan startSession
$files = [
    __DIR__ . '/../public/index.php',
    __DIR__ . '/../public/login.php',
    __DIR__ . '/../public/dashboard.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Verificar si utiliza session_start directamente
        if (strpos($content, 'session_start()') !== false) {
            $basename = basename($file);
            echo "⚠️ El archivo {$basename} usa session_start() directamente.\n";
            echo "   Considere reemplazarlo por require_once __DIR__ . '/../includes/session.php';\n";
            echo "   y luego llamar a startSession();\n";
        }
    }
}

echo "\nProceso completado. Verifique los cambios realizados antes de continuar.\n";