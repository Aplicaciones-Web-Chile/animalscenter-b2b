<?php
/**
 * Controlador para la generación y descarga de archivos Excel
 */

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/excel_export.php';

// Verificar autenticación
requireLogin();

// Título de la página
$pageTitle = 'Exportar a Excel';

// Determinar el tipo de exportación
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

// Validar el tipo de exportación solicitado
if (!in_array($tipo, ['productos', 'ventas', 'facturas'])) {
    // Si no hay tipo o no es válido, mostrar página de selección
    include 'header.php';
    ?>
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h1 class="mb-0"><i class="fas fa-file-export me-2"></i>Exportación de Datos</h1>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Seleccione el tipo de datos a exportar</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-boxes fa-3x mb-3 text-primary"></i>
                                        <h5 class="card-title">Productos</h5>
                                        <p class="card-text">Exportar listado de productos con stock y precios.</p>
                                        <a href="exportar.php?tipo=productos" class="btn btn-primary">
                                            <i class="fas fa-file-excel me-2"></i>Exportar Productos
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-shopping-cart fa-3x mb-3 text-success"></i>
                                        <h5 class="card-title">Ventas</h5>
                                        <p class="card-text">Exportar historial de ventas con detalles por fecha.</p>
                                        <a href="exportar.php?tipo=ventas" class="btn btn-success">
                                            <i class="fas fa-file-excel me-2"></i>Exportar Ventas
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-file-invoice-dollar fa-3x mb-3 text-info"></i>
                                        <h5 class="card-title">Facturas</h5>
                                        <p class="card-text">Exportar listado de facturas con estado y montos.</p>
                                        <a href="exportar.php?tipo=facturas" class="btn btn-info">
                                            <i class="fas fa-file-excel me-2"></i>Exportar Facturas
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    include 'footer.php';
    exit;
}

// Obtener fechas para filtrar (si aplica)
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');
$productoId = $_GET['producto_id'] ?? '';
$estado = $_GET['estado'] ?? '';

// Nombre de archivo por defecto
$filename = 'Exportacion_' . ucfirst($tipo) . '_' . date('Y-m-d') . '.xlsx';

// Crear una nueva instancia de Spreadsheet
$spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Configurar el título y metadatos del documento
$spreadsheet->getProperties()
    ->setCreator("TIENDA DE MASCOTAS ANIMALS CENTER LTDA")
    ->setLastModifiedBy("Sistema B2B")
    ->setTitle("Exportación de " . ucfirst($tipo))
    ->setSubject("Listado de " . ucfirst($tipo))
    ->setDescription("Archivo exportado desde el sistema B2B")
    ->setKeywords("excel b2b " . $tipo)
    ->setCategory("Reportes B2B");

// Configurar el nombre de la hoja
$sheet->setTitle(ucfirst($tipo));

// Establecer el encabezado con nombre de la empresa
$sheet->setCellValue('A1', 'TIENDA DE MASCOTAS ANIMALS CENTER LTDA');
$sheet->mergeCells('A1:G1');

// Establecer el título del informe
$sheet->setCellValue('A2', 'Listado de ' . ucfirst($tipo));
$sheet->mergeCells('A2:G2');

// Establecer el período del informe si aplica
if ($tipo === 'ventas' || $tipo === 'facturas') {
    $sheet->setCellValue('A3', 'Período: ' . date('d/m/Y', strtotime($fechaInicio)) . ' - ' . date('d/m/Y', strtotime($fechaFin)));
    $sheet->mergeCells('A3:G3');
}

// Establecer estilos para el encabezado
$headerStyle = [
    'font' => [
        'bold' => true,
        'size' => 14,
    ],
    'alignment' => [
        'horizontal' => PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
    ],
    'fill' => [
        'fillType' => PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => [
            'rgb' => 'C5D9F1',
        ],
    ],
];

$sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
$sheet->getStyle('A2:G2')->applyFromArray($headerStyle);

if ($tipo === 'ventas' || $tipo === 'facturas') {
    $sheet->getStyle('A3:G3')->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 12,
        ],
        'alignment' => [
            'horizontal' => PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        ],
    ]);
}

// Preparar la consulta según el tipo de exportación
$filtroProveedor = "";
$params = [];

