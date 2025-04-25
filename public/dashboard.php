<?php
/**
 * Dashboard principal del sistema B2B
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 2.0
 */

// Cargar configuración y funciones necesarias
require_once __DIR__ . '/../config/app.php';
require_once APP_ROOT . '/includes/session.php';

// Iniciar sesión
startSession();

// Verificar autenticación antes de cualquier output
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Título de la página
$pageTitle = 'Dashboard';

// Obtener datos del usuario
$userId = getUserId();
$userRole = getUserRole();
$userName = $_SESSION['nombre'] ?? 'Usuario';

// Incluir el encabezado
include 'header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
                <div class="user-info">
                    <span class="me-2"><i class="fas fa-user"></i> <?php echo htmlspecialchars($userName); ?></span>
                    <span class="badge bg-<?php echo $userRole === 'admin' ? 'danger' : 'primary'; ?>">
                        <?php echo ucfirst($userRole); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Panel de Bienvenida -->
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Bienvenido al Sistema B2B de AnimalsCenter</h5>
                    <p class="card-text">
                        Desde aquí podrás gestionar todas tus operaciones con AnimalsCenter.
                        <?php if ($userRole === 'admin'): ?>
                        Como administrador, tienes acceso a todas las funcionalidades del sistema.
                        <?php else: ?>
                        Como proveedor, puedes gestionar tus productos y órdenes de compra.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Menú Rápido -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-bolt me-2"></i>Acciones Rápidas</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php if ($userRole === 'admin'): ?>
                        <a href="usuarios.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-users me-2"></i>Gestionar Usuarios
                        </a>
                        <a href="productos.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-box me-2"></i>Gestionar Productos
                        </a>
                        <!--<a href="ordenes.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-shopping-cart me-2"></i>Ver Órdenes
                        </a>-->
                        <?php else: ?>
                        <a href="mis-productos.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-box me-2"></i>Mis Productos
                        </a>
                        <!--<a href="mis-ordenes.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-shopping-cart me-2"></i>Mis Órdenes
                        </a>-->
                        <a href="perfil.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-user-cog me-2"></i>Mi Perfil
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>Resumen</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Próximamente se mostrarán estadísticas relevantes según tu rol.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir el pie de página
include 'footer.php';
?>
