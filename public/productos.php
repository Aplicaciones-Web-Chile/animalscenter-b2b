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
require_once __DIR__ . '/../includes/api_client.php';

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


// Valor monto neto
$valorNeto = getMontoVentaNetoFromAPI($fechaInicio, $fechaFin, $proveedor);

// Cantidad unidades vendidas
$unidadesVendidas = getCantidadVendidaFromAPI($fechaInicio, $fechaFin, $proveedor);

// SKU activos
$skuActivos = getCantidadSkuActivosFromAPI($proveedor);

// Detalle Valor monto neto
$detalleValorNeto = getDetalleVentaNeta($fechaInicio, $fechaFin, $proveedor);

// Detalle Cantidad unidades vendidas
$detalleUnidadesVendidas = getDetalleUnidadesVendidas($fechaInicio, $fechaFin, $proveedor);

// Detalle SKU activos
$detalleSkuActivos = getDetalleSkuActivos($proveedor);

// Stock unidades
$stockUnidades = getStockUnidadesFromAPI($fechaInicio, $fechaFin, $proveedor);

// Detalle Total Stock Unidades
$detalleStockUnidades = getDetalleStockUnidadesFromAPI($fechaInicio, $fechaFin, $proveedor);

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
                </h1>
                <a href="exportar.php?tipo=productos&fecha_inicio=<?php echo urlencode($fechaInicio); ?>&fecha_fin=<?php echo urlencode($fechaFin); ?>&proveedor=<?php echo urlencode($proveedor); ?>" class="btn btn-success">
                    <i class="fas fa-file-excel me-2"></i>Exportar a Excel
                </a>
            </div>
        </div>
    </div>

    <!-- Tarjetas de información -->
    <div class="row mb-4">
        <!-- Tarjeta 1: Monto de venta neto -->
        <div class="col-md-3 mb-3">
            <div class="card dashboard-card dashboard-card-primary h-100 shadow-sm">
                <div class="card-body position-relative">
                    <div class="dashboard-card-icon-wrapper bg-primary shadow">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <h5 class="card-title text-primary mt-3">Venta</h5>
                    <h2 class="dashboard-card-value"><?php echo formatCurrency($valorNeto); ?></h2>
                    <p class="card-text text-muted small">Monto de venta neto del período</p>
                </div>
                <div class="card-footer bg-transparent border-0 pb-3">
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalVentaNeta">
                        Ver detalles <i class="fas fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Tarjeta 2: Total Stock Valorizado -->
        <div class="col-md-3 mb-3">
            <div class="card dashboard-card dashboard-card-info h-100 shadow-sm">
                <div class="card-body position-relative">
                    <div class="dashboard-card-icon-wrapper bg-info shadow">
                        <i class="fas fa-box"></i>
                    </div>
                    <h5 class="card-title text-info mt-3">Total Stock Valorizado</h5>
                    <h2 class="dashboard-card-value"><?php echo 'Pendiente...' ?></h2>
                    <p class="card-text text-muted small">Cantidad total de stock valorizado</p>
                </div>
                <div class="card-footer bg-transparent border-0 pb-3">
                    <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalStockValorizado">
                        Ver detalles <i class="fas fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Tarjeta 3: Unidades vendidas -->
        <div class="col-md-3 mb-3">
            <div class="card dashboard-card dashboard-card-success h-100 shadow-sm">
                <div class="card-body position-relative">
                    <div class="dashboard-card-icon-wrapper bg-success shadow">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h5 class="card-title text-success mt-3">Venta Unidades</h5>
                    <h2 class="dashboard-card-value"><?php echo $unidadesVendidas; ?></h2>
                    <p class="card-text text-muted small">Cantidad total de unidades vendidas</p>
                </div>
                <div class="card-footer bg-transparent border-0 pb-3">
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalUnidadesVendidas">
                        Ver detalles <i class="fas fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Tarjeta 4: Pendiente -->
        <div class="col-md-3 mb-3">
            <div class="card dashboard-card dashboard-card-warning h-100 shadow-sm">
                <div class="card-body position-relative">
                    <div class="dashboard-card-icon-wrapper bg-warning shadow">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h5 class="card-title text-warning mt-3">Total stock unidades</h5>
                    <h2 class="dashboard-card-value"><?php echo $stockUnidades; ?></h2>
                    <p class="card-text text-muted small">Cantidad total de stock unidades</p>
                </div>
                <div class="card-footer bg-transparent border-0 pb-3">
                    <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalStockUnidades">
                        Ver detalles <i class="fas fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Estilos personalizados para las tarjetas modernas -->
    <style>
        .dashboard-card {
            border-radius: 12px;
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        }

        .dashboard-card-icon-wrapper {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dashboard-card-icon-wrapper i {
            font-size: 20px;
            color: white;
        }

        .dashboard-card-value {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 10px 0;
        }

        .dashboard-card-primary .dashboard-card-value { color: #0d6efd; }
        .dashboard-card-success .dashboard-card-value { color: #198754; }
        .dashboard-card-info .dashboard-card-value { color: #0dcaf0; }
        .dashboard-card-warning .dashboard-card-value { color: #ffc107; }

        /* Efecto de brillo en hover */
        .dashboard-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.3) 50%, rgba(255,255,255,0) 100%);
            transform: rotate(30deg);
            opacity: 0;
            transition: opacity 0.5s ease;
        }

        .dashboard-card:hover::before {
            animation: shine 1.5s ease;
        }

        @keyframes shine {
            0% { opacity: 0; transform: rotate(30deg) translateX(-300%); }
            30% { opacity: 1; }
            100% { opacity: 0; transform: rotate(30deg) translateX(300%); }
        }
    </style>

    <!-- Filtros y búsqueda -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card filtros-card">
                <div class="card-header bg-light">
                    <div class="d-flex flex-wrap justify-content-between align-items-center">
                        <h5 class="mb-2 mb-md-0">
                            <i class="fas fa-filter me-2" style="color: var(--primary-color);"></i>Filtros de búsqueda
                        </h5>

                        <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
                            <?php if (!empty($busqueda)): ?>
                                <a href="productos.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Limpiar filtros
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form id="form-filtros" action="" method="GET" class="row g-3 filtros-form">
                        <div class="col-md-3">
                            <label for="busqueda" class="form-label">Búsqueda</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="busqueda" name="busqueda"
                                    placeholder="Nombre, código o marca" value="<?php echo htmlspecialchars($busqueda); ?>">
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

                        <!-- Botón BUSCAR dentro del formulario -->
                        <?php if ($esAdmin): ?>
                            <div class="col-12 d-flex justify-content-end">
                        <?php else:?>
                            <div class="col-md-3 d-flex align-items-end">
                        <?php endif;?>
                            <button type="submit" class="btn btn-primary">BUSCAR</button>
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
// Pasar el total de productos desde PHP a JavaScript
const totalProductosGeneral = <?php echo isset($totalProductos) ? (int)$totalProductos : 0; ?>;

document.addEventListener('DOMContentLoaded', function() {
    const buscador = document.getElementById('tablaBuscador');
    const limpiarBtn = document.getElementById('limpiarBusqueda');
    const resultadosInfo = document.getElementById('resultadosBusqueda');
    const tabla = document.querySelector('.table-striped');
    const filas = tabla ? Array.from(tabla.querySelectorAll('tbody tr')) : [];
    let totalFilasPagina = filas.length;

    // Función para actualizar el contador de resultados
    function actualizarContadorResultados(mostrados) {
        resultadosInfo.textContent = `Mostrando ${mostrados} de ${totalProductosGeneral} productos`;
    }

    // Inicializar el contador
    actualizarContadorResultados(totalFilasPagina);

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

<!-- Modales para detalles de tarjetas -->

<!-- Modal Detalle Venta Neta -->
<div class="modal fade" id="modalVentaNeta" tabindex="-1" aria-labelledby="modalVentaNetaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalVentaNetaLabel">
                    <i class="fas fa-dollar-sign me-2"></i> Detalle de Venta Neta
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Marca</th>
                                <th>Cantidad</th>
                                <th>Precio Unit.</th>
                                <th>Total Neto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($detalleValorNeto)): ?>
                                <?php foreach ($detalleValorNeto as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['TIPO']); ?></td>
                                        <td><?php echo htmlspecialchars($item['FOLIO']); ?></td>
                                        <td><?php echo htmlspecialchars($item['FECHA_DE_EMISION']); ?></td>
                                        <td><?php echo htmlspecialchars($item['PRODUCTO_CODIGO']); ?></td>
                                        <td><?php echo htmlspecialchars($item['PRODUCTO_DESCRIPCION']); ?></td>
                                        <td><?php echo htmlspecialchars($item['MARCA_DESCRIPCION']); ?></td>
                                        <td><?php echo htmlspecialchars($item['CANTIDAD']); ?></td>
                                        <td><?php echo formatCurrency($item['PRECIO_UNITARIO_NETO']); ?></td>
                                        <td><?php echo formatCurrency($item['TOTAL_NETO']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">No hay datos disponibles</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted"><i class="fas fa-info-circle me-1"></i> Haga clic en el botón para exportar estos datos directamente a Excel</span>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cerrar</button>
                    <a href="exportar.php?tipo=detalle_venta_neta&fecha_inicio=<?php echo urlencode($fechaInicio); ?>&fecha_fin=<?php echo urlencode($fechaFin); ?>&proveedor=<?php echo urlencode($proveedor); ?>" class="btn btn-primary btn-lg">
                        <i class="fas fa-file-excel me-2"></i>Exportar a Excel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalle Unidades Vendidas -->
<div class="modal fade" id="modalUnidadesVendidas" tabindex="-1" aria-labelledby="modalUnidadesVendidasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalUnidadesVendidasLabel">
                    <i class="fas fa-shopping-cart me-2"></i> Detalle de Unidades Vendidas
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Marca</th>
                                <th>Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($detalleUnidadesVendidas)): ?>
                                <?php foreach ($detalleUnidadesVendidas as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['TIPO']); ?></td>
                                        <td><?php echo htmlspecialchars($item['FOLIO']); ?></td>
                                        <td><?php echo htmlspecialchars($item['FECHA_DE_EMISION']); ?></td>
                                        <td><?php echo htmlspecialchars($item['PRODUCTO_CODIGO']); ?></td>
                                        <td><?php echo htmlspecialchars($item['PRODUCTO_DESCRIPCION']); ?></td>
                                        <td><?php echo htmlspecialchars($item['MARCA_DESCRIPCION']); ?></td>
                                        <td><?php echo htmlspecialchars($item['CANTIDAD']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No hay datos disponibles</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted"><i class="fas fa-info-circle me-1"></i> Haga clic en el botón para exportar estos datos directamente a Excel</span>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cerrar</button>
                    <a href="exportar.php?tipo=detalle_unidades_vendidas&fecha_inicio=<?php echo urlencode($fechaInicio); ?>&fecha_fin=<?php echo urlencode($fechaFin); ?>&proveedor=<?php echo urlencode($proveedor); ?>" class="btn btn-success btn-lg">
                        <i class="fas fa-file-excel me-2"></i>Exportar a Excel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalle SKU Activos -->
<div class="modal fade" id="modalSkuActivos" tabindex="-1" aria-labelledby="modalSkuActivosLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="modalSkuActivosLabel">
                    <i class="fas fa-box me-2"></i> Detalle de SKU Activos
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Código de Barra</th>
                                <th>Marca</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($detalleSkuActivos)): ?>
                                <?php foreach ($detalleSkuActivos as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['PRODUCTO_CODIGO']); ?></td>
                                        <td><?php echo htmlspecialchars($item['PRODUCTO_DESCRIPCION']); ?></td>
                                        <td><?php echo htmlspecialchars($item['CODIGO_DE_BARRA']); ?></td>
                                        <td><?php echo htmlspecialchars($item['MARCA_DESCRIPCION']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">No hay datos disponibles</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted"><i class="fas fa-info-circle me-1"></i> Haga clic en el botón para exportar estos datos directamente a Excel</span>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cerrar</button>
                    <a href="exportar.php?tipo=detalle_sku_activos&proveedor=<?php echo urlencode($proveedor); ?>" class="btn btn-info btn-lg">
                        <i class="fas fa-file-excel me-2"></i>Exportar a Excel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalle Stock Unidades -->
<div class="modal fade" id="modalStockUnidades" tabindex="-1" aria-labelledby="modalStockUnidadesLabel" aria-hidden="true">
    <!-- Estilos específicos para este modal -->
    <style>
        .large-column {
            width: 120px !important;
            max-width: 120px !important;
            min-width: 120px !important;
        }
        /* Estilo para la columna Unidad */
        #modalStockUnidades .unidad-column {
            width: 80px !important;
            max-width: 80px !important;
            min-width: 80px !important;
        }
        
        /* Asegurar que la tabla respete los anchos de columna */
        #modalStockUnidades .table {
            table-layout: fixed;
        }
    </style>
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="modalStockUnidadesLabel">
                    <i class="fas fa-boxes me-2"></i> Detalle de Stock Unidades
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th class="large-column">Descripción</th>
                                <th class="large-column">Marca</th>
                                <th class="large-column">Categoría</th>
                                <th class="large-column">Subcategoría</th>
                                <th class="unidad-column">Unidad</th>
                                <th>Cant. Envase</th>
                                <th>Stock Suc.1</th>
                                <th>Stock Suc.2</th>
                                <th>Stock Suc.3</th>
                                <th>Stock Suc.4</th>
                                <th>Stock Suc.5</th>
                                <th>Stock Web</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($detalleStockUnidades)): ?>
                                <?php foreach ($detalleStockUnidades as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['KINS']); ?></td>
                                        <td class="large-column"><?php echo htmlspecialchars($item['DINS']); ?></td>
                                        <td><?php echo htmlspecialchars($item['DMAR']); ?></td>
                                        <td class="large-column"><?php echo htmlspecialchars($item['DFAI']); ?></td>
                                        <td class="large-column"><?php echo htmlspecialchars($item['DSUI']); ?></td>
                                        <td class="unidad-column"><?php echo htmlspecialchars($item['UINS']); ?></td>
                                        <td><?php echo htmlspecialchars($item['CENV']); ?></td>
                                        <td><?php echo htmlspecialchars($item['ST01']); ?></td>
                                        <td><?php echo htmlspecialchars($item['ST02']); ?></td>
                                        <td><?php echo htmlspecialchars($item['ST03']); ?></td>
                                        <td><?php echo htmlspecialchars($item['ST04']); ?></td>
                                        <td><?php echo htmlspecialchars($item['ST05']); ?></td>
                                        <td><?php echo htmlspecialchars($item['ST07']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="13" class="text-center">No hay datos disponibles</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted"><i class="fas fa-info-circle me-1"></i> Haga clic en el botón para exportar estos datos directamente a Excel</span>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cerrar</button>
                    <a href="exportar.php?tipo=detalle_stock_unidades&fecha_inicio=<?php echo urlencode($fechaInicio); ?>&fecha_fin=<?php echo urlencode($fechaFin); ?>&proveedor=<?php echo urlencode($proveedor); ?>" class="btn btn-warning btn-lg">
                        <i class="fas fa-file-excel me-2"></i>Exportar a Excel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Estilos adicionales para los modales -->
<style>
    .modal-content {
        border: none;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 15px 35px rgba(0,0,0,0.2);
    }

    .modal-header {
        padding: 1.2rem 1.5rem;
    }

    .modal-header .btn-close:focus {
        box-shadow: none;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid rgba(0,0,0,0.05);
    }

    .modal .table {
        margin-bottom: 0;
    }

    .modal .table th {
        position: sticky;
        top: 0;
        background-color: #f8f9fa;
        z-index: 1;
    }

    /* Animación para el modal */
    .modal.fade .modal-dialog {
        transform: scale(0.9);
        opacity: 0;
        transition: transform 0.3s ease, opacity 0.3s ease;
    }

    .modal.show .modal-dialog {
        transform: scale(1);
        opacity: 1;
    }

</style>

<?php
// Incluir el pie de página
include 'footer.php';
?>
