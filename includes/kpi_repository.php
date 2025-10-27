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
