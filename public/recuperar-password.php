<?php
/**
 * Página de recuperación de contraseña
 * 
 * Este archivo gestiona el proceso de recuperación de contraseñas utilizando
 * el sistema robusto de autenticación con registro detallado de eventos.
 * Implementa el patrón MVC para mejor organización.
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.1
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar dependencias
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/Logger.php';
require_once __DIR__ . '/../includes/ErrorHandler.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/PasswordController.php';

// Inicializar los controladores
$authController = new AuthController();
$passwordController = new PasswordController();

// Generar un nuevo token CSRF
$csrfToken = $authController->generateCSRFToken();

// Redirigir si ya está autenticado
if ($authController->isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

// Procesar el formulario de recuperación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener datos del formulario
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validar token CSRF
    if (!$authController->validateCSRFToken($csrf_token)) {
        $error = "Error de seguridad: token CSRF inválido. Por favor, recargue la página e intente de nuevo.";
    } else {
        // Procesar la solicitud de recuperación usando el controlador
        $result = $passwordController->requestPasswordReset($email);
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
    
    // Generar un nuevo token CSRF después de cada intento
    $csrfToken = $authController->generateCSRFToken();
}

// Incluir encabezado simplificado para login
$pageTitle = "Recuperar Contraseña";
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
                <h4 class="m-0 fw-bold">Recuperación de Contraseña</h4>
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
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success mb-4">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                    <div class="text-center mb-3">
                        <a href="login.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i> Volver al inicio de sesión
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-4">
                        Ingrese su correo electrónico para recibir un enlace que le permitirá restablecer su contraseña.
                    </p>
                    <form method="post" action="recuperar-password.php" class="login-form">
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
                        
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-login">
                                <i class="fas fa-paper-plane me-2"></i> Enviar enlace de recuperación
                            </button>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="login.php" class="text-decoration-none text-muted">
                                <i class="fas fa-arrow-left me-1"></i> Volver al inicio de sesión
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            <div class="login-footer">
                <span>Sistema B2B AnimalsCenter - <?php echo date('Y'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
