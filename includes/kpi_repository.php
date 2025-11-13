<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/api_client.php';

/**
 * KPI Venta Neta para múltiples proveedores (KPRV_LIST).
 * - Cachea por combinación exacta (fini, fter, set de KPRV canonicalizado).
 * - TTL cuando el rango incluye hoy; permanente cuando es rango cerrado.
 */
function getMontoVentaNetoMulti(
  string $fechaInicioDMY,
  string $fechaFinDMY,
  array $kprvList,
  bool $forceRefresh = false,
  int $ttlSegundosParaHoy = 900 // 15 min
): int {
  $pdo = getDbConnection();
  $finiY = fechaDMYtoYMD($fechaInicioDMY);
  $fterY = fechaDMYtoYMD($fechaFinDMY);

  // Canonicaliza el conjunto
  [$normList, $jsonList, $listHash] = canonicalizarKprvList($kprvList);
  if (empty($normList)) {
    return 0; // sin proveedores, no hay KPI que calcular
  }

  // 1) Intentar leer caché
  if (!$forceRefresh) {
    $sel = $pdo->prepare(
      "SELECT valor_neto, expires_at
         FROM api_cache_kpi_venta_neta_set
        WHERE fini = :fi AND fter = :ff AND kprv_set_hash = :h
        LIMIT 1"
    );
    $sel->execute([':fi' => $finiY, ':ff' => $fterY, ':h' => $listHash]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      if (empty($row['expires_at']) || (new DateTime() < new DateTime($row['expires_at']))) {
        return (int) $row['valor_neto'];
      }
      // si expiró, seguimos a refrescar
    }
  }

  // 2) Llamar a API con KPRV_LIST
  $valorNeto = 0;
  try {
    $resp = callApi('kpi_venta_neta', [
      'FINI' => $fechaInicioDMY,
      'FTER' => $fechaFinDMY,
      'KPRV_LIST' => $normList,
    ]);

    if (isset($resp['estado']) && (int) $resp['estado'] === 1 && isset($resp['datos'][0]['NETO'])) {
      $valorNeto = (int) $resp['datos'][0]['NETO'];
    } else {
      // si la API no trae valor válido, no sobreescribir cache previo válido
      if (isset($row)) {
        return (int) $row['valor_neto'];
      }
      return 0;
    }
  } catch (Throwable $e) {
    error_log("[getMontoVentaNetoMulti][API error] " . $e->getMessage());
    if (isset($row))
      return (int) $row['valor_neto'];
    return 0;
  }

  // 3) Upsert caché
  $sourceHash = hash('sha256', $finiY . '|' . $fterY . '|' . $listHash);
  $expiresAt = null;
  if (isTodayOrAfter($fterY)) {
    $expiresAt = (new DateTime("+{$ttlSegundosParaHoy} seconds"))->format('Y-m-d H:i:s');
  }

  $ins = $pdo->prepare(
    "INSERT INTO api_cache_kpi_venta_neta_set
       (fini, fter, kprv_list_json, kprv_set_hash, valor_neto, source_hash, last_synced_at, expires_at)
     VALUES
       (:fi, :ff, :json, :h, :v, :sh, NOW(), :exp)
     ON DUPLICATE KEY UPDATE
       kprv_list_json = VALUES(kprv_list_json),
       valor_neto     = VALUES(valor_neto),
       source_hash    = VALUES(source_hash),
       last_synced_at = VALUES(last_synced_at),
       expires_at     = VALUES(expires_at)"
  );

  $ins->execute([
    ':fi' => $finiY,
    ':ff' => $fterY,
    ':json' => $jsonList,
    ':h' => $listHash,
    ':v' => $valorNeto,
    ':sh' => $sourceHash,
    ':exp' => $expiresAt
  ]);

  return $valorNeto;
}

/**
 * KPI: Unidades vendidas (multi-proveedor).
 * - Endpoint: kpi_unidades_vendidas
 * - Body: FINI, FTER, KPRV_LIST: [ ... ]
 * - Cachea por (fini, fter, set de KPRV canonicalizado).
 */
function getCantidadVendidaMulti(
  string $fechaInicioDMY,
  string $fechaFinDMY,
  array $kprvList,
  bool $forceRefresh = false,
  int $ttlSegundosParaHoy = 900 // 15 min
): float {
  $pdo = getDbConnection();
  $finiY = fechaDMYtoYMD($fechaInicioDMY);
  $fterY = fechaDMYtoYMD($fechaFinDMY);

  // Canonicaliza lista de KPRV
  [$normList, $jsonList, $listHash] = canonicalizarKprvList($kprvList);
  if (empty($normList))
    return 0.0;

  // 1) Intentar leer desde caché
  if (!$forceRefresh) {
    $sel = $pdo->prepare(
      "SELECT cantidad, expires_at
         FROM api_cache_kpi_unidades_vendidas_set
        WHERE fini = :fi AND fter = :ff AND kprv_set_hash = :h
        LIMIT 1"
    );
    $sel->execute([':fi' => $finiY, ':ff' => $fterY, ':h' => $listHash]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      if (empty($row['expires_at']) || (new DateTime() < new DateTime($row['expires_at']))) {
        return (float) $row['cantidad']; // ⬅️ ya no (int)
      }
      // si expiró → refrescamos abajo
    }
  }

  // 2) Llamar API
  try {
    $resp = callApi('kpi_unidades_vendidas', [
      'FINI' => $fechaInicioDMY,
      'FTER' => $fechaFinDMY,
      'KPRV_LIST' => $normList
    ]);

    if (!isset($resp['estado']) || (int) $resp['estado'] !== 1 || !isset($resp['datos'][0]['CANT'])) {
      // API sin dato válido → conservar caché previo si existe
      if (isset($row))
        return (float) $row['cantidad'];
      return 0.0;
    }
  } catch (Throwable $e) {
    error_log("[getCantidadVendidaMulti][API error] " . $e->getMessage());
    if (isset($row))
      return (float) $row['cantidad'];
    return 0.0;
  }

  // Normaliza “0,57” → "0.570" (string para DECIMAL)
  $raw = $resp['datos'][0]['CANT'] ?? null;
  $cantidadS = normalizarNumeroLatam($raw, 3);   // string "0.570"
  $cantidadF = (float) $cantidadS;                // para devolver al caller

  // 3) Upsert caché
  $sourceHash = hash('sha256', $finiY . '|' . $fterY . '|' . $listHash);
  $expiresAt = null;
  if (isTodayOrAfter($fterY)) {
    $expiresAt = (new DateTime("+{$ttlSegundosParaHoy} seconds"))->format('Y-m-d H:i:s');
  }

  $ins = $pdo->prepare(
    "INSERT INTO api_cache_kpi_unidades_vendidas_set
       (fini, fter, kprv_list_json, kprv_set_hash, cantidad, source_hash, last_synced_at, expires_at)
     VALUES
       (:fi, :ff, :json, :h, :c, :sh, NOW(), :exp)
     ON DUPLICATE KEY UPDATE
       kprv_list_json = VALUES(kprv_list_json),
       cantidad       = VALUES(cantidad),
       source_hash    = VALUES(source_hash),
       last_synced_at = VALUES(last_synced_at),
       expires_at     = VALUES(expires_at)"
  );

  $ins->execute([
    ':fi' => $finiY,
    ':ff' => $fterY,
    ':json' => $jsonList,
    ':h' => $listHash,
    ':c' => $cantidadS,
    ':sh' => $sourceHash,
    ':exp' => $expiresAt
  ]);

  return $cantidadF;
}

