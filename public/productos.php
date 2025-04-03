<?php
/**
 * Página de gestión de productos
 * Permite ver, filtrar y exportar productos del proveedor logueado
 */

// Incluir archivos necesarios
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

// Iniciar sesión
startSession();

// Verificar autenticación
requireLogin();

// Título de la página
$pageTitle = 'Gestión de Productos';

// Incluir el encabezado
include 'header.php';

// Inicializar variables para filtros
$busqueda = $_GET['busqueda'] ?? '';
$fechaInicio = $_GET['fecha_inicio'] ?? date('d/m/Y'); // Por defecto el día actual
$fechaFin = $_GET['fecha_fin'] ?? date('d/m/Y'); // Por defecto el día actual
$proveedor = $_GET['proveedor'] ?? '78843490'; // Código del proveedor por defecto
$distribuidor = $_GET['distribuidor'] ?? '001'; // Código del distribuidor por defecto

// Inicializar variables para paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$porPagina = 10;

// Función para consumir la API de productos
function obtenerProductosAPI($distribuidor, $fechaInicio, $fechaFin, $proveedor) {
    // URL y configuración de la API
    $url = "https://api2.aplicacionesweb.cl/apiacenter/productos/vtayrepxsuc";
    $token = "94ec33d0d75949c298f47adaa78928c2";
    
    // Datos a enviar
    $data = [
        "Distribuidor" => $distribuidor,
        "FINI" => $fechaInicio,
        "FTER" => $fechaFin,
        "KPRV" => $proveedor
    ];
    
    // Configuración de la petición
    $options = [
        'http' => [
            'header' => "Authorization: $token\r\n" .
                       "Content-Type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($data)
        ]
    ];
    
    // Crear contexto y realizar petición
    $context = stream_context_create($options);
    
    try {
        // Registrar la llamada a la API en el log
        error_log("Llamando a API: $url con datos: " . json_encode($data));
        
        // Realizar la petición
        $result = file_get_contents($url, false, $context);
        
        if ($result === false) {
            error_log("Error al obtener datos de la API: No se pudo conectar");
            return ['estado' => 0, 'datos' => [], 'error' => 'No se pudo conectar con la API'];
        }
        
        // Decodificar respuesta
        $response = json_decode($result, true);
        
        // Verificar estructura de respuesta
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Error al decodificar respuesta JSON: " . json_last_error_msg());
            return ['estado' => 0, 'datos' => [], 'error' => 'Error al procesar la respuesta'];
        }
        
        return $response;
    } catch (Exception $e) {
        error_log("Excepción al llamar a la API: " . $e->getMessage());
        return ['estado' => 0, 'datos' => [], 'error' => $e->getMessage()];
    }
}

// Obtener productos desde la API
$respuestaAPI = obtenerProductosAPI($distribuidor, $fechaInicio, $fechaFin, $proveedor);

// Verificar que la respuesta sea correcta
if (!isset($respuestaAPI['estado']) || $respuestaAPI['estado'] !== 1) {
    $productos = [];
    $mensajeError = $respuestaAPI['error'] ?? 'Error al obtener productos de la API';
} else {
    $productosAPI = $respuestaAPI['datos'] ?? [];
    
    // Filtrar por búsqueda si se especifica
    if (!empty($busqueda)) {
        $productosAPI = array_filter($productosAPI, function($producto) use ($busqueda) {
            return (stripos($producto['PRODUCTO_DESCRIPCION'], $busqueda) !== false) || 
                   (stripos($producto['PRODUCTO_CODIGO'], $busqueda) !== false) ||
                   (stripos($producto['MARCA_DESCRIPCION'], $busqueda) !== false);
        });
    }
    
    // Contar total de productos después del filtro
    $totalProductos = count($productosAPI);
    $totalPaginas = ceil($totalProductos / $porPagina);
    
    // Ordenar productos según criterio
    // Por defecto ordenamos por descripción del producto
    usort($productosAPI, function($a, $b) {
        return strcmp($a['PRODUCTO_DESCRIPCION'], $b['PRODUCTO_DESCRIPCION']);
    });
    
    // Aplicar paginación
    $offset = ($pagina - 1) * $porPagina;
    $productos = array_slice($productosAPI, $offset, $porPagina);
}

