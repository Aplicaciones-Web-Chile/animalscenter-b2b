<?php
require_once dirname(__DIR__) . '/includes/productos_repository.php';
require_once dirname(__DIR__) . '/includes/api_client.php';

/**
 * Devuelve productos (multi-proveedor) para la fecha solicitada:
 * - Si existe snapshot <= fecha_fin → lee histórico del snapshot
 * - Si NO existe → fallback a API, una llamada por proveedor, y une resultados
 *
 * NOTA: aplica filtros/paginación de forma consistente (en SQL para cache; en memoria para fallback).
 */
function obtenerProductosParaFechaMulti(
  string $distribuidor,
  string $fechaInicioDMY,
  string $fechaFinDMY,
  array $proveedores,
  string $busqueda = '',
  int $page = 1,
  int $pageSize = 50,
  string $estrategia = 'exact_or_api' // 'exact_or_api' | 'nearest_past'
): array {
  $ff = DateTime::createFromFormat('d/m/Y', $fechaFinDMY) ?: new DateTime('today');
  $ffY = $ff->format('Y-m-d');

  $snap = obtenerSnapshotParaFecha($ffY); // MAX(snapshot_date) ≤ ffY

  $hoyY = (new DateTime('today'))->format('Y-m-d');

  $usarCache = false;
  if ($estrategia === 'nearest_past') {
    // comportamiento anterior: siempre usa el snapshot más cercano <= ffY
    $usarCache = ($snap !== null);
  } else { // 'exact_or_api'
    if ($snap !== null) {
      if ($snap === $ffY) {
        // hay snapshot exacto para la fecha solicitada → usar caché
        $usarCache = true;
      } else {
        // snapshot es anterior a la fecha solicitada
        if ($ffY < $hoyY) {
          // el usuario pidió una fecha histórica (pasado) → usar el snapshot anterior
          $usarCache = true;
        } else {
          // el usuario pidió hoy o futuro y no hay snapshot exacto → ir a API
          $usarCache = false;
        }
      }
    } else {
      // no hay snapshots ≤ fecha solicitada → si es hoy/futuro, API; si es pasado sin datos, API también
      $usarCache = false;
    }
  }

  if ($usarCache) {
    $res = obtenerProductosDesdeCacheFechaMulti($proveedores, $snap, $busqueda, $page, $pageSize);
    return [
      'from' => 'cache_hist',
      'snapshot' => $snap,
      'total' => $res['total'],
      'items' => $res['items'],
    ];
  }

  // ===== Fallback a API (para hoy/futuro o si no hay snapshot histórico utilizable) =====
  $rows = [];
  foreach ($proveedores as $prov) {
    $resp = obtenerProductosAPI($distribuidor, $fechaInicioDMY, $fechaFinDMY, $prov);
    if (!isset($resp['estado']) || (int) $resp['estado'] !== 1)
      continue;

    $datos = is_array($resp['datos'] ?? null) ? $resp['datos'] : [];
    foreach ($datos as $p) {
      $rows[] = [
        'proveedor' => (string) $prov,
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
        'unidad_de_medida' => $p['UNIDAD_DE_MEDIDA'] ?? null,
        'updated_at_api' => null,
        'snapshot_date' => null
      ];
    }
  }

  // búsqueda + orden + paginación (igual que antes) …
  if ($busqueda !== '') {
    $q = mb_strtolower($busqueda);
    $rows = array_values(array_filter($rows, function ($r) use ($q) {
      return str_contains(mb_strtolower((string) $r['producto_descripcion']), $q)
        || str_contains(mb_strtolower((string) ($r['codigo_de_barra'] ?? '')), $q)
        || str_contains((string) $r['producto_codigo'], $q);
    }));
  }
  usort($rows, function ($a, $b) {
    $cmp = strcmp($a['producto_descripcion'], $b['producto_descripcion']);
    return $cmp !== 0 ? $cmp : strcmp($a['proveedor'], $b['proveedor']);
  });
  $total = count($rows);
  $offset = max(0, ($page - 1) * $pageSize);
  $items = array_slice($rows, $offset, $pageSize);

  return [
    'from' => 'api_fallback',
    'snapshot' => null,
    'total' => $total,
    'items' => $items,
  ];
}

