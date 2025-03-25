<?php
/**
 * Script para corregir el flujo de redirección
 * Este script modifica el archivo login.php para mejorar la gestión de sesiones durante redirecciones
 */

echo "Corrigiendo flujo de redirección...\n";

// Modificar public/login.php
$loginFile = __DIR__ . '/../public/login.php';
if (file_exists($loginFile)) {
    $content = file_get_contents($loginFile);
    
    // Buscar la sección de redirección
    $pattern = '/if\s*\(\s*\$result\s*\[\s*[\'"]success[\'"]\s*\]\s*\)\s*\{[^}]*\}/s';
    $sample = 'if ($result[\'success\']) {
            // Asegurar que la sesión se guarda antes de redireccionar
            session_write_close();
            header("Location: " . ($result[\'redirect\'] ?? \'dashboard.php\'));
            exit;
        }';
    
    // Verificar si contiene la línea session_write_close()
    if (strpos($content, 'session_write_close()') === false) {
        $newContent = preg_replace_callback($pattern, function($matches) {
            // Añadir session_write_close() antes del header
            return str_replace('header(', 'session_write_close();
            header(', $matches[0]);
        }, $content);
        
        if ($newContent !== $content) {
            file_put_contents($loginFile, $newContent);
            echo "✅ Archivo public/login.php actualizado correctamente.\n";
        } else {
            echo "⚠️ No se pudieron aplicar cambios a public/login.php automáticamente.\n";
            echo "   Por favor, añada la línea 'session_write_close();' antes de cada redirección.\n";
        }
    } else {
        echo "✅ El archivo public/login.php ya contiene session_write_close().\n";
    }
} else {
    echo "❌ No se encontró el archivo public/login.php\n";
}

// Verificar si también es necesario en index.php y dashboard.php
$files = [
    __DIR__ . '/../public/index.php',
    __DIR__ . '/../public/dashboard.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Verificar si contiene redirecciones sin session_write_close()
        if (strpos($content, 'header(') !== false && strpos($content, 'session_write_close()') === false) {
            $basename = basename($file);
            echo "⚠️ El archivo {$basename} contiene redirecciones sin session_write_close().\n";
            echo "   Considere añadir session_write_close() antes de cada header().\n";
        }
    }
}

echo "\nProceso completado. Verifique los cambios realizados antes de continuar.\n";