/**
 * KPI: Cantidad de SKUs activos (multi-proveedor).
 * - No tiene fechas → se cachea con TTL global (default 15 min).
 * - Endpoint: kpi_sku_activos
 * - Body: { "KPRV_LIST": ["...","..."] }
 *
 * @return float  (devuelvo float por consistencia con posibles valores con decimales que envíe la API)
 */
function getCantidadSkuActivosMulti(
  array $kprvList,
  bool $forceRefresh = false,
  int $ttlSegundos = 900 // 15 minutos
): float {
  $pdo = getDbConnection();

  // Canonicaliza el conjunto
  [$normList, $jsonList, $listHash] = canonicalizarKprvList($kprvList);
  if (empty($normList))
    return 0.0;

  // 1) Intentar leer desde cache
  if (!$forceRefresh) {
    $sel = $pdo->prepare(
      "SELECT cantidad, expires_at
         FROM api_cache_kpi_sku_activos_set
        WHERE kprv_set_hash = :h
        LIMIT 1"
    );
    $sel->execute([':h' => $listHash]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      if (empty($row['expires_at']) || (new DateTime() < new DateTime($row['expires_at']))) {
        return (float) $row['cantidad'];
      }
      // expirado → refrescamos abajo
    }
  }

  // 2) Llamar API
  try {
    $resp = callApi('kpi_sku_activos', ['KPRV_LIST' => $normList]);

    if (!isset($resp['estado']) || (int) $resp['estado'] !== 1 || !isset($resp['datos'][0]['CANT'])) {
      // API sin dato válido → conservar cache previo, si existe
      if (isset($row))
        return (float) $row['cantidad'];
      return 0.0;
    }
  } catch (Throwable $e) {
    error_log("[getCantidadSkuActivosMulti][API error] " . $e->getMessage());
    if (isset($row))
      return (float) $row['cantidad'];
    return 0.0;
  }

  // Normaliza el valor (por si viene "0,57")
  $raw = $resp['datos'][0]['CANT'] ?? null;
  $cantidadS = normalizarNumeroLatam($raw, 3);  // string "123.000" o "0.570"
  $cantidadF = (float) $cantidadS;

  // 3) Upsert cache con TTL
  $sourceHash = $listHash; // para trazabilidad simple
  $expiresAt = (new DateTime("+{$ttlSegundos} seconds"))->format('Y-m-d H:i:s');

  $ins = $pdo->prepare(
    "INSERT INTO api_cache_kpi_sku_activos_set
       (kprv_list_json, kprv_set_hash, cantidad, source_hash, last_synced_at, expires_at)
     VALUES
       (:json, :h, :c, :sh, NOW(), :exp)
     ON DUPLICATE KEY UPDATE
       kprv_list_json = VALUES(kprv_list_json),
       cantidad       = VALUES(cantidad),
       source_hash    = VALUES(source_hash),
       last_synced_at = VALUES(last_synced_at),
       expires_at     = VALUES(expires_at)"
  );

  $ins->execute([
    ':json' => $jsonList,
    ':h' => $listHash,
    ':c' => $cantidadS,   // string normalizado → DECIMAL
    ':sh' => $sourceHash,
    ':exp' => $expiresAt
  ]);

  return $cantidadF;
}

/**
 * KPI: Detalle de venta neta (multi-proveedor, con fechas).
 * - Endpoint: kpi_venta_neta_detalle
 * - Body: { FINI: d/m/Y, FTER: d/m/Y, KPRV_LIST: [...] }
 * - Cachea el payload completo por (fini, fter, set de KPRV canonicalizado).
 * - TTL sólo si FTER >= hoy (datos “abiertos”). Para rangos cerrados, sin TTL (persistente).
 *
 * @return array Arreglo de ítems de detalle (misma forma que API).
 */
