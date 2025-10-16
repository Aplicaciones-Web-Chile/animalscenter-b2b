<?php
/**
 * Full snapshot nocturno de productos → api_cache_productos
 * - Ejecutar por cron cerca de las 00:00
 * - Recorre proveedores definidos y upsertea todos los productos del rango
 */

declare(strict_types=1);

// === Bootstrap básico ===
require_once dirname(__DIR__) . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/api_client.php';

// === Configuración del snapshot ===
const DISTRIBUIDOR = '001';

// Proveedores a sincronizar (ajusta a tu realidad: lista fija o cargar de tu tabla de proveedores)
$PROVEEDORES = [
  '78843490'
];

// Rango de fechas del snapshot:
// - Si tu API requiere FINI/FTER, define acá la ventana “oficial” del corte.
// - Ejemplo: snapshot del día anterior (00:00–23:59:59)
$fechaInicio = (new DateTime('yesterday 00:00:00'))->format('d/m/Y');
$fechaFin = (new DateTime('yesterday 23:59:59'))->format('d/m/Y');

// === Helpers de logging de sync ===
function syncLogStart(PDO $pdo, string $endpoint, string $syncType, ?string $sinceParam): int
{
  $sql = "INSERT INTO api_sync_log (endpoint, sync_type, since_param, started_at, status)
            VALUES (:endpoint, :sync_type, :since_param, NOW(), 'ok')";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':endpoint' => $endpoint,
    ':sync_type' => $syncType,
    ':since_param' => $sinceParam
  ]);
  return (int) $pdo->lastInsertId();
}

function syncLogFinish(PDO $pdo, int $logId, string $status, int $upserted, int $deleted = 0, ?string $errorMsg = null): void
{
  $sql = "UPDATE api_sync_log
               SET finished_at = NOW(),
                   status = :status,
                   items_upserted = :upserted,
                   items_deleted = :deleted,
                   error_message = :error
             WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':status' => $status,
    ':upserted' => $upserted,
    ':deleted' => $deleted,
    ':error' => $errorMsg,
    ':id' => $logId
  ]);
}

