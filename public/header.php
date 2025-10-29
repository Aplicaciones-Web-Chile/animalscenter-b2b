<?php
/**
 * Plantilla de encabezado para todas las páginas
 */

// Incluir archivos necesarios
require_once __DIR__ . '/../includes/session.php';

// Iniciar sesión si no está iniciada
startSession();

// Si no está definido el título, asignar uno por defecto
$pageTitle = $pageTitle ?? 'Sistema B2B';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo htmlspecialchars($pageTitle); ?> - AnimalsCenter</title>

    <!-- Bootstrap CSS y JS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">

    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="css/style.css">

    <!-- Favicon -->
    <link rel="shortcut icon" href="img/favicon.ico" type="image/x-icon">

    <style>
        .navbar-nav .nav-link {
            padding: 0.5rem 1rem;
            transition: color 0.3s ease;
        }

        .navbar-nav .nav-link:hover {
            color: rgba(255, 255, 255, 0.9);
        }

        .navbar-nav .nav-link i {
            margin-right: 0.5rem;
        }

        @media (min-width: 992px) {
            .navbar-nav .nav-link {
                border-radius: 0.25rem;
            }

            .navbar-nav .nav-link:hover {
                background-color: rgba(255, 255, 255, 0.1);
            }
        }
    </style>
</head>

<body class="bg-light">

    <?php if (isLoggedIn()): ?>
        <!-- Barra de navegación -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                    <img src="img/logo-acenter-fondo.png" alt="Logo" height="50" class="d-inline-block me-2">
                    Sistema B2B
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain"
                    aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarMain">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>"
                                href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'productos.php' ? 'active' : ''; ?>"
                                href="productos.php">
                                <i class="fas fa-box"></i> Productos
                            </a>
                        </li>
                        <!--
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'ventas.php' ? 'active' : ''; ?>" href="ventas.php">
                            <i class="fas fa-shopping-cart"></i> Ventas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'facturas.php' ? 'active' : ''; ?>" href="facturas.php">
                            <i class="fas fa-file-invoice-dollar"></i> Facturas
                        </a>
                    </li>
                    -->
                        <?php if (isAdmin()): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'usuarios.php' ? 'active' : ''; ?>"
                                    href="usuarios.php">
                                    <i class="fas fa-users"></i> Usuarios
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <div class="d-flex align-items-center">
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center" type="button"
                                id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-2"></i>
                                <span><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userMenu">
                                <li><a class="dropdown-item d-flex align-items-center" href="perfil.php">
                                        <i class="fas fa-user me-2"></i> Mi Perfil
                                    </a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item d-flex align-items-center text-danger" href="logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
                                    </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>

    <!-- Mostrar mensajes flash si existen -->
    <div class="container mt-3">
        <?php if (function_exists('displayFlashMessage'))
            displayFlashMessage(); ?>
    </div>