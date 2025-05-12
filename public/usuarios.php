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
require_once __DIR__ . '/../includes/api_client.php';

// Iniciar sesión
startSession();

// Verificar que sea administrador
requireAdmin();

// Título de la página
$pageTitle = 'Gestión de Usuarios';

// Procesar formulario de creación/edición de usuario
$mensaje = '';
$tipoMensaje = '';

// Inicializar variables para el formulario
$usuario = [
    'id' => '',
    'nombre' => '',
    'email' => '',
    'rut' => '',
    'rol' => 'proveedor',
    'habilitado' => 'S', // Por defecto habilitado
    'marcas' => []
];

// Obtener listado de marcas desde la API
$marcasDisponibles = [];
try {
    $marcasDisponibles = getMarcasFromAPI();
} catch (Exception $e) {
    logError('Error al obtener marcas: ' . $e->getMessage());
}

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
    // Determinar si el usuario está habilitado (solo aplica para proveedores)
    $habilitado = ($rol === 'proveedor' && isset($_POST['habilitado'])) ? 'S' : 'N';
    // Para administradores, siempre habilitado
    if ($rol === 'admin') {
        $habilitado = 'S';
    }
    // Obtener marcas del nuevo campo JSON
    $marcas = [];
    if (isset($_POST['marcas_json']) && !empty($_POST['marcas_json'])) {
        $marcas = json_decode($_POST['marcas_json'], true) ?: [];
    }

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
    $rutOriginal = $rut; // Guardar el formato original para mostrar errores

    // Ver si tiene formato y validarlo
    if (preg_match('/^[0-9]{1,2}\.[0-9]{3}\.[0-9]{3}-[0-9kK]$/', $rut)) {
        // Si tiene formato, limpiarlo para almacenamiento
        $rut = limpiarRut($rut);
    } else if (!preg_match('/^[0-9]{7,8}[0-9kK]$/', $rut)) {
        // Si no tiene formato válido ni es un RUT limpio válido
        $errores[] = 'El formato del RUT debe ser XX.XXX.XXX-X o bien un RUT numérico';
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
            // Verificar si el email ya existe (solo si se proporcionó un email)
            if (!empty($email)) {
                $existeEmail = fetchOne("SELECT id FROM usuarios WHERE email = ?", [$email]);
                if ($existeEmail) {
                    setFlashMessage('error', 'El email ya está registrado.');
                    redirect('usuarios.php');
                }
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
            $sql = "INSERT INTO usuarios (nombre, email, password_hash, rol, rut, habilitado) VALUES (?, ?, ?, ?, ?, ?)";
            $params = [$nombre, $email, $passwordHash, $rol, $rut, $habilitado];

            $db = getDbConnection();
            $stmt = $db->prepare($sql);
            if ($stmt->execute($params)) {
                $nuevoUsuarioId = $db->lastInsertId();
                // Si es proveedor, guardar las marcas seleccionadas y sincronizar con la API
                if ($rol === 'proveedor') {
                    try {
                        // Guardar marcas en BD local y sincronizar con API
                        guardarMarcasProveedor($nuevoUsuarioId, $marcas);
                        logError('Sincronización exitosa de marcas para el nuevo proveedor RUT: ' . $rut);
                    } catch (Exception $e) {
                        // Registrar el error pero continuar
                        logError('Error al sincronizar marcas con API para el nuevo proveedor RUT: ' . $rut . '. Error: ' . $e->getMessage());
                        // Añadir mensaje de advertencia para el usuario
                        setFlashMessage('warning', 'Usuario creado, pero hubo un problema al sincronizar marcas con la API. Se ha registrado el error.');
                        redirect('usuarios.php');
                    }
                }
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

            // Verificar si el email ya existe para otro usuario (solo si se proporcionó un email)
            if (!empty($email)) {
                $existeEmail = fetchOne("SELECT id FROM usuarios WHERE email = ? AND id != ?", [$email, $id]);
                if ($existeEmail) {
                    setFlashMessage('error', 'El email ya está registrado por otro usuario.');
                    redirect('usuarios.php?id=' . $id);
                }
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
                // Si es proveedor, actualizar las marcas seleccionadas y sincronizar con la API
                if ($rol === 'proveedor') {
                    try {
                        // Guardar marcas en BD local y sincronizar con API
                        guardarMarcasProveedor($id, $marcas);
                        logError('Sincronización exitosa de marcas para el proveedor editado RUT: ' . $rut);
                    } catch (Exception $e) {
                        // Registrar el error pero continuar
                        logError('Error al sincronizar marcas con API para el proveedor editado RUT: ' . $rut . '. Error: ' . $e->getMessage());
                        // Añadir mensaje de advertencia para el usuario
                        setFlashMessage('warning', 'Usuario actualizado, pero hubo un problema al sincronizar marcas con la API. Se ha registrado el error.');
                        redirect('usuarios.php');
                    }
                }

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
            'rol' => $rol,
            'marcas' => $marcas
        ];
    }
}

// Cargar datos de usuario para edición
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $usuario = fetchOne("SELECT id, nombre, email, rol, rut, habilitado FROM usuarios WHERE id = ?", [$id]);

    // Si es un proveedor, obtener sus marcas asociadas
    if ($usuario && $usuario['rol'] === 'proveedor') {
        $usuario['marcas'] = getMarcasProveedor($id);
    } else {
        $usuario['marcas'] = [];
    }

    if (!$usuario) {
        setFlashMessage('error', 'Usuario no encontrado.');
        redirect('usuarios.php');
    }
}

