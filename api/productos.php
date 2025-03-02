<?php
/**
 * Endpoints de productos
 */

$currentUserRut = getCurrentUserRut();

// Obtener productos del usuario
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $productos = fetchAll("SELECT * FROM productos WHERE proveedor_rut = ?", [$currentUserRut]);
    jsonResponse($productos);
}

// Resto de métodos no implementados según especificación
ejsonResponse(['error' => 'Método no implementado'], 501);
