<?php
// includes/productos_repository.php
require_once dirname(__DIR__) . '/config/database.php';

/**
 * Devuelve la fecha de snapshot más reciente <= $fechaObjetivo (Y-m-d)
 */
function obtenerSnapshotParaFecha(string $fechaObjetivoYmd): ?string
{
  $pdo = getDbConnection();
  $stmt = $pdo->prepare(
    "SELECT MAX(snapshot_date)
       FROM api_cache_productos_hist
      WHERE snapshot_date <= :f"
  );
  $stmt->execute([':f' => $fechaObjetivoYmd]);
  $snap = $stmt->fetchColumn();
  return $snap ?: null;
}

/**
 * Histórico multi-proveedor para una fecha de snapshot concreta.
 * Retorna ['items'=>[], 'total'=>int]
 */
function obtenerProductosDesdeCacheFechaMulti(
  array $proveedores,           // lista de KPRV
  string $snapshotDateYmd,      // 'Y-m-d'
  string $busqueda = '',
  int $page = 1,
  int $pageSize = 50
): array {
  if (empty($proveedores))
    return ['items' => [], 'total' => 0];

  $pdo = getDbConnection();

  // placeholders para IN (...)
  $inPlaceholders = [];
  $params = [':snap' => $snapshotDateYmd];
  foreach ($proveedores as $i => $kprv) {
    $ph = ":prov{$i}";
    $inPlaceholders[] = $ph;
    $params[$ph] = (string) $kprv;
  }

  $where = [];
  $where[] = "h.snapshot_date = :snap";
  $where[] = "h.proveedor IN (" . implode(',', $inPlaceholders) . ")";

  if ($busqueda !== '') {
    // NOTA: si pasas a FULLTEXT, ajusta esta parte
    $where[] = "(h.producto_descripcion LIKE :q
                 OR h.codigo_de_barra LIKE :q
                 OR CAST(h.producto_codigo AS CHAR) LIKE :q)";
    $params[':q'] = '%' . $busqueda . '%';
  }

  $whereSql = implode(' AND ', $where);

  // total para paginación
  $sqlCount = "SELECT COUNT(*) FROM api_cache_productos_hist h WHERE $whereSql";
  $stmtC = $pdo->prepare($sqlCount);
  foreach ($params as $k => $v)
    $stmtC->bindValue($k, $v);
  $stmtC->execute();
  $total = (int) $stmtC->fetchColumn();

  // página
  $sql = "SELECT
            h.proveedor, h.producto_codigo, h.codigo_de_barra,
            h.producto_descripcion, h.marca_descripcion, h.familia_descripcion, h.subfamilia_descripcion,
            h.venta_sucursal01, h.stock_bodega01,
            h.venta_sucursal02, h.stock_bodega02,
            h.venta_sucursal03, h.stock_bodega03,
            h.venta_sucursal04, h.stock_bodega04,
            h.venta_sucursal05, h.stock_bodega05,
            h.venta_distribucion, h.venta_sucursal07,
            h.kg, h.precio_ultima_compra, h.unidad_de_medida,
            h.updated_at_api, h.snapshot_date
          FROM api_cache_productos_hist h
          WHERE $whereSql
          ORDER BY h.producto_descripcion ASC, h.proveedor ASC
          LIMIT :off, :lim";

  $stmt = $pdo->prepare($sql);
  foreach ($params as $k => $v)
    $stmt->bindValue($k, $v);
  $stmt->bindValue(':off', max(0, ($page - 1) * $pageSize), PDO::PARAM_INT);
  $stmt->bindValue(':lim', $pageSize, PDO::PARAM_INT);
  $stmt->execute();

  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

  return ['items' => $items, 'total' => $total];
}