// Eliminar usuario
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];

    // No permitir eliminar al usuario actual
    if ($id === (int)$_SESSION['user']['id']) {
        setFlashMessage('error', 'No puedes eliminar tu propio usuario.');
        redirect('usuarios.php');
    }

    // Obtener el rol y RUT del usuario antes de eliminarlo
    $usuario = fetchOne("SELECT rol, rut FROM usuarios WHERE id = ?", [$id]);

    // Iniciar una transacción para asegurar que todas las operaciones se completen o ninguna
    $db = getDbConnection();
    $db->beginTransaction();

    try {
        // Si es un proveedor, eliminar sus asociaciones con marcas en la BD local
        if ($usuario && $usuario['rol'] === 'proveedor') {
            // Eliminar asociaciones en la base de datos local
            executeQuery("DELETE FROM proveedores_marcas WHERE proveedor_id = ?", [$id]);

            // No es necesario eliminar asociaciones en la API cuando se elimina un usuario proveedor
            logError('Usuario proveedor eliminado (RUT: ' . $usuario['rut'] . '). No se realizaron llamadas a la API.');
        }

        // Eliminar el usuario
        executeQuery("DELETE FROM usuarios WHERE id = ?", [$id]);

        // Confirmar la transacción
        $db->commit();

        setFlashMessage('success', 'Usuario eliminado correctamente.');
    } catch (Exception $e) {
        // Revertir la transacción en caso de error
        $db->rollBack();
        logError('Error al eliminar usuario: ' . $e->getMessage());
        setFlashMessage('error', 'Error al eliminar el usuario: ' . $e->getMessage());
    }

    redirect('usuarios.php');
}

// Obtener listado de usuarios para la tabla
$usuarios = fetchAll("SELECT id, nombre, email, rol, rut, fecha_creacion, habilitado FROM usuarios ORDER BY nombre");

// Incluir el encabezado
include 'header.php';
?>

