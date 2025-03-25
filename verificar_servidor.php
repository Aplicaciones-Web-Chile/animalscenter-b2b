<?php
/**
 * Script para verificar que el servidor local está funcionando
 */

echo "=== VERIFICACIÓN DE SERVIDOR LOCAL ===\n\n";

$url = "http://localhost:8080/login.php";
echo "Verificando acceso a: $url\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

if ($error) {
    echo "Error de conexión: " . $error . "\n";
} else {
    echo "Código de respuesta HTTP: " . $httpCode . "\n";
    if ($httpCode >= 200 && $httpCode < 300) {
        echo "✅ El servidor responde correctamente\n";
        
        // Verificar si hay formulario de login
        if (strpos($result, 'csrf_token') !== false) {
            echo "✅ Formulario de login con token CSRF encontrado\n";
        } else {
            echo "❌ No se encontró token CSRF en la página de login\n";
        }
    } else {
        echo "❌ El servidor responde con código de error\n";
    }
}

// Verificar si hay servidor web activo en el puerto 8080
echo "\nVerificando servidor web en puerto 8080...\n";
$connection = @fsockopen('localhost', 8080);
if (is_resource($connection)) {
    echo "✅ Servidor web activo en puerto 8080\n";
    fclose($connection);
} else {
    echo "❌ No se detectó servidor web en puerto 8080\n";
    echo "Ejecute los siguientes comandos para iniciar un servidor PHP:\n";
    echo "cd /Users/juanjorquera/Dropbox\\ \\(Personal\\)/Sites/animalscenter/b2b/b2b-app/public\n";
    echo "php -S localhost:8080\n";
}

echo "\n=== FIN DE LA VERIFICACIÓN ===\n";