function getDetalleVentaNetaMulti(
  string $fechaInicioDMY,
  string $fechaFinDMY,
  array $kprvList,
  bool $forceRefresh = false,
  int $ttlSegundosParaHoy = 900  // 15 min
): array {
  $pdo = getDbConnection();
  $finiY = fechaDMYtoYMD($fechaInicioDMY);
  $fterY = fechaDMYtoYMD($fechaFinDMY);

  // Canonicalizar set de proveedores
  [$normList, $jsonList, $listHash] = canonicalizarKprvList($kprvList);
  if (empty($normList))
    return [];

  // 1) Cache read
  if (!$forceRefresh) {
    $sel = $pdo->prepare(
      "SELECT payload_json, rows_count, expires_at
         FROM api_cache_kpi_venta_neta_det_set
        WHERE fini = :fi AND fter = :ff AND kprv_set_hash = :h
        LIMIT 1"
    );
    $sel->execute([':fi' => $finiY, ':ff' => $fterY, ':h' => $listHash]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      if (empty($row['expires_at']) || (new DateTime() < new DateTime($row['expires_at']))) {
        // Decodifica y retorna
        $payload = is_string($row['payload_json']) ? json_decode($row['payload_json'], true)
          : $row['payload_json']; // si es tipo JSON nativo
        return is_array($payload) ? $payload : [];
      }
      // expirado → refrescamos abajo
    }
  }

  // 2) API call
  try {
    $resp = callApi('kpi_venta_neta_detalle', [
      'FINI' => $fechaInicioDMY,
      'FTER' => $fechaFinDMY,
      'KPRV_LIST' => $normList,
    ]);

    if (!isset($resp['estado']) || (int) $resp['estado'] !== 1 || !isset($resp['datos']) || !is_array($resp['datos'])) {
      // si la API falla o responde sin datos, usa el cache previo si existía
      if (isset($row)) {
        $payload = is_string($row['payload_json']) ? json_decode($row['payload_json'], true)
          : $row['payload_json'];
        return is_array($payload) ? $payload : [];
      }
      return [];
    }
  } catch (Throwable $e) {
    error_log("[getDetalleVentaNetaMulti][API error] " . $e->getMessage());
    if (isset($row)) {
      $payload = is_string($row['payload_json']) ? json_decode($row['payload_json'], true)
        : $row['payload_json'];
      return is_array($payload) ? $payload : [];
    }
    return [];
  }

  // 3) Normalización opcional del payload (si la API trae números como "0,57")
  //    Si en tu front ya formateas, puedes guardar crudo. Si prefieres, aquí podrías
  //    recorrer $resp['datos'] y normalizar columnas numéricas específicas.
  $datos = $resp['datos']; // array de arrays

  $rowsCount = count($datos);
  $jsonPayload = json_encode($datos, JSON_UNESCAPED_UNICODE);

  // TTL sólo si el rango pisa hoy
  $expiresAt = null;
  if (isTodayOrAfter($fterY)) {
    $expiresAt = (new DateTime("+{$ttlSegundosParaHoy} seconds"))->format('Y-m-d H:i:s');
  }

  $sourceHash = hash('sha256', $finiY . '|' . $fterY . '|' . $listHash);

  // 4) Upsert cache
  $ins = $pdo->prepare(
    "INSERT INTO api_cache_kpi_venta_neta_det_set
       (fini, fter, kprv_list_json, kprv_set_hash, payload_json, rows_count, source_hash, last_synced_at, expires_at)
     VALUES
       (:fi, :ff, :json, :h, :payload, :rows, :sh, NOW(), :exp)
     ON DUPLICATE KEY UPDATE
       kprv_list_json = VALUES(kprv_list_json),
       payload_json   = VALUES(payload_json),
       rows_count     = VALUES(rows_count),
       source_hash    = VALUES(source_hash),
       last_synced_at = VALUES(last_synced_at),
       expires_at     = VALUES(expires_at)"
  );
  $ins->execute([
    ':fi' => $finiY,
    ':ff' => $fterY,
    ':json' => $jsonList,
    ':h' => $listHash,
    ':payload' => $jsonPayload,
    ':rows' => $rowsCount,
    ':sh' => $sourceHash,
    ':exp' => $expiresAt
  ]);

  return $datos;
}

/**
 * KPI: Detalle de unidades vendidas (multi-proveedor, con fechas).
 * Endpoint: kpi_unidades_vendidas_detalle
 * Body: { FINI: 'd/m/Y', FTER: 'd/m/Y', KPRV_LIST: [...] }
 * Cache: (fini, fter, set de KPRV) con TTL sólo si FTER >= hoy.
 */
function getDetalleUnidadesVendidasMulti(
  string $fechaInicioDMY,
  string $fechaFinDMY,
  array $kprvList,
  bool $forceRefresh = false,
  int $ttlSegundosParaHoy = 900 // 15 min
): array {
  $pdo = getDbConnection();
  $finiY = fechaDMYtoYMD($fechaInicioDMY);
  $fterY = fechaDMYtoYMD($fechaFinDMY);

  // Canonicaliza proveedores
  [$normList, $jsonList, $listHash] = canonicalizarKprvList($kprvList);
  if (empty($normList))
    return [];

  // 1) Leer caché
  if (!$forceRefresh) {
    $sel = $pdo->prepare(
      "SELECT payload_json, rows_count, expires_at
         FROM api_cache_kpi_unidades_vendidas_det_set
        WHERE fini = :fi AND fter = :ff AND kprv_set_hash = :h
        LIMIT 1"
    );
    $sel->execute([':fi' => $finiY, ':ff' => $fterY, ':h' => $listHash]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      if (empty($row['expires_at']) || (new DateTime() < new DateTime($row['expires_at']))) {
        $payload = is_string($row['payload_json'])
          ? json_decode($row['payload_json'], true)
          : $row['payload_json']; // si el driver ya devuelve JSON
        return is_array($payload) ? $payload : [];
      }
      // expirado → refrescar
    }
  }

  // 2) Llamar API
  try {
    $resp = callApi('kpi_unidades_vendidas_detalle', [
      'FINI' => $fechaInicioDMY,
      'FTER' => $fechaFinDMY,
      'KPRV_LIST' => $normList,
    ]);

    if (!isset($resp['estado']) || (int) $resp['estado'] !== 1 || !isset($resp['datos']) || !is_array($resp['datos'])) {
      if (isset($row)) {
        $payload = is_string($row['payload_json'])
          ? json_decode($row['payload_json'], true)
          : $row['payload_json'];
        return is_array($payload) ? $payload : [];
      }
      return [];
    }
  } catch (Throwable $e) {
    error_log("[getDetalleUnidadesVendidasMulti][API error] " . $e->getMessage());
    if (isset($row)) {
      $payload = is_string($row['payload_json'])
        ? json_decode($row['payload_json'], true)
        : $row['payload_json'];
      return is_array($payload) ? $payload : [];
    }
    return [];
  }

  // 3) (Opcional) Normalizar campos numéricos específicos del detalle
  // $datos = array_map(function($it) {
  //   if (isset($it['CANT'])) $it['CANT'] = (float) normalizarNumeroLatam($it['CANT'], 3);
  //   if (isset($it['NETO'])) $it['NETO'] = (float) normalizarNumeroLatam($it['NETO'], 2);
  //   return $it;
  // }, $resp['datos']);

  $datos = $resp['datos']; // guardar tal cual por ahora
  $rowsCount = count($datos);
  $jsonPayload = json_encode($datos, JSON_UNESCAPED_UNICODE);

  // TTL sólo si el rango pisa hoy
  $expiresAt = null;
  if (isTodayOrAfter($fterY)) {
    $expiresAt = (new DateTime("+{$ttlSegundosParaHoy} seconds"))->format('Y-m-d H:i:s');
  }
  $sourceHash = hash('sha256', $finiY . '|' . $fterY . '|' . $listHash);

  // 4) Upsert en caché
  $ins = $pdo->prepare(
    "INSERT INTO api_cache_kpi_unidades_vendidas_det_set
       (fini, fter, kprv_list_json, kprv_set_hash, payload_json, rows_count, source_hash, last_synced_at, expires_at)
     VALUES
       (:fi, :ff, :json, :h, :payload, :rows, :sh, NOW(), :exp)
     ON DUPLICATE KEY UPDATE
       kprv_list_json = VALUES(kprv_list_json),
       payload_json   = VALUES(payload_json),
       rows_count     = VALUES(rows_count),
       source_hash    = VALUES(source_hash),
       last_synced_at = VALUES(last_synced_at),
       expires_at     = VALUES(expires_at)"
  );
  $ins->execute([
    ':fi' => $finiY,
    ':ff' => $fterY,
    ':json' => $jsonList,
    ':h' => $listHash,
    ':payload' => $jsonPayload,
    ':rows' => $rowsCount,
    ':sh' => $sourceHash,
    ':exp' => $expiresAt
  ]);

  return $datos;
}

