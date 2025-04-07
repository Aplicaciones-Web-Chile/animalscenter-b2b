<?php
/**
 * Controlador para la generación y descarga de archivos Excel
 */

// Iniciar buffer de salida para evitar problemas con headers
ob_start();

// Evitar salida de texto antes de headers
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Incluir archivos necesarios
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Iniciar sesión
startSession();

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
$fechaInicio = $_GET['fecha_inicio'] ?? date('d/m/Y');
$fechaFin = $_GET['fecha_fin'] ?? date('d/m/Y');
$productoId = $_GET['producto_id'] ?? '';
$estado = $_GET['estado'] ?? '';
$codigoProveedor = $_GET['proveedor'] ?? '78843490'; // Código del proveedor por defecto
$distribuidor = $_GET['distribuidor'] ?? '001'; // Código del distribuidor por defecto

// Nombre de archivo por defecto
$filename = 'Exportacion_' . ucfirst($tipo) . '_' . date('Y-m-d-His') . '.xlsx';

// Crear una nueva instancia de Spreadsheet
$spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Configurar el título y metadatos del documento
$spreadsheet->getProperties()
    ->setCreator("TIENDA DE MASCOTAS ANIMALS CENTER LTDA")
    ->setLastModifiedBy("Sistema B2B")
    ->setTitle("Informe B2B")
    ->setSubject("Informe B2B de Productos")
    ->setDescription("Archivo exportado desde el sistema B2B")
    ->setKeywords("excel b2b informe productos stock")
    ->setCategory("Reportes B2B");

// Configurar el nombre de la hoja
$sheet->setTitle(ucfirst($tipo));

// Establecer diseño de página para impresión
$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
$sheet->getPageSetup()->setFitToPage(true);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);

// Estos encabezados serán configurados dentro de cada función específica de exportación
// para adaptar el formato según el tipo de exportación

// Estos estilos serán configurados dentro de cada función específica de exportación
// para adaptar el formato según el tipo de exportación

// Preparar la consulta según el tipo de exportación
$filtroProveedor = "";
$params = [];

