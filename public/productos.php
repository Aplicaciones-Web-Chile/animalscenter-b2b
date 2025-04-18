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
$busqueda       = $_GET['busqueda'] ?? '';
$fechaInicio    = $_GET['fecha_inicio'] ?? date('d/m/Y'); // Por defecto el día actual
$fechaFin       = $_GET['fecha_fin'] ?? date('d/m/Y'); // Por defecto el día actual
$distribuidor   = $_GET['distribuidor'] ?? '001'; // Código del distribuidor por defecto

// Determinar el rol del usuario actual
$esAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';

// Si el usuario es admin, puede cambiar el proveedor, si no, se usa el de la sesión
if ($esAdmin) {
    // El admin puede filtrar por cualquier proveedor
    $proveedor = $_GET['proveedor'] ?? '78843490'; // Código del proveedor por defecto para admin
} else {
    // Un proveedor solo puede ver sus propios productos
    $proveedor = $_SESSION['rut_proveedor'] ?? '0'; // Usar el código almacenado en la sesión
}

// Inicializar variables para paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$porPagina = 20;

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
    
    /* 
        Guardo $productosAPI en un json con los datos de los productos en un archivo 
        físico en la carpeta tmp con un nombre descriptivo de la fecha en que se ejecutó.
        Antes de guardarlo, lo limpio para que no tenga datos duplicados.
    */
    $file = file_put_contents(__DIR__ . '/tmp/productos_' . date('Y-m-d_H-i-s') . '.json', json_encode($productosAPI));
    if ($file === false) {
        error_log("Error al guardar productos en el archivo");
        die('Error al guardar productos en el archivo');
    }

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
                        <?php if ($esAdmin): ?>
                        <div class="col-md-3">
                            <label for="proveedor" class="form-label">Código Proveedor</label>
                            <input type="text" class="form-control" id="proveedor" name="proveedor" 
                                value="<?php echo htmlspecialchars($proveedor); ?>" placeholder="Código Proveedor">
                        </div>
                        <?php else: ?>
                        <!-- Para usuarios no administradores, enviamos el valor del proveedor como campo oculto -->
                        <input type="hidden" name="proveedor" value="<?php echo htmlspecialchars($proveedor); ?>">
                        <?php endif; ?>
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
                <div class="card-body bg-light py-2">
                    <!-- Buscador JavaScript para filtrar la tabla -->
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="input-group" style="max-width: 500px;">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" id="tablaBuscador" class="form-control" placeholder="Buscar por código, nombre, marca o familia..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="limpiarBusqueda">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div id="resultadosBusqueda" class="text-muted small"></div>
                    </div>
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
                                        <th width="15%">Ventas <br> Distribución </th>
                                        <th width="8%">Ventas <br> WEB</th>
                                        <th width="8%">Ventas <br> VERGARA</th>
                                        <th width="8%">Stock <br> VERGARA</th>
                                        <th width="8%">Ventas <br> LAMPA</th>
                                        <th width="8%">Stock <br> LAMPA</th>
                                        <th width="8%">Ventas <br> PANAMERICANA</th>
                                        <th width="8%">Stock <br> PANAMERICANA</th>
                                        <th width="8%">Ventas <br> MATTA</th>
                                        <th width="8%">Stock <br> MATTA</th>
                                        <th width="8%">Ventas <br> PROVIDENCIA</th>
                                        <th width="8%">Stock <br> PROVIDENCIA</th>
                                        <th width="8%">Acciones</th>
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
                                                    <?php if (!empty($producto['CODIGO_DE_BARRA'])): ?>
                                                        <small class="text-muted">COD: <?php echo htmlspecialchars($producto['CODIGO_DE_BARRA']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($producto['MARCA_DESCRIPCION']); ?></td>
                                            <td><?php echo htmlspecialchars($producto['FAMILIA_DESCRIPCION']); ?></td>
                                            <td><?php echo htmlspecialchars($producto['VENTA_DISTRIBUCION']); ?></td>
                                            <td><?php echo $producto['VENTA_SUCURSAL07']; ?></td>
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
                                            <td><?php echo $producto['VENTA_SUCURSAL03']; ?></td>
                                            <td>
                                                <span class="badge badge-stock <?php echo $stockClass3; ?>">
                                                    <?php echo $producto['STOCK_BODEGA03']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $producto['VENTA_SUCURSAL04']; ?></td>
                                            <td>
                                                <span class="badge badge-stock <?php echo $stockClass4; ?>">
                                                    <?php echo $producto['STOCK_BODEGA04']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $producto['VENTA_SUCURSAL05']; ?></td>
                                            <td>
                                                <span class="badge badge-stock <?php echo $stockClass5; ?>">
                                                    <?php echo $producto['STOCK_BODEGA05']; ?>
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
                                <div class="col-7">${producto.CODIGO_DE_BARRA || 'N/A'}</div>
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
                                        <td>VERGARA</td>
                                        <td class="text-center">${producto.VENTA_SUCURSAL01 || '0'}</td>
                                        <td class="text-center">
                                            <span class="badge badge-stock ${producto.STOCK_BODEGA01 <= 5 ? 'low' : (producto.STOCK_BODEGA01 <= 20 ? 'medium' : '')}">
                                                ${producto.STOCK_BODEGA01 || '0'}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>LAMPA</td>
                                        <td class="text-center">${producto.VENTA_SUCURSAL02 || '0'}</td>
                                        <td class="text-center">
                                            <span class="badge badge-stock ${producto.STOCK_BODEGA02 <= 5 ? 'low' : (producto.STOCK_BODEGA02 <= 20 ? 'medium' : '')}">
                                                ${producto.STOCK_BODEGA02 || '0'}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>PANAMERICANA</td>
                                        <td class="text-center">${producto.VENTA_SUCURSAL03 || '0'}</td>
                                        <td class="text-center">
                                            <span class="badge badge-stock ${producto.STOCK_BODEGA03 <= 5 ? 'low' : (producto.STOCK_BODEGA03 <= 20 ? 'medium' : '')}">
                                                ${producto.STOCK_BODEGA03 || '0'}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>MATTA</td>
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

<!-- Script para el buscador de la tabla -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const buscador = document.getElementById('tablaBuscador');
    const limpiarBtn = document.getElementById('limpiarBusqueda');
    const resultadosInfo = document.getElementById('resultadosBusqueda');
    const tabla = document.querySelector('.table-striped');
    const filas = tabla ? Array.from(tabla.querySelectorAll('tbody tr')) : [];
    let totalFilas = filas.length;
    
    // Función para actualizar el contador de resultados
    function actualizarContadorResultados(mostrados) {
        resultadosInfo.textContent = `Mostrando ${mostrados} de ${totalFilas} productos`;
    }
    
    // Inicializar el contador
    actualizarContadorResultados(totalFilas);
    
    // Función para filtrar las filas de la tabla
    function filtrarTabla() {
        const textoBusqueda = buscador.value.toLowerCase().trim();
        let filasVisibles = 0;
        
        // Si no hay texto de búsqueda, mostrar todas las filas
        if (textoBusqueda === '') {
            filas.forEach(fila => {
                fila.style.display = '';
                filasVisibles++;
            });
        } else {
            // Filtrar filas según el texto de búsqueda
            filas.forEach(fila => {
                // Extraer el texto de las celdas relevantes (código, producto, marca, familia)
                const codigo = fila.cells[0].textContent.toLowerCase();
                const producto = fila.cells[1].textContent.toLowerCase();
                const marca = fila.cells[2].textContent.toLowerCase();
                const familia = fila.cells[3].textContent.toLowerCase();
                
                // Comprobar si el texto de búsqueda está en alguna de las celdas
                if (codigo.includes(textoBusqueda) || 
                    producto.includes(textoBusqueda) || 
                    marca.includes(textoBusqueda) || 
                    familia.includes(textoBusqueda)) {
                    fila.style.display = '';
                    filasVisibles++;
                } else {
                    fila.style.display = 'none';
                }
            });
        }
        
        // Actualizar contador de resultados
        actualizarContadorResultados(filasVisibles);
        
        // Mostrar/ocultar el botón de limpiar según haya texto o no
        if (textoBusqueda === '') {
            limpiarBtn.classList.add('d-none');
        } else {
            limpiarBtn.classList.remove('d-none');
        }
    }
    
    // Evento para filtrar al escribir en el buscador
    buscador.addEventListener('input', filtrarTabla);
    
    // Evento para limpiar la búsqueda
    limpiarBtn.addEventListener('click', function() {
        buscador.value = '';
        filtrarTabla();
        buscador.focus();
    });
    
    // Ocultar el botón de limpiar al inicio
    limpiarBtn.classList.add('d-none');
});
</script>

<?php
// Incluir el pie de página
include 'footer.php';
?>
