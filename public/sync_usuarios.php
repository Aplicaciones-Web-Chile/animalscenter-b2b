<?php
/**
 * Sincronización de Usuarios con API
 * Este archivo sincroniza los proveedores y sus marcas desde la API con la base de datos local
 */

// Activar manejo de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Incluir archivos necesarios
    require_once __DIR__ . '/../config/app.php';
    require_once __DIR__ . '/../includes/session.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/helpers.php';
    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/../includes/api_client.php';
    
    // Iniciar sesión
    startSession();
    
    // Verificar rol (permitir acceso por ahora para debug)
    if (!isLoggedIn()) {
        die("<h1>Error de autenticación</h1><p>Debe iniciar sesión para acceder a esta página</p>");
    }
    
    if (!isAdmin()) {
        echo "<h1>Advertencia de Permisos</h1>";
        echo "<p>Nota de depuración: Normalmente, esta página requiere permisos de administrador.</p>";
        echo "<p>Estado de la sesión: " . (isLoggedIn() ? 'Conectado' : 'No conectado') . "</p>";
        echo "<p>Rol: " . ($_SESSION['rol'] ?? 'No definido') . "</p>";
        echo "<hr>";
    }
}
catch (Exception $e) {
    echo "<h1>Error detectado</h1>";
    echo "<p>Ha ocurrido el siguiente error: " . $e->getMessage() . "</p>";
    exit;
}

// Título de la página
$pageTitle = 'Sincronización de Usuarios';

// Variables para resultados
$resultados = [
    'nuevos' => [],
    'actualizados' => [],
    'errores' => []
];

