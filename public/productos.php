<?php
/**
 * Página de gestión de productos
 * Permite ver, filtrar y exportar productos del proveedor logueado
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

// Verificar autenticación
requireLogin();

// Título de la página
$pageTitle = 'Gestión de Productos';

// Incluir el encabezado
include 'header.php';

// Inicializar variables para filtros
$busqueda = $_GET['busqueda'] ?? '';
$ordenamiento = $_GET['orden'] ?? 'nombre';
$direccion = $_GET['dir'] ?? 'asc';

// Inicializar variables para paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$porPagina = 10;
$offset = ($pagina - 1) * $porPagina;

// Filtrar por proveedor si es un usuario proveedor
$filtroProveedor = "";
$params = [];

if ($_SESSION['user']['rol'] === 'proveedor') {
    $filtroProveedor = "WHERE proveedor_rut = ?";
    $params[] = $_SESSION['user']['rut'];
} elseif (!empty($busqueda)) {
    $filtroProveedor = "WHERE nombre LIKE ? OR sku LIKE ?";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
} elseif (!empty($busqueda) && $_SESSION['user']['rol'] === 'proveedor') {
    $filtroProveedor = "WHERE proveedor_rut = ? AND (nombre LIKE ? OR sku LIKE ?)";
    $params[] = $_SESSION['user']['rut'];
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

// Ordenamiento
$ordenSQL = "ORDER BY $ordenamiento $direccion";

// Contar total de productos
$sqlTotal = "SELECT COUNT(*) as total FROM productos $filtroProveedor";
$resultado = fetchOne($sqlTotal, $params);
$totalProductos = $resultado ? $resultado['total'] : 0;
$totalPaginas = ceil($totalProductos / $porPagina);

// Obtener productos con paginación
$sql = "SELECT p.*, u.nombre as proveedor 
        FROM productos p 
        LEFT JOIN usuarios u ON p.proveedor_rut = u.rut 
        $filtroProveedor 
        $ordenSQL 
        LIMIT $offset, $porPagina";
$productos = fetchAll($sql, $params);

?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mb-0"><i class="fas fa-boxes me-2"></i>Gestión de Productos</h1>
                <?php if ($_SESSION['user']['rol'] === 'admin'): ?>
                <a href="exportar.php?tipo=productos" class="btn btn-success">
                    <i class="fas fa-file-excel me-2"></i>Exportar a Excel
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filtros y búsqueda -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <form action="" method="GET" class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" name="busqueda" 
                                    placeholder="Buscar por nombre o SKU" value="<?php echo htmlspecialchars($busqueda); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select name="orden" class="form-select">
                                <option value="nombre" <?php echo $ordenamiento === 'nombre' ? 'selected' : ''; ?>>Ordenar por Nombre</option>
                                <option value="sku" <?php echo $ordenamiento === 'sku' ? 'selected' : ''; ?>>Ordenar por SKU</option>
                                <option value="stock" <?php echo $ordenamiento === 'stock' ? 'selected' : ''; ?>>Ordenar por Stock</option>
                                <option value="precio" <?php echo $ordenamiento === 'precio' ? 'selected' : ''; ?>>Ordenar por Precio</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="dir" class="form-select">
                                <option value="asc" <?php echo $direccion === 'asc' ? 'selected' : ''; ?>>Ascendente</option>
                                <option value="desc" <?php echo $direccion === 'desc' ? 'selected' : ''; ?>>Descendente</option>
                            </select>
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
                                        <th>SKU</th>
                                        <th>Nombre</th>
                                        <th>Stock</th>
                                        <th>Precio</th>
                                        <?php if ($_SESSION['user']['rol'] === 'admin'): ?>
                                        <th>Proveedor</th>
                                        <?php endif; ?>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productos as $producto): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($producto['sku']); ?></td>
                                            <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                            <td><?php echo $producto['stock']; ?></td>
                                            <td>$<?php echo number_format($producto['precio'], 2, ',', '.'); ?></td>
                                            <?php if ($_SESSION['user']['rol'] === 'admin'): ?>
                                            <td><?php echo htmlspecialchars($producto['proveedor']); ?></td>
                                            <?php endif; ?>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="verDetalles(<?php echo $producto['id']; ?>)">
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
                                            <a class="page-link" href="?pagina=<?php echo ($pagina - 1); ?>&busqueda=<?php echo urlencode($busqueda); ?>&orden=<?php echo $ordenamiento; ?>&dir=<?php echo $direccion; ?>">
                                                &laquo; Anterior
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                        <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                                            <a class="page-link" href="?pagina=<?php echo $i; ?>&busqueda=<?php echo urlencode($busqueda); ?>&orden=<?php echo $ordenamiento; ?>&dir=<?php echo $direccion; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($pagina < $totalPaginas): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?php echo ($pagina + 1); ?>&busqueda=<?php echo urlencode($busqueda); ?>&orden=<?php echo $ordenamiento; ?>&dir=<?php echo $direccion; ?>">
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
function verDetalles(id) {
    const modal = new bootstrap.Modal(document.getElementById('modalDetalles'));
    
    // Mostrar el modal
    modal.show();
    
    // Cargar detalles vía AJAX
    fetch(`/api/productos.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const producto = data.data;
                document.getElementById('detallesProducto').innerHTML = `
                    <table class="table table-borderless">
                        <tr>
                            <th>SKU:</th>
                            <td>${producto.sku}</td>
                        </tr>
                        <tr>
                            <th>Nombre:</th>
                            <td>${producto.nombre}</td>
                        </tr>
                        <tr>
                            <th>Stock:</th>
                            <td>${producto.stock} unidades</td>
                        </tr>
                        <tr>
                            <th>Precio:</th>
                            <td>$${parseFloat(producto.precio).toLocaleString('es-CL')}</td>
                        </tr>
                        <tr>
                            <th>Valor total:</th>
                            <td>$${(producto.stock * producto.precio).toLocaleString('es-CL')}</td>
                        </tr>
                    </table>
                `;
            } else {
                document.getElementById('detallesProducto').innerHTML = `
                    <div class="alert alert-danger">
                        Error al cargar los detalles del producto: ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('detallesProducto').innerHTML = `
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