// Verificar si es un proveedor logueado usando la estructura correcta de sesión
if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'proveedor' && isset($_SESSION['user_rut'])) {
    $proveedorRut = $_SESSION['user_rut'];
} else {
    // Si no hay usuario en sesión o no es un proveedor, usar el código de proveedor de los parámetros GET
    $proveedorRut = $codigoProveedor;
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
foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Crear el archivo Excel en el directorio temporal
$writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

// Asegurar que exista el directorio de reportes
$reportDir = __DIR__ . '/../storage/exports/';
if (!file_exists($reportDir)) {
    mkdir($reportDir, 0755, true);
}

// Guardarlo en el directorio de reportes para tener un historial
$savedFile = $reportDir . $filename;
$writer->save($savedFile);

// Crear un archivo temporal para la descarga
$temp_file = tempnam(sys_get_temp_dir(), 'excel');
$writer->save($temp_file);

// Verificar si hay errores hasta este punto
if (error_get_last()) {
    // Si hay errores, mostrar mensaje de error y redirigir
    $_SESSION['error_message'] = 'Ha ocurrido un error al generar el archivo Excel. Por favor intente nuevamente.';
    header("Location: productos.php");
    exit;
}

// Asegurarse de que no se ha enviado ninguna salida antes
ob_clean();

// Enviar el archivo al navegador para su descarga
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Enviar el archivo y limpiar recursos
try {
    $file = fopen($temp_file, 'rb');
    fpassthru($file);
    fclose($file);
    unlink($temp_file);
    exit;
} catch (Exception $e) {
    error_log("Error al enviar el archivo Excel: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error al descargar el archivo. Por favor intente nuevamente.';
    header("Location: productos.php");
    exit;
}

/**
 * Función para exportar productos a Excel
 */
function exportarProductos($sheet, $row_idx, $proveedorRut) {
    global $fechaInicio, $fechaFin, $codigoProveedor, $distribuidor;
    
    // URL y configuración de la API (igual que en productos.php)
    $url = "https://api2.aplicacionesweb.cl/apiacenter/productos/vtayrepxsuc";
    $token = "94ec33d0d75949c298f47adaa78928c2";
    
    // Datos a enviar a la API
    $data = [
        "Distribuidor" => $distribuidor,
        "FINI" => $fechaInicio,
        "FTER" => $fechaFin,
        "KPRV" => $codigoProveedor
    ];
    
    // Configuración de la petición
    $options = [
        'http' => [
            'header' => "Authorization: $token\r\n" .
                       "Content-Type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($data)
        ]
    ];
    
    // Crear contexto y realizar petición
    $context = stream_context_create($options);
    
    try {
        // Realizar la petición
        $result = file_get_contents($url, false, $context);
        
        if ($result === false) {
            return;
        }
        
        // Decodificar respuesta
        $response = json_decode($result, true);
        
        // Verificar estructura de respuesta
        if (json_last_error() !== JSON_ERROR_NONE || !isset($response['estado']) || $response['estado'] !== 1) {
            return;
        }
        
        $productos = $response['datos'] ?? [];
        
        // Configurar el título principal del informe
        $sheet->setCellValue('A1', 'TIENDA DE MASCOTAS ANIMALS CENTER LTDA');
        $sheet->mergeCells('A1:V1');
        
        // Configurar subtítulo del informe
        $sheet->setCellValue('A2', 'Informe B2B');
        $sheet->mergeCells('A2:V2');
        
        // Establecer fechas del informe
        $sheet->setCellValue('A3', 'Desde el ' . $fechaInicio . ' hasta el ' . $fechaFin);
        $sheet->mergeCells('A3:V3');
        
        // Aplicar estilos al encabezado
        $headerStyle = [
            'font' => [
                'bold' => true,
                'size' => 14,
            ],
            'alignment' => [
                'horizontal' => PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            'fill' => [
                'fillType' => PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => 'FFFF00',
                ],
            ],
        ];
        
        $sheet->getStyle('A1:V3')->applyFromArray($headerStyle);
        
        // Saltar una fila para iniciar el contenido
        $row_idx = 5;
        
        // Texto "ORDENADO POR CÓDIGO"
        $sheet->setCellValue('A' . $row_idx, 'ORDENADO POR CÓDIGO');
        $sheet->mergeCells('A' . $row_idx . ':V' . $row_idx);
        $sheet->getStyle('A' . $row_idx)->getFont()->setBold(true);
        
        // Establecer encabezados de columna - exactamente como en la imagen
        $row_idx++;
        $sheet->setCellValue('A' . $row_idx, 'CÓDIGO');
        $sheet->setCellValue('B' . $row_idx, 'COD BARRA');
        $sheet->setCellValue('C' . $row_idx, 'DESCRIPCIÓN');
        $sheet->setCellValue('D' . $row_idx, 'MARCA');
        $sheet->setCellValue('E' . $row_idx, 'CATEGORÍA');
        $sheet->setCellValue('F' . $row_idx, 'Sub categoría');
        $sheet->setCellValue('G' . $row_idx, 'UN Compra');
        $sheet->setCellValue('H' . $row_idx, 'S1 COPIAPO');
        $sheet->setCellValue('I' . $row_idx, 'STOCK');
        $sheet->setCellValue('J' . $row_idx, 'S2 LA SERENA');
        $sheet->setCellValue('K' . $row_idx, 'STOCK');
        $sheet->setCellValue('L' . $row_idx, 'S3 ANTOFAGASTA');
        $sheet->setCellValue('M' . $row_idx, 'STOCK');
        $sheet->setCellValue('N' . $row_idx, 'S4 ÑUÑOA');
        $sheet->setCellValue('O' . $row_idx, 'STOCK');
        $sheet->setCellValue('P' . $row_idx, 'S5 PROVIDENCIA');
        $sheet->setCellValue('Q' . $row_idx, 'STOCK');
        $sheet->setCellValue('R' . $row_idx, 'Stock Valorizado');
        $sheet->setCellValue('S' . $row_idx, 'Suma Ventas (U)');
        $sheet->setCellValue('T' . $row_idx, 'Suma Sto (U)');
        $sheet->setCellValue('U' . $row_idx, 'Rot.');
        $sheet->setCellValue('V' . $row_idx, 'B/P');
        
        // Estilo para encabezados de columna
        $columnHeaderStyle = [
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9D9D9'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];
        
        // Aplicar colores específicos como en la imagen
        $sheet->getStyle('R' . $row_idx)->getFill()->getStartColor()->setRGB('FFFF00'); // Amarillo
        $sheet->getStyle('S' . $row_idx)->getFill()->getStartColor()->setRGB('FFFF00'); // Amarillo
        $sheet->getStyle('T' . $row_idx)->getFill()->getStartColor()->setRGB('FFFF00'); // Amarillo
        $sheet->getStyle('U' . $row_idx)->getFill()->getStartColor()->setRGB('FFFF00'); // Amarillo
        
        $sheet->getStyle('A' . $row_idx . ':V' . $row_idx)->applyFromArray($columnHeaderStyle);
        
        // Iniciar las filas de datos
        $row_idx++;
        $startDataRow = $row_idx;
        
        // Llenar datos
        foreach ($productos as $producto) {
            $sheet->setCellValue('A' . $row_idx, $producto['PRODUCTO_CODIGO']);
            $sheet->setCellValue('B' . $row_idx, $producto['PRODUCTO_BARCODE'] ?? '');
            $sheet->setCellValue('C' . $row_idx, $producto['PRODUCTO_DESCRIPCION']);
            $sheet->setCellValue('D' . $row_idx, $producto['MARCA_DESCRIPCION']);
            $sheet->setCellValue('E' . $row_idx, $producto['FAMILIA_DESCRIPCION']); // Categoría
            $sheet->setCellValue('F' . $row_idx, $producto['SUBFAMILIA_DESCRIPCION'] ?? ''); // Subcategoría
            $sheet->setCellValue('G' . $row_idx, $producto['UNIDAD_COMPRA'] ?? '1');
            
            // Sucursales y stock (usar los datos de la API o valores predeterminados si no están disponibles)
            $sheet->setCellValue('H' . $row_idx, 'COPIAPO');
            $sheet->setCellValue('I' . $row_idx, $producto['STOCK_BODEGA01'] ?? '0');
            $sheet->setCellValue('J' . $row_idx, 'LA SERENA');
            $sheet->setCellValue('K' . $row_idx, $producto['STOCK_BODEGA02'] ?? '0');
            $sheet->setCellValue('L' . $row_idx, 'ANTOFAGASTA');
            $sheet->setCellValue('M' . $row_idx, $producto['STOCK_BODEGA03'] ?? '0');
            $sheet->setCellValue('N' . $row_idx, 'ÑUÑOA');
            $sheet->setCellValue('O' . $row_idx, $producto['STOCK_BODEGA04'] ?? '0');
            $sheet->setCellValue('P' . $row_idx, 'PROVIDENCIA');
            $sheet->setCellValue('Q' . $row_idx, $producto['STOCK_BODEGA05'] ?? '0');
            
            // Valor unitario y datos de ventas
            $valorUnitario = $producto['PRECIO_VENTA'] ?? '0';
            $ventaSuc1 = $producto['VENTA_SUCURSAL01'] ?? 0;
            $ventaSuc2 = $producto['VENTA_SUCURSAL02'] ?? 0;
            $ventaSuc3 = $producto['VENTA_SUCURSAL03'] ?? 0;
            $ventaSuc4 = $producto['VENTA_SUCURSAL04'] ?? 0;
            $ventaSuc5 = $producto['VENTA_SUCURSAL05'] ?? 0;
            
            // Fórmula R: Stock valorizado (suma de stocks * unidad de compra)
            // Calculamos directamente el valor en lugar de usar una fórmula para evitar problemas de formato
            $stockTotal = ($producto['STOCK_BODEGA01'] ?? 0) + ($producto['STOCK_BODEGA02'] ?? 0) + 
                        ($producto['STOCK_BODEGA03'] ?? 0) + ($producto['STOCK_BODEGA04'] ?? 0) + 
                        ($producto['STOCK_BODEGA05'] ?? 0);
            $unidadCompra = $producto['UNIDAD_COMPRA'] ?? 1;
            $stockValorizado = $stockTotal * $unidadCompra;
            $sheet->setCellValue('R' . $row_idx, $stockValorizado);
            
            // Columna S: Suma de ventas por sucursal
            // Asignar directamente el valor numérico para evitar problemas con las fórmulas
            $sumaVentas = $ventaSuc1 + $ventaSuc2 + $ventaSuc3 + $ventaSuc4 + $ventaSuc5;
            $sheet->setCellValue('S' . $row_idx, $sumaVentas);
            
            // Columna T: Suma Stock (total de stock en todas las sucursales)
            $sumaStock = ($producto['STOCK_BODEGA01'] ?? 0) + ($producto['STOCK_BODEGA02'] ?? 0) + 
                        ($producto['STOCK_BODEGA03'] ?? 0) + ($producto['STOCK_BODEGA04'] ?? 0) + 
                        ($producto['STOCK_BODEGA05'] ?? 0);
            $sheet->setCellValue('T' . $row_idx, $sumaStock);
            
            // Columna U: Rotación (ventas / stock, evitando división por cero)
            if ($sumaVentas > 0 && $sumaStock > 0) {
                $rotacion = $sumaStock / $sumaVentas;
                $sheet->setCellValue('U' . $row_idx, $rotacion);
            } else {
                $sheet->setCellValue('U' . $row_idx, 0);
            }
            
            // Marcar si es B/P (Buena Percha) - columna V
            // Consideramos B/P si el producto tiene rotación > 1 o tiene stock y ventas positivas
            $esBP = false;
            if (($sumaVentas > 0 && $sumaStock > 0) || (isset($rotacion) && $rotacion > 1)) {
                $esBP = true;
            }
            $sheet->setCellValue('V' . $row_idx, $esBP ? 'SI' : 'NO');
            
            $row_idx++;
        }
        
        // Dar formato a todas las celdas de datos
        $lastDataRow = $row_idx - 1;
        if ($lastDataRow >= $startDataRow) {
            // Aplicar bordes a los datos
            $sheet->getStyle('A' . $startDataRow . ':V' . $lastDataRow)->getBorders()->getAllBorders()->setBorderStyle(
                PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
            
            // Formato numérico para las columnas de stock
            $columnRanges = ['I:I', 'K:K', 'M:M', 'O:O', 'Q:Q', 'S:S', 'T:T'];
            foreach ($columnRanges as $range) {
                $sheet->getStyle($range . $startDataRow . ':' . $range . $lastDataRow)
                    ->getNumberFormat()->setFormatCode('#,##0');
            }
            
            // Formato moneda para valores
            $sheet->getStyle('R' . $startDataRow . ':R' . $lastDataRow)
                ->getNumberFormat()->setFormatCode('_($* #,##0.00_);_($* (#,##0.00);_($* "-"??_);_(@_)');
            
            // Formato decimal para rotación
            $sheet->getStyle('U' . $startDataRow . ':U' . $lastDataRow)
                ->getNumberFormat()->setFormatCode('0.00');
        }
        
        // Ajustar anchos de columnas automáticamente
        foreach (range('A', 'V') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Congelar paneles para facilitar navegación
        $sheet->freezePane('A8');
        
        // Establecer configuración de impresión
        $sheet->getPageSetup()->setOrientation(PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        
        // Establecer encabezado y pie de página para impresión
        $sheet->getHeaderFooter()->setOddHeader('&C&BInforme B2B - ' . date('d/m/Y'));
        $sheet->getHeaderFooter()->setOddFooter('&L&B' . $sheet->getTitle() . '&R&PTienda de Mascotas Animals Center');
        
    } catch (Exception $e) {
        // Si hay un error, dejamos que el proceso continúe pero registramos el error
        error_log("Error en exportación a Excel: " . $e->getMessage());
    }
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
        ->setFormatCode('$#,##0');
}