/**
 * KPI: Detalle de SKUs activos (multi-proveedor, sin fechas).
 * Endpoint: kpi_sku_activos_detalle
 * Body: { KPRV_LIST: [...] }
 * Cache: por set de KPRV con TTL (default 15 min).
 */
function getDetalleSkuActivosMulti(
  array $kprvList,
  bool $forceRefresh = false,
  int $ttlSegundos = 900 // 15 min
): array {
  $pdo = getDbConnection();

  // Canonicaliza set de proveedores
  [$normList, $jsonList, $listHash] = canonicalizarKprvList($kprvList);
  if (empty($normList))
    return [];

  // 1) Leer caché
  if (!$forceRefresh) {
    $sel = $pdo->prepare(
      "SELECT payload_json, rows_count, expires_at
         FROM api_cache_kpi_sku_activos_det_set
        WHERE kprv_set_hash = :h
        LIMIT 1"
    );
    $sel->execute([':h' => $listHash]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      if (empty($row['expires_at']) || (new DateTime() < new DateTime($row['expires_at']))) {
        $payload = is_string($row['payload_json'])
          ? json_decode($row['payload_json'], true)
          : $row['payload_json'];
        return is_array($payload) ? $payload : [];
      }
      // expirado → refrescar
    }
  }

  // 2) Llamar API
  try {
    $resp = callApi('kpi_sku_activos_detalle', ['KPRV_LIST' => $normList]);

    if (!isset($resp['estado']) || (int) $resp['estado'] !== 1 || !isset($resp['datos']) || !is_array($resp['datos'])) {
      if (isset($row)) {
        $payload = is_string($row['payload_json'])
          ? json_decode($row['payload_json'], true)
          : $row['payload_json'];
        return is_array($payload) ? $payload : [];
      }
      return [];
    }
  } catch (Throwable $e) {
    error_log("[getDetalleSkuActivosMulti][API error] " . $e->getMessage());
    if (isset($row)) {
      $payload = is_string($row['payload_json'])
        ? json_decode($row['payload_json'], true)
        : $row['payload_json'];
      return is_array($payload) ? $payload : [];
    }
    return [];
  }

  // 3) (Opcional) Normalizar campos numéricos del detalle si la API usa comas decimales
  // $datos = array_map(function($it){
  //   if (isset($it['CANT'])) $it['CANT'] = (float) normalizarNumeroLatam($it['CANT'], 3);
  //   return $it;
  // }, $resp['datos']);

  $datos = $resp['datos']; // guardar tal cual por ahora
  $rowsCount = count($datos);
  $jsonPayload = json_encode($datos, JSON_UNESCAPED_UNICODE);

  $expiresAt = (new DateTime("+{$ttlSegundos} seconds"))->format('Y-m-d H:i:s');
  $sourceHash = $listHash;

  // 4) Upsert caché
  $ins = $pdo->prepare(
    "INSERT INTO api_cache_kpi_sku_activos_det_set
       (kprv_list_json, kprv_set_hash, payload_json, rows_count, source_hash, last_synced_at, expires_at)
     VALUES
       (:json, :h, :payload, :rows, :sh, NOW(), :exp)
     ON DUPLICATE KEY UPDATE
       kprv_list_json = VALUES(kprv_list_json),
       payload_json   = VALUES(payload_json),
       rows_count     = VALUES(rows_count),
       source_hash    = VALUES(source_hash),
       last_synced_at = VALUES(last_synced_at),
       expires_at     = VALUES(expires_at)"
  );
  $ins->execute([
    ':json' => $jsonList,
    ':h' => $listHash,
    ':payload' => $jsonPayload,
    ':rows' => $rowsCount,
    ':sh' => $sourceHash,
    ':exp' => $expiresAt
  ]);

  return $datos;
}

/**
 * KPI: Total stock en unidades (multi-proveedor, con fechas y KEMP).
 * Endpoint: kpi_total_stock_unidades
 * Body: { KEMP: '001', FINI: 'd/m/Y', FTER: 'd/m/Y', KPRV_LIST: [...] }
 * Cache: (kemp, fini, fter, set de KPRV) con TTL sólo si FTER >= hoy.
 *
 * @return float
 */
