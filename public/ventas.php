<?php
/**
 * Página de gestión de ventas
 * Maneja la lógica de visualización de ventas utilizando el patrón MVC
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 2.0
 */

// Incluir archivos necesarios
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../controllers/VentaController.php';

// Iniciar sesión
startSession();

// Verificar autenticación
requireLogin();

// Verificar que sea admin o proveedor
if (!isAdmin() && !isProveedor()) {
    setFlashMessage('error', 'No tienes permisos para acceder a esta página');
    redirect('dashboard.php');
}

// Inicializar controlador
$ventaController = new VentaController();

// Título de la página
$pageTitle = 'Gestión de Ventas';

// Incluir el encabezado
include 'header.php';

// Obtener parámetros de filtros y paginación
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');
$productoId = $_GET['producto_id'] ?? '';
$ordenamiento = $_GET['orden'] ?? 'fecha';
$direccion = $_GET['dir'] ?? 'desc';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$porPagina = 10;
$offset = ($pagina - 1) * $porPagina;

// Parámetros para el controlador
$params = [
    'offset' => $offset,
    'limite' => $porPagina,
    'proveedorRut' => isProveedor() ? $_SESSION['user_rut'] : null
];

// Obtener datos de ventas
$ventasData = $ventaController->getVentas($params);
// Si ocurrió un error al obtener las ventas
if (!$ventasData['success']) {
    $_SESSION['error_message'] = $ventasData['message'] ?? 'Error al cargar las ventas';
    header('Location: error.php');
    exit;
}

// Extraer datos para la vista
$ventas = $ventasData['data']['ventas'];
$totalVentas = $ventasData['data']['total'];
$totalUnidades = $ventasData['data']['totalUnidades'];
$totalPaginas = ceil($totalVentas / $porPagina);