// Proceso de sincronización
if (isset($_GET['sync']) && $_GET['sync'] == 1) {
    // Nota: Validación de token CSRF temporalmente desactivada para pruebas
    // El token CSRF se sigue generando en el enlace por compatibilidad
    
    try {
        // Mostrar mensajes de depuración durante el proceso
        $debug = true;
        $debugInfo = [];
        
        // Obtener proveedores desde la API
        $proveedoresAPI = getProveedoresFromAPI();
        $debugInfo[] = "Proveedores encontrados en API: " . count($proveedoresAPI);
        
        if (empty($proveedoresAPI)) {
            throw new Exception('No se encontraron proveedores en la API.');
        }
        
        $db = getDbConnection();
        $db->beginTransaction();
        
        // Arreglo para contar resultados
        $contadores = [
            'procesados' => 0,
            'existentes' => 0, 
            'nuevos' => 0,
            'errores' => 0
        ];
        
        foreach ($proveedoresAPI as $proveedor) {
            $contadores['procesados']++;
            $rutProveedor = $proveedor['KPRV'];
            $nombreProveedor = $proveedor['RAZO'];
            
            // Registrar info para debug
            $debugInfo[] = "Procesando proveedor: {$nombreProveedor} (RUT: {$rutProveedor})";
            
            // Verificar si el proveedor existe en el sistema local
            $existeEnSistema = fetchOne("SELECT id, rol FROM usuarios WHERE rut = ?", [$rutProveedor]);
            
            if ($existeEnSistema) {
                $contadores['existentes']++;
                $debugInfo[] = "  - Proveedor ya existe en BD local (ID: {$existeEnSistema['id']})";
            }
            
            // Si no existe, crearlo
            if (!$existeEnSistema) {
                $debugInfo[] = "  - Creando nuevo proveedor en BD local";
                try {
                    // Dejar el correo como NULL hasta que se configure manualmente
                    $debugInfo[] = "  - Email: Se configurará NULL";
                    
                    // Insertar nuevo usuario con password_hash como NULL
                    $sql = "INSERT INTO usuarios (nombre, email, password_hash, rol, rut) VALUES (?, NULL, NULL, 'proveedor', ?)";
                    $params = [$nombreProveedor, $rutProveedor];
                    $debugInfo[] = "  - Usando password_hash NULL (campo modificado para aceptar NULL)";
                    
                    // Ejecutar la consulta
                    executeQuery($sql, $params);
                    $nuevoUsuarioId = $db->lastInsertId();
                    $contadores['nuevos']++;
                    
                    // Registrar la creación exitosa
                    $resultados['nuevos'][] = [
                        'id' => $nuevoUsuarioId,
                        'nombre' => $nombreProveedor,
                        'rut' => $rutProveedor
                    ];
                    
                    // Ahora sincronizar sus marcas
                    $marcasIds = [];
                    try {
                        // Obtener marcas asociadas al proveedor desde la API
                        $marcasProveedor = getMarcasIdsProveedorFromAPI($rutProveedor);
                        
                        if (!empty($marcasProveedor)) {
                            // Guardar las marcas en la BD local
                            guardarMarcasProveedor($nuevoUsuarioId, $marcasProveedor);
                        }
                    } catch (Exception $e) {
                        $contadores['errores']++;
                        $debugInfo[] = "  - ERROR: " . $e->getMessage();
                        logError('Error al sincronizar marcas para nuevo usuario ' . $rutProveedor . ': ' . $e->getMessage());
                        $resultados['errores'][] = 'Error al sincronizar marcas para ' . $nombreProveedor . ' (' . $rutProveedor . '): ' . $e->getMessage();
                    }
                    
                } catch (Exception $e) {
                    $contadores['errores']++;
                    $debugInfo[] = "  - ERROR al crear usuario: " . $e->getMessage();
                    logError('Error al crear usuario desde API: ' . $e->getMessage());
                    $resultados['errores'][] = 'Error al crear usuario ' . $nombreProveedor . ' (' . $rutProveedor . '): ' . $e->getMessage();
                }
            } else {
                // El usuario ya existe, actualizar sus marcas
                $usuarioId = $existeEnSistema['id'];
                
                try {
                    // Obtener marcas asociadas al proveedor desde la API
                    $marcasProveedor = getMarcasIdsProveedorFromAPI($rutProveedor);
                    
                    // Obtener marcas actuales del proveedor en la BD local
                    $marcasActuales = getMarcasProveedor($usuarioId);
                    
                    // Verificar si hay cambios
                    $cambios = false;
                    
                    // Comprobar si hay marcas diferentes
                    $marcasNuevas = array_diff($marcasProveedor, $marcasActuales);
                    $marcasEliminadas = array_diff($marcasActuales, $marcasProveedor);
                    
                    if (!empty($marcasNuevas) || !empty($marcasEliminadas)) {
                        $cambios = true;
                    }
                    
                    if ($cambios) {
                        // Sincronizar las marcas
                        guardarMarcasProveedor($usuarioId, $marcasProveedor);
                        
                        // Registrar la actualización
                        $resultados['actualizados'][] = [
                            'id' => $usuarioId,
                            'nombre' => $nombreProveedor,
                            'rut' => $rutProveedor,
                            'nuevas_marcas' => count($marcasNuevas),
                            'marcas_eliminadas' => count($marcasEliminadas)
                        ];
                    }
                } catch (Exception $e) {
                    logError('Error al sincronizar marcas para usuario existente ' . $rutProveedor . ': ' . $e->getMessage());
                    $resultados['errores'][] = 'Error al sincronizar marcas para ' . $nombreProveedor . ' (' . $rutProveedor . '): ' . $e->getMessage();
                }
            }
        }
        
        // Confirmar transacción
        $db->commit();
        
        // Guardar información de depuración en la sesión para mostrarla
        $_SESSION['debug_info'] = [
            'info' => $debugInfo,
            'contadores' => $contadores
        ];
        
        // Mensaje de éxito
        if (!empty($resultados['nuevos']) || !empty($resultados['actualizados'])) {
            setFlashMessage('success', 'Sincronización completada con éxito: ' . $contadores['nuevos'] . ' nuevos, ' . count($resultados['actualizados']) . ' actualizados.');
        } else {
            setFlashMessage('info', 'No se encontraron cambios para sincronizar. Procesados: ' . $contadores['procesados'] . ', Ya existentes: ' . $contadores['existentes'] . '.');
        }
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        logError('Error en sincronización: ' . $e->getMessage());
        setFlashMessage('error', 'Error en la sincronización: ' . $e->getMessage());
    }
    
    // Redireccionar para evitar reenvío del formulario
    redirect('sync_usuarios.php');
}

