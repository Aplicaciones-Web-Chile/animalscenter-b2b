<?php
/**
 * Cliente API para integraciones externas
 * Este archivo contiene funciones para conectarse a APIs externas
 */

/**
 * Obtiene el listado de marcas desde la API
 * 
 * @param string $distribuidor Código del distribuidor
 * @return array Listado de marcas
 */
function getMarcasFromAPI($distribuidor = '001') {
    // URL de la API
    $url = 'https://api2.aplicacionesweb.cl/apiacenter/b2b/get_marcas';
    
    // Datos para enviar
    $data = [
        'Distribuidor' => $distribuidor
    ];
    
    // Configurar la petición cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: 94ec33d0d75949c298f47adaa78928c2',
        'Content-Type: application/json'
    ]);
    
    // Ejecutar la petición
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Verificar si hubo errores
    if ($error) {
        logError("Error al conectar con API de marcas: $error", 'ERROR');
        return [];
    }
    
    // Verificar el código de respuesta HTTP
    if ($httpCode != 200) {
        logError("API de marcas respondió con código $httpCode: $response", 'ERROR');
        return [];
    }
    
    // Decodificar la respuesta JSON
    $responseData = json_decode($response, true);
    
    // Verificar si la respuesta es válida
    if (!$responseData || !isset($responseData['datos']) || $responseData['estado'] != 1) {
        logError("Respuesta de API de marcas inválida: $response", 'ERROR');
        return [];
    }
    
    // Formatear los datos para que sean más fáciles de usar
    $marcas = [];
    foreach ($responseData['datos'] as $marca) {
        $marcas[] = [
            'id' => $marca['KMAR'],
            'nombre' => $marca['DMAR']
        ];
    }
    
    return $marcas;
}

/**
 * Guarda las marcas asociadas a un proveedor
 * 
 * @param int $usuarioId ID del usuario proveedor
 * @param array $marcas Array con IDs de marcas seleccionadas
 * @return bool Resultado de la operación
 */
function guardarMarcasProveedor($usuarioId, $marcas) {
    // Primero eliminar las asociaciones existentes
    $sql = "DELETE FROM proveedores_marcas WHERE proveedor_id = ?";
    executeQuery($sql, [$usuarioId]);
    
    // Si no hay marcas seleccionadas, terminar
    if (empty($marcas)) {
        return true;
    }
    
    // Obtener todas las marcas disponibles para tener los nombres
    $todasLasMarcas = getMarcasFromAPI();
    $marcasMap = [];
    foreach ($todasLasMarcas as $marca) {
        $marcasMap[$marca['id']] = $marca['nombre'];
    }
    
    // Preparar consulta para inserción múltiple
    $placeholders = implode(',', array_fill(0, count($marcas), '(?,?,?)'));
    $sql = "INSERT INTO proveedores_marcas (proveedor_id, marca_id, marca_nombre) VALUES $placeholders";
    
    // Preparar parámetros
    $params = [];
    foreach ($marcas as $marcaId) {
        $params[] = $usuarioId;
        $params[] = $marcaId;
        $params[] = $marcasMap[$marcaId] ?? 'Marca '.$marcaId; // Nombre de la marca o un valor por defecto
    }
    
    // Ejecutar la consulta
    return executeQuery($sql, $params) ? true : false;
}

/**
 * Obtiene las marcas asociadas a un proveedor
 * 
 * @param int $usuarioId ID del usuario proveedor
 * @return array Array con IDs de marcas asociadas
 */
function getMarcasProveedor($usuarioId) {
    $sql = "SELECT marca_id FROM proveedores_marcas WHERE proveedor_id = ?";
    $marcas = fetchAll($sql, [$usuarioId]);
    
    // Extraer solo los IDs de marca
    return array_map(function($item) {
        return $item['marca_id'];
    }, $marcas);
}