function getStockUnidadesMulti(
  string $fechaInicioDMY,
  string $fechaFinDMY,
  array $kprvList,
  string $kemp = '001',
  bool $forceRefresh = false,
  int $ttlSegundosParaHoy = 900 // 15 min
): float {
  $pdo = getDbConnection();
  $finiY = fechaDMYtoYMD($fechaInicioDMY);
  $fterY = fechaDMYtoYMD($fechaFinDMY);

  // Canonicaliza proveedores
  [$normList, $jsonList, $listHash] = canonicalizarKprvList($kprvList);
  if (empty($normList))
    return 0.0;

  // 1) Leer cache
  if (!$forceRefresh) {
    $sel = $pdo->prepare(
      "SELECT cantidad, expires_at
         FROM api_cache_kpi_total_stock_unidades_set
        WHERE kemp = :ke AND fini = :fi AND fter = :ff AND kprv_set_hash = :h
        LIMIT 1"
    );
    $sel->execute([':ke' => $kemp, ':fi' => $finiY, ':ff' => $fterY, ':h' => $listHash]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      if (empty($row['expires_at']) || (new DateTime() < new DateTime($row['expires_at']))) {
        return (float) $row['cantidad'];
      }
      // expirado → refrescar
    }
  }

  // 2) API call
  try {
    $resp = callApi('kpi_total_stock_unidades', [
      'KEMP' => $kemp,
      'FINI' => $fechaInicioDMY,
      'FTER' => $fechaFinDMY,
      'KPRV_LIST' => $normList
    ]);

    if (!isset($resp['estado']) || (int) $resp['estado'] !== 1 || !isset($resp['datos'][0]['CANT'])) {
      if (isset($row))
        return (float) $row['cantidad'];
      return 0.0;
    }
  } catch (Throwable $e) {
    error_log("[getStockUnidadesMulti][API error] " . $e->getMessage());
    if (isset($row))
      return (float) $row['cantidad'];
    return 0.0;
  }

  // 3) Normalizar valor (por si viene "0,57")
  $raw = $resp['datos'][0]['CANT'] ?? null;
  $cantidadS = normalizarNumeroLatam($raw, 3); // "123.000" o "0.570"
  $cantidadF = (float) $cantidadS;

  // TTL sólo si el rango pisa hoy
  $expiresAt = null;
  if (isTodayOrAfter($fterY)) {
    $expiresAt = (new DateTime("+{$ttlSegundosParaHoy} seconds"))->format('Y-m-d H:i:s');
  }

  $sourceHash = hash('sha256', $kemp . '|' . $finiY . '|' . $fterY . '|' . $listHash);

  // 4) Upsert cache
  $ins = $pdo->prepare(
    "INSERT INTO api_cache_kpi_total_stock_unidades_set
       (kemp, fini, fter, kprv_list_json, kprv_set_hash, cantidad, source_hash, last_synced_at, expires_at)
     VALUES
       (:ke, :fi, :ff, :json, :h, :c, :sh, NOW(), :exp)
     ON DUPLICATE KEY UPDATE
       kprv_list_json = VALUES(kprv_list_json),
       cantidad       = VALUES(cantidad),
       source_hash    = VALUES(source_hash),
       last_synced_at = VALUES(last_synced_at),
       expires_at     = VALUES(expires_at)"
  );

  $ins->execute([
    ':ke' => $kemp,
    ':fi' => $finiY,
    ':ff' => $fterY,
    ':json' => $jsonList,
    ':h' => $listHash,
    ':c' => $cantidadS,  // string normalizado → DECIMAL
    ':sh' => $sourceHash,
    ':exp' => $expiresAt
  ]);

  return $cantidadF;
}

/**
 * KPI: Detalle total de stock en unidades (multi-proveedor, con fechas y KEMP).
 * Endpoint: kpi_total_stock_unidades_detalle
 * Body: { KEMP: '001', FINI: 'd/m/Y', FTER: 'd/m/Y', KPRV_LIST: [...] }
 * Cache por (kemp, fini, fter, set de KPRV). TTL sólo si FTER >= hoy.
 */
function getDetalleStockUnidadesMulti(
  string $fechaInicioDMY,
  string $fechaFinDMY,
  array $kprvList,
  string $kemp = '001',
  bool $forceRefresh = false,
  int $ttlSegundosParaHoy = 900 // 15 min
): array {
  $pdo = getDbConnection();
  $finiY = fechaDMYtoYMD($fechaInicioDMY);
  $fterY = fechaDMYtoYMD($fechaFinDMY);

  // Canonicaliza set de proveedores
  [$normList, $jsonList, $listHash] = canonicalizarKprvList($kprvList);
  if (empty($normList))
    return [];

  // 1) Intentar leer desde caché
  if (!$forceRefresh) {
    $sel = $pdo->prepare(
      "SELECT payload_json, rows_count, expires_at
         FROM api_cache_kpi_total_stock_unidades_det_set
        WHERE kemp = :ke AND fini = :fi AND fter = :ff AND kprv_set_hash = :h
        LIMIT 1"
    );
    $sel->execute([':ke' => $kemp, ':fi' => $finiY, ':ff' => $fterY, ':h' => $listHash]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      if (empty($row['expires_at']) || (new DateTime() < new DateTime($row['expires_at']))) {
        $payload = is_string($row['payload_json'])
          ? json_decode($row['payload_json'], true)
          : $row['payload_json']; // si el driver ya lo devuelve como JSON
        return is_array($payload) ? $payload : [];
      }
      // expiró → refrescar abajo
    }
  }

  // 2) Llamar API
  try {
    $resp = callApi('kpi_total_stock_unidades_detalle', [
      'KEMP' => $kemp,
      'FINI' => $fechaInicioDMY,
      'FTER' => $fechaFinDMY,
      'KPRV_LIST' => $normList
    ]);

    if (!isset($resp['estado']) || (int) $resp['estado'] !== 1 || !isset($resp['datos']) || !is_array($resp['datos'])) {
      if (isset($row)) {
        $payload = is_string($row['payload_json'])
          ? json_decode($row['payload_json'], true)
          : $row['payload_json'];
        return is_array($payload) ? $payload : [];
      }
      return [];
    }
  } catch (Throwable $e) {
    error_log("[getDetalleStockUnidadesMulti][API error] " . $e->getMessage());
    if (isset($row)) {
      $payload = is_string($row['payload_json'])
        ? json_decode($row['payload_json'], true)
        : $row['payload_json'];
      return is_array($payload) ? $payload : [];
    }
    return [];
  }

  // 3) (Opcional) Normalización de campos numéricos del detalle
  //    Si la API trae columnas con coma decimal (p.ej. 'CANT'), puedes normalizarlas:
  // $datos = array_map(function($it){
  //   if (isset($it['CANT'])) $it['CANT'] = (float) normalizarNumeroLatam($it['CANT'], 3);
  //   return $it;
  // }, $resp['datos']);

  $datos = $resp['datos']; // guardar tal cual por ahora
  $rowsCount = count($datos);
  $jsonPayload = json_encode($datos, JSON_UNESCAPED_UNICODE);

  // TTL sólo si el rango incluye hoy
  $expiresAt = null;
  if (isTodayOrAfter($fterY)) {
    $expiresAt = (new DateTime("+{$ttlSegundosParaHoy} seconds"))->format('Y-m-d H:i:s');
  }

  $sourceHash = hash('sha256', $kemp . '|' . $finiY . '|' . $fterY . '|' . $listHash);

  // 4) Upsert en caché
  $ins = $pdo->prepare(
    "INSERT INTO api_cache_kpi_total_stock_unidades_det_set
       (kemp, fini, fter, kprv_list_json, kprv_set_hash, payload_json, rows_count, source_hash, last_synced_at, expires_at)
     VALUES
       (:ke, :fi, :ff, :json, :h, :payload, :rows, :sh, NOW(), :exp)
     ON DUPLICATE KEY UPDATE
       kprv_list_json = VALUES(kprv_list_json),
       payload_json   = VALUES(payload_json),
       rows_count     = VALUES(rows_count),
       source_hash    = VALUES(source_hash),
       last_synced_at = VALUES(last_synced_at),
       expires_at     = VALUES(expires_at)"
  );

  $ins->execute([
    ':ke' => $kemp,
    ':fi' => $finiY,
    ':ff' => $fterY,
    ':json' => $jsonList,
    ':h' => $listHash,
    ':payload' => $jsonPayload,
    ':rows' => $rowsCount,
    ':sh' => $sourceHash,
    ':exp' => $expiresAt
  ]);

  return $datos;
}