?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mb-0"><i class="fas fa-boxes me-2"></i>Gestión de Productos</h1>
                <a href="exportar.php?tipo=productos" class="btn btn-success">
                    <i class="fas fa-file-excel me-2"></i>Exportar a Excel
                </a>
            </div>
        </div>
    </div>

    <!-- Filtros y búsqueda -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <form action="" method="GET" class="row g-3">
                        <div class="col-md-3">
                            <div class="input-group">
                                <input type="text" class="form-control" name="busqueda" 
                                    placeholder="Buscar por nombre o código" value="<?php echo htmlspecialchars($busqueda); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                            <input type="text" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                                value="<?php echo htmlspecialchars($fechaInicio); ?>" placeholder="dd/mm/yyyy">
                        </div>
                        <div class="col-md-3">
                            <label for="fecha_fin" class="form-label">Fecha Fin</label>
                            <input type="text" class="form-control" id="fecha_fin" name="fecha_fin" 
                                value="<?php echo htmlspecialchars($fechaFin); ?>" placeholder="dd/mm/yyyy">
                        </div>
                        <div class="col-md-3">
                            <label for="proveedor" class="form-label">Código Proveedor</label>
                            <input type="text" class="form-control" id="proveedor" name="proveedor" 
                                value="<?php echo htmlspecialchars($proveedor); ?>" placeholder="Código Proveedor">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Listado de productos -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Listado de Productos</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($productos)): ?>
                        <div class="alert alert-info">
                            No se encontraron productos. <?php echo !empty($busqueda) ? 'Intente con otra búsqueda.' : ''; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Producto</th>
                                        <th>Marca</th>
                                        <th>Familia</th>
                                        <th>Suc. 1</th>
                                        <th>Stock 1</th>
                                        <th>Suc. 2</th>
                                        <th>Stock 2</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productos as $producto): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($producto['PRODUCTO_CODIGO']); ?></td>
                                            <td><?php echo htmlspecialchars($producto['PRODUCTO_DESCRIPCION']); ?></td>
                                            <td><?php echo htmlspecialchars($producto['MARCA_DESCRIPCION']); ?></td>
                                            <td><?php echo htmlspecialchars($producto['FAMILIA_DESCRIPCION']); ?></td>
                                            <td><?php echo $producto['VENTA_SUCURSAL01']; ?></td>
                                            <td><?php echo $producto['STOCK_BODEGA01']; ?></td>
                                            <td><?php echo $producto['VENTA_SUCURSAL02']; ?></td>
                                            <td><?php echo $producto['STOCK_BODEGA02']; ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="verDetalles('<?php echo $producto['PRODUCTO_CODIGO']; ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación -->
                        <?php if ($totalPaginas > 1): ?>
                            <nav aria-label="Paginación de productos">
                                <ul class="pagination justify-content-center">
                                    <?php if ($pagina > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?php echo ($pagina - 1); ?>&busqueda=<?php echo urlencode($busqueda); ?>&fecha_inicio=<?php echo urlencode($fechaInicio); ?>&fecha_fin=<?php echo urlencode($fechaFin); ?>&proveedor=<?php echo urlencode($proveedor); ?>">
                                                &laquo; Anterior
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                        <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                                            <a class="page-link" href="?pagina=<?php echo $i; ?>&busqueda=<?php echo urlencode($busqueda); ?>&fecha_inicio=<?php echo urlencode($fechaInicio); ?>&fecha_fin=<?php echo urlencode($fechaFin); ?>&proveedor=<?php echo urlencode($proveedor); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($pagina < $totalPaginas): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?php echo ($pagina + 1); ?>&busqueda=<?php echo urlencode($busqueda); ?>&fecha_inicio=<?php echo urlencode($fechaInicio); ?>&fecha_fin=<?php echo urlencode($fechaFin); ?>&proveedor=<?php echo urlencode($proveedor); ?>">
                                                Siguiente &raquo;
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para mostrar detalles del producto -->
<div class="modal fade" id="modalDetalles" tabindex="-1" aria-labelledby="modalDetallesLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalDetallesLabel">Detalles del Producto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detallesProducto">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Función para mostrar detalles del producto
function verDetalles(codigo) {
    const modal = new bootstrap.Modal(document.getElementById('modalDetalles'));
    
    // Mostrar el modal
    modal.show();
    
    // Buscar el producto en la tabla actual
    const productos = <?php echo json_encode($productos); ?>;
    let producto = null;
    
    for (let i = 0; i < productos.length; i++) {
        if (productos[i].PRODUCTO_CODIGO === codigo) {
            producto = productos[i];
            break;
        }
    }
    
    if (producto) {
        document.getElementById('detallesProducto').innerHTML = `
            <table class="table table-borderless">
                <tr>
                    <th>Código:</th>
                    <td>${producto.PRODUCTO_CODIGO}</td>
                </tr>
                <tr>
                    <th>Descripción:</th>
                    <td>${producto.PRODUCTO_DESCRIPCION}</td>
                </tr>
                <tr>
                    <th>Marca:</th>
                    <td>${producto.MARCA_DESCRIPCION}</td>
                </tr>
                <tr>
                    <th>Familia:</th>
                    <td>${producto.FAMILIA_DESCRIPCION}</td>
                </tr>
                <tr>
                    <th colspan="2" class="bg-light text-center">Ventas y Stock por Sucursal</th>
                </tr>
                <tr>
                    <th>Sucursal 1 - Ventas:</th>
                    <td>${producto.VENTA_SUCURSAL01}</td>
                </tr>
                <tr>
                    <th>Sucursal 1 - Stock:</th>
                    <td>${producto.STOCK_BODEGA01}</td>
                </tr>
                <tr>
                    <th>Sucursal 2 - Ventas:</th>
                    <td>${producto.VENTA_SUCURSAL02}</td>
                </tr>
                <tr>
                    <th>Sucursal 2 - Stock:</th>
                    <td>${producto.STOCK_BODEGA02}</td>
                </tr>
                <tr>
                    <th>Sucursal 3 - Ventas:</th>
                    <td>${producto.VENTA_SUCURSAL03 || '0'}</td>
                </tr>
                <tr>
                    <th>Sucursal 3 - Stock:</th>
                    <td>${producto.STOCK_BODEGA03 || '0'}</td>
                </tr>
            </table>
        `;
    } else {
        document.getElementById('detallesProducto').innerHTML = `
            <div class="alert alert-danger">
                No se pudo encontrar la información del producto con código ${codigo}.
            </div>
        `;
    }
}
</script>

<?php
// Incluir el pie de página
include 'footer.php';
?>
