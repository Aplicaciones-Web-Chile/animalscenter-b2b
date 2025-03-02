<?php
/**
 * Dashboard principal del sistema B2B
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['rol'])) {
    header('Location: login.php');
    exit;
}

// Título de la página
$pageTitle = 'Dashboard';

// Incluir el encabezado
include 'header.php';

// Variables para almacenar datos
$totalProductos = 0;
$totalVentas = 0;
$totalFacturas = 0;
$facturasPendientes = 0;
$ventasRecientes = [];

// Filtrar por proveedor si es un usuario proveedor
$filtroProveedor = "";
$params = [];

if ($_SESSION['user']['rol'] === 'proveedor') {
    $filtroProveedor = "WHERE proveedor_rut = ?";
    $params[] = $_SESSION['user']['rut'];
}

// Obtener estadísticas
// 1. Total de productos
$sql = "SELECT COUNT(*) as total FROM productos $filtroProveedor";
$result = fetchOne($sql, $params);
$totalProductos = $result ? $result['total'] : 0;

// 2. Total de ventas
$sql = "SELECT COUNT(*) as total FROM ventas $filtroProveedor";
$result = fetchOne($sql, $params);
$totalVentas = $result ? $result['total'] : 0;

// 3. Total de facturas
$sql = "SELECT COUNT(*) as total FROM facturas $filtroProveedor";
$result = fetchOne($sql, $params);
$totalFacturas = $result ? $result['total'] : 0;

// 4. Facturas pendientes
$sql = "SELECT COUNT(*) as total FROM facturas WHERE estado = 'pendiente' $filtroProveedor";
if (!empty($filtroProveedor)) {
    $sql = "SELECT COUNT(*) as total FROM facturas WHERE estado = 'pendiente' AND " . substr($filtroProveedor, 6);
}
$result = fetchOne($sql, $params);
$facturasPendientes = $result ? $result['total'] : 0;

// 5. Ventas recientes (últimas 5)
$sql = "SELECT v.id, p.nombre, v.cantidad, v.fecha 
        FROM ventas v 
        JOIN productos p ON v.producto_id = p.id 
        $filtroProveedor 
        ORDER BY v.fecha DESC LIMIT 5";
$ventasRecientes = fetchAll($sql, $params);

?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
                <a href="exportar.php?tipo=informe" class="btn btn-success">
                    <i class="fas fa-file-excel me-2"></i>Exportar Informe Excel
                </a>
            </div>
        </div>
    </div>
    
    <!-- Tarjetas de estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Productos</h5>
                            <h2 class="mb-0"><?php echo $totalProductos; ?></h2>
                        </div>
                        <i class="fas fa-box fa-2x"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="productos.php" class="text-white">Ver detalles</a>
                    <i class="fas fa-angle-right"></i>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Ventas</h5>
                            <h2 class="mb-0"><?php echo $totalVentas; ?></h2>
                        </div>
                        <i class="fas fa-shopping-cart fa-2x"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="ventas.php" class="text-white">Ver detalles</a>
                    <i class="fas fa-angle-right"></i>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Facturas</h5>
                            <h2 class="mb-0"><?php echo $totalFacturas; ?></h2>
                        </div>
                        <i class="fas fa-file-invoice-dollar fa-2x"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="facturas.php" class="text-white">Ver detalles</a>
                    <i class="fas fa-angle-right"></i>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Pendientes</h5>
                            <h2 class="mb-0"><?php echo $facturasPendientes; ?></h2>
                        </div>
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="facturas.php?estado=pendiente" class="text-white">Ver detalles</a>
                    <i class="fas fa-angle-right"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sección de formulario de exportación rápida -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-file-export me-2"></i>Exportar Informe Personalizado</h5>
                </div>
                <div class="card-body">
                    <form action="exportar.php" method="get" class="row g-3">
                        <input type="hidden" name="tipo" value="informe">
                        
                        <div class="col-md-5">
                            <label for="fecha_inicio" class="form-label">Fecha de Inicio:</label>
                            <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" 
                                   value="<?php echo date('Y-m-01'); ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label for="fecha_fin" class="form-label">Fecha de Fin:</label>
                            <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-file-excel me-2"></i>Generar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Ventas recientes -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Ventas Recientes</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($ventasRecientes): ?>
                                    <?php foreach ($ventasRecientes as $venta): ?>
                                        <tr>
                                            <td><?php echo $venta['id']; ?></td>
                                            <td><?php echo htmlspecialchars($venta['nombre']); ?></td>
                                            <td><?php echo $venta['cantidad']; ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No hay ventas recientes</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="ventas.php" class="btn btn-outline-primary">Ver todas las ventas</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir el pie de página
include 'footer.php';
?>
