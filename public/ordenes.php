<?php
/**
 * Página de gestión de órdenes
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
$pageTitle = 'Gestión de Órdenes';

// Incluir el encabezado
include 'header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Gestión de Órdenes</h1>
                <?php if (isAdmin()): ?>
                <a href="nueva-orden.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Nueva Orden
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Listado de Órdenes</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        Próximamente: Listado y gestión de órdenes
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
