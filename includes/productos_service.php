<?php
require_once dirname(__DIR__) . '/includes/productos_repository.php';
require_once APP_ROOT . '/includes/api_client.php';

/**
 * Devuelve productos desde histórico por fecha_fin (snapshot <= fecha_fin).
 * Si no hay snapshot para esa fecha → fallback a API.
 *
 * @param string $distribuidor   p.ej. '001'
 * @param string $fechaInicioDMY formato 'd/m/Y'
 * @param string $fechaFinDMY    formato 'd/m/Y'
 * @param string $proveedor      KPRV
 */
function obtenerProductosParaFecha(
  string $distribuidor,
  string $fechaInicioDMY,
  string $fechaFinDMY,
  string $proveedor,
  string $busqueda = '',
  int $page = 1,
  int $pageSize = 50
): array {
  // fecha fin normalizada (para elegir snapshot)
  $ff = DateTime::createFromFormat('d/m/Y', $fechaFinDMY) ?: new DateTime('today');
  $ffY = $ff->format('Y-m-d');

  // snapshot más cercano <= fecha_fin
  $snap = obtenerSnapshotParaFecha($ffY);

  if ($snap !== null) {
    // Hay cache histórico para esa fecha
    $items = obtenerProductosDesdeCacheFecha($proveedor, $snap, $busqueda, $page, $pageSize);
    return [
      'from' => 'cache_hist',
      'snapshot' => $snap,
      'items' => $items
    ];
  }

  // ===== Fallback a API (no hay snapshot para la fecha seleccionada) =====
  $resp = obtenerProductosAPI($distribuidor, $fechaInicioDMY, $fechaFinDMY, $proveedor);

  if (!isset($resp['estado']) || (int) $resp['estado'] !== 1) {
    // API falló → sin datos
    return [
      'from' => 'api_fallback_error',
      'snapshot' => null,
      'items' => []
    ];
  }

  $datos = is_array($resp['datos'] ?? null) ? $resp['datos'] : [];

  // Normaliza al mismo shape que el cache_hist
  $rows = [];
  foreach ($datos as $p) {
    $rows[] = [
      'proveedor' => $proveedor,
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
      'updated_at_api' => null,      // sin dato “oficial”
      'snapshot_date' => null       // no guardamos snapshot en fallback
    ];
  }

  // Filtrar por búsqueda (igual que en cache)
  if ($busqueda !== '') {
    $q = mb_strtolower($busqueda);
    $rows = array_values(array_filter($rows, function ($r) use ($q) {
      return str_contains(mb_strtolower((string) $r['producto_descripcion']), $q)
        || str_contains(mb_strtolower((string) $r['codigo_de_barra']), $q)
        || str_contains((string) $r['producto_codigo'], $q);
    }));
  }

  // Orden y paginación en memoria
  usort($rows, fn($a, $b) => strcmp($a['producto_descripcion'], $b['producto_descripcion']));
  $offset = max(0, ($page - 1) * $pageSize);
  $items = array_slice($rows, $offset, $pageSize);

  return [
    'from' => 'api_fallback',
    'snapshot' => null,
    'items' => $items
  ];

}