<!-- Cargar el CSS para el selector de marcas tipo pill -->
<link rel="stylesheet" href="<?php echo APP_URL . '/public/assets/css/pill-selector.css'; ?>">


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
                                <th>Marcas Asociadas</th>
                                <th>Fecha de Creación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u):
                                // Determinar si el usuario está deshabilitado
                                $deshabilitado = ($u['rol'] === 'proveedor' && isset($u['habilitado']) && $u['habilitado'] !== 'S');
                                // Clase CSS para usuarios deshabilitados
                                $claseDeshabilitado = $deshabilitado ? 'usuario-deshabilitado' : '';
                            ?>
                                <tr class="<?php echo $claseDeshabilitado; ?>">
                                    <td><?php echo htmlspecialchars($u['nombre'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars(formatearRut($u['rut'] ?? '')); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $u['rol'] === 'admin' ? 'danger' : 'success'; ?>">
                                            <?php echo $u['rol'] === 'admin' ? 'Administrador' : 'Proveedor'; ?>
                                        </span>
                                        <?php if ($deshabilitado): ?>
                                            <span class="ms-1 badge bg-secondary">Deshabilitado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($u['rol'] === 'proveedor') {
                                            $marcasUsuario = getMarcasProveedor($u['id']);

                                            if (empty($marcasUsuario)) {
                                                echo '<span class="text-muted">Sin marcas asociadas</span>';
                                            } else {
                                                echo '<div class="marcas-container">';

                                                // Buscar nombres de las marcas
                                                foreach ($marcasUsuario as $marcaId) {
                                                    $marcaNombre = '';
                                                    foreach ($marcasDisponibles as $marca) {
                                                        if ($marca['id'] === $marcaId) {
                                                            $marcaNombre = $marca['nombre'];
                                                            break;
                                                        }
                                                    }

                                                    // Si no encontramos el nombre, usar el ID
                                                    if (empty($marcaNombre)) {
                                                        $marcaNombre = 'Marca ' . $marcaId;
                                                    }

                                                    echo '<span class="badge bg-info text-dark me-1 mb-1">' . htmlspecialchars($marcaNombre) . '</span>';
                                                }

                                                echo '</div>';
                                            }
                                        } else {
                                            echo '<span class="text-muted">N/A</span>';
                                        }
                                        ?>
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

<!-- Botón para sincronizar usuarios y proveedores -->
<div class="d-flex justify-content-center mt-4 mb-4">
    <a href="sync_usuarios.php" class="btn btn-primary btn-lg">
        <i class="fas fa-sync-alt me-2"></i>Sincronizar Proveedores con la API
    </a>
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
                                   value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>" autocomplete="off">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="rut" class="form-label">RUT</label>
                            <input type="text" class="form-control" id="rut" name="rut"
                                   placeholder="Ej: 12.345.678-9"
                                   value="<?php echo !empty($usuario['rut']) ? htmlspecialchars(formatearRut($usuario['rut'])) : ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="rol" class="form-label">Rol</label>
                            <select class="form-select" id="rol" name="rol" onchange="toggleMarcasField()">
                                <option value="proveedor" <?php echo $usuario['rol'] === 'proveedor' ? 'selected' : ''; ?>>Proveedor</option>
                                <option value="admin" <?php echo $usuario['rol'] === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                            </select>
                        </div>
                    </div>

                    <!-- Campo de habilitado (solo visible para proveedores) -->
                    <div class="row mb-3" id="habilitadoContainer" style="<?php echo $usuario['rol'] === 'admin' ? 'display:none;' : ''; ?>">
                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="habilitado" name="habilitado" value="S" <?php echo (!isset($usuario['habilitado']) || $usuario['habilitado'] === 'S') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="habilitado">Usuario habilitado</label>
                                <div class="form-text">Los usuarios proveedores deshabilitados no podrán iniciar sesión en el sistema.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Campo de marcas (solo visible para proveedores) -->
                    <div class="row mb-3" id="marcasContainer" style="<?php echo $usuario['rol'] === 'admin' ? 'display:none;' : ''; ?>">
                        <div class="col-md-12">
                            <label for="marcasInput" class="form-label">Marcas asociadas</label>
                            <div class="pill-selector-container" id="pillSelectorContainer">
                                <input type="text" class="pill-selector-input" id="marcasInput" placeholder="Escriba para buscar marcas...">
                            </div>
                            <input type="hidden" id="marcasHidden" name="marcas_json">
                            <div class="form-text">Escriba el nombre de la marca y selecciónela de la lista</div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">
                                <?php echo empty($usuario['id']) ? 'Contraseña' : 'Nueva contraseña (dejar en blanco para mantener)'; ?>
                            </label>
                            <input type="password" class="form-control" id="password" name="password"
                                   <?php echo empty($usuario['id']) ? 'required' : ''; ?> autocomplete="new-password">
                            <?php if (empty($usuario['id'])): ?>
                                <div class="form-text">Mínimo 6 caracteres</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="confirmar_password" class="form-label">Confirmar contraseña</label>
                            <input type="password" class="form-control" id="confirmar_password" name="confirmar_password"
                                   <?php echo empty($usuario['id']) ? 'required' : ''; ?> autocomplete="new-password">
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

<!-- Script para mostrar/ocultar el campo de marcas según el rol -->
<script src="<?php echo APP_URL . '/public/assets/js/pill-selector.js'; ?>"></script>
<script>
function toggleMarcasField() {
    const rolSelect = document.getElementById('rol');
    const marcasContainer = document.getElementById('marcasContainer');
    const habilitadoContainer = document.getElementById('habilitadoContainer');

    if (rolSelect.value === 'admin') {
        marcasContainer.style.display = 'none';
        habilitadoContainer.style.display = 'none';
    } else {
        marcasContainer.style.display = 'block';
        habilitadoContainer.style.display = 'block';
    }
}

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    // Asegurarse que el estado inicial es correcto
    toggleMarcasField();

    // Inicializar el selector de marcas tipo pill
    const marcasData = <?php echo json_encode($marcasDisponibles); ?>;

    // Obtener las marcas ya seleccionadas para el usuario
    const selectedMarcasIds = <?php echo json_encode($usuario['marcas'] ?? []); ?>;
    const selectedMarcas = selectedMarcasIds.map(id => {
        const marca = marcasData.find(m => m.id === id);
        return marca ? marca : { id: id, nombre: 'Marca ' + id };
    });

    // Inicializar el componente
    const pillSelector = new PillSelector({
        containerSelector: '#pillSelectorContainer',
        inputSelector: '#marcasInput',
        hiddenInputSelector: '#marcasHidden',
        dataSource: marcasData,
        placeholder: 'Escriba para buscar marcas...',
        noResultsText: 'No se encontraron marcas',
        selectedItems: selectedMarcas
    });

    // Manejar la creación de usuarios desde la API
    document.querySelectorAll('.crear-usuario-api').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();

            // Obtener datos del proveedor
            const rut = this.getAttribute('data-rut');
            const nombre = this.getAttribute('data-nombre');

            // Abrir el modal de creación de usuario
            const modal = new bootstrap.Modal(document.getElementById('modalUsuario'));

            // Llenar el formulario con los datos del proveedor
            document.getElementById('id').value = '';
            document.getElementById('nombre').value = nombre;
            document.getElementById('rut').value = rut;
            document.getElementById('email').value = '';
            document.getElementById('rol').value = 'proveedor';

            // Asegurarse que el campo de marcas esté visible
            document.getElementById('marcasContainer').style.display = 'block';

            // Limpiar marcas seleccionadas
            pillSelector.clearItems();

            // Mostrar el modal
            modal.show();

            // Enfocar el campo de email
            setTimeout(() => {
                document.getElementById('email').focus();
            }, 500);
        });
    });
});
</script>

<!-- Estilos personalizados para usuarios deshabilitados -->
<style>
    /* Aplicar opacidad a las celdas de usuarios deshabilitados, excepto la última (acciones) */
    .usuario-deshabilitado td:not(:last-child) {
        opacity: 0.4;
    }

    /* Efecto hover para mejorar legibilidad al pasar el mouse */
    .usuario-deshabilitado:hover td:not(:last-child) {
        opacity: 0.7;
        transition: opacity 0.3s ease;
    }
</style>

<?php include 'footer.php'; ?>