if ($_SESSION['user']['rol'] === 'proveedor') {
    $proveedorRut = $_SESSION['user']['rut'];
} else {
    $proveedorRut = null;
}

$row_idx = 5;

// Exportar según el tipo seleccionado
switch ($tipo) {
    case 'productos':
        exportarProductos($sheet, $row_idx, $proveedorRut);
        break;
    case 'ventas':
        exportarVentas($sheet, $row_idx, $proveedorRut, $fechaInicio, $fechaFin, $productoId);
        break;
    case 'facturas':
        exportarFacturas($sheet, $row_idx, $proveedorRut, $fechaInicio, $fechaFin, $estado);
        break;
}

// Ajustar anchos de columnas automáticamente
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Crear el archivo Excel en el directorio temporal
$writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$temp_file = tempnam(sys_get_temp_dir(), 'excel');
$writer->save($temp_file);

// Enviar el archivo al navegador para su descarga
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$file = fopen($temp_file, 'rb');
fpassthru($file);
fclose($file);
unlink($temp_file);
exit;

/**
 * Función para exportar productos a Excel
 */
function exportarProductos($sheet, $row_idx, $proveedorRut) {
    // Establecer encabezados de columna
    $sheet->setCellValue('A' . $row_idx, 'SKU');
    $sheet->setCellValue('B' . $row_idx, 'Nombre');
    $sheet->setCellValue('C' . $row_idx, 'Stock');
    $sheet->setCellValue('D' . $row_idx, 'Precio');
    $sheet->setCellValue('E' . $row_idx, 'Valor Total');
    
    // Estilo para encabezados
    $sheet->getStyle('A' . $row_idx . ':E' . $row_idx)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'D9D9D9'],
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN],
        ],
    ]);
    
    $row_idx++;
    
    // Preparar consulta
    $sql = "SELECT p.*, u.nombre as proveedor 
            FROM productos p
            LEFT JOIN usuarios u ON p.proveedor_rut = u.rut";
    
    $params = [];
    
    if ($proveedorRut) {
        $sql .= " WHERE p.proveedor_rut = ?";
        $params[] = $proveedorRut;
    }
    
    $sql .= " ORDER BY p.nombre ASC";
    
    // Obtener datos
    $productos = fetchAll($sql, $params);
    
    // Llenar datos
    foreach ($productos as $producto) {
        $sheet->setCellValue('A' . $row_idx, $producto['sku']);
        $sheet->setCellValue('B' . $row_idx, $producto['nombre']);
        $sheet->setCellValue('C' . $row_idx, $producto['stock']);
        $sheet->setCellValue('D' . $row_idx, $producto['precio']);
        $sheet->setCellValue('E' . $row_idx, $producto['stock'] * $producto['precio']);
        
        $row_idx++;
    }
    
    // Dar formato a las celdas
    $sheet->getStyle('C' . ($row_idx - count($productos)) . ':C' . ($row_idx - 1))->getNumberFormat()
        ->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
    
    $sheet->getStyle('D' . ($row_idx - count($productos)) . ':E' . ($row_idx - 1))->getNumberFormat()
        ->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_CLP_SIMPLE);
}

/**
 * Función para exportar ventas a Excel
 */
