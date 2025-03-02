<?php
/**
 * Página de inicio de sesión
 */

// Iniciar sesión primero
session_start();

// Cargar dependencias
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

// Redirigir si ya está autenticado
redirectIfAuthenticated();

$error = '';

// Procesar el formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener datos del formulario
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validar CSRF token
    if (!validateCsrfToken($csrf_token)) {
        $error = "Error de seguridad: token CSRF inválido. Por favor, recargue la página e intente de nuevo.";
    }
    // Validar email y contraseña
    elseif (empty($email) || empty($password)) {
        $error = "Por favor, complete todos los campos.";
    } else {
        // Buscar usuario en la base de datos
        try {
            $user = fetchOne("SELECT * FROM usuarios WHERE email = ?", [$email]);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Login exitoso
                login($user);
                
                // Redirigir según rol
                header("Location: dashboard.php");
                exit;
            } else {
                // Credenciales inválidas
                $error = "Email o contraseña incorrectos.";
                
                // Registrar intento fallido de login
                logLoginAttempt($email, false);
            }
        } catch (Exception $e) {
            $error = "Error al procesar su solicitud. Por favor, intente de nuevo más tarde.";
            error_log("Error en login: " . $e->getMessage());
        }
    }
}

// Generar token CSRF
$csrfToken = generateCsrfToken();

// Incluir encabezado simplificado para login
$pageTitle = "Inicio de Sesión";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - AnimalsCenter</title>
    
    <!-- Fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="img/favicon.ico" type="image/x-icon">
</head>
<body>
    <div class="login-container">
        <div class="login-card card animate-fade-in">
            <div class="login-header">
                <h4 class="m-0 fw-bold">Acceso al Sistema B2B</h4>
            </div>
            <div class="login-body">
                <div class="text-center mb-4">
                    <img src="img/logo.png" alt="Logo AnimalsCenter" class="login-logo">
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="login.php" class="login-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="mb-4">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="far fa-envelope text-muted"></i>
                            </span>
                            <input type="email" class="form-control border-start-0" id="email" name="email" 
                                   placeholder="correo@ejemplo.com" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="d-flex justify-content-between">
                            <label for="password" class="form-label">Contraseña</label>
                            <a href="#" class="text-decoration-none text-muted small">¿Olvidó su contraseña?</a>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" class="form-control border-start-0" id="password" name="password" required>
                        </div>
                    </div>
                    
                    <div class="d-grid mt-5">
                        <button type="submit" class="btn btn-primary btn-login">
                            Iniciar sesión
                        </button>
                    </div>
                </form>
            </div>
            <div class="login-footer">
                <span>Sistema B2B AnimalsCenter - <?php echo date('Y'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script para mostrar/ocultar contraseña
        document.addEventListener('DOMContentLoaded', function() {
            // Agregar funcionalidad si se necesita (mostrar/ocultar contraseña, etc.)
        });
    </script>
</body>
</html>
