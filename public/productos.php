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
                <h1 class="mb-0">
                    <i class="fas fa-boxes me-2" style="color: var(--primary-color);"></i>
                    <span>Gestión de Productos</span>
                    <span class="badge bg-primary ms-2" style="font-size: 0.5em; vertical-align: middle;"><?php echo $totalProductos ?? 0; ?> productos</span>
                </h1>
                <a href="exportar.php?tipo=productos&fecha_inicio=<?php echo urlencode($fechaInicio); ?>&fecha_fin=<?php echo urlencode($fechaFin); ?>&proveedor=<?php echo urlencode($proveedor); ?>" class="btn btn-success">
                    <i class="fas fa-file-excel me-2"></i>Exportar a Excel
                </a>
            </div>
        </div>
    </div>

    <!-- Filtros y búsqueda -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card filtros-card">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-filter me-2" style="color: var(--primary-color);"></i>Filtros de búsqueda</h5>
                        <?php if (!empty($busqueda)): ?>
                            <a href="productos.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Limpiar filtros
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <form action="" method="GET" class="row g-3 filtros-form">
                        <div class="col-md-3">
                            <label for="busqueda" class="form-label">Búsqueda</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="busqueda" name="busqueda" 
                                    placeholder="Nombre, código o marca" value="<?php echo htmlspecialchars($busqueda); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
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
                        <div class="table-responsive mb-3">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th width="10%">Código</th>
                                        <th width="30%">Producto</th>
                                        <th width="15%">Marca</th>
                                        <th width="15%">Familia</th>
                                        <th width="8%">Ventas</th>
                                        <th width="8%">Stock</th>
                                        <th width="8%">Ventas</th>
                                        <th width="8%">Stock</th>
                                        <th width="8%">Acciones</th>
                                    </tr>
                                    <tr class="text-muted" style="font-size: 0.8rem; text-transform: none;">
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th>Suc. 1</th>
                                        <th>Suc. 1</th>
                                        <th>Suc. 2</th>
                                        <th>Suc. 2</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productos as $producto): ?>
                                        <?php 
                                        // Determinar clase para indicadores de stock
                                        $stockClass1 = $producto['STOCK_BODEGA01'] <= 5 ? 'low' : ($producto['STOCK_BODEGA01'] <= 20 ? 'medium' : '');
                                        $stockClass2 = $producto['STOCK_BODEGA02'] <= 5 ? 'low' : ($producto['STOCK_BODEGA02'] <= 20 ? 'medium' : '');
                                        ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($producto['PRODUCTO_CODIGO']); ?></td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-medium"><?php echo htmlspecialchars($producto['PRODUCTO_DESCRIPCION']); ?></span>
                                                    <?php if (!empty($producto['PRODUCTO_BARCODE'])): ?>
                                                        <small class="text-muted">COD: <?php echo htmlspecialchars($producto['PRODUCTO_BARCODE']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($producto['MARCA_DESCRIPCION']); ?></td>
                                            <td><?php echo htmlspecialchars($producto['FAMILIA_DESCRIPCION']); ?></td>
                                            <td><?php echo $producto['VENTA_SUCURSAL01']; ?></td>
                                            <td>
                                                <span class="badge badge-stock <?php echo $stockClass1; ?>">
                                                    <?php echo $producto['STOCK_BODEGA01']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $producto['VENTA_SUCURSAL02']; ?></td>
                                            <td>
                                                <span class="badge badge-stock <?php echo $stockClass2; ?>">
                                                    <?php echo $producto['STOCK_BODEGA02']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-action btn-info" onclick="verDetalles('<?php echo $producto['PRODUCTO_CODIGO']; ?>')" 
                                                    data-tooltip="Ver detalles del producto">
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalDetallesLabel"><i class="fas fa-box-open me-2"></i>Detalles del Producto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="detallesProducto">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-3 text-muted">Cargando información del producto...</p>
                </div>
            </div>
            <div class="modal-footer">
                <!--<a href="#" class="btn btn-primary me-auto d-none" id="exportarProductoBtn">
                    <i class="fas fa-file-excel me-1"></i> Exportar producto
                </a>-->
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
        // Calcular el total de stock y ventas
        const totalStock = (
            (producto.STOCK_BODEGA01 || 0) + 
            (producto.STOCK_BODEGA02 || 0) + 
            (producto.STOCK_BODEGA03 || 0) + 
            (producto.STOCK_BODEGA04 || 0) + 
            (producto.STOCK_BODEGA05 || 0)
        );
        
        const totalVentas = (
            (producto.VENTA_SUCURSAL01 || 0) + 
            (producto.VENTA_SUCURSAL02 || 0) + 
            (producto.VENTA_SUCURSAL03 || 0) + 
            (producto.VENTA_SUCURSAL04 || 0) + 
            (producto.VENTA_SUCURSAL05 || 0)
        );
        
        // Calcular la rotación
        const rotacion = totalVentas > 0 && totalStock > 0 ? (totalStock / totalVentas).toFixed(2) : 0;
        
        // Determinar si es "Buena Percha" (B/P)
        const esBP = (totalVentas > 0 && totalStock > 0) || rotacion > 1;
        
        document.getElementById('detallesProducto').innerHTML = `
            <div class="row g-0">
                <div class="col-md-4 bg-light">
                    <div class="p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary text-white rounded p-2 me-3">
                                <i class="fas fa-barcode fa-2x"></i>
                            </div>
                            <div>
                                <h3 class="mb-0">${producto.PRODUCTO_CODIGO}</h3>
                                <div class="text-muted small">Código de Producto</div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2">Información General</h5>
                            <div class="row mb-2">
                                <div class="col-5 text-muted">Descripción:</div>
                                <div class="col-7 fw-medium">${producto.PRODUCTO_DESCRIPCION}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 text-muted">Código de Barra:</div>
                                <div class="col-7">${producto.PRODUCTO_BARCODE || 'N/A'}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 text-muted">Marca:</div>
                                <div class="col-7">${producto.MARCA_DESCRIPCION}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 text-muted">Familia:</div>
                                <div class="col-7">${producto.FAMILIA_DESCRIPCION}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 text-muted">Subfamilia:</div>
                                <div class="col-7">${producto.SUBFAMILIA_DESCRIPCION || 'N/A'}</div>
                            </div>
                        </div>
                        
                        <div>
                            <h5 class="border-bottom pb-2">Resumen</h5>
                            <div class="row mb-2">
                                <div class="col-6 text-muted">Stock Total:</div>
                                <div class="col-6 fw-bold">${totalStock}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6 text-muted">Ventas Totales:</div>
                                <div class="col-6 fw-bold">${totalVentas}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6 text-muted">Rotación:</div>
                                <div class="col-6 fw-bold">${rotacion}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6 text-muted">Buena Percha:</div>
                                <div class="col-6">
                                    <span class="badge ${esBP ? 'bg-success' : 'bg-secondary'}">
                                        ${esBP ? 'SI' : 'NO'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="p-4">
                        <h5 class="border-bottom pb-2 mb-3">Detalle por Sucursal</h5>
                        
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Sucursal</th>
                                        <th class="text-center">Ventas</th>
                                        <th class="text-center">Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>COPIAPO</td>
                                        <td class="text-center">${producto.VENTA_SUCURSAL01 || '0'}</td>
                                        <td class="text-center">
                                            <span class="badge badge-stock ${producto.STOCK_BODEGA01 <= 5 ? 'low' : (producto.STOCK_BODEGA01 <= 20 ? 'medium' : '')}">
                                                ${producto.STOCK_BODEGA01 || '0'}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>LA SERENA</td>
                                        <td class="text-center">${producto.VENTA_SUCURSAL02 || '0'}</td>
                                        <td class="text-center">
                                            <span class="badge badge-stock ${producto.STOCK_BODEGA02 <= 5 ? 'low' : (producto.STOCK_BODEGA02 <= 20 ? 'medium' : '')}">
                                                ${producto.STOCK_BODEGA02 || '0'}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>ANTOFAGASTA</td>
                                        <td class="text-center">${producto.VENTA_SUCURSAL03 || '0'}</td>
                                        <td class="text-center">
                                            <span class="badge badge-stock ${producto.STOCK_BODEGA03 <= 5 ? 'low' : (producto.STOCK_BODEGA03 <= 20 ? 'medium' : '')}">
                                                ${producto.STOCK_BODEGA03 || '0'}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>ÑUÑOA</td>
                                        <td class="text-center">${producto.VENTA_SUCURSAL04 || '0'}</td>
                                        <td class="text-center">
                                            <span class="badge badge-stock ${producto.STOCK_BODEGA04 <= 5 ? 'low' : (producto.STOCK_BODEGA04 <= 20 ? 'medium' : '')}">
                                                ${producto.STOCK_BODEGA04 || '0'}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>PROVIDENCIA</td>
                                        <td class="text-center">${producto.VENTA_SUCURSAL05 || '0'}</td>
                                        <td class="text-center">
                                            <span class="badge badge-stock ${producto.STOCK_BODEGA05 <= 5 ? 'low' : (producto.STOCK_BODEGA05 <= 20 ? 'medium' : '')}">
                                                ${producto.STOCK_BODEGA05 || '0'}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Mostrar botón de exportar producto
        const exportarBtn = document.getElementById('exportarProductoBtn');
        exportarBtn.classList.remove('d-none');
        exportarBtn.href = `exportar.php?tipo=productos&fecha_inicio=<?php echo urlencode($fechaInicio); ?>&fecha_fin=<?php echo urlencode($fechaFin); ?>&proveedor=${producto.PRODUCTO_CODIGO}`;
    
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
