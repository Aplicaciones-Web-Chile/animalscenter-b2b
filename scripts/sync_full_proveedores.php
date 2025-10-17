<?php
/**
 * Full snapshot nocturno de proveedores → api_cache_proveedores
 * Ejecutar por cron (idealmente 00:00).
 *
 * Requisitos:
 *  - Tabla: api_cache_proveedores (kprv PK, razon_social, updated_at_api, snapshot_date, source_hash, last_synced_at)
 *  - Tabla: api_sync_log
 *  - Config DB: config/database.php con getDbConnection()
 *  - Cliente API: includes/api_client.php con getProveedoresFromAPI()
 */

declare(strict_types=1);

// === Bootstrap ===
require_once dirname(__DIR__) . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/api_client.php';
require_once APP_ROOT . '/includes/helpers.php';

// --- upsert en cache ---
/**
 * Upsertea un proveedor en api_cache_proveedores.
 * Devuelve true si hubo cambios (insert/update con contenido nuevo), false si solo se tocó metadata.
 */
function upsertProveedorCache(PDO $pdo, array $prov, string $snapshotDate): bool
{
  // Normalización (API: KPRV, RAZO)
  $kprv = isset($prov['KPRV']) ? (string) $prov['KPRV'] : '';
  $razonSocial = trim((string) ($prov['RAZO'] ?? ''));

  if ($kprv === '') {
    // KPRV es obligatorio en nuestro esquema; omite registros inválidos
    return false;
  }

  // Hash sólo de los campos relevantes a negocio
  $sourceHash = hash('sha256', json_encode([$kprv, $razonSocial], JSON_UNESCAPED_UNICODE));

  // consulta hash actual
  $sel = $pdo->prepare("SELECT source_hash FROM api_cache_proveedores WHERE kprv = :kprv");
  $sel->execute([':kprv' => $kprv]);
  $row = $sel->fetch(PDO::FETCH_ASSOC);

  if ($row && $row['source_hash'] === $sourceHash) {
    // Sin cambios de contenido: solo metadata de sync
    $upd = $pdo->prepare(
      "UPDATE api_cache_proveedores
               SET last_synced_at = NOW(),
                   snapshot_date  = :snap
             WHERE kprv = :kprv"
    );
    $upd->execute([':snap' => $snapshotDate, ':kprv' => $kprv]);
    return false;
  }

  // Insert/Update con contenido
  $ins = $pdo->prepare(
    "INSERT INTO api_cache_proveedores (
            kprv, razon_social, updated_at_api, snapshot_date, source_hash, last_synced_at
         ) VALUES (
            :kprv, :razon_social, NOW(), :snapshot_date, :source_hash, NOW()
         )
         ON DUPLICATE KEY UPDATE
            razon_social   = VALUES(razon_social),
            updated_at_api = VALUES(updated_at_api),
            snapshot_date  = VALUES(snapshot_date),
            source_hash    = VALUES(source_hash),
            last_synced_at = VALUES(last_synced_at)"
  );

  $ins->execute([
    ':kprv' => $kprv,
    ':razon_social' => $razonSocial,
    ':snapshot_date' => $snapshotDate,
    ':source_hash' => $sourceHash
  ]);

  return true;
}

// === MAIN ===
$pdo = getDbConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Para auditoría: full sin parámetros de ventana (el endpoint no usa FINI/FTER)
$logId = syncLogStart($pdo, 'proveedores', 'full', 'no_window');

$totalUpserts = 0;
$batchSize = 1000; // defensivo; la lista suele ser acotada, pero mantenemos el patrón
$i = 0;

try {
  // marca de snapshot: el día en que corre el job (si lo programas 00:00, corresponde al día actual)
  $snapshotDate = (new DateTime('now'))->format('m/d/Y');

  // Llamada a API (usa tu api_client.php)
  $lista = getProveedoresFromAPI();

  if (!is_array($lista)) {
    throw new RuntimeException('Respuesta inesperada: proveedores no es array.');
  }

  // Transacción por lotes
  if (!$pdo->inTransaction()) {
    $pdo->beginTransaction();
  }

  foreach ($lista as $prov) {
    $changed = upsertProveedorCache($pdo, $prov, $snapshotDate);
    if ($changed) {
      $totalUpserts++;
    }

    if ((++$i % $batchSize) === 0) {
      if ($pdo->inTransaction()) {
        $pdo->commit();
      }
      $pdo->beginTransaction();
    }
  }

  // commit remanente
  if ($pdo->inTransaction()) {
    $pdo->commit();
  }

  syncLogFinish($pdo, $logId, 'ok', $totalUpserts, 0, null);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  error_log("[sync_full_proveedores][ERROR] " . $e->getMessage());
  syncLogFinish($pdo, $logId, 'error', $totalUpserts, 0, $e->getMessage());
  exit(1);
}
