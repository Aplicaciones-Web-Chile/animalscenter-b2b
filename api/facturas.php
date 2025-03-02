<?php
/**
 * Endpoints de facturas
 * Gestiona la consulta de facturas por proveedor
 */

// Verificar autenticación
if (!isAuthenticated()) {
    jsonResponse(['error' => 'No autorizado'], 401);
}

$currentUserRut = getCurrentUserRut();
$isAdmin = isAdmin();

// Método GET - Listar facturas
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['id'])) {
    $rutFiltro = $isAdmin && isset($_GET['rut']) ? $_GET['rut'] : $currentUserRut;
    
    // Filtros opcionales
    $fechaInicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : null;
    $fechaFin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : null;
    $estado = isset($_GET['estado']) ? $_GET['estado'] : null;
    
    // Construir consulta base
    $sql = "SELECT f.*, v.fecha as venta_fecha, v.producto_id, p.nombre as producto_nombre, p.sku 
            FROM facturas f 
            JOIN ventas v ON f.venta_id = v.id
            JOIN productos p ON v.producto_id = p.id
            WHERE f.proveedor_rut = ?";
    $params = [$rutFiltro];
    
    // Agregar filtros si están presentes
    if ($fechaInicio) {
        $sql .= " AND f.fecha >= ?";
        $params[] = $fechaInicio . ' 00:00:00';
    }
    
    if ($fechaFin) {
        $sql .= " AND f.fecha <= ?";
        $params[] = $fechaFin . ' 23:59:59';
    }
    
    if ($estado && in_array($estado, ['pendiente', 'pagada', 'vencida'])) {
        $sql .= " AND f.estado = ?";
        $params[] = $estado;
    }
    
    // Ordenar por fecha descendente
    $sql .= " ORDER BY f.fecha DESC";
    
    // Paginación
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $porPagina = isset($_GET['limite']) ? (int)$_GET['limite'] : 20;
    $offset = ($pagina - 1) * $porPagina;
    
    // Contar total de registros para la paginación
    $sqlCount = str_replace("SELECT f.*, v.fecha as venta_fecha", "SELECT COUNT(*) as total", $sql);
    $totalRegistros = fetchOne($sqlCount, $params)['total'];
    
    // Agregar límite para paginación
    $sql .= " LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $porPagina;
    
    // Ejecutar consulta
    $facturas = fetchAll($sql, $params);
    
    // Formatear fechas y añadir información adicional
    foreach ($facturas as &$factura) {
        $factura['fecha_formateada'] = formatDateTime($factura['fecha']);
        $factura['venta_fecha_formateada'] = formatDateTime($factura['venta_fecha']);
        $factura['monto_formateado'] = formatCurrency($factura['monto']);
        
        // Calcular días de vencimiento para facturas pendientes
        if ($factura['estado'] === 'pendiente') {
            $fechaFactura = new DateTime($factura['fecha']);
            $hoy = new DateTime();
            $diasPendientes = $fechaFactura->diff($hoy)->days;
            $factura['dias_pendientes'] = $diasPendientes;
        }
    }
    
    // Preparar respuesta con paginación
    $response = [
        'facturas' => $facturas,
        'paginacion' => [
            'total' => $totalRegistros,
            'pagina_actual' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => ceil($totalRegistros / $porPagina)
        ]
    ];
    
    jsonResponse($response);
}

// Método GET - Detalle de una factura específica
else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $facturaId = (int)$_GET['id'];
    
    // Construir consulta para obtener detalle completo
    $sql = "SELECT f.*, v.fecha as venta_fecha, v.cantidad, v.producto_id, 
                  p.nombre as producto_nombre, p.sku, p.precio
           FROM facturas f 
           JOIN ventas v ON f.venta_id = v.id
           JOIN productos p ON v.producto_id = p.id
           WHERE f.id = ?";
           
    $params = [$facturaId];
    
    // Si no es admin, verificar que la factura pertenezca al proveedor
    if (!$isAdmin) {
        $sql .= " AND f.proveedor_rut = ?";
        $params[] = $currentUserRut;
    }
    
    $factura = fetchOne($sql, $params);
    
    if (!$factura) {
        jsonResponse(['error' => 'Factura no encontrada o sin acceso'], 404);
    }
    
    // Formatear fechas y montos
    $factura['fecha_formateada'] = formatDateTime($factura['fecha']);
    $factura['venta_fecha_formateada'] = formatDateTime($factura['venta_fecha']);
    $factura['monto_formateado'] = formatCurrency($factura['monto']);
    
    // Si la factura está pendiente, calcular días pendientes
    if ($factura['estado'] === 'pendiente') {
        $fechaFactura = new DateTime($factura['fecha']);
        $hoy = new DateTime();
        $diasPendientes = $fechaFactura->diff($hoy)->days;
        $factura['dias_pendientes'] = $diasPendientes;
    }
    
    // Calcular subtotal, IVA y total
    $subtotal = $factura['monto'] / 1.19; // Suponiendo IVA de 19% en Chile
    $iva = $factura['monto'] - $subtotal;
    
    $factura['desglose'] = [
        'subtotal' => round($subtotal),
        'subtotal_formateado' => formatCurrency(round($subtotal)),
        'iva' => round($iva),
        'iva_formateado' => formatCurrency(round($iva)),
        'total' => $factura['monto'],
        'total_formateado' => $factura['monto_formateado']
    ];
    
    jsonResponse($factura);
}

// Método no implementado
else {
    jsonResponse(['error' => 'Método no permitido'], 405);
}
