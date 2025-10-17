<?php
// includes/proveedores_repository.php
require_once dirname(__DIR__) . '/config/database.php';
require_once APP_ROOT . '/includes/api_client.php';

/**
 * Devuelve la lista de KPRV a usar.
 * - Por defecto, toma los del último snapshot en api_cache_proveedores.
 * - Si el caché está vacío u obsoleto, hace fallback a la API, pobla cache y devuelve los KPRV.
 *
 * @param int  $maxDesfaseDias   Días de tolerancia para considerar fresco el snapshot (ej. 2)
 * @param bool $soloUsuarios     Si true, limita a proveedores con usuario (rol='proveedor' y habilitado='1')
 * @param bool $razonSocial      Si true, retorna también razon_social (array de arrays). Si false, solo KPRV (array de strings)
 * @return array
 *   - $razonSocial=false  => string[]                   (kprv)
 *   - $razonSocial=true   => array<int, array{kprv:string, razon_social:string}>
 */
function obtenerKPRVDesdeCache(int $maxDesfaseDias = 2, bool $soloUsuarios = false, bool $razonSocial = false): array
{
  $pdo = getDbConnection();

  // 1) Fecha del último snapshot en cache
  $sqlMax = "SELECT MAX(snapshot_date) AS max_snap FROM api_cache_proveedores";
  $maxSnap = $pdo->query($sqlMax)->fetchColumn();

  $usarCache = false;
  if ($maxSnap) {
    // snapshot_date es DATE (Y-m-d). Compara con el mismo formato.
    $limite = (new DateTime("now"))->modify("-{$maxDesfaseDias} days")->format('Y-m-d');
    $usarCache = $maxSnap >= $limite; // cache “fresco” dentro de tolerancia
  }

  // Helper para construir la SELECT base del caché (último snapshot)
  $lastSnapFilter = "snapshot_date = (SELECT MAX(snapshot_date) FROM api_cache_proveedores)";

  if ($usarCache) {
    if ($razonSocial === false) {
      // Solo KPRV
      $sql = $soloUsuarios
        ? "SELECT u.rut AS kprv
                     FROM usuarios u
                     JOIN api_cache_proveedores p ON p.kprv = u.rut
                    WHERE u.rol = 'proveedor' AND u.habilitado = '1'
                      AND p.$lastSnapFilter
                 ORDER BY u.rut"
        : "SELECT kprv
                     FROM api_cache_proveedores
                    WHERE $lastSnapFilter
                 ORDER BY kprv";
      return $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN, 0);
    } else {
      // KPRV + razon_social
      $sql = $soloUsuarios
        ? "SELECT p.kprv, p.razon_social
                     FROM usuarios u
                     JOIN api_cache_proveedores p ON p.kprv = u.rut
                    WHERE u.rol = 'proveedor' AND u.habilitado = '1'
                      AND p.$lastSnapFilter
                 ORDER BY p.kprv"
        : "SELECT p.kprv, p.razon_social
                     FROM api_cache_proveedores p
                    WHERE p.$lastSnapFilter
                 ORDER BY p.kprv";
      return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
  }

  // 2) Fallback: cache vacío/viejo → consultar API una vez y poblar cache
  $lista = getProveedoresFromAPI();
  if (!is_array($lista) || empty($lista)) {
    return [];
  }

  // upsert mínimo para sembrar cache (sin batches porque el set suele ser pequeño)
  $snapshotDate = (new DateTime('now'))->format('Y-m-d');
  $pdo->beginTransaction();
  try {
    $ins = $pdo->prepare(
      "INSERT INTO api_cache_proveedores (kprv, razon_social, updated_at_api, snapshot_date, source_hash, last_synced_at)
             VALUES (:kprv, :razon, NOW(), :snap, :hash, NOW())
             ON DUPLICATE KEY UPDATE
               razon_social   = VALUES(razon_social),
               updated_at_api = VALUES(updated_at_api),
               snapshot_date  = VALUES(snapshot_date),
               source_hash    = VALUES(source_hash),
               last_synced_at = VALUES(last_synced_at)"
    );

    foreach ($lista as $prov) {
      $kprv = (string) ($prov['KPRV'] ?? '');
      $raz = trim((string) ($prov['RAZO'] ?? ''));
      if ($kprv === '')
        continue;

      $hash = hash('sha256', json_encode([$kprv, $raz], JSON_UNESCAPED_UNICODE));
      $ins->execute([
        ':kprv' => $kprv,
        ':razon' => $raz,
        ':snap' => $snapshotDate,
        ':hash' => $hash
      ]);
    }
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction())
      $pdo->rollBack();
    error_log("[obtenerKPRVDesdeCache][fallback] " . $e->getMessage());
    // En caso de error al sembrar, como último recurso devolvemos desde la respuesta de API:
    if ($razonSocial === false) {
      return array_values(array_filter(array_map(fn($p) => (string) ($p['KPRV'] ?? ''), $lista)));
    } else {
      $out = [];
      foreach ($lista as $prov) {
        $k = (string) ($prov['KPRV'] ?? '');
        if ($k === '')
          continue;
        $out[] = ['kprv' => $k, 'razon_social' => trim((string) ($prov['RAZO'] ?? ''))];
      }
      return $out;
    }
  }

  // 3) Tras sembrar, leemos del caché con el mismo formato que el branch “usarCache”
  if ($razonSocial === false) {
    $sql = $soloUsuarios
      ? "SELECT u.rut AS kprv
                 FROM usuarios u
                 JOIN api_cache_proveedores p ON p.kprv = u.rut
                WHERE u.rol = 'proveedor' AND u.habilitado = '1'
                  AND p.$lastSnapFilter
             ORDER BY u.rut"
      : "SELECT kprv
                 FROM api_cache_proveedores
                WHERE $lastSnapFilter
             ORDER BY kprv";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN, 0);
  } else {
    $sql = $soloUsuarios
      ? "SELECT p.kprv, p.razon_social
                 FROM usuarios u
                 JOIN api_cache_proveedores p ON p.kprv = u.rut
                WHERE u.rol = 'proveedor' AND u.habilitado = '1'
                  AND p.$lastSnapFilter
             ORDER BY p.kprv"
      : "SELECT p.kprv, p.razon_social
                 FROM api_cache_proveedores p
                WHERE p.$lastSnapFilter
             ORDER BY p.kprv";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  }
}