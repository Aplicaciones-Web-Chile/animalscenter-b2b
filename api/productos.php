<?php
/**
 * Endpoints de productos
 * Permite obtener listados y detalles de productos para el usuario autenticado
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

// Verificar si el usuario está autenticado
if (!isLoggedIn()) {
    jsonResponse(['error' => 'No autorizado. Debe iniciar sesión.'], 401);
    exit;
}

$currentUserRut = getCurrentUserRut();
$currentUserRole = $_SESSION['user']['rol'] ?? '';

// Obtener todos los productos o productos por ID específico
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Si se proporciona un ID de producto específico en la URL
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $id = (int)$_GET['id'];
        
        // Consulta SQL según el rol del usuario
        if ($currentUserRole === 'admin') {
            $producto = fetchOne("SELECT p.*, u.nombre as proveedor_nombre 
                                FROM productos p 
                                LEFT JOIN usuarios u ON p.proveedor_rut = u.rut 
                                WHERE p.id = ?", 
                                [$id]);
        } else {
            $producto = fetchOne("SELECT p.*, u.nombre as proveedor_nombre 
                                FROM productos p 
                                LEFT JOIN usuarios u ON p.proveedor_rut = u.rut 
                                WHERE p.id = ? AND p.proveedor_rut = ?", 
                                [$id, $currentUserRut]);
        }
        
        if (!$producto) {
            jsonResponse(['error' => 'Producto no encontrado'], 404);
        } else {
            jsonResponse($producto);
        }
    } 
    // Obtener stock de todos los productos
    elseif (isset($_GET['stock']) && $_GET['stock'] === 'true') {
        // Para administradores, mostrar stock de todos los productos
        if ($currentUserRole === 'admin') {
            $productos = fetchAll("SELECT p.id, p.nombre, p.sku, p.stock, p.precio, 
                                u.nombre as proveedor_nombre, p.proveedor_rut
                                FROM productos p
                                LEFT JOIN usuarios u ON p.proveedor_rut = u.rut
                                ORDER BY p.nombre");
        } 
        // Para proveedores, mostrar solo sus productos
        else {
            $productos = fetchAll("SELECT p.id, p.nombre, p.sku, p.stock, p.precio
                                FROM productos p
                                WHERE p.proveedor_rut = ?
                                ORDER BY p.nombre", 
                                [$currentUserRut]);
        }
        
        jsonResponse($productos);
    }
    // Obtener todos los productos con posibles filtros
    else {
        $params = [];
        $condiciones = [];
        $orderBy = "ORDER BY p.nombre ASC";
        
        // Procesar parámetros de filtro
        if (isset($_GET['busqueda']) && !empty($_GET['busqueda'])) {
            $busqueda = '%' . $_GET['busqueda'] . '%';
            $condiciones[] = "(p.nombre LIKE ? OR p.sku LIKE ?)";
            $params[] = $busqueda;
            $params[] = $busqueda;
        }
        
        // Filtro por proveedor para administradores
        if ($currentUserRole === 'admin' && isset($_GET['proveedor']) && !empty($_GET['proveedor'])) {
            $condiciones[] = "p.proveedor_rut = ?";
            $params[] = $_GET['proveedor'];
        } else if ($currentUserRole !== 'admin') {
            // Si es proveedor, solo puede ver sus productos
            $condiciones[] = "p.proveedor_rut = ?";
            $params[] = $currentUserRut;
        }
        
        // Construir la cláusula WHERE
        $whereClause = !empty($condiciones) ? "WHERE " . implode(" AND ", $condiciones) : "";
        
        // Ordenamiento
        if (isset($_GET['orden']) && !empty($_GET['orden'])) {
            $camposValidos = ['nombre', 'sku', 'stock', 'precio'];
            $orden = in_array($_GET['orden'], $camposValidos) ? $_GET['orden'] : 'nombre';
            $direccion = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'desc') ? 'DESC' : 'ASC';
            $orderBy = "ORDER BY p.$orden $direccion";
        }
        
        // Obtener productos según los filtros
        $sql = "SELECT p.*, u.nombre as proveedor_nombre 
                FROM productos p 
                LEFT JOIN usuarios u ON p.proveedor_rut = u.rut 
                $whereClause 
                $orderBy";
        
        $productos = fetchAll($sql, $params);
        jsonResponse($productos);
    }
}
// Implementación para métodos no permitidos según especificación
else {
    jsonResponse(['error' => 'Método no permitido. Solo se admiten consultas GET para productos.'], 405);
}
