<?php
/**
 * Página de inicio de sesión
 */

// Cargar dependencias
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

// Redirigir si ya está autenticado
redirectIfAuthenticated();

$error = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error = 'Error de seguridad. Por favor, intente nuevamente.';
    } else {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Por favor, complete todos los campos.';
        } else {
            // Buscar usuario por email
            $user = fetchOne("SELECT * FROM usuarios WHERE email = ?", [$email]);
            
            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Credenciales incorrectas. Por favor, intente nuevamente.';
            } else {
                // Iniciar sesión
                login($user);
                
                // Redirigir al dashboard
                header('Location: dashboard.php');
                exit;
            }
        }
    }
}

// Generar token CSRF
$csrfToken = generateCsrfToken();

// Incluir encabezado
$pageTitle = "Inicio de sesión";
include __DIR__ . '/header.php';
?>

<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Acceso al Sistema B2B</h4>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img src="img/logo.png" alt="Logo AnimalsCenter" class="img-fluid" style="max-height: 100px;">
                    </div>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="login.php">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo electrónico</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="correo@ejemplo.com" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Iniciar sesión</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center text-muted">
                    <small>Sistema B2B AnimalsCenter - <?php echo date('Y'); ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
