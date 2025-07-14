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
$fechaInicio    = $_GET['fecha_inicio'] ?? date('d/m/Y'); // Por defecto el día actual
$fechaFin       = $_GET['fecha_fin'] ?? date('d/m/Y'); // Por defecto el día actual

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
            <h5 class="mb-0"><i class="fas fa-search me-2"></i>Consulta</h5>
        </div>
        <div class="card-body">
            <form action="api_test.php" method="GET" class="row g-3">
                <div class="col-md-6">
                    <label for="rut" class="form-label">RUT del Proveedor:</label>
                    <input type="text" class="form-control" id="rut" name="rut" value="<?php echo htmlspecialchars($rutProveedor); ?>" required>
                    <div class="form-text">Ingrese el RUT sin puntos ni guión, tal como lo recibe la API.</div>
                </div>
                <div class="col-md-3">
                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                    <input type="text" class="form-control date-input" id="fecha_inicio" name="fecha_inicio"
                        value="<?php echo htmlspecialchars($fechaInicio); ?>" placeholder="dd/mm/yyyy">
                </div>
                <div class="col-md-3">
                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                    <input type="text" class="form-control date-input" id="fecha_fin" name="fecha_fin"
                        value="<?php echo htmlspecialchars($fechaFin); ?>" placeholder="dd/mm/yyyy">
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
        <!-- Marcas asociadas al proveedor -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Resultado</h5>
            </div>
            <div class="card-body">
                <?php
                try {
                    // Obtener marcas desde la API
                    $resultadoRaw = null;
                    
                    // Hacer la llamada directa a la API para mostrar el resultado completo
                    $endpoint = API_BASE_URL . '/kpi_total_stock_unidades_detalle';
                    $ch = curl_init($endpoint);
                    
                    $data = [
                        'Distribuidor' => API_DISTRIBUIDOR,
                        'KEMP' => '001',
                        'FINI' => $fechaInicio,
                        'FTER' => $fechaFin,
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
                    
                    echo '<div class="mb-4">';
                    echo '<h5>Respuesta completa de la API:</h5>';
                    echo '<div class="bg-dark text-light p-3 rounded" style="max-height: 300px; overflow-y: auto;">';
                    echo '<pre>' . htmlspecialchars($resultadoRaw) . '</pre>';
                    echo '</div>';
                    echo '</div>';
                } catch (Exception $e) {
                    echo '<div class="alert alert-danger">';
                    echo '<i class="fas fa-exclamation-triangle me-2"></i>Error al obtener marcas: ' . htmlspecialchars($e->getMessage());
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Incluir el pie de página
include 'footer.php';
?>
