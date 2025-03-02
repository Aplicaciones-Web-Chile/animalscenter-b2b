<?php
/**
 * Endpoints de ventas
 * Gestiona la consulta de ventas por proveedor
 */

// Verificar autenticación
if (!isAuthenticated()) {
    jsonResponse(['error' => 'No autorizado'], 401);
}

$currentUserRut = getCurrentUserRut();
$isAdmin = isAdmin();

// Método GET - Listar ventas
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rutFiltro = $isAdmin && isset($_GET['rut']) ? $_GET['rut'] : $currentUserRut;
    
    // Filtros opcionales
    $fechaInicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : null;
    $fechaFin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : null;
    $productoId = isset($_GET['producto_id']) ? $_GET['producto_id'] : null;
    
    // Construir consulta base
    $sql = "SELECT v.*, p.nombre as producto_nombre, p.sku 
            FROM ventas v 
            JOIN productos p ON v.producto_id = p.id 
            WHERE v.proveedor_rut = ?";
    $params = [$rutFiltro];
    
    // Agregar filtros si están presentes
    if ($fechaInicio) {
        $sql .= " AND v.fecha >= ?";
        $params[] = $fechaInicio . ' 00:00:00';
    }
    
    if ($fechaFin) {
        $sql .= " AND v.fecha <= ?";
        $params[] = $fechaFin . ' 23:59:59';
    }
    
    if ($productoId) {
        $sql .= " AND v.producto_id = ?";
        $params[] = $productoId;
    }
    
    // Ordenar por fecha descendente
    $sql .= " ORDER BY v.fecha DESC";
    
    // Paginación
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $porPagina = isset($_GET['limite']) ? (int)$_GET['limite'] : 20;
    $offset = ($pagina - 1) * $porPagina;
    
    // Contar total de registros para la paginación
    $sqlCount = str_replace("SELECT v.*, p.nombre as producto_nombre, p.sku", "SELECT COUNT(*) as total", $sql);
    $totalRegistros = fetchOne($sqlCount, $params)['total'];
    
    // Agregar límite para paginación
    $sql .= " LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $porPagina;
    
    // Ejecutar consulta
    $ventas = fetchAll($sql, $params);
    
    // Formatear fechas y calcular totales
    foreach ($ventas as &$venta) {
        $venta['fecha_formateada'] = formatDateTime($venta['fecha']);
        $venta['monto_total'] = floatval($venta['cantidad']) * floatval($venta['precio']);
    }
    
    // Preparar respuesta con paginación
    $response = [
        'ventas' => $ventas,
        'paginacion' => [
            'total' => $totalRegistros,
            'pagina_actual' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => ceil($totalRegistros / $porPagina)
        ]
    ];
    
    jsonResponse($response);
}

// Método GET - Detalle de una venta específica
else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $ventaId = (int)$_GET['id'];
    
    // Construir consulta para obtener detalle completo
    $sql = "SELECT v.*, p.nombre as producto_nombre, p.sku, 
                  f.id as factura_id, f.monto as factura_monto, f.estado as factura_estado
           FROM ventas v 
           JOIN productos p ON v.producto_id = p.id
           LEFT JOIN facturas f ON f.venta_id = v.id
           WHERE v.id = ?";
           
    $params = [$ventaId];
    
    // Si no es admin, verificar que la venta pertenezca al proveedor
    if (!$isAdmin) {
        $sql .= " AND v.proveedor_rut = ?";
        $params[] = $currentUserRut;
    }
    
    $venta = fetchOne($sql, $params);
    
    if (!$venta) {
        jsonResponse(['error' => 'Venta no encontrada o sin acceso'], 404);
    }
    
    // Formatear fechas
    $venta['fecha_formateada'] = formatDateTime($venta['fecha']);
    $venta['monto_total'] = floatval($venta['cantidad']) * floatval($venta['precio']);
    
    jsonResponse($venta);
}

// Método no implementado
else {
    jsonResponse(['error' => 'Método no permitido'], 405);
}
