<?php
// includes/productos_repository.php
require_once dirname(__DIR__) . '/config/database.php';

/**
 * Devuelve la fecha de snapshot más reciente <= $fechaObjetivo (Y-m-d)
 */
function obtenerSnapshotParaFecha(string $fechaObjetivoYmd): ?string
{
  $pdo = getDbConnection();
  $stmt = $pdo->prepare("SELECT MAX(snapshot_date) FROM api_cache_productos_hist WHERE snapshot_date <= :f");
  $stmt->execute([':f' => $fechaObjetivoYmd]);
  $snap = $stmt->fetchColumn();
  return $snap ?: null;
}

/**
 * Lee productos desde histórico para una fecha de snapshot concreta.
 */
function obtenerProductosDesdeCacheFecha(
  string $proveedor,
  string $snapshotDateYmd,
  string $busqueda = '',
  int $page = 1,
  int $pageSize = 50
): array {
  $pdo = getDbConnection();

  $where = ["h.proveedor = :prov", "h.snapshot_date = :snap"];
  $params = [':prov' => $proveedor, ':snap' => $snapshotDateYmd];

  if ($busqueda !== '') {
    $where[] = "(h.producto_descripcion LIKE :q OR h.codigo_de_barra LIKE :q OR h.producto_codigo LIKE :qnum)";
    $params[':q'] = '%' . $busqueda . '%';
    $params[':qnum'] = '%' . $busqueda . '%';
  }

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
            WHERE " . implode(' AND ', $where) . "
            ORDER BY h.producto_descripcion ASC
            LIMIT :off, :lim";

  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':off', max(0, ($page - 1) * $pageSize), PDO::PARAM_INT);
  $stmt->bindValue(':lim', $pageSize, PDO::PARAM_INT);
  foreach ($params as $k => $v)
    $stmt->bindValue($k, $v);
  $stmt->execute();

  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
