<?php
// Configuración de la base de datos
$host = 'db';
$dbname = 'b2b_database';
$username = 'root';
$password = 'secret';

try {
    // Conectar a la base de datos
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Generar hash de contraseña
    $email = 'admin@animalscenter.cl';
    $plainPassword = 'AdminB2B123';
    $passwordHash = password_hash($plainPassword, PASSWORD_BCRYPT);
    
    // Actualizar usuario
    $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE email = ?");
    $result = $stmt->execute([$passwordHash, $email]);
    
    if ($result) {
        echo "Contraseña actualizada correctamente.<br>";
        
        // Verificar el usuario actualizado
        $stmt = $pdo->prepare("SELECT id, nombre, email, rol, password_hash FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "Usuario encontrado:<br>";
            echo "ID: " . $user['id'] . "<br>";
            echo "Nombre: " . $user['nombre'] . "<br>";
            echo "Email: " . $user['email'] . "<br>";
            echo "Rol: " . $user['rol'] . "<br>";
            echo "Hash de contraseña: " . $user['password_hash'] . "<br>";
            
            // Verificar que la contraseña funciona
            $verify = password_verify($plainPassword, $user['password_hash']);
            echo "Verificación de contraseña: " . ($verify ? "CORRECTA" : "INCORRECTA") . "<br>";
        } else {
            echo "Usuario no encontrado.";
        }
    } else {
        echo "Error al actualizar la contraseña.";
    }
} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage();
}
?>