// Incluir el encabezado
include 'header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1><i class="fas fa-sync-alt me-2"></i>Sincronización de Usuarios</h1>
                <a href="usuarios.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Volver a Usuarios
                </a>
            </div>
            <p class="lead">Esta herramienta sincroniza los proveedores y sus marcas desde la API con la base de datos local.</p>
        </div>
    </div>

    <?php 
    // Mostrar mensaje flash si existe
    $flashMessage = getFlashMessage();
    if ($flashMessage): 
    ?>
        <div class="alert alert-<?php echo $flashMessage['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $flashMessage['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Card con información sobre la sincronización -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información sobre la Sincronización</h5>
        </div>
        <div class="card-body">
            <p>Al sincronizar los usuarios con la API, se realizarán las siguientes acciones:</p>
            <ul>
                <li>Los proveedores que existen en la API pero no en la base de datos local serán creados automáticamente.</li>
                <li>Los nuevos usuarios se crearán con una contraseña temporal vacía. Deberá establecerse una contraseña antes de que puedan iniciar sesión.</li>
                <li>Se sincronizarán las marcas asociadas a cada proveedor según la información de la API.</li>
                <li>Los usuarios existentes no se modificarán, solo se actualizarán sus marcas asociadas si es necesario.</li>
            </ul>
            
            <div class="alert alert-warning">
                <strong>Nota:</strong> Este proceso puede tardar unos momentos dependiendo del número de proveedores en la API.
            </div>
            
            <div class="text-center mt-4">
                <a href="sync_usuarios.php?sync=1&token=<?php echo generateCsrfToken(); ?>" class="btn btn-primary btn-lg">
                    <i class="fas fa-sync-alt me-2"></i>Iniciar Sincronización
                </a>
            </div>
        </div>
    </div>
    
    <?php if (isset($_GET['sync']) && $_GET['sync'] == 1): ?>
        <!-- Resultados de la sincronización -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Resultados de la Sincronización</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($resultados['nuevos'])): ?>
                    <h4>Nuevos Usuarios Creados (<?php echo count($resultados['nuevos']); ?>)</h4>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>RUT</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultados['nuevos'] as $nuevo): ?>
                                    <tr>
                                        <td><?php echo $nuevo['id']; ?></td>
                                        <td><?php echo htmlspecialchars($nuevo['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($nuevo['rut']); ?></td>
                                        <td>
                                            <a href="usuarios.php?id=<?php echo $nuevo['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit me-1"></i>Completar Datos
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($resultados['actualizados'])): ?>
                    <h4 class="mt-4">Usuarios Actualizados (<?php echo count($resultados['actualizados']); ?>)</h4>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>RUT</th>
                                    <th>Cambios en Marcas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultados['actualizados'] as $actualizado): ?>
                                    <tr>
                                        <td><?php echo $actualizado['id']; ?></td>
                                        <td><?php echo htmlspecialchars($actualizado['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($actualizado['rut']); ?></td>
                                        <td>
                                            <?php if ($actualizado['nuevas_marcas'] > 0): ?>
                                                <span class="badge bg-success">+<?php echo $actualizado['nuevas_marcas']; ?> nuevas</span>
                                            <?php endif; ?>
                                            <?php if ($actualizado['marcas_eliminadas'] > 0): ?>
                                                <span class="badge bg-danger">-<?php echo $actualizado['marcas_eliminadas']; ?> eliminadas</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="usuarios.php?id=<?php echo $actualizado['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-edit me-1"></i>Ver Detalles
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($resultados['errores'])): ?>
                    <h4 class="mt-4 text-danger">Errores (<?php echo count($resultados['errores']); ?>)</h4>
                    <ul class="list-group">
                        <?php foreach ($resultados['errores'] as $error): ?>
                            <li class="list-group-item list-group-item-danger"><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <?php if (empty($resultados['nuevos']) && empty($resultados['actualizados']) && empty($resultados['errores'])): ?>
                    <div class="alert alert-info">
                        No se encontraron cambios para sincronizar. Todos los proveedores y sus marcas ya están actualizados.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Sección de depuración -->
    <?php if (isset($_SESSION['debug_info'])): ?>
    <div class="card mt-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fas fa-bug me-2"></i>Información de Depuración</h5>
        </div>
        <div class="card-body">
            <h4>Contadores</h4>
            <ul class="list-group mb-4">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Proveedores procesados
                    <span class="badge bg-primary rounded-pill"><?php echo $_SESSION['debug_info']['contadores']['procesados']; ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Proveedores existentes
                    <span class="badge bg-info rounded-pill"><?php echo $_SESSION['debug_info']['contadores']['existentes']; ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Nuevos proveedores creados
                    <span class="badge bg-success rounded-pill"><?php echo $_SESSION['debug_info']['contadores']['nuevos']; ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Errores encontrados
                    <span class="badge bg-danger rounded-pill"><?php echo $_SESSION['debug_info']['contadores']['errores']; ?></span>
                </li>
            </ul>
            
            <h4>Registro detallado</h4>
            <div class="alert alert-secondary">
                <pre class="mb-0" style="max-height: 400px; overflow-y: auto;"><?php 
                foreach ($_SESSION['debug_info']['info'] as $line) {
                    echo htmlspecialchars($line) . "\n";
                }
                ?></pre>
            </div>
            
            <!-- Limpiar información de depuración para futuras sincronizaciones -->
            <?php unset($_SESSION['debug_info']); ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Incluir el pie de página
include 'footer.php';
?>
