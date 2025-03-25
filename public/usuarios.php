<?php
/**
 * Gestión de Usuarios
 * Este archivo permite a los administradores gestionar usuarios del sistema
 */

// Incluir archivos necesarios
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/security.php';

// Iniciar sesión
startSession();

// Verificar que sea administrador
requireAdmin();

// Título de la página
$pageTitle = 'Gestión de Usuarios';

// Procesar formulario de creación/edición de usuario
$mensaje = '';
$tipoMensaje = '';
$usuario = [
    'id' => '',
    'nombre' => '',
    'email' => '',
    'rut' => '',
    'rol' => 'proveedor'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    // Verificar token CSRF
    if (!checkCsrfToken()) {
        setFlashMessage('error', 'Error de seguridad: Token CSRF inválido.');
        redirect('usuarios.php');
    }

    // Obtener y sanitizar datos del formulario
    $nombre = sanitizeInput($_POST['nombre'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $rut = sanitizeInput($_POST['rut'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmarPassword = $_POST['confirmar_password'] ?? '';
    $rol = $_POST['rol'] ?? 'proveedor';
    
    // Validaciones básicas
    $errores = [];
    
    if (empty($nombre)) {
        $errores[] = 'El nombre es obligatorio.';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El email es inválido.';
    }
    
    if (empty($rut)) {
        $errores[] = 'El RUT es obligatorio.';
    }
    
    // Validar RUT chileno (formato básico)
    if (!preg_match('/^[0-9]{1,2}\.[0-9]{3}\.[0-9]{3}-[0-9kK]$/', $rut)) {
        $errores[] = 'El formato del RUT debe ser XX.XXX.XXX-X';
    }
    
    // Validar contraseña para usuarios nuevos
    if ($_POST['accion'] === 'crear') {
        if (empty($password)) {
            $errores[] = 'La contraseña es obligatoria.';
        } elseif (strlen($password) < 6) {
            $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($password !== $confirmarPassword) {
            $errores[] = 'Las contraseñas no coinciden.';
        }
    }
    
    // Si no hay errores, proceder con la acción
    if (empty($errores)) {
        // Crear nuevo usuario
        if ($_POST['accion'] === 'crear') {
            // Verificar si el email ya existe
            $existeEmail = fetchOne("SELECT id FROM usuarios WHERE email = ?", [$email]);
            if ($existeEmail) {
                setFlashMessage('error', 'El email ya está registrado.');
                redirect('usuarios.php');
            }
            
            // Verificar si el RUT ya existe
            $existeRut = fetchOne("SELECT id FROM usuarios WHERE rut = ?", [$rut]);
            if ($existeRut) {
                setFlashMessage('error', 'El RUT ya está registrado.');
                redirect('usuarios.php');
            }
            
            // Hashear contraseña
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insertar nuevo usuario
            $sql = "INSERT INTO usuarios (nombre, email, password_hash, rol, rut) VALUES (?, ?, ?, ?, ?)";
            $params = [$nombre, $email, $passwordHash, $rol, $rut];
            
            if (executeQuery($sql, $params)) {
                setFlashMessage('success', 'Usuario creado correctamente.');
                redirect('usuarios.php');
            } else {
                setFlashMessage('error', 'Error al crear el usuario.');
                redirect('usuarios.php');
            }
        }
        // Editar usuario existente
        elseif ($_POST['accion'] === 'editar' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            
            // Verificar si el email ya existe para otro usuario
            $existeEmail = fetchOne("SELECT id FROM usuarios WHERE email = ? AND id != ?", [$email, $id]);
            if ($existeEmail) {
                setFlashMessage('error', 'El email ya está registrado por otro usuario.');
                redirect('usuarios.php?id=' . $id);
            }
            
            // Verificar si el RUT ya existe para otro usuario
            $existeRut = fetchOne("SELECT id FROM usuarios WHERE rut = ? AND id != ?", [$rut, $id]);
            if ($existeRut) {
                setFlashMessage('error', 'El RUT ya está registrado por otro usuario.');
                redirect('usuarios.php?id=' . $id);
            }
            
            // Actualizar usuario
            $sql = "UPDATE usuarios SET nombre = ?, email = ?, rol = ?, rut = ? WHERE id = ?";
            $params = [$nombre, $email, $rol, $rut, $id];
            
            // Si se proporcionó una nueva contraseña, actualizarla también
            if (!empty($password)) {
                if ($password !== $confirmarPassword) {
                    setFlashMessage('error', 'Las contraseñas no coinciden.');
                    redirect('usuarios.php?id=' . $id);
                }
                
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios SET nombre = ?, email = ?, password_hash = ?, rol = ?, rut = ? WHERE id = ?";
                $params = [$nombre, $email, $passwordHash, $rol, $rut, $id];
            }
            
            if (executeQuery($sql, $params)) {
                setFlashMessage('success', 'Usuario actualizado correctamente.');
                redirect('usuarios.php');
            } else {
                setFlashMessage('error', 'Error al actualizar el usuario.');
                redirect('usuarios.php?id=' . $id);
            }
        }
    } else {
        // Si hay errores, mostrarlos
        $tipoMensaje = 'error';
        $mensaje = implode('<br>', $errores);
        
        // Mantener los datos del formulario
        $usuario = [
            'id' => $_POST['id'] ?? '',
            'nombre' => $nombre,
            'email' => $email,
            'rut' => $rut,
            'rol' => $rol
        ];
    }
}

// Cargar datos de usuario para edición
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $usuario = fetchOne("SELECT id, nombre, email, rol, rut FROM usuarios WHERE id = ?", [$id]);
    
    if (!$usuario) {
        setFlashMessage('error', 'Usuario no encontrado.');
        redirect('usuarios.php');
    }
}

// Eliminar usuario
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar']) && isset($_GET['token'])) {
    // Verificar token CSRF
    if (!validateCsrfToken($_GET['token'])) {
        setFlashMessage('error', 'Error de seguridad: Token CSRF inválido.');
        redirect('usuarios.php');
    }
    
    $id = (int)$_GET['eliminar'];
    
    // No permitir eliminar al usuario actual
    if ($id === (int)$_SESSION['user']['id']) {
        setFlashMessage('error', 'No puedes eliminar tu propio usuario.');
        redirect('usuarios.php');
    }
    
    // Eliminar usuario
    if (executeQuery("DELETE FROM usuarios WHERE id = ?", [$id])) {
        setFlashMessage('success', 'Usuario eliminado correctamente.');
    } else {
        setFlashMessage('error', 'Error al eliminar el usuario.');
    }
    
    redirect('usuarios.php');
}

// Obtener listado de usuarios para la tabla
$usuarios = fetchAll("SELECT id, nombre, email, rol, rut, fecha_creacion FROM usuarios ORDER BY nombre");

// Incluir el encabezado
include 'header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mb-0"><i class="fas fa-users me-2"></i>Gestión de Usuarios</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUsuario">
                    <i class="fas fa-plus me-2"></i>Nuevo Usuario
                </button>
            </div>
        </div>
    </div>
    
    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-<?php echo $tipoMensaje === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Listado de usuarios -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Listado de Usuarios</h5>
        </div>
        <div class="card-body">
            <?php if (empty($usuarios)): ?>
                <div class="alert alert-info">No hay usuarios registrados.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>RUT</th>
                                <th>Rol</th>
                                <th>Fecha de Creación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td><?php echo htmlspecialchars($u['rut']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $u['rol'] === 'admin' ? 'danger' : 'success'; ?>">
                                            <?php echo $u['rol'] === 'admin' ? 'Administrador' : 'Proveedor'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDateTime($u['fecha_creacion']); ?></td>
                                    <td>
                                        <a href="usuarios.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ((int)$u['id'] !== (int)getUserId()): ?>
                                            <a href="usuarios.php?eliminar=<?php echo $u['id']; ?>&token=<?php echo generateCsrfToken(); ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('¿Estás seguro de eliminar este usuario?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para crear/editar usuario -->
<div class="modal fade" id="modalUsuario" tabindex="-1" aria-labelledby="modalUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalUsuarioLabel">
                    <?php echo empty($usuario['id']) ? 'Nuevo Usuario' : 'Editar Usuario'; ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="usuarios.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($usuario['id']); ?>">
                    <input type="hidden" name="accion" value="<?php echo empty($usuario['id']) ? 'crear' : 'editar'; ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label">Nombre completo</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" 
                                   value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="rut" class="form-label">RUT</label>
                            <input type="text" class="form-control" id="rut" name="rut" 
                                   placeholder="Ej: 12.345.678-9"
                                   value="<?php echo htmlspecialchars($usuario['rut']); ?>" required>
                            <div class="form-text">Formato: XX.XXX.XXX-X</div>
                        </div>
                        <div class="col-md-6">
                            <label for="rol" class="form-label">Rol</label>
                            <select class="form-select" id="rol" name="rol">
                                <option value="proveedor" <?php echo $usuario['rol'] === 'proveedor' ? 'selected' : ''; ?>>Proveedor</option>
                                <option value="admin" <?php echo $usuario['rol'] === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">
                                <?php echo empty($usuario['id']) ? 'Contraseña' : 'Nueva contraseña (dejar en blanco para mantener)'; ?>
                            </label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   <?php echo empty($usuario['id']) ? 'required' : ''; ?>>
                            <?php if (empty($usuario['id'])): ?>
                                <div class="form-text">Mínimo 6 caracteres</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="confirmar_password" class="form-label">Confirmar contraseña</label>
                            <input type="password" class="form-control" id="confirmar_password" name="confirmar_password" 
                                   <?php echo empty($usuario['id']) ? 'required' : ''; ?>>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo empty($usuario['id']) ? 'Crear Usuario' : 'Actualizar Usuario'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Si hay un ID en la URL, abrir el modal automáticamente
if (isset($_GET['id']) && is_numeric($_GET['id'])):
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var modal = new bootstrap.Modal(document.getElementById('modalUsuario'));
        modal.show();
    });
</script>
<?php endif; ?>

<?php
// Incluir el pie de página
include 'footer.php';
?>
