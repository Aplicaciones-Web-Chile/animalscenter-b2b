<?php
/**
 * Cierre de sesión
 * Este archivo maneja el proceso de cierre de sesión del usuario
 */

// Incluir archivos necesarios
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

// Utilizar la función de logout que ya existe en session.php
logout();

// Establecer mensaje flash
setFlashMessage('success', 'Has cerrado sesión correctamente.');

// Redirigir al login
redirect('login.php');
