<?php
/**
 * Mi Perfil
 * Este archivo permite a los usuarios modificar sus datos personales
 */

// Incluir archivos necesarios
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/security.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Iniciar sesión y verificar que esté autenticado
startSession();
requireLogin();

// Obtener datos del usuario actual
$userId = $_SESSION['user_id'];
$usuario = fetchOne("SELECT * FROM usuarios WHERE id = ?", [$userId]);

if (!$usuario) {
    setFlashMessage('error', 'No se pudo cargar la información de tu perfil.');
    redirect('dashboard.php');
}

// Procesar el formulario de actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_perfil'])) {
    // Verificar token CSRF
    // if (!checkCsrfToken()) {
    //     setFlashMessage('error', 'Error de seguridad. Por favor, intenta nuevamente.');
    //     redirect('perfil.php');
    // }

    // Obtener y sanear datos del formulario
    $nombre = sanitizeInput($_POST['nombre']);
    $email = sanitizeInput($_POST['email']);
    $rut = sanitizeInput($_POST['rut']);
    $password = $_POST['password'] ?? '';
    $confirmarPassword = $_POST['confirmar_password'] ?? '';

    // Validaciones básicas
    $errores = [];

    if (empty($nombre)) {
        $errores[] = 'El nombre es obligatorio.';
    }

    // El email no es obligatorio, pero si se proporciona debe ser válido
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El formato del email es inválido.';
    }

    if (empty($rut)) {
        $errores[] = 'El RUT es obligatorio.';
    }

    // Validar RUT chileno (acepta con o sin formato)
    $rutOriginal = $rut; // Guardar el original para mostrar errores

    // Validar que el RUT tenga solo números y máximo 6 dígitos
    if (!preg_match('/^[0-9]{1,8}$/', $rut)) {
        $errores[] = 'El RUT debe contener solo números, sin puntos ni guión, y tener un máximo de 8 dígitos. Ejemplo válido: 781392';
    }


    // Verificar si el email ya existe para otro usuario
    if (!empty($email) && $email !== $usuario['email']) {
        $existeEmail = fetchOne("SELECT id FROM usuarios WHERE email = ? AND id != ?", [$email, $userId]);
        if ($existeEmail) {
            $errores[] = 'El email ya está registrado por otro usuario.';
        }
    }

    // Verificar si el RUT ya existe para otro usuario
    if ($rut !== $usuario['rut']) {
        $existeRut = fetchOne("SELECT id FROM usuarios WHERE rut = ? AND id != ?", [$rut, $userId]);
        if ($existeRut) {
            $errores[] = 'El RUT ya está registrado por otro usuario.';
        }
    }

    // Validar contraseña si se proporcionó una nueva
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($password !== $confirmarPassword) {
            $errores[] = 'Las contraseñas no coinciden.';
        }
    }

    // Si no hay errores, actualizar el perfil
    if (empty($errores)) {
        // Preparar la consulta SQL según si se cambió la contraseña o no
        $sql = "UPDATE usuarios SET nombre = ?, email = ?, rut = ? WHERE id = ?";
        $params = [$nombre, $email, $rut, $userId];

        if (!empty($password)) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE usuarios SET nombre = ?, email = ?, rut = ?, password_hash = ? WHERE id = ?";
            $params = [$nombre, $email, $rut, $passwordHash, $userId];
        }

        if (executeQuery($sql, $params)) {
            // Actualizar la información de sesión
            $_SESSION['nombre'] = $nombre;
            $_SESSION['email'] = $email;
            $_SESSION['rut'] = $rut;

            setFlashMessage('success', 'Tu perfil ha sido actualizado correctamente.');
            redirect('perfil.php');
        } else {
            setFlashMessage('error', 'Error al actualizar el perfil.');
        }
    } else {
        // Mostrar errores
        foreach ($errores as $error) {
            setFlashMessage('error', $error);
        }
    }
}

// Título de la página
$pageTitle = 'Mi Perfil';

// Incluir el encabezado
include 'header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1><i class="fas fa-user-circle me-2"></i>Mi Perfil</h1>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Editar información personal</h5>
                </div>
                <div class="card-body">
                    <form action="perfil.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="actualizar_perfil" value="1">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nombre" class="form-label">Nombre completo</label>
                                <input type="text" class="form-control" id="nombre" name="nombre"
                                       value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>" autocomplete="off">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="rut" class="form-label"> (Sin digito verificador)</label>
                                <input type="text" class="form-control" id="rut" name="rut"
                                       placeholder="Ej: 12.345.678-9"
                                       value="<?php echo !empty($usuario['rut']) ? htmlspecialchars($usuario['rut']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Rol</label>
                                <input type="text" class="form-control" value="<?php echo $usuario['rol'] === 'admin' ? 'Administrador' : 'Proveedor'; ?>" disabled>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Nueva contraseña</label>
                                <input type="password" class="form-control" id="password" name="password" autocomplete="new-password">
                                <div class="form-text">Dejar en blanco para mantener la contraseña actual</div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirmar_password" class="form-label">Confirmar nueva contraseña</label>
                                <input type="password" class="form-control" id="confirmar_password" name="confirmar_password" autocomplete="new-password">
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="dashboard.php" class="btn btn-secondary me-md-2">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Actualizar Perfil</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