/**
 * KPI: Total de stock (valor monetario) multi-proveedor, con fechas y KEMP.
 * Endpoint: kpi_total_stock_valor
 * Body: { KEMP:'001', FINI:'d/m/Y', FTER:'d/m/Y', KPRV_LIST:[...] }
 * Cache: (kemp, fini, fter, set de KPRV). TTL sólo si FTER >= hoy.
 *
 * @return float
 */
function getTotalStockValorMulti(
  string $fechaInicioDMY,
  string $fechaFinDMY,
  array $kprvList,
  string $kemp = '001',
  bool $forceRefresh = false,
  int $ttlSegundosParaHoy = 900 // 15 min
): float {
  $pdo = getDbConnection();
  $finiY = fechaDMYtoYMD($fechaInicioDMY);
  $fterY = fechaDMYtoYMD($fechaFinDMY);

  // Canonicaliza KPRV
  [$normList, $jsonList, $listHash] = canonicalizarKprvList($kprvList);
  if (empty($normList))
    return 0.0;

  // 1) Leer cache
  if (!$forceRefresh) {
    $sel = $pdo->prepare(
      "SELECT valor, expires_at
         FROM api_cache_kpi_total_stock_valor_set
        WHERE kemp = :ke AND fini = :fi AND fter = :ff AND kprv_set_hash = :h
        LIMIT 1"
    );
    $sel->execute([':ke' => $kemp, ':fi' => $finiY, ':ff' => $fterY, ':h' => $listHash]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      if (empty($row['expires_at']) || (new DateTime() < new DateTime($row['expires_at']))) {
        return (float) $row['valor'];
      }
      // expirado → refrescar
    }
  }

  // 2) API call
  try {
    $resp = callApi('kpi_total_stock_valor', [
      'KEMP' => $kemp,
      'FINI' => $fechaInicioDMY,
      'FTER' => $fechaFinDMY,
      'KPRV_LIST' => $normList
    ]);

    if (!isset($resp['estado']) || (int) $resp['estado'] !== 1 || !isset($resp['datos'][0]['VALO'])) {
      if (isset($row))
        return (float) $row['valor'];
      return 0.0;
    }
  } catch (Throwable $e) {
    error_log("[getTotalStockValorMulti][API error] " . $e->getMessage());
    if (isset($row))
      return (float) $row['valor'];
    return 0.0;
  }

  // 3) Normalizar VALO (puede venir como "1.234,56" o "1234,56")
  $raw = $resp['datos'][0]['VALO'] ?? null;
  $valorS = normalizarNumeroLatam($raw, 2); // string "1234.56"
  $valorF = (float) $valorS;

  // TTL sólo si el rango pisa hoy
  $expiresAt = null;
  if (isTodayOrAfter($fterY)) {
    $expiresAt = (new DateTime("+{$ttlSegundosParaHoy} seconds"))->format('Y-m-d H:i:s');
  }

  $sourceHash = hash('sha256', $kemp . '|' . $finiY . '|' . $fterY . '|' . $listHash);

  // 4) Upsert cache
  $ins = $pdo->prepare(
    "INSERT INTO api_cache_kpi_total_stock_valor_set
       (kemp, fini, fter, kprv_list_json, kprv_set_hash, valor, source_hash, last_synced_at, expires_at)
     VALUES
       (:ke, :fi, :ff, :json, :h, :v, :sh, NOW(), :exp)
     ON DUPLICATE KEY UPDATE
       kprv_list_json = VALUES(kprv_list_json),
       valor          = VALUES(valor),
       source_hash    = VALUES(source_hash),
       last_synced_at = VALUES(last_synced_at),
       expires_at     = VALUES(expires_at)"
  );

  $ins->execute([
    ':ke' => $kemp,
    ':fi' => $finiY,
    ':ff' => $fterY,
    ':json' => $jsonList,
    ':h' => $listHash,
    ':v' => $valorS,   // string normalizado → DECIMAL
    ':sh' => $sourceHash,
    ':exp' => $expiresAt
  ]);

  return $valorF;
}

/**
 * KPI: Detalle de total de stock (valor) — multi-proveedor, con fechas y KEMP.
 * Endpoint: kpi_total_stock_valor_detalle
 * Body: { KEMP:'001', FINI:'d/m/Y', FTER:'d/m/Y', KPRV_LIST:[...] }
 * Cachea el payload completo por (kemp, fini, fter, set de KPRV).
 * TTL sólo si FTER >= hoy (dato “abierto”).
 */
