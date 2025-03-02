<?php
/**
 * API para la gestión de ventas
 * Permite obtener información de ventas para consumo desde el frontend
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
    
    // Si se proporciona un ID, obtener detalles de una venta específica
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        
        // Verificar que tenga acceso a la venta
        $sql = "SELECT v.*, p.nombre as producto, p.sku, u.nombre as proveedor 
                FROM ventas v 
                JOIN productos p ON v.producto_id = p.id 
                LEFT JOIN usuarios u ON v.proveedor_rut = u.rut 
                WHERE v.id = ?";
        $params = [$id];
        
        // Si es un proveedor, solo puede ver sus propias ventas
        if ($_SESSION['user']['rol'] === 'proveedor') {
            $sql .= " AND v.proveedor_rut = ?";
            $params[] = $_SESSION['user']['rut'];
        }
        
        $venta = fetchOne($sql, $params);
        
        if ($venta) {
            echo json_encode([
                'success' => true,
                'data' => $venta
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Venta no encontrada o no tiene permisos para acceder a esta venta.'
            ]);
        }
    } 
    // Si no se proporciona un ID, listar todas las ventas accesibles
    else {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $fechaInicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] . ' 00:00:00' : date('Y-m-d 00:00:00', strtotime('-30 days'));
        $fechaFin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] . ' 23:59:59' : date('Y-m-d 23:59:59');
        $productoId = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;
        
        // Construir condiciones de filtrado
        $condiciones = ["v.fecha BETWEEN ? AND ?"];
        $params = [$fechaInicio, $fechaFin];
        
        if ($productoId > 0) {
            $condiciones[] = "v.producto_id = ?";
            $params[] = $productoId;
        }
        
        // Si es un proveedor, solo puede ver sus propias ventas
        if ($_SESSION['user']['rol'] === 'proveedor') {
            $condiciones[] = "v.proveedor_rut = ?";
            $params[] = $_SESSION['user']['rut'];
        }
        
        // Crear string de WHERE con condiciones
        $where = "WHERE " . implode(" AND ", $condiciones);
        
        // Contar total de ventas
        $sqlTotal = "SELECT COUNT(*) as total FROM ventas v $where";
        $resultado = fetchOne($sqlTotal, $params);
        $totalVentas = $resultado ? $resultado['total'] : 0;
        
        // Obtener ventas con paginación
        $sql = "SELECT v.*, p.nombre as producto, p.sku, u.nombre as proveedor 
                FROM ventas v 
                JOIN productos p ON v.producto_id = p.id 
                LEFT JOIN usuarios u ON v.proveedor_rut = u.rut 
                $where 
                ORDER BY v.fecha DESC 
                LIMIT $limit OFFSET $offset";
        $ventas = fetchAll($sql, $params);
        
        echo json_encode([
            'success' => true,
            'total' => $totalVentas,
            'data' => $ventas
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
