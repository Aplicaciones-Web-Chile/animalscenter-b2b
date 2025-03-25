<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?>AnimalsCenter B2B</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="/assets/css/style.css" rel="stylesheet">
    
    <?php if (isset($headerScripts)) echo $headerScripts; ?>
</head>
<body>
    <!-- Header -->
    <header class="bg-primary text-white">
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container">
                <a class="navbar-brand" href="/dashboard">
                    <img src="/assets/img/logo.png" alt="AnimalsCenter B2B" height="40">
                </a>
                
                <?php if ($isAuthenticated): ?>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarMain">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="/dashboard">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/facturas">
                                <i class="fas fa-file-invoice"></i> Facturas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/productos">
                                <i class="fas fa-box"></i> Productos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/ventas">
                                <i class="fas fa-chart-line"></i> Ventas
                            </a>
                        </li>
                    </ul>
                    
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle"></i> <?= e($currentUser['nombre'] ?? 'Usuario') ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li>
                                    <a class="dropdown-item" href="/perfil">
                                        <i class="fas fa-id-card"></i> Mi Perfil
                                    </a>
                                </li>
                                <?php if (isset($currentUser['rol']) && $currentUser['rol'] === 'admin'): ?>
                                <li>
                                    <a class="dropdown-item" href="/admin/usuarios">
                                        <i class="fas fa-users-cog"></i> Administrar Usuarios
                                    </a>
                                </li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="/logout">
                                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </nav>
    </header>
    
    <!-- Main Content -->
    <main class="py-4">
        <div class="container">
            <?php if ($session->has('_flash_success')): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= e($session->getFlash('success')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($session->has('_flash_error')): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= e($session->getFlash('error')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?= $content ?>
        </div>
    </main>
    
    <!-- Footer -->
    <footer class="bg-light text-center text-muted py-3 mt-5 border-top">
        <div class="container">
            <p class="mb-0">&copy; <?= date('Y') ?> AnimalsCenter B2B. Todos los derechos reservados.</p>
        </div>
    </footer>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery (for additional functionality) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="/assets/js/app.js"></script>
    
    <?php if (isset($footerScripts)) echo $footerScripts; ?>
</body>
</html>
