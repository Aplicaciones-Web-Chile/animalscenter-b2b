<?php
/**
 * Cliente API para integraciones externas
 * Este archivo contiene funciones para conectarse a APIs externas
 */

// Definición de constantes para la API
define('API_BASE_URL', 'https://api2.aplicacionesweb.cl/apiacenter/b2b');
define('API_KEY', '94ec33d0d75949c298f47adaa78928c2');
define('API_DISTRIBUIDOR', '001');

/**
 * Realiza una llamada genérica a la API
 *
 * @param string $endpoint Endpoint de la API (sin la URL base)
 * @param array $data Datos a enviar en la solicitud
 * @return array|string Respuesta de la API
 * @throws Exception Si ocurre un error en la llamada
 */
function callApi($endpoint, $data = [])
{
    $url = API_BASE_URL . '/' . $endpoint;

    // Asegurar que siempre se envíe el distribuidor
    if (!isset($data['Distribuidor'])) {
        $data['Distribuidor'] = API_DISTRIBUIDOR;
    }

    $postData = json_encode($data);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . API_KEY,
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postData)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($error) {
        logError("Error en llamada a API ($endpoint): $error");
        throw new Exception("Error en la llamada a la API: $error");
    }

    if ($httpCode !== 200) {
        logError("API respondió con código $httpCode para $endpoint: $response");
        throw new Exception("La API respondió con código $httpCode");
    }

    // Intentar decodificar como JSON
    $decodedResponse = json_decode($response, true);

    // Si es un JSON válido y tiene la estructura esperada, devolver los datos
    if (json_last_error() === JSON_ERROR_NONE) {
        return $decodedResponse;
    }

    // Si no es JSON, devolver la respuesta como string
    return $response;
}

/**
 * Obtiene todas las marcas disponibles desde la API
 *
 * @param string $distribuidor Código del distribuidor (opcional)
 * @return array Lista de marcas
 */
function getMarcasFromAPI($distribuidor = null)
{
    try {
        // Si se proporciona un distribuidor, usarlo; de lo contrario, usar el predeterminado
        $data = [];
        if ($distribuidor !== null) {
            $data['Distribuidor'] = $distribuidor;
        }

        $response = callApi('get_marcas', $data);

        if (isset($response['estado']) && $response['estado'] == 1 && isset($response['datos'])) {
            // Transformar el formato para que sea más fácil de usar en la aplicación
            $marcas = [];
            foreach ($response['datos'] as $marca) {
                $marcas[] = [
                    'id' => $marca['KMAR'],
                    'nombre' => $marca['DMAR']
                ];
            }
            return $marcas;
        }
    } catch (Exception $e) {
        logError('Error al obtener marcas desde API: ' . $e->getMessage());
    }

    return [];
}

/**
 * Obtiene todos los proveedores desde la API
 *
 * @return array Lista de proveedores
 */
function getProveedoresFromAPI()
{
    try {
        $response = callApi('get_proveedores');

        if (isset($response['estado']) && $response['estado'] == 1 && isset($response['datos'])) {
            return $response['datos'];
        }
    } catch (Exception $e) {
        logError('Error al obtener proveedores desde API: ' . $e->getMessage());
    }

    return [];
}

/**
 * Obtiene las marcas asociadas a un proveedor específico desde la API
 *
 * @param string $rut RUT del proveedor
 * @return array Lista de marcas asociadas al proveedor
 */
function getMarcasProveedorFromAPI($rut)
{
    try {
        $rutLimpio = limpiarRut($rut);

        $response = callApi('get_prvmarcas', [
            'KPRV' => $rutLimpio
        ]);

        if (isset($response['estado']) && $response['estado'] == 1 && isset($response['datos'])) {
            // Transformar el formato para que sea más fácil de usar en la aplicación
            $marcas = [];
            foreach ($response['datos'] as $item) {
                $marcas[] = [
                    'id' => $item['KMAR'],
                    'nombre' => $item['DMAR']
                ];
            }
            return $marcas;
        }
    } catch (Exception $e) {
        logError('Error al obtener marcas del proveedor ' . $rut . ' desde API: ' . $e->getMessage());
    }

    return [];
}