function getDetalleTotalStockValorMulti(
  string $fechaInicioDMY,
  string $fechaFinDMY,
  array $kprvList,
  string $kemp = '001',
  bool $forceRefresh = false,
  int $ttlSegundosParaHoy = 900 // 15 min
): array {
  $pdo = getDbConnection();
  $finiY = fechaDMYtoYMD($fechaInicioDMY);
  $fterY = fechaDMYtoYMD($fechaFinDMY);

  // Canonicaliza proveedores
  [$normList, $jsonList, $listHash] = canonicalizarKprvList($kprvList);
  if (empty($normList))
    return [];

  // 1) Leer caché
  if (!$forceRefresh) {
    $sel = $pdo->prepare(
      "SELECT payload_json, rows_count, expires_at
         FROM api_cache_kpi_total_stock_valor_det_set
        WHERE kemp = :ke AND fini = :fi AND fter = :ff AND kprv_set_hash = :h
        LIMIT 1"
    );
    $sel->execute([':ke' => $kemp, ':fi' => $finiY, ':ff' => $fterY, ':h' => $listHash]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      if (empty($row['expires_at']) || (new DateTime() < new DateTime($row['expires_at']))) {
        $payload = is_string($row['payload_json'])
          ? json_decode($row['payload_json'], true)
          : $row['payload_json']; // según el driver
        return is_array($payload) ? $payload : [];
      }
      // expirado → refrescar
    }
  }

  // 2) API call
  try {
    $resp = callApi('kpi_total_stock_valor_detalle', [
      'KEMP' => $kemp,
      'FINI' => $fechaInicioDMY,
      'FTER' => $fechaFinDMY,
      'KPRV_LIST' => $normList
    ]);

    if (!isset($resp['estado']) || (int) $resp['estado'] !== 1 || !isset($resp['datos']) || !is_array($resp['datos'])) {
      if (isset($row)) {
        $payload = is_string($row['payload_json'])
          ? json_decode($row['payload_json'], true)
          : $row['payload_json'];
        return is_array($payload) ? $payload : [];
      }
      return [];
    }
  } catch (Throwable $e) {
    error_log("[getDetalleTotalStockValorMulti][API error] " . $e->getMessage());
    if (isset($row)) {
      $payload = is_string($row['payload_json'])
        ? json_decode($row['payload_json'], true)
        : $row['payload_json'];
      return is_array($payload) ? $payload : [];
    }
    return [];
  }

  // 3) (Opcional) Normalizar columnas numéricas del detalle (p. ej. VALO, CANT, etc.)
  // $datos = array_map(function($it){
  //   if (isset($it['VALO'])) $it['VALO'] = (float) normalizarNumeroLatam($it['VALO'], 2);
  //   if (isset($it['CANT'])) $it['CANT'] = (float) normalizarNumeroLatam($it['CANT'], 3);
  //   return $it;
  // }, $resp['datos']);

  $datos = $resp['datos']; // guardar tal cual por ahora
  $rowsCount = count($datos);
  $jsonPayload = json_encode($datos, JSON_UNESCAPED_UNICODE);

  // TTL sólo si el rango pisa hoy
  $expiresAt = null;
  if (isTodayOrAfter($fterY)) {
    $expiresAt = (new DateTime("+{$ttlSegundosParaHoy} seconds"))->format('Y-m-d H:i:s');
  }

  $sourceHash = hash('sha256', $kemp . '|' . $finiY . '|' . $fterY . '|' . $listHash);

  // 4) Upsert caché
  $ins = $pdo->prepare(
    "INSERT INTO api_cache_kpi_total_stock_valor_det_set
       (kemp, fini, fter, kprv_list_json, kprv_set_hash, payload_json, rows_count, source_hash, last_synced_at, expires_at)
     VALUES
       (:ke, :fi, :ff, :json, :h, :payload, :rows, :sh, NOW(), :exp)
     ON DUPLICATE KEY UPDATE
       kprv_list_json = VALUES(kprv_list_json),
       payload_json   = VALUES(payload_json),
       rows_count     = VALUES(rows_count),
       source_hash    = VALUES(source_hash),
       last_synced_at = VALUES(last_synced_at),
       expires_at     = VALUES(expires_at)"
  );

  $ins->execute([
    ':ke' => $kemp,
    ':fi' => $finiY,
    ':ff' => $fterY,
    ':json' => $jsonList,
    ':h' => $listHash,
    ':payload' => $jsonPayload,
    ':rows' => $rowsCount,
    ':sh' => $sourceHash,
    ':exp' => $expiresAt
  ]);

  return $datos;
}

/**
 * KPI: Venta neta últimos 6 meses (multi-proveedor).
 * Endpoint: kpi_venta_neta6meses
 * Body: { FTER: 'd/m/Y', KPRV_LIST: [...] }
 * Cache: (FTER, set de KPRV) con TTL sólo si FTER >= hoy.
 *
 * @return array  Serie tal como la entrega la API (recomendado guardar crudo).
 */