// === Upsert a la tabla de caché ===
function upsertProductoCache(PDO $pdo, string $proveedor, array $p, string $snapshotDate): bool
{
  // Mapear campos desde la API
  $payload = [
    'producto_codigo' => (int) ($p['PRODUCTO_CODIGO'] ?? 0),
    'codigo_de_barra' => $p['CODIGO_DE_BARRA'] ?? null,
    'producto_descripcion' => $p['PRODUCTO_DESCRIPCION'] ?? '',
    'marca_descripcion' => $p['MARCA_DESCRIPCION'] ?? null,
    'familia_descripcion' => $p['FAMILIA_DESCRIPCION'] ?? null,
    'subfamilia_descripcion' => $p['SUBFAMILIA_DESCRIPCION'] ?? null,
    'venta_sucursal01' => (int) ($p['VENTA_SUCURSAL01'] ?? 0),
    'stock_bodega01' => (int) ($p['STOCK_BODEGA01'] ?? 0),
    'venta_sucursal02' => (int) ($p['VENTA_SUCURSAL02'] ?? 0),
    'stock_bodega02' => (int) ($p['STOCK_BODEGA02'] ?? 0),
    'venta_sucursal03' => (int) ($p['VENTA_SUCURSAL03'] ?? 0),
    'stock_bodega03' => (int) ($p['STOCK_BODEGA03'] ?? 0),
    'venta_sucursal04' => (int) ($p['VENTA_SUCURSAL04'] ?? 0),
    'stock_bodega04' => (int) ($p['STOCK_BODEGA04'] ?? 0),
    'venta_sucursal05' => (int) ($p['VENTA_SUCURSAL05'] ?? 0),
    'stock_bodega05' => (int) ($p['STOCK_BODEGA05'] ?? 0),
    'venta_distribucion' => (int) ($p['VENTA_DISTRIBUCION'] ?? 0),
    'venta_sucursal07' => (int) ($p['VENTA_SUCURSAL07'] ?? 0),
    'kg' => isset($p['KG']) ? (float) $p['KG'] : null,
    'precio_ultima_compra' => isset($p['PRECIO_ULTIMA_COMPRA']) ? (float) $p['PRECIO_ULTIMA_COMPRA'] : null,
    'unidad_de_medida' => $p['UNIDAD_DE_MEDIDA'] ?? null
  ];

  // Hash del payload significativo para evitar updates innecesarios
  $hashFields = [
    $payload['codigo_de_barra'],
    $payload['producto_descripcion'],
    $payload['marca_descripcion'],
    $payload['familia_descripcion'],
    $payload['subfamilia_descripcion'],
    $payload['venta_sucursal01'],
    $payload['stock_bodega01'],
    $payload['venta_sucursal02'],
    $payload['stock_bodega02'],
    $payload['venta_sucursal03'],
    $payload['stock_bodega03'],
    $payload['venta_sucursal04'],
    $payload['stock_bodega04'],
    $payload['venta_sucursal05'],
    $payload['stock_bodega05'],
    $payload['venta_distribucion'],
    $payload['venta_sucursal07'],
    $payload['kg'],
    $payload['precio_ultima_compra'],
    $payload['unidad_de_medida']
  ];
  $sourceHash = hash('sha256', json_encode($hashFields, JSON_UNESCAPED_UNICODE));

  // ¿Existe con el mismo hash?
  $q = $pdo->prepare("SELECT source_hash FROM api_cache_productos
                         WHERE proveedor = :prov AND producto_codigo = :cod");
  $q->execute([':prov' => $proveedor, ':cod' => $payload['producto_codigo']]);
  $row = $q->fetch(PDO::FETCH_ASSOC);

  if ($row && $row['source_hash'] === $sourceHash) {
    // Solo marca last_synced_at y snapshot_date actual
    $pdo->prepare("UPDATE api_cache_productos
                          SET last_synced_at = NOW(), snapshot_date = :snap
                        WHERE proveedor = :prov AND producto_codigo = :cod")
      ->execute([':snap' => $snapshotDate, ':prov' => $proveedor, ':cod' => $payload['producto_codigo']]);
    return false; // no hubo cambios de contenido
  }

  // Upsert
  $sql = "INSERT INTO api_cache_productos (
                proveedor, producto_codigo, codigo_de_barra,
                producto_descripcion, marca_descripcion, familia_descripcion, subfamilia_descripcion,
                venta_sucursal01, stock_bodega01, venta_sucursal02, stock_bodega02,
                venta_sucursal03, stock_bodega03, venta_sucursal04, stock_bodega04,
                venta_sucursal05, stock_bodega05, venta_distribucion, venta_sucursal07,
                kg, precio_ultima_compra, unidad_de_medida,
                updated_at_api, snapshot_date, source_hash, last_synced_at
            )
            VALUES (
                :proveedor, :producto_codigo, :codigo_de_barra,
                :producto_descripcion, :marca_descripcion, :familia_descripcion, :subfamilia_descripcion,
                :venta_sucursal01, :stock_bodega01, :venta_sucursal02, :stock_bodega02,
                :venta_sucursal03, :stock_bodega03, :venta_sucursal04, :stock_bodega04,
                :venta_sucursal05, :stock_bodega05, :venta_distribucion, :venta_sucursal07,
                :kg, :precio_ultima_compra, :unidad_de_medida,
                NOW(), :snapshot_date, :source_hash, NOW()
            )
            ON DUPLICATE KEY UPDATE
                codigo_de_barra = VALUES(codigo_de_barra),
                producto_descripcion = VALUES(producto_descripcion),
                marca_descripcion = VALUES(marca_descripcion),
                familia_descripcion = VALUES(familia_descripcion),
                subfamilia_descripcion = VALUES(subfamilia_descripcion),
                venta_sucursal01 = VALUES(venta_sucursal01),
                stock_bodega01 = VALUES(stock_bodega01),
                venta_sucursal02 = VALUES(venta_sucursal02),
                stock_bodega02 = VALUES(stock_bodega02),
                venta_sucursal03 = VALUES(venta_sucursal03),
                stock_bodega03 = VALUES(stock_bodega03),
                venta_sucursal04 = VALUES(venta_sucursal04),
                stock_bodega04 = VALUES(stock_bodega04),
                venta_sucursal05 = VALUES(venta_sucursal05),
                stock_bodega05 = VALUES(stock_bodega05),
                venta_distribucion = VALUES(venta_distribucion),
                venta_sucursal07 = VALUES(venta_sucursal07),
                kg = VALUES(kg),
                precio_ultima_compra = VALUES(precio_ultima_compra),
                unidad_de_medida = VALUES(unidad_de_medida),
                updated_at_api = VALUES(updated_at_api),
                snapshot_date = VALUES(snapshot_date),
                source_hash = VALUES(source_hash),
                last_synced_at = VALUES(last_synced_at)";
  $stmt = $pdo->prepare($sql);

  $params = array_merge([
    ':proveedor' => $proveedor,
    ':snapshot_date' => $snapshotDate,
    ':source_hash' => $sourceHash
  ], array_combine(array_map(fn($k) => ":$k", array_keys($payload)), array_values($payload)));

  $stmt->execute($params);
  return true; // hubo cambio (insert o update con contenido nuevo)
}

// === MAIN ===
$pdo = getDbConnection(); // de config/database.php
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Para auditoría: “full” con sinceParam = ventana usada
$sinceParam = "FINI={$fechaInicio};FTER={$fechaFin}";
$logId = syncLogStart($pdo, 'productos', 'full', $sinceParam);

$totalUpserts = 0;

try {
  $snapshotDate = (new DateTime('yesterday'))->format('Y-m-d'); // marca del snapshot

  foreach ($PROVEEDORES as $prov) {
    error_log("[sync_full_productos] Proveedor={$prov} FINI={$fechaInicio} FTER={$fechaFin}");

    // Si la API no pagina, bastará con una sola llamada:
    $resp = obtenerProductosAPI(DISTRIBUIDOR, $fechaInicio, $fechaFin, $prov);

    if (!isset($resp['estado']) || (int) $resp['estado'] !== 1) {
      throw new RuntimeException("API error (prov={$prov}): " . ($resp['error'] ?? 'sin detalle'));
    }

    $items = $resp['datos'] ?? [];
    if (!is_array($items)) {
      throw new RuntimeException("Estructura inesperada de datos (prov={$prov}).");
    }

    // Upsert en transacción por lote
    $pdo->beginTransaction();
    $batchUpserts = 0;
    $i = 0;

    // importante: excepciones activadas
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    try {
      if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
      }

      foreach ($items as $p) {
        $changed = upsertProductoCache($pdo, $prov, $p, $snapshotDate);
        if ($changed) {
          $batchUpserts++;
        }

        // commit cada 1000
        if ((++$i % 1000) === 0) {
          if ($pdo->inTransaction()) {
            $pdo->commit();
          }
          $pdo->beginTransaction();
        }
      }

      // commit final para el remanente (< 1000)
      if ($pdo->inTransaction()) {
        $pdo->commit();
      }

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e; // que lo capture tu try/catch externo
    }

    $pdo->commit();
    $totalUpserts += $batchUpserts;

    error_log("[sync_full_productos] Proveedor={$prov} upserts={$batchUpserts}");
  }

  syncLogFinish($pdo, $logId, 'ok', $totalUpserts, 0, null);
  echo "[OK] Full snapshot productos finalizado. Upserts={$totalUpserts}\n";

} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  error_log("[sync_full_productos][ERROR] " . $e->getMessage());
  syncLogFinish($pdo, $logId, 'error', $totalUpserts, 0, $e->getMessage());
  // Salida no cero si lo quieres capturar en cron
  exit(1);
}
