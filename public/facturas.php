<?php
/**
 * Página de gestión de facturas
 * Permite ver, filtrar y exportar facturas del proveedor logueado
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
$pageTitle = 'Gestión de Facturas';

// Incluir el encabezado
include 'header.php';

// Inicializar variables para filtros
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');
$estado = $_GET['estado'] ?? '';
$ordenamiento = $_GET['orden'] ?? 'fecha';
$direccion = $_GET['dir'] ?? 'desc';

// Inicializar variables para paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$porPagina = 10;
$offset = ($pagina - 1) * $porPagina;

// Construir condiciones de filtrado
$condiciones = ["f.fecha BETWEEN ? AND ?"];
$params = [$fechaInicio . " 00:00:00", $fechaFin . " 23:59:59"];

// Filtrar por estado específico
if (!empty($estado)) {
    $condiciones[] = "f.estado = ?";
    $params[] = $estado;
}

// Filtrar por proveedor si es un usuario proveedor
if ($_SESSION['user']['rol'] === 'proveedor') {
    $condiciones[] = "f.proveedor_rut = ?";
    $params[] = $_SESSION['user']['rut'];
}

// Crear string de WHERE con condiciones
$where = "";
if (!empty($condiciones)) {
    $where = "WHERE " . implode(" AND ", $condiciones);
}

// Ordenamiento
$ordenSQL = "ORDER BY f.$ordenamiento $direccion";

// Contar total de facturas
$sqlTotal = "SELECT COUNT(*) as total FROM facturas f $where";
$resultado = fetchOne($sqlTotal, $params);
$totalFacturas = $resultado ? $resultado['total'] : 0;
$totalPaginas = ceil($totalFacturas / $porPagina);

// Obtener facturas con paginación
$sql = "SELECT f.*, v.producto_id, p.nombre as producto, p.sku, u.nombre as proveedor
        FROM facturas f
        JOIN ventas v ON f.venta_id = v.id
        JOIN productos p ON v.producto_id = p.id
        LEFT JOIN usuarios u ON f.proveedor_rut = u.rut
        $where
        $ordenSQL
        LIMIT $offset, $porPagina";
$facturas = fetchAll($sql, $params);

// Calcular monto total para las facturas filtradas
$sqlMontoTotal = "SELECT SUM(monto) as total FROM facturas f $where";
$resultadoMonto = fetchOne($sqlMontoTotal, $params);
$montoTotal = $resultadoMonto ? $resultadoMonto['total'] : 0;

// Contador por estados
$sqlEstados = "SELECT 
                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado = 'pagada' THEN 1 ELSE 0 END) as pagadas,
                SUM(CASE WHEN estado = 'vencida' THEN 1 ELSE 0 END) as vencidas
               FROM facturas f 
               $where";
$contadorEstados = fetchOne($sqlEstados, $params);

?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Gestión de Facturas</h1>
                <a href="exportar.php?tipo=facturas&fecha_inicio=<?php echo $fechaInicio; ?>&fecha_fin=<?php echo $fechaFin; ?>&estado=<?php echo $estado; ?>" class="btn btn-success">
                    <i class="fas fa-file-excel me-2"></i>Exportar a Excel
                </a>
            </div>
        </div>
    </div>

    <!-- Tarjetas de resumen -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total</h5>
                    <p class="card-text display-6"><?php echo number_format($totalFacturas, 0, ',', '.'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title">Pendientes</h5>
                    <p class="card-text display-6"><?php echo number_format($contadorEstados['pendientes'] ?? 0, 0, ',', '.'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Pagadas</h5>
                    <p class="card-text display-6"><?php echo number_format($contadorEstados['pagadas'] ?? 0, 0, ',', '.'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Vencidas</h5>
                    <p class="card-text display-6"><?php echo number_format($contadorEstados['vencidas'] ?? 0, 0, ',', '.'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjeta de monto total -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="card-title">Monto Total Facturas</h5>
                        </div>
                        <div class="col-md-6 text-end">
                            <p class="display-5 mb-0">$<?php echo number_format($montoTotal, 0, ',', '.'); ?></p>
                        </div>
                    </div>
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
                        <div class="col-md-2">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="">Todos</option>
                                <option value="pendiente" <?php echo $estado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="pagada" <?php echo $estado === 'pagada' ? 'selected' : ''; ?>>Pagada</option>
                                <option value="vencida" <?php echo $estado === 'vencida' ? 'selected' : ''; ?>>Vencida</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="orden" class="form-label">Ordenar por</label>
                            <select class="form-select" id="orden" name="orden">
                                <option value="fecha" <?php echo $ordenamiento === 'fecha' ? 'selected' : ''; ?>>Fecha</option>
                                <option value="monto" <?php echo $ordenamiento === 'monto' ? 'selected' : ''; ?>>Monto</option>
                                <option value="id" <?php echo $ordenamiento === 'id' ? 'selected' : ''; ?>>ID</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="dir" class="form-label">Dirección</label>
                            <select class="form-select" id="dir" name="dir">
                                <option value="desc" <?php echo $direccion === 'desc' ? 'selected' : ''; ?>>Descendente</option>
                                <option value="asc" <?php echo $direccion === 'asc' ? 'selected' : ''; ?>>Ascendente</option>
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

    <!-- Listado de facturas -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Listado de Facturas</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($facturas)): ?>
                        <div class="alert alert-info">
                            No se encontraron facturas que coincidan con los filtros seleccionados.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Fecha</th>
                                        <th>Producto</th>
                                        <th>Monto</th>
                                        <th>Estado</th>
                                        <?php if ($_SESSION['user']['rol'] === 'admin'): ?>
                                        <th>Proveedor</th>
                                        <?php endif; ?>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($facturas as $factura): ?>
                                        <tr>
                                            <td><?php echo $factura['id']; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($factura['fecha'])); ?></td>
                                            <td><?php echo htmlspecialchars($factura['producto']); ?></td>
                                            <td>$<?php echo number_format($factura['monto'], 0, ',', '.'); ?></td>
                                            <td>
                                                <?php if ($factura['estado'] === 'pendiente'): ?>
                                                    <span class="badge bg-warning text-dark">Pendiente</span>
                                                <?php elseif ($factura['estado'] === 'pagada'): ?>
                                                    <span class="badge bg-success">Pagada</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Vencida</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($_SESSION['user']['rol'] === 'admin'): ?>
                                            <td><?php echo htmlspecialchars($factura['proveedor']); ?></td>
                                            <?php endif; ?>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="verDetalles(<?php echo $factura['id']; ?>)">
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
                            <nav aria-label="Paginación de facturas">
                                <ul class="pagination justify-content-center">
                                    <?php if ($pagina > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?php echo ($pagina - 1); ?>&fecha_inicio=<?php echo $fechaInicio; ?>&fecha_fin=<?php echo $fechaFin; ?>&estado=<?php echo $estado; ?>&orden=<?php echo $ordenamiento; ?>&dir=<?php echo $direccion; ?>">
                                                &laquo; Anterior
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                        <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                                            <a class="page-link" href="?pagina=<?php echo $i; ?>&fecha_inicio=<?php echo $fechaInicio; ?>&fecha_fin=<?php echo $fechaFin; ?>&estado=<?php echo $estado; ?>&orden=<?php echo $ordenamiento; ?>&dir=<?php echo $direccion; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($pagina < $totalPaginas): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?php echo ($pagina + 1); ?>&fecha_inicio=<?php echo $fechaInicio; ?>&fecha_fin=<?php echo $fechaFin; ?>&estado=<?php echo $estado; ?>&orden=<?php echo $ordenamiento; ?>&dir=<?php echo $direccion; ?>">
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

<!-- Modal para mostrar detalles de la factura -->
<div class="modal fade" id="modalDetalles" tabindex="-1" aria-labelledby="modalDetallesLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalDetallesLabel">Detalles de la Factura</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detallesFactura">
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
// Función para mostrar detalles de la factura
function verDetalles(id) {
    const modal = new bootstrap.Modal(document.getElementById('modalDetalles'));
    
    // Mostrar el modal
    modal.show();
    
    // Cargar detalles vía AJAX
    fetch(`/api/facturas.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const factura = data.data;
                let estadoClass = '';
                let estadoTexto = '';
                
                switch(factura.estado) {
                    case 'pendiente':
                        estadoClass = 'bg-warning text-dark';
                        estadoTexto = 'Pendiente';
                        break;
                    case 'pagada':
                        estadoClass = 'bg-success';
                        estadoTexto = 'Pagada';
                        break;
                    case 'vencida':
                        estadoClass = 'bg-danger';
                        estadoTexto = 'Vencida';
                        break;
                }
                
                document.getElementById('detallesFactura').innerHTML = `
                    <div class="row mb-3">
                        <div class="col-12 text-center">
                            <span class="badge ${estadoClass} fs-6 p-2">${estadoTexto}</span>
                        </div>
                    </div>
                    <table class="table table-borderless">
                        <tr>
                            <th>ID Factura:</th>
                            <td>${factura.id}</td>
                        </tr>
                        <tr>
                            <th>ID Venta:</th>
                            <td>${factura.venta_id}</td>
                        </tr>
                        <tr>
                            <th>Fecha:</th>
                            <td>${new Date(factura.fecha).toLocaleDateString('es-CL')}</td>
                        </tr>
                        <tr>
                            <th>Producto:</th>
                            <td>${factura.producto}</td>
                        </tr>
                        <tr>
                            <th>Monto:</th>
                            <td>$${parseInt(factura.monto).toLocaleString('es-CL')}</td>
                        </tr>
                        <tr>
                            <th>Proveedor:</th>
                            <td>${factura.proveedor}</td>
                        </tr>
                    </table>
                `;
            } else {
                document.getElementById('detallesFactura').innerHTML = `
                    <div class="alert alert-danger">
                        Error al cargar los detalles de la factura: ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('detallesFactura').innerHTML = `
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