function getVentaNetaSeisMesesMulti(
  string $fechaFinDMY,        // FTER (d/m/Y)
  array $kprvList,
  bool $forceRefresh = false,
  int $ttlSegundosParaHoy = 900 // 15 min
): array {
  $pdo = getDbConnection();
  $fterY = fechaDMYtoYMD($fechaFinDMY);

  // Canonicaliza lista de KPRV
  [$normList, $jsonList, $listHash] = canonicalizarKprvList($kprvList);
  if (empty($normList))
    return [];

  // 1) Intentar leer desde caché
  if (!$forceRefresh) {
    $sel = $pdo->prepare(
      "SELECT payload_json, rows_count, expires_at
         FROM api_cache_kpi_venta_neta_6m_set
        WHERE fter = :ff AND kprv_set_hash = :h
        LIMIT 1"
    );
    $sel->execute([':ff' => $fterY, ':h' => $listHash]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    if ($row && isset($row['rows_count']) && (int) $row['rows_count'] > 0) {
      if (empty($row['expires_at']) || (new DateTime() < new DateTime($row['expires_at']))) {
        $payload = is_string($row['payload_json'])
          ? json_decode($row['payload_json'], true)
          : $row['payload_json']; // si el driver devuelve JSON nativo
        return is_array($payload) ? $payload : [];
      }
      // expiró → refrescar abajo
    }
  }

  // 2) Llamar API
  try {
    $resp = callApi('kpi_venta_neta6meses', [
      'FTER' => $fechaFinDMY,
      'KPRV_LIST' => $normList
    ]);

    if (!isset($resp['estado']) || (int) $resp['estado'] !== 1 || !isset($resp['datos']) || !is_array($resp['datos'])) {
      // Si falla la API, devolver caché previo si existía
      if (isset($row)) {
        $payload = is_string($row['payload_json'])
          ? json_decode($row['payload_json'], true)
          : $row['payload_json'];
        return is_array($payload) ? $payload : [];
      }
      return [];
    }
  } catch (Throwable $e) {
    error_log("[getVentaNetaSeisMesesMulti][API error] " . $e->getMessage());
    if (isset($row)) {
      $payload = is_string($row['payload_json'])
        ? json_decode($row['payload_json'], true)
        : $row['payload_json'];
      return is_array($payload) ? $payload : [];
    }
    return [];
  }

  // 3) (Opcional) Normalización de campos numéricos dentro de la serie
  //    Si el payload trae NETO como "1.234,56", puedes normalizar aquí:
  // $datos = array_map(function($it){
  //   if (isset($it['NETO'])) $it['NETO'] = (float) normalizarNumeroLatam($it['NETO'], 2);
  //   return $it;
  // }, $resp['datos']);

  $datos = $resp['datos']; // guardamos crudo por ahora
  $rowsCount = count($datos);
  $jsonPayload = json_encode($datos, JSON_UNESCAPED_UNICODE);

  // TTL sólo si FTER >= hoy (dato “abierto”)
  $expiresAt = null;
  if (isTodayOrAfter($fterY)) {
    $expiresAt = (new DateTime("+{$ttlSegundosParaHoy} seconds"))->format('Y-m-d H:i:s');
  }

  $sourceHash = hash('sha256', $fterY . '|' . $listHash);

  // 4) Upsert caché
  $ins = $pdo->prepare(
    "INSERT INTO api_cache_kpi_venta_neta_6m_set
       (fter, kprv_list_json, kprv_set_hash, payload_json, rows_count, source_hash, last_synced_at, expires_at)
     VALUES
       (:ff, :json, :h, :payload, :rows, :sh, NOW(), :exp)
     ON DUPLICATE KEY UPDATE
       kprv_list_json = VALUES(kprv_list_json),
       payload_json   = VALUES(payload_json),
       rows_count     = VALUES(rows_count),
       source_hash    = VALUES(source_hash),
       last_synced_at = VALUES(last_synced_at),
       expires_at     = VALUES(expires_at)"
  );

  $ins->execute([
    ':ff' => $fterY,
    ':json' => $jsonList,
    ':h' => $listHash,
    ':payload' => $jsonPayload,
    ':rows' => $rowsCount,
    ':sh' => $sourceHash,
    ':exp' => $expiresAt
  ]);

  return $datos;
}

/**
 * KPI: Total stock valorizado - últimos 6 meses (multi-proveedor).
 * Endpoint: kpi_total_stock_valor6meses
 * Body: { FTER:'d/m/Y', KPRV_LIST:[...] }
 * Cache: (FTER, set de KPRV), TTL sólo si FTER >= hoy.
 *
 * @return array Serie tal y como la devuelve la API.
 */
function getTotalStockValorSeisMesesMulti(
  string $fechaFinDMY,          // FTER d/m/Y
  array $kprvList,
  bool $forceRefresh = false,
  int $ttlSegundosParaHoy = 900 // 15 min
): array {
  $pdo = getDbConnection();
  $fterY = fechaDMYtoYMD($fechaFinDMY);

  // Canonicaliza proveedores
  [$normList, $jsonList, $listHash] = canonicalizarKprvList($kprvList);
  if (empty($normList))
    return [];

  // 1) Leer caché
  if (!$forceRefresh) {
    $sel = $pdo->prepare(
      "SELECT payload_json, rows_count, expires_at
         FROM api_cache_kpi_total_stock_valor_6m_set
        WHERE fter = :ff AND kprv_set_hash = :h
        LIMIT 1"
    );
    $sel->execute([':ff' => $fterY, ':h' => $listHash]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    if ($row && isset($row['rows_count']) && (int) $row['rows_count'] > 0) {
      if (empty($row['expires_at']) || (new DateTime() < new DateTime($row['expires_at']))) {
        $payload = is_string($row['payload_json'])
          ? json_decode($row['payload_json'], true)
          : $row['payload_json']; // si el driver ya devuelve JSON
        return is_array($payload) ? $payload : [];
      }
      // expirado → refrescar
    }
  }

  // 2) Llamar API
  try {
    $resp = callApi('kpi_total_stock_valor6meses', [
      'FTER' => $fechaFinDMY,
      'KPRV_LIST' => $normList
    ]);

    if (!isset($resp['estado']) || (int) $resp['estado'] !== 1 || !isset($resp['datos']) || !is_array($resp['datos'])) {
      if (isset($row)) {
        $payload = is_string($row['payload_json'])
          ? json_decode($row['payload_json'], true)
          : $row['payload_json'];
        return is_array($payload) ? $payload : [];
      }
      return [];
    }
  } catch (Throwable $e) {
    error_log("[getTotalStockValorSeisMesesMulti][API error] " . $e->getMessage());
    if (isset($row)) {
      $payload = is_string($row['payload_json'])
        ? json_decode($row['payload_json'], true)
        : $row['payload_json'];
      return is_array($payload) ? $payload : [];
    }
    return [];
  }

  // (Opcional) Normalización de campos monetarios dentro de la serie si la API usa coma decimal
  // $datos = array_map(function($it){
  //   if (isset($it['VALO'])) $it['VALO'] = (float) normalizarNumeroLatam($it['VALO'], 2);
  //   return $it;
  // }, $resp['datos']);

  $datos = $resp['datos']; // guardar crudo
  $rowsCount = count($datos);
  $jsonPayload = json_encode($datos, JSON_UNESCAPED_UNICODE);

  // TTL sólo si FTER ≥ hoy
  $expiresAt = null;
  if (isTodayOrAfter($fterY)) {
    $expiresAt = (new DateTime("+{$ttlSegundosParaHoy} seconds"))->format('Y-m-d H:i:s');
  }

  $sourceHash = hash('sha256', $fterY . '|' . $listHash);

  // 4) Upsert caché
  $ins = $pdo->prepare(
    "INSERT INTO api_cache_kpi_total_stock_valor_6m_set
       (fter, kprv_list_json, kprv_set_hash, payload_json, rows_count, source_hash, last_synced_at, expires_at)
     VALUES
       (:ff, :json, :h, :payload, :rows, :sh, NOW(), :exp)
     ON DUPLICATE KEY UPDATE
       kprv_list_json = VALUES(kprv_list_json),
       payload_json   = VALUES(payload_json),
       rows_count     = VALUES(rows_count),
       source_hash    = VALUES(source_hash),
       last_synced_at = VALUES(last_synced_at),
       expires_at     = VALUES(expires_at)"
  );

  $ins->execute([
    ':ff' => $fterY,
    ':json' => $jsonList,
    ':h' => $listHash,
    ':payload' => $jsonPayload,
    ':rows' => $rowsCount,
    ':sh' => $sourceHash,
    ':exp' => $expiresAt
  ]);

  return $datos;
}
