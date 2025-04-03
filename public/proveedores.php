<?php
/**
 * Página de gestión de proveedores
 * Solo accesible para administradores
 */

// Incluir archivos necesarios
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

// Iniciar sesión
startSession();

// Verificar que sea administrador
requireAdmin();

// Título de la página
$pageTitle = 'Gestión de Proveedores';

// Incluir el encabezado
include 'header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mb-0"><i class="fas fa-users me-2"></i>Gestión de Proveedores</h1>
                <a href="nuevo-proveedor.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Nuevo Proveedor
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Listado de Proveedores</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        Próximamente: Listado y gestión de proveedores
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