function exportarVentas($sheet, $row_idx, $proveedorRut, $fechaInicio, $fechaFin, $productoId) {
    // Establecer encabezados de columna
    $sheet->setCellValue('A' . $row_idx, 'ID');
    $sheet->setCellValue('B' . $row_idx, 'Fecha');
    $sheet->setCellValue('C' . $row_idx, 'Producto');
    $sheet->setCellValue('D' . $row_idx, 'SKU');
    $sheet->setCellValue('E' . $row_idx, 'Cantidad');
    $sheet->setCellValue('F' . $row_idx, 'Proveedor');
    
    // Estilo para encabezados
    $sheet->getStyle('A' . $row_idx . ':F' . $row_idx)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'D9D9D9'],
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN],
        ],
    ]);
    
    $row_idx++;
    
    // Preparar consulta
    $condiciones = ["v.fecha BETWEEN ? AND ?"];
    $params = [$fechaInicio . " 00:00:00", $fechaFin . " 23:59:59"];
    
    if (!empty($productoId)) {
        $condiciones[] = "v.producto_id = ?";
        $params[] = $productoId;
    }
    
    if ($proveedorRut) {
        $condiciones[] = "v.proveedor_rut = ?";
        $params[] = $proveedorRut;
    }
    
    $where = "WHERE " . implode(" AND ", $condiciones);
    
    $sql = "SELECT v.*, p.nombre as producto, p.sku, u.nombre as proveedor
            FROM ventas v
            JOIN productos p ON v.producto_id = p.id
            LEFT JOIN usuarios u ON v.proveedor_rut = u.rut
            $where
            ORDER BY v.fecha DESC";
    
    // Obtener datos
    $ventas = fetchAll($sql, $params);
    
    // Llenar datos
    foreach ($ventas as $venta) {
        $sheet->setCellValue('A' . $row_idx, $venta['id']);
        $sheet->setCellValue('B' . $row_idx, date('d/m/Y H:i', strtotime($venta['fecha'])));
        $sheet->setCellValue('C' . $row_idx, $venta['producto']);
        $sheet->setCellValue('D' . $row_idx, $venta['sku']);
        $sheet->setCellValue('E' . $row_idx, $venta['cantidad']);
        $sheet->setCellValue('F' . $row_idx, $venta['proveedor']);
        
        $row_idx++;
    }
    
    // Dar formato a las celdas
    $sheet->getStyle('E' . ($row_idx - count($ventas)) . ':E' . ($row_idx - 1))->getNumberFormat()
        ->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
}

/**
 * Función para exportar facturas a Excel
 */
function exportarFacturas($sheet, $row_idx, $proveedorRut, $fechaInicio, $fechaFin, $estado) {
    // Establecer encabezados de columna
    $sheet->setCellValue('A' . $row_idx, 'ID');
    $sheet->setCellValue('B' . $row_idx, 'Fecha');
    $sheet->setCellValue('C' . $row_idx, 'Producto');
    $sheet->setCellValue('D' . $row_idx, 'Monto');
    $sheet->setCellValue('E' . $row_idx, 'Estado');
    $sheet->setCellValue('F' . $row_idx, 'Proveedor');
    
    // Estilo para encabezados
    $sheet->getStyle('A' . $row_idx . ':F' . $row_idx)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'D9D9D9'],
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN],
        ],
    ]);
    
    $row_idx++;
    
    // Preparar consulta
    $condiciones = ["f.fecha BETWEEN ? AND ?"];
    $params = [$fechaInicio . " 00:00:00", $fechaFin . " 23:59:59"];
    
    if (!empty($estado)) {
        $condiciones[] = "f.estado = ?";
        $params[] = $estado;
    }
    
    if ($proveedorRut) {
        $condiciones[] = "f.proveedor_rut = ?";
        $params[] = $proveedorRut;
    }
    
    $where = "WHERE " . implode(" AND ", $condiciones);
    
    $sql = "SELECT f.*, v.producto_id, p.nombre as producto, p.sku, u.nombre as proveedor
            FROM facturas f
            JOIN ventas v ON f.venta_id = v.id
            JOIN productos p ON v.producto_id = p.id
            LEFT JOIN usuarios u ON f.proveedor_rut = u.rut
            $where
            ORDER BY f.fecha DESC";
    
    // Obtener datos
    $facturas = fetchAll($sql, $params);
    
    // Llenar datos
    foreach ($facturas as $factura) {
        $sheet->setCellValue('A' . $row_idx, $factura['id']);
        $sheet->setCellValue('B' . $row_idx, date('d/m/Y', strtotime($factura['fecha'])));
        $sheet->setCellValue('C' . $row_idx, $factura['producto']);
        $sheet->setCellValue('D' . $row_idx, $factura['monto']);
        $sheet->setCellValue('E' . $row_idx, ucfirst($factura['estado']));
        $sheet->setCellValue('F' . $row_idx, $factura['proveedor']);
        
        $row_idx++;
    }
    
    // Dar formato a las celdas
    $sheet->getStyle('D' . ($row_idx - count($facturas)) . ':D' . ($row_idx - 1))->getNumberFormat()
        ->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_CLP_SIMPLE);
}
