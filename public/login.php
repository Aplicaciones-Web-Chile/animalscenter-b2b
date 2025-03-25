<?php
/**
 * Página de inicio de sesión simplificada
 * Sin validación CSRF para facilitar el proceso
 */

// Cargar configuración de la aplicación
require_once __DIR__ . '/../config/app.php';

// Incluir archivos necesarios
require_once APP_ROOT . '/controllers/AuthController.php';
require_once APP_ROOT . '/includes/session.php';

// Iniciar sesión
startSession();

// Inicializar controlador de autenticación
$authController = new AuthController();

// Si el usuario ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Procesar formulario enviado
$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar credenciales
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor, complete todos los campos.';
    } else {
        // Intentar login
        $result = $authController->login($username, $password);
        
        if ($result['success']) {
            session_write_close(); // Importante: guardar la sesión antes de redirigir
            header('Location: ' . ($result['redirect'] ?? 'dashboard.php'));
            exit;
        } else {
            $error = $result['message'] ?? 'Credenciales inválidas.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión - AnimalsCenter</title>
    
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
                
                <form method="POST" action="login.php" class="needs-validation" novalidate>
                    
                    <div class="mb-4">
                        <label for="username" class="form-label">Usuario o correo electrónico</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="far fa-user text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" id="username" name="username" 
                                   placeholder="nombre.usuario o correo@ejemplo.com" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="d-flex justify-content-between">
                            <label for="password" class="form-label">Contraseña</label>
                            <a href="recuperar-password.php" class="text-decoration-none text-muted small">¿Olvidó su contraseña?</a>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" class="form-control border-start-0" id="password" name="password" required>
                            <button class="btn btn-outline-secondary border border-start-0" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                        <label class="form-check-label" for="remember_me">Mantener sesión iniciada</label>
                    </div>
                    
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-login">
                            <i class="fas fa-sign-in-alt me-2"></i> Iniciar sesión
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
        });
    </script>
</body>
</html>