/**
 * Obtiene los IDs de las marcas asociadas a un proveedor desde la API
 *
 * @param string $rut RUT del proveedor
 * @return array Lista de IDs de marcas
 */
function getMarcasIdsProveedorFromAPI($rut)
{
    $marcas = getMarcasProveedorFromAPI($rut);
    return array_column($marcas, 'id');
}

/**
 * Asigna una marca a un proveedor en la API
 *
 * @param string $rut RUT del proveedor
 * @param string $marcaId ID de la marca a asignar
 * @param string|null $marcaIdAnterior ID de la marca anterior (para actualización)
 * @return bool True si la operación fue exitosa
 */
function asignarMarcaProveedorAPI($rut, $marcaId, $marcaIdAnterior = null)
{
    try {
        $rutLimpio = limpiarRut($rut);

        $data = [
            'KPRV' => $rutLimpio,
            'KMAR_NEW' => $marcaId
        ];

        if ($marcaIdAnterior !== null) {
            $data['KMAR_OLD'] = $marcaIdAnterior;
        }

        $response = callApi('set_prvmarca', $data);

        return $response === "OK";
    } catch (Exception $e) {
        logError('Error al asignar marca a proveedor en API: ' . $e->getMessage());
        return false;
    }
}

/**
 * Elimina la asociación de una marca con un proveedor en la API
 *
 * @param string $rut RUT del proveedor
 * @param string $marcaId ID de la marca a eliminar
 * @return bool True si la operación fue exitosa
 */
function eliminarMarcaProveedorAPI($rut, $marcaId)
{
    try {
        $rutLimpio = limpiarRut($rut);

        $response = callApi('del_prvmarca', [
            'KPRV' => $rutLimpio,
            'KMAR' => $marcaId
        ]);

        return $response === "OK";
    } catch (Exception $e) {
        logError('Error al eliminar marca de proveedor en API: ' . $e->getMessage());
        return false;
    }
}

/**
 * Sincroniza las marcas de un proveedor con la API
 *
 * @param string $rut RUT del proveedor
 * @param array $nuevasMarcasIds IDs de las marcas que debe tener el proveedor
 * @return bool True si la sincronización fue exitosa
 */
function sincronizarMarcasProveedorAPI($rut, $nuevasMarcasIds)
{
    try {
        // Obtener marcas actuales del proveedor en la API
        $marcasActualesIds = getMarcasIdsProveedorFromAPI($rut);

        // Determinar marcas a agregar y eliminar
        $marcasAgregar = array_diff($nuevasMarcasIds, $marcasActualesIds);
        $marcasEliminar = array_diff($marcasActualesIds, $nuevasMarcasIds);

        // Eliminar marcas que ya no están seleccionadas
        foreach ($marcasEliminar as $marcaId) {
            eliminarMarcaProveedorAPI($rut, $marcaId);
        }

        // Agregar nuevas marcas seleccionadas
        foreach ($marcasAgregar as $marcaId) {
            asignarMarcaProveedorAPI($rut, $marcaId);
        }

        return true;
    } catch (Exception $e) {
        logError("Error al sincronizar marcas del proveedor $rut con API: " . $e->getMessage());
        return false;
    }
}

/**
 * Guarda las marcas asociadas a un proveedor, tanto en BD local como en API
 *
 * @param int $proveedorId ID del proveedor en la base de datos local
 * @param array $marcasIds IDs de las marcas a asociar
 * @return bool True si la operación fue exitosa
 */
