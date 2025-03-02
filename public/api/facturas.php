<?php
/**
 * API para la gestión de facturas
 * Permite obtener información de facturas para consumo desde el frontend
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
    
    // Si se proporciona un ID, obtener detalles de una factura específica
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        
        // Verificar que tenga acceso a la factura
        $sql = "SELECT f.*, v.producto_id, p.nombre as producto, p.sku, u.nombre as proveedor 
                FROM facturas f 
                JOIN ventas v ON f.venta_id = v.id 
                JOIN productos p ON v.producto_id = p.id 
                LEFT JOIN usuarios u ON f.proveedor_rut = u.rut 
                WHERE f.id = ?";
        $params = [$id];
        
        // Si es un proveedor, solo puede ver sus propias facturas
        if ($_SESSION['user']['rol'] === 'proveedor') {
            $sql .= " AND f.proveedor_rut = ?";
            $params[] = $_SESSION['user']['rut'];
        }
        
        $factura = fetchOne($sql, $params);
        
        if ($factura) {
            echo json_encode([
                'success' => true,
                'data' => $factura
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Factura no encontrada o no tiene permisos para acceder a esta factura.'
            ]);
        }
    } 
    // Si no se proporciona un ID, listar todas las facturas accesibles
    else {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $fechaInicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] . ' 00:00:00' : date('Y-m-d 00:00:00', strtotime('-30 days'));
        $fechaFin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] . ' 23:59:59' : date('Y-m-d 23:59:59');
        $estado = isset($_GET['estado']) ? $_GET['estado'] : '';
        
        // Construir condiciones de filtrado
        $condiciones = ["f.fecha BETWEEN ? AND ?"];
        $params = [$fechaInicio, $fechaFin];
        
        if (!empty($estado)) {
            $condiciones[] = "f.estado = ?";
            $params[] = $estado;
        }
        
        // Si es un proveedor, solo puede ver sus propias facturas
        if ($_SESSION['user']['rol'] === 'proveedor') {
            $condiciones[] = "f.proveedor_rut = ?";
            $params[] = $_SESSION['user']['rut'];
        }
        
        // Crear string de WHERE con condiciones
        $where = "WHERE " . implode(" AND ", $condiciones);
        
        // Contar total de facturas
        $sqlTotal = "SELECT COUNT(*) as total FROM facturas f $where";
        $resultado = fetchOne($sqlTotal, $params);
        $totalFacturas = $resultado ? $resultado['total'] : 0;
        
        // Obtener facturas con paginación
        $sql = "SELECT f.*, v.producto_id, p.nombre as producto, p.sku, u.nombre as proveedor 
                FROM facturas f 
                JOIN ventas v ON f.venta_id = v.id 
                JOIN productos p ON v.producto_id = p.id 
                LEFT JOIN usuarios u ON f.proveedor_rut = u.rut 
                $where 
                ORDER BY f.fecha DESC 
                LIMIT $limit OFFSET $offset";
        $facturas = fetchAll($sql, $params);
        
        echo json_encode([
            'success' => true,
            'total' => $totalFacturas,
            'data' => $facturas
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
