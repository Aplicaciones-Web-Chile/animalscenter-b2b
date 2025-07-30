<?php
/**
 * Página de restablecimiento de contraseña
 * 
 * Este archivo gestiona el proceso de restablecimiento de contraseñas
 * después de que el usuario haya solicitado recuperarla. Verifica
 * la validez del token y permite al usuario establecer una nueva contraseña.
 * Implementa el patrón MVC para mejor organización.
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.1
 */

// Cargar dependencias y configuraciones ANTES de iniciar la sesión
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';

// Iniciar sesión después de cargar las configuraciones
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/Logger.php';
require_once __DIR__ . '/../includes/ErrorHandler.php';
require_once __DIR__ . '/../includes/AuthHandler.php';
require_once __DIR__ . '/../controllers/PasswordController.php';

// Inicializar los controladores
$authHandler = new AuthHandler();
$passwordController = new PasswordController();

// Generar un nuevo token CSRF
$csrfToken = $authHandler->generateCSRFToken();

// Redirigir si ya está autenticado
if ($authHandler->isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';
$email = '';
$tokenValido = false;
$token = $_GET['token'] ?? '';
$tokenData = null;

// Validar el token
if (!empty($token)) {
    // Usar el controlador para validar el token
    $tokenResult = $passwordController->validateResetToken($token);
    
    if ($tokenResult['success']) {
        $tokenValido = true;
        $tokenData = $tokenResult['data'];
        $email = $tokenData['email'];
    } else {
        $error = $tokenResult['message'];
    }
} else {
    $error = "Se requiere un token de restablecimiento válido para continuar.";
}

// Procesar el formulario de restablecimiento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValido) {
    // Obtener datos del formulario
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $formToken = $_POST['token'] ?? '';
    $formEmail = $_POST['email'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validar token CSRF
    if (!$authHandler->validateCSRFToken($csrf_token)) {
        $error = "Error de seguridad: token CSRF inválido. Por favor, recargue la página e intente de nuevo.";
    }
    // Validar que los datos del token coincidan
    elseif ($formToken !== $token || $formEmail !== $email) {
        $error = "Error de seguridad: datos de restablecimiento inválidos.";
        
        // Registrar posible manipulación
        Logger::warning("Posible manipulación de datos en formulario de restablecimiento", Logger::SECURITY, [
            'expected_token' => $token,
            'submitted_token' => $formToken,
            'expected_email' => $email,
            'submitted_email' => $formEmail,
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
    } else {
        // Usar el controlador para restablecer la contraseña
        $resetResult = $passwordController->resetPassword($token, $password, $confirmPassword);
        
        if ($resetResult['success']) {
            $success = $resetResult['message'];
        } else {
            $error = $resetResult['message'];
        }
    }
    
    // Generar un nuevo token CSRF después de cada intento
    $csrfToken = $authHandler->generateCSRFToken();
}

// Incluir encabezado simplificado
$pageTitle = "Restablecer Contraseña";
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

    <style>
        .password-strength {
            height: 5px;
            transition: all 0.3s ease;
            margin-top: 8px;
        }
        .password-feedback {
            font-size: 12px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card card animate-fade-in">
            <div class="login-header">
                <h4 class="m-0 fw-bold">Restablecer Contraseña</h4>
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
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i> Ir al inicio de sesión
                        </a>
                    </div>
                <?php elseif ($tokenValido): ?>
                    <p class="text-muted mb-4">
                        Ingrese su nueva contraseña a continuación. Asegúrese de elegir una contraseña segura.
                    </p>
                    <form method="post" id="passwordResetForm" class="login-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">Nueva contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fas fa-lock text-muted"></i>
                                </span>
                                <input type="password" class="form-control border-start-0" id="password" name="password" 
                                       minlength="8" required>
                                <button class="btn btn-outline-secondary border border-start-0" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength bg-light rounded"></div>
                            <div class="password-feedback text-muted"></div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirmar nueva contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fas fa-lock text-muted"></i>
                                </span>
                                <input type="password" class="form-control border-start-0" id="confirm_password" 
                                       name="confirm_password" minlength="8" required>
                            </div>
                        </div>
                        
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-login">
                                <i class="fas fa-save me-2"></i> Guardar nueva contraseña
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-center">
                        <p>
                            <i class="fas fa-link-slash fa-3x text-muted mb-3"></i>
                        </p>
                        <p class="text-muted">
                            El enlace para restablecer su contraseña no es válido o ha expirado.
                        </p>
                        <a href="recuperar-password.php" class="btn btn-outline-primary mt-3">
                            <i class="fas fa-redo me-2"></i> Solicitar nuevo enlace
                        </a>
                        <div class="mt-3">
                            <a href="login.php" class="text-decoration-none text-muted">
                                <i class="fas fa-arrow-left me-1"></i> Volver al inicio de sesión
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="login-footer">
                <span>Sistema B2B AnimalsCenter - <?php echo date('Y'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($tokenValido): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Función para mostrar/ocultar contraseña
            const togglePassword = document.querySelector('#togglePassword');
            const password = document.querySelector('#password');
            
            togglePassword.addEventListener('click', function() {
                // Cambiar el tipo de input
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                
                // Cambiar el ícono
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
            
            // Validación de contraseñas coincidentes
            const confirmPassword = document.querySelector('#confirm_password');
            const form = document.querySelector('#passwordResetForm');
            
            form.addEventListener('submit', function(e) {
                if (password.value !== confirmPassword.value) {
                    e.preventDefault();
                    alert('Las contraseñas no coinciden');
                }
            });
            
            // Evaluador de fortaleza de contraseña
            const strengthBar = document.querySelector('.password-strength');
            const feedbackElement = document.querySelector('.password-feedback');
            
            password.addEventListener('input', function() {
                const val = password.value;
                let strength = 0;
                let feedback = '';
                
                // Criterios de fortaleza
                if (val.length >= 8) strength += 1;
                if (val.length >= 10) strength += 1;
                if (/[A-Z]/.test(val)) strength += 1;
                if (/[0-9]/.test(val)) strength += 1;
                if (/[^A-Za-z0-9]/.test(val)) strength += 1;
                
                // Determinar color y mensaje según fortaleza
                switch (strength) {
                    case 0:
                    case 1:
                        strengthBar.style.width = '20%';
                        strengthBar.className = 'password-strength bg-danger';
                        feedback = 'Muy débil: Aumente la longitud y use diferentes tipos de caracteres.';
                        break;
                    case 2:
                        strengthBar.style.width = '40%';
                        strengthBar.className = 'password-strength bg-warning';
                        feedback = 'Débil: Intente incluir mayúsculas, números y símbolos.';
                        break;
                    case 3:
                        strengthBar.style.width = '60%';
                        strengthBar.className = 'password-strength bg-info';
                        feedback = 'Buena: Incluya más variedad de caracteres para mayor seguridad.';
                        break;
                    case 4:
                        strengthBar.style.width = '80%';
                        strengthBar.className = 'password-strength bg-primary';
                        feedback = 'Fuerte: Buena combinación de caracteres.';
                        break;
                    case 5:
                        strengthBar.style.width = '100%';
                        strengthBar.className = 'password-strength bg-success';
                        feedback = 'Muy fuerte: Excelente combinación de longitud y tipos de caracteres.';
                        break;
                }
                
                feedbackElement.textContent = feedback;
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>
