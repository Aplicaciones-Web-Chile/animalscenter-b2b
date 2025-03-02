<?php
/**
 * API para la gestión de productos
 * Permite obtener información de productos para consumo desde el frontend
 */

// Cabeceras para API JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../config/database.php';

// Verificar que el usuario esté autenticado
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado. Debe iniciar sesión.'
    ]);
    exit;
}

// Método GET para obtener información
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // Si se proporciona un ID, obtener detalles de un producto específico
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        
        // Verificar que tenga acceso al producto
        $sql = "SELECT p.*, u.nombre as proveedor FROM productos p 
                LEFT JOIN usuarios u ON p.proveedor_rut = u.rut 
                WHERE p.id = ?";
        $params = [$id];
        
        // Si es un proveedor, solo puede ver sus propios productos
        if ($_SESSION['user']['rol'] === 'proveedor') {
            $sql .= " AND p.proveedor_rut = ?";
            $params[] = $_SESSION['user']['rut'];
        }
        
        $producto = fetchOne($sql, $params);
        
        if ($producto) {
            echo json_encode([
                'success' => true,
                'data' => $producto
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Producto no encontrado o no tiene permisos para acceder a este producto.'
            ]);
        }
    } 
    // Si no se proporciona un ID, listar todos los productos accesibles
    else {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';
        
        // Construir condiciones de filtrado
        $condiciones = [];
        $params = [];
        
        if (!empty($busqueda)) {
            $condiciones[] = "(p.nombre LIKE ? OR p.sku LIKE ?)";
            $params[] = "%$busqueda%";
            $params[] = "%$busqueda%";
        }
        
        // Si es un proveedor, solo puede ver sus propios productos
        if ($_SESSION['user']['rol'] === 'proveedor') {
            $condiciones[] = "p.proveedor_rut = ?";
            $params[] = $_SESSION['user']['rut'];
        }
        
        // Crear string de WHERE con condiciones
        $where = "";
        if (!empty($condiciones)) {
            $where = "WHERE " . implode(" AND ", $condiciones);
        }
        
        // Contar total de productos
        $sqlTotal = "SELECT COUNT(*) as total FROM productos p $where";
        $resultado = fetchOne($sqlTotal, $params);
        $totalProductos = $resultado ? $resultado['total'] : 0;
        
        // Obtener productos con paginación
        $sql = "SELECT p.*, u.nombre as proveedor 
                FROM productos p 
                LEFT JOIN usuarios u ON p.proveedor_rut = u.rut 
                $where 
                ORDER BY p.nombre ASC 
                LIMIT $limit OFFSET $offset";
        $productos = fetchAll($sql, $params);
        
        echo json_encode([
            'success' => true,
            'total' => $totalProductos,
            'data' => $productos
        ]);
    }
} else {
    // Método no permitido
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
}
?>