?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Gestión de Ventas</h1>
                <a href="exportar.php?tipo=ventas&fecha_inicio=<?php echo $fechaInicio; ?>&fecha_fin=<?php echo $fechaFin; ?>&producto_id=<?php echo $productoId; ?>" class="btn btn-success">
                    <i class="fas fa-file-excel me-2"></i>Exportar a Excel
                </a>
            </div>
        </div>
    </div>

    <!-- Tarjetas de resumen -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total de Ventas</h5>
                    <p class="card-text display-6"><?php echo number_format($totalVentas, 0, ',', '.'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Unidades Vendidas</h5>

                    <p class="card-text display-6"><?php echo number_format($totalUnidades, 0, ',', '.'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Periodo</h5>
                    <p class="card-text"><?php echo date('d/m/Y', strtotime($fechaInicio)); ?> - <?php echo date('d/m/Y', strtotime($fechaFin)); ?></p>
                </div>
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
                            <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                                   value="<?php echo $fechaInicio; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="fecha_fin" class="form-label">Fecha Fin</label>
                            <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                                   value="<?php echo $fechaFin; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="producto_id" class="form-label">Producto</label>
                            <select class="form-select" id="producto_id" name="producto_id">
                                <option value="">Todos los productos</option>
                                <?php foreach ($ventasData['data']['productos'] as $producto): ?>
                                <option value="<?php echo $producto['id']; ?>" <?php echo $productoId == $producto['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($producto['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="orden" class="form-label">Ordenar por</label>
                            <select class="form-select" id="orden" name="orden">
                                <option value="fecha" <?php echo $ordenamiento === 'fecha' ? 'selected' : ''; ?>>Fecha</option>
                                <option value="cantidad" <?php echo $ordenamiento === 'cantidad' ? 'selected' : ''; ?>>Cantidad</option>
                                <option value="id" <?php echo $ordenamiento === 'id' ? 'selected' : ''; ?>>ID</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label for="dir" class="form-label">Orden</label>
                            <select class="form-select" id="dir" name="dir">
                                <option value="desc" <?php echo $direccion === 'desc' ? 'selected' : ''; ?>>↓</option>
                                <option value="asc" <?php echo $direccion === 'asc' ? 'selected' : ''; ?>>↑</option>
                            </select>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i>Filtrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Listado de ventas -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Listado de Ventas</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($ventas)): ?>
                        <div class="alert alert-info">
                            No se encontraron ventas en el período seleccionado.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Fecha</th>
                                        <th>Producto</th>
                                        <th>SKU</th>
                                        <th>Cantidad</th>
                                        <?php if ($_SESSION['user']['rol'] === 'admin'): ?>
                                        <th>Proveedor</th>
                                        <?php endif; ?>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ventas as $venta): ?>
                                        <tr>
                                            <td><?php echo $venta['id']; ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha'])); ?></td>
                                            <td><?php echo htmlspecialchars($venta['producto']); ?></td>
                                            <td><?php echo htmlspecialchars($venta['sku']); ?></td>
                                            <td><?php echo $venta['cantidad']; ?></td>
                                            <?php if ($_SESSION['user']['rol'] === 'admin'): ?>
                                            <td><?php echo htmlspecialchars($venta['proveedor']); ?></td>
                                            <?php endif; ?>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="verDetalles(<?php echo $venta['id']; ?>)">
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
                            <nav aria-label="Paginación de ventas">
                                <ul class="pagination justify-content-center">
                                    <?php if ($pagina > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?php echo ($pagina - 1); ?>&fecha_inicio=<?php echo $fechaInicio; ?>&fecha_fin=<?php echo $fechaFin; ?>&producto_id=<?php echo $productoId; ?>&orden=<?php echo $ordenamiento; ?>&dir=<?php echo $direccion; ?>">
                                                &laquo; Anterior
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                        <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                                            <a class="page-link" href="?pagina=<?php echo $i; ?>&fecha_inicio=<?php echo $fechaInicio; ?>&fecha_fin=<?php echo $fechaFin; ?>&producto_id=<?php echo $productoId; ?>&orden=<?php echo $ordenamiento; ?>&dir=<?php echo $direccion; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($pagina < $totalPaginas): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?php echo ($pagina + 1); ?>&fecha_inicio=<?php echo $fechaInicio; ?>&fecha_fin=<?php echo $fechaFin; ?>&producto_id=<?php echo $productoId; ?>&orden=<?php echo $ordenamiento; ?>&dir=<?php echo $direccion; ?>">
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

<!-- Modal para mostrar detalles de la venta -->
<div class="modal fade" id="modalDetalles" tabindex="-1" aria-labelledby="modalDetallesLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalDetallesLabel">Detalles de la Venta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detallesVenta">
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
// Función para mostrar detalles de la venta
function verDetalles(id) {
    const modal = new bootstrap.Modal(document.getElementById('modalDetalles'));
    
    // Mostrar el modal
    modal.show();
    
    // Cargar detalles vía AJAX
    fetch(`/api/ventas.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const venta = data.data;
                document.getElementById('detallesVenta').innerHTML = `
                    <table class="table table-borderless">
                        <tr>
                            <th>ID Venta:</th>
                            <td>${venta.id}</td>
                        </tr>
                        <tr>
                            <th>Producto:</th>
                            <td>${venta.producto}</td>
                        </tr>
                        <tr>
                            <th>SKU:</th>
                            <td>${venta.sku}</td>
                        </tr>
                        <tr>
                            <th>Cantidad:</th>
                            <td>${venta.cantidad} unidades</td>
                        </tr>
                        <tr>
                            <th>Fecha:</th>
                            <td>${new Date(venta.fecha).toLocaleString('es-CL')}</td>
                        </tr>
                        <tr>
                            <th>Proveedor:</th>
                            <td>${venta.proveedor}</td>
                        </tr>
                    </table>
                `;
            } else {
                document.getElementById('detallesVenta').innerHTML = `
                    <div class="alert alert-danger">
                        Error al cargar los detalles de la venta: ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('detallesVenta').innerHTML = `
                <div class="alert alert-danger">
                    Error de comunicación con el servidor.
                </div>
            `;
            console.error('Error:', error);
        });
}
</script>

<?php
// Incluir el pie de página
include 'footer.php';
?>