function guardarMarcasProveedor($proveedorId, $marcasIds)
{
    // Obtener el RUT del proveedor
    $proveedor = fetchOne("SELECT rut FROM usuarios WHERE id = ?", [$proveedorId]);
    if (!$proveedor) {
        throw new Exception("Proveedor no encontrado");
    }
    $rutProveedor = $proveedor['rut'];

    // 1. Obtener marcas actuales del proveedor en la BD local
    $marcasActuales = fetchAll("SELECT marca_id FROM proveedores_marcas WHERE proveedor_id = ?", [$proveedorId]);
    $marcasActualesIds = array_column($marcasActuales, 'marca_id');

    // 2. Determinar marcas a agregar y eliminar
    $marcasAgregar = array_diff($marcasIds, $marcasActualesIds);
    $marcasEliminar = array_diff($marcasActualesIds, $marcasIds);

    // Iniciar transacción en BD local
    $db = getDbConnection();
    $db->beginTransaction();

    try {
        // 3. Eliminar marcas que ya no están seleccionadas
        if (!empty($marcasEliminar)) {
            // Eliminar de la BD local
            $placeholders = implode(',', array_fill(0, count($marcasEliminar), '?'));
            executeQuery(
                "DELETE FROM proveedores_marcas WHERE proveedor_id = ? AND marca_id IN ($placeholders)",
                array_merge([$proveedorId], $marcasEliminar)
            );
        }

        // 4. Agregar nuevas marcas seleccionadas
        if (!empty($marcasAgregar)) {
            // Obtener detalles de las marcas a agregar
            $todasLasMarcas = getMarcasFromAPI();
            $marcasInfo = [];
            foreach ($todasLasMarcas as $marca) {
                if (in_array($marca['id'], $marcasAgregar)) {
                    $marcasInfo[$marca['id']] = $marca['nombre'];
                }
            }

            // Agregar a la BD local
            $stmt = $db->prepare("INSERT INTO proveedores_marcas (proveedor_id, marca_id, marca_nombre) VALUES (?, ?, ?)");
            foreach ($marcasInfo as $marcaId => $marcaNombre) {
                $stmt->execute([$proveedorId, $marcaId, $marcaNombre]);
            }
        }

        // 5. Sincronizar con la API
        sincronizarMarcasProveedorAPI($rutProveedor, $marcasIds);

        // Confirmar transacción
        $db->commit();
        return true;

    } catch (Exception $e) {
        // Revertir cambios en caso de error
        $db->rollBack();
        logError("Error al guardar marcas de proveedor: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Obtiene las marcas asociadas a un proveedor desde la base de datos local
 *
 * @param int $proveedorId ID del proveedor
 * @return array IDs de las marcas asociadas
 */
function getMarcasProveedor($proveedorId)
{
    $marcas = fetchAll("SELECT marca_id FROM proveedores_marcas WHERE proveedor_id = ?", [$proveedorId]);
    return array_column($marcas, 'marca_id');
}

function obtenerProductosAPI($distribuidor, $fechaInicio, $fechaFin, $proveedor)
{
    // URL y configuración de la API
    $url = "https://api2.aplicacionesweb.cl/apiacenter/productos/vtayrepxsuc";
    $token = "94ec33d0d75949c298f47adaa78928c2";

    // Datos a enviar
    $data = [
        "Distribuidor" => $distribuidor,
        "FINI" => $fechaInicio,
        "FTER" => $fechaFin,
        "KPRV" => $proveedor
    ];

    // Configuración de la petición
    $options = [
        'http' => [
            'header' => "Authorization: $token\r\n" .
                "Content-Type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($data),
            'timeout' => 60
        ]
    ];

    // Crear contexto y realizar petición
    $context = stream_context_create($options);

    try {
        // Registrar la llamada a la API en el log
        error_log("Llamando a API: $url con datos: " . json_encode($data));

        // Realizar la petición
        $result = file_get_contents($url, false, $context);

        if ($result === false) {
            error_log("Error al obtener datos de la API: No se pudo conectar");
            return ['estado' => 0, 'datos' => [], 'error' => 'No se pudo conectar con la API'];
        }

        // Decodificar respuesta
        $response = json_decode($result, true);

        // Verificar estructura de respuesta
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Error al decodificar respuesta JSON: " . json_last_error_msg());
            return ['estado' => 0, 'datos' => [], 'error' => 'Error al procesar la respuesta'];
        }

        return $response;
    } catch (Exception $e) {
        error_log("Excepción al llamar a la API: " . $e->getMessage());
        return ['estado' => 0, 'datos' => [], 'error' => $e->getMessage()];
    }
}
