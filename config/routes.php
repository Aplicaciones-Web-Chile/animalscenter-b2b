<?php
/**
 * Definición de rutas de la aplicación
 * 
 * Este archivo define las rutas disponibles y las asocia a controladores
 * y acciones que las gestionarán.
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

// Obtener router
$router = $this->getRouter();

// Rutas públicas
$router->get('', 'AuthController@showLogin');
$router->get('login', 'AuthController@showLogin');
$router->post('login', 'AuthController@login');
$router->get('logout', 'AuthController@logout');
$router->get('recover-password', 'AuthController@showRecoverPassword');
$router->post('recover-password', 'AuthController@recoverPassword');
$router->get('reset-password/{token}', 'AuthController@showResetPassword');
$router->post('reset-password', 'AuthController@resetPassword');

// Rutas protegidas (requieren autenticación)
// Dashboard
$router->get('dashboard', 'DashboardController@index');

// Facturas
$router->get('facturas', 'FacturasController@index');
$router->get('facturas/{id}', 'FacturasController@show');
$router->get('facturas/descargar/{id}', 'FacturasController@download');
$router->get('facturas/exportar', 'FacturasController@exportForm');
$router->post('facturas/exportar', 'FacturasController@export');

// Productos
$router->get('productos', 'ProductosController@index');
$router->get('productos/{id}', 'ProductosController@show');
$router->get('productos/exportar', 'ProductosController@exportForm');
$router->post('productos/exportar', 'ProductosController@export');

// Ventas
$router->get('ventas', 'VentasController@index');
$router->get('ventas/{id}', 'VentasController@show');
$router->get('ventas/exportar', 'VentasController@exportForm');
$router->post('ventas/exportar', 'VentasController@export');

// Perfil de usuario
$router->get('perfil', 'PerfilController@index');
$router->post('perfil', 'PerfilController@update');
$router->post('perfil/password', 'PerfilController@updatePassword');

// API
$router->get('api/facturas', 'Api\FacturasController@index');
$router->get('api/facturas/{id}', 'Api\FacturasController@show');
$router->get('api/productos', 'Api\ProductosController@index');
$router->get('api/productos/{id}', 'Api\ProductosController@show');
$router->get('api/ventas', 'Api\VentasController@index');
$router->get('api/ventas/{id}', 'Api\VentasController@show');

// Rutas para administrador
$router->get('admin/usuarios', 'Admin\UsuariosController@index');
$router->get('admin/usuarios/crear', 'Admin\UsuariosController@create');
$router->post('admin/usuarios/crear', 'Admin\UsuariosController@store');
$router->get('admin/usuarios/editar/{id}', 'Admin\UsuariosController@edit');
$router->post('admin/usuarios/editar/{id}', 'Admin\UsuariosController@update');
$router->post('admin/usuarios/eliminar/{id}', 'Admin\UsuariosController@delete');

// Ruta de error 404
$router->get('404', 'ErrorController@notFound');

// Ruta por defecto para manejar rutas no encontradas
// Esta debe ser la última ruta definida
$router->get('{any}', 'ErrorController@notFound');
