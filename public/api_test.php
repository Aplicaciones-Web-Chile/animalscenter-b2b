<?php
/**
 * Archivo de pruebas para la API de proveedores y marcas
 * Este archivo permite probar y depurar las llamadas a la API externa
 */

// Incluir archivos necesarios
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/api_client.php';

// Iniciar sesión y verificar permisos
startSession();
requireAdmin();

// Protección básica
header('Content-Type: text/html; charset=utf-8');

// Parámetros de prueba
$rutProveedor = isset($_GET['rut']) ? $_GET['rut'] : '78843490'; // RUT por defecto
$showRaw = isset($_GET['raw']) && $_GET['raw'] == '1'; // Mostrar respuesta completa

// Título de la página
$pageTitle = 'Pruebas de API';

// Incluir el encabezado
include 'header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1><i class="fas fa-flask me-2"></i>Pruebas de API</h1>
                <a href="usuarios.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Volver a Usuarios
                </a>
            </div>
            <p class="lead">Esta herramienta permite probar las llamadas a la API para verificar que se estén obteniendo correctamente las marcas asociadas a los proveedores.</p>
        </div>
    </div>

    <!-- Formulario de prueba -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-search me-2"></i>Consultar Proveedor</h5>
        </div>
        <div class="card-body">
            <form action="api_test.php" method="GET" class="row g-3">
                <div class="col-md-6">
                    <label for="rut" class="form-label">RUT del Proveedor:</label>
                    <input type="text" class="form-control" id="rut" name="rut" value="<?php echo htmlspecialchars($rutProveedor); ?>" required>
                    <div class="form-text">Ingrese el RUT sin puntos ni guión, tal como lo recibe la API.</div>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="raw" name="raw" value="1" <?php echo $showRaw ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="raw">Mostrar respuesta completa de la API</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Consultar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($rutProveedor)): ?>
        <!-- Datos del proveedor -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>Información del Proveedor</h5>
            </div>
            <div class="card-body">
                <?php
                // Búsqueda del proveedor en la API
                $proveedoresAPI = getProveedoresFromAPI();
                $proveedorEncontrado = null;
                
                foreach ($proveedoresAPI as $proveedor) {
                    if ($proveedor['KPRV'] === $rutProveedor) {
                        $proveedorEncontrado = $proveedor;
                        break;
                    }
                }
                
                if ($proveedorEncontrado) {
                    echo '<div class="mb-3">';
                    echo '<h5>Proveedor en API:</h5>';
                    echo '<ul class="list-group">';
                    echo '<li class="list-group-item"><strong>RUT:</strong> ' . htmlspecialchars($proveedorEncontrado['KPRV']) . '</li>';
                    echo '<li class="list-group-item"><strong>Razón Social:</strong> ' . htmlspecialchars($proveedorEncontrado['RAZO']) . '</li>';
                    echo '</ul>';
                    echo '</div>';
                    
                    // Verificar si existe en la base de datos local
                    $proveedorLocal = fetchOne("SELECT id, nombre, rol, email FROM usuarios WHERE rut = ?", [$rutProveedor]);
                    
                    if ($proveedorLocal) {
                        echo '<div class="mb-3">';
                        echo '<h5>Proveedor en Base de Datos Local:</h5>';
                        echo '<ul class="list-group">';
                        echo '<li class="list-group-item"><strong>ID:</strong> ' . htmlspecialchars($proveedorLocal['id']) . '</li>';
                        echo '<li class="list-group-item"><strong>Nombre:</strong> ' . htmlspecialchars($proveedorLocal['nombre']) . '</li>';
                        echo '<li class="list-group-item"><strong>Email:</strong> ' . (empty($proveedorLocal['email']) ? '<span class="text-muted">No definido</span>' : htmlspecialchars($proveedorLocal['email'])) . '</li>';
                        echo '<li class="list-group-item"><strong>Rol:</strong> ' . htmlspecialchars($proveedorLocal['rol']) . '</li>';
                        echo '</ul>';
                        echo '</div>';
                    } else {
                        echo '<div class="alert alert-warning">';
                        echo '<i class="fas fa-exclamation-triangle me-2"></i>Este proveedor NO existe en la base de datos local.';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="alert alert-danger">';
                    echo '<i class="fas fa-exclamation-triangle me-2"></i>Proveedor no encontrado en la API.';
                    echo '</div>';
                }
                ?>
            </div>
        </div>

        <!-- Marcas asociadas al proveedor -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Marcas Asociadas</h5>
            </div>
            <div class="card-body">
                <?php
                try {
                    // Obtener marcas desde la API
                    $resultadoRaw = null;
                    $marcasAPI = [];
                    
                    if ($showRaw) {
                        // Hacer la llamada directa a la API para mostrar el resultado completo
                        $endpoint = API_BASE_URL . '/get_prvmarcas';
                        $ch = curl_init($endpoint);
                        
                        $data = [
                            'Distribuidor' => API_DISTRIBUIDOR,
                            'KPRV' => $rutProveedor
                        ];
                        
                        $postData = json_encode($data);
                        
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Authorization: ' . API_KEY,
                            'Content-Type: application/json',
                            'Content-Length: ' . strlen($postData)
                        ]);
                        
                        $resultadoRaw = curl_exec($ch);
                        $error = curl_error($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if (!$error && $httpCode == 200) {
                            $resultadoJSON = json_decode($resultadoRaw, true);
                            if (isset($resultadoJSON['datos'])) {
                                $marcasAPI = $resultadoJSON['datos'];
                            }
                        }
                        
                        echo '<div class="mb-4">';
                        echo '<h5>Respuesta completa de la API:</h5>';
                        echo '<div class="bg-dark text-light p-3 rounded" style="max-height: 300px; overflow-y: auto;">';
                        echo '<pre>' . htmlspecialchars($resultadoRaw) . '</pre>';
                        echo '</div>';
                        echo '</div>';
                    } else {
                        // Usar la función de API cliente
                        $marcasAPI = getMarcasProveedorFromAPI($rutProveedor);
                    }
                    
                    if (!empty($marcasAPI)) {
                        echo '<div class="table-responsive">';
                        echo '<table class="table table-striped table-hover">';
                        echo '<thead>';
                        echo '<tr>';
                        echo '<th>Código</th>';
                        echo '<th>Nombre</th>';
                        echo '<th>Estado en BD Local</th>';
                        echo '</tr>';
                        echo '</thead>';
                        echo '<tbody>';
                        
                        // Si el proveedor está en la BD local, obtener sus marcas
                        $marcasLocales = [];
                        if (isset($proveedorLocal)) {
                            $marcasLocales = getMarcasProveedor($proveedorLocal['id']);
                        }
                        
                        foreach ($marcasAPI as $marca) {
                            $marcaId = $marca['KMAR'];
                            $marcaNombre = $marca['DMAR'];
                            $existeEnLocal = in_array($marcaId, $marcasLocales);
                            
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($marcaId) . '</td>';
                            echo '<td>' . htmlspecialchars($marcaNombre) . '</td>';
                            echo '<td>';
                            if ($existeEnLocal) {
                                echo '<span class="badge bg-success">Asociada</span>';
                            } else {
                                echo '<span class="badge bg-danger">No asociada</span>';
                            }
                            echo '</td>';
                            echo '</tr>';
                        }
                        
                        echo '</tbody>';
                        echo '</table>';
                        echo '</div>';
                    } else {
                        echo '<div class="alert alert-info">';
                        echo '<i class="fas fa-info-circle me-2"></i>Este proveedor no tiene marcas asociadas en la API.';
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="alert alert-danger">';
                    echo '<i class="fas fa-exclamation-triangle me-2"></i>Error al obtener marcas: ' . htmlspecialchars($e->getMessage());
                    echo '</div>';
                }
                ?>
            </div>
        </div>

        <!-- Herramientas de depuración -->
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-code me-2"></i>Código de Ejemplo</h5>
            </div>
            <div class="card-body">
                <h5>Ejemplo de petición cURL:</h5>
                <div class="bg-dark text-light p-3 rounded">
                    <pre>curl "<?php echo API_BASE_URL; ?>/get_prvmarcas"
-H "Authorization: <?php echo API_KEY; ?>"
-H "Content-Type: application/json"
-d '{"Distribuidor":"<?php echo API_DISTRIBUIDOR; ?>", "KPRV": "<?php echo htmlspecialchars($rutProveedor); ?>" }'</pre>
                </div>
                
                <h5 class="mt-3">Código PHP:</h5>
                <div class="bg-dark text-light p-3 rounded">
                    <pre>// Obtener marcas de un proveedor específico
$rut = '<?php echo htmlspecialchars($rutProveedor); ?>';
$marcas = getMarcasProveedorFromAPI($rut);

// Mostrar resultados
foreach ($marcas as $marca) {
    echo $marca['id'] . ': ' . $marca['nombre'] . "\n";
}</pre>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Incluir el pie de página
include 'footer.php';
?>
