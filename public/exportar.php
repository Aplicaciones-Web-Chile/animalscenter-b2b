<?php
/**
 * Controlador para la generación y descarga de archivos Excel
 *
 * Incluye funciones para exportar detalles de:
 * - Venta neta
 * - Unidades vendidas
 * - SKU activos
 * - Stock total valorizado
 */

/**
 * Función para exportar detalle de stock total valorizado a Excel
 */
function exportarDetalleStockValorizado($sheet, $row_idx, $proveedorRut, $fechaInicio, $fechaFin)
{
    // Establecer encabezados de columna
    $sheet->setCellValue('A' . $row_idx, 'Código');
    $sheet->setCellValue('B' . $row_idx, 'Descripción');
    $sheet->setCellValue('C' . $row_idx, 'Cód. Marca');
    $sheet->setCellValue('D' . $row_idx, 'Cód. Barras');
    $sheet->setCellValue('E' . $row_idx, 'Unidad');
    $sheet->setCellValue('F' . $row_idx, 'Marca');
    $sheet->setCellValue('G' . $row_idx, 'Categoría');
    $sheet->setCellValue('H' . $row_idx, 'Subcategoría');
    $sheet->setCellValue('I' . $row_idx, 'Cant. Envase');
    $sheet->setCellValue('J' . $row_idx, 'Precio Últ. Compra');
    $sheet->setCellValue('K' . $row_idx, 'Stock Suc.1');
    $sheet->setCellValue('L' . $row_idx, 'Stock Suc.2');
    $sheet->setCellValue('M' . $row_idx, 'Stock Suc.3');
    $sheet->setCellValue('N' . $row_idx, 'Stock Suc.4');
    $sheet->setCellValue('O' . $row_idx, 'Stock Suc.5');
    $sheet->setCellValue('P' . $row_idx, 'Stock Web');
    $sheet->setCellValue('Q' . $row_idx, 'Valor Total');

    // Estilo para encabezados
    $sheet->getStyle('A' . $row_idx . ':Q' . $row_idx)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '28A745'], // Color verde para coincidir con el modal
        ],
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'], // Texto blanco para mejor contraste
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN],
        ],
    ]);

    $row_idx++;

    // Incluir el archivo con la función getDetalleTotalStockFromAPI
    require_once __DIR__ . '/../includes/api_client.php';

    // Obtener datos usando la misma función que en productos.php
    $detalleStockTotalValor = getDetalleTotalStockFromAPI($fechaInicio, $fechaFin, $proveedorRut);

    // Llenar datos
    foreach ($detalleStockTotalValor as $item) {
        // Formatear códigos de producto como texto
        $sheet->setCellValueExplicit('A' . $row_idx, $item['KINS'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('B' . $row_idx, $item['DINS']);
        $sheet->setCellValueExplicit('C' . $row_idx, $item['KMAR'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('D' . $row_idx, $item['BCOD'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('E' . $row_idx, $item['UINS']);
        $sheet->setCellValue('F' . $row_idx, $item['DMAR']);
        $sheet->setCellValue('G' . $row_idx, $item['DFAI']);
        $sheet->setCellValue('H' . $row_idx, $item['DSUI']);
        $sheet->setCellValue('I' . $row_idx, $item['CENV']);
        $sheet->setCellValue('J' . $row_idx, $item['PRUL']);
        $sheet->setCellValue('K' . $row_idx, $item['ST01']);
        $sheet->setCellValue('L' . $row_idx, $item['ST02']);
        $sheet->setCellValue('M' . $row_idx, $item['ST03']);
        $sheet->setCellValue('N' . $row_idx, $item['ST04']);
        $sheet->setCellValue('O' . $row_idx, $item['ST05']);
        $sheet->setCellValue('P' . $row_idx, $item['ST07']); // ST07 corresponde a Stock Web
        $sheet->setCellValue('Q' . $row_idx, $item['VALO']);

        $row_idx++;
    }

    // Dar formato a las celdas numéricas
    $lastDataRow = $row_idx - 1;
    $firstDataRow = $row_idx - count($detalleStockTotalValor);

    if (count($detalleStockTotalValor) > 0) {
        // Formato para cantidad envase
        $sheet->getStyle('I' . $firstDataRow . ':I' . $lastDataRow)->getNumberFormat()
            ->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);

        // Formato para precio última compra (moneda en pesos chilenos)
        $sheet->getStyle('J' . $firstDataRow . ':J' . $lastDataRow)->getNumberFormat()
            ->setFormatCode('"$"#,##0;-"$"#,##0');

        // Formato para columnas de stock
        $sheet->getStyle('K' . $firstDataRow . ':P' . $lastDataRow)->getNumberFormat()
            ->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);

        // Formato para valor total (moneda en pesos chilenos)
        $sheet->getStyle('Q' . $firstDataRow . ':Q' . $lastDataRow)->getNumberFormat()
            ->setFormatCode('"$"#,##0;-"$"#,##0');
    }

    // Ajustar ancho de columnas
    $sheet->getColumnDimension('A')->setWidth(12); // Código
    $sheet->getColumnDimension('B')->setWidth(30); // Descripción
    $sheet->getColumnDimension('C')->setWidth(10); // Cód. Marca
    $sheet->getColumnDimension('D')->setWidth(15); // Cód. Barras
    $sheet->getColumnDimension('E')->setWidth(10); // Unidad
    $sheet->getColumnDimension('F')->setWidth(15); // Marca
    $sheet->getColumnDimension('G')->setWidth(15); // Categoría
    $sheet->getColumnDimension('H')->setWidth(15); // Subcategoría
    $sheet->getColumnDimension('I')->setWidth(12); // Cant. Envase
    $sheet->getColumnDimension('J')->setWidth(15); // Precio Últ. Compra

    // Columnas de stock con mismo ancho
    for ($col = 'K'; $col <= 'P'; $col++) {
        $sheet->getColumnDimension($col)->setWidth(12);
    }

    // Valor total
    $sheet->getColumnDimension('Q')->setWidth(15);
}

/**
 * Función para exportar detalle de stock unidades a Excel
 */
function exportarDetalleStockUnidades($sheet, $row_idx, $proveedorRut, $fechaInicio, $fechaFin)
{
    // Establecer encabezados de columna
    $sheet->setCellValue('A' . $row_idx, 'Código');
    $sheet->setCellValue('B' . $row_idx, 'Descripción');
    $sheet->setCellValue('C' . $row_idx, 'Marca');
    $sheet->setCellValue('D' . $row_idx, 'Categoría');
    $sheet->setCellValue('E' . $row_idx, 'Subcategoría');
    $sheet->setCellValue('F' . $row_idx, 'Unidad');
    $sheet->setCellValue('G' . $row_idx, 'Cant. Envase');
    $sheet->setCellValue('H' . $row_idx, 'Stock Suc.1');
    $sheet->setCellValue('I' . $row_idx, 'Stock Suc.2');
    $sheet->setCellValue('J' . $row_idx, 'Stock Suc.3');
    $sheet->setCellValue('K' . $row_idx, 'Stock Suc.4');
    $sheet->setCellValue('L' . $row_idx, 'Stock Suc.5');
    $sheet->setCellValue('M' . $row_idx, 'Stock Web');

    // Estilo para encabezados
    $sheet->getStyle('A' . $row_idx . ':M' . $row_idx)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FFC107'], // Color amarillo para coincidir con el modal
        ],
        'font' => [
            'bold' => true,
            'color' => ['rgb' => '000000'], // Texto negro para mejor contraste con fondo amarillo
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN],
        ],
    ]);

    $row_idx++;

    // Incluir el archivo con la función getDetalleStockUnidadesFromAPI
    require_once __DIR__ . '/../includes/api_client.php';

    // Obtener datos usando la misma función que en productos.php
    $detalleStockUnidades = getDetalleStockUnidadesFromAPI($fechaInicio, $fechaFin, $proveedorRut);

    // Llenar datos
    foreach ($detalleStockUnidades as $item) {
        // Formatear códigos de producto como texto
        $sheet->setCellValueExplicit('A' . $row_idx, $item['KINS'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('B' . $row_idx, $item['DINS']);
        $sheet->setCellValue('C' . $row_idx, $item['DMAR']);
        $sheet->setCellValue('D' . $row_idx, $item['DFAI']);
        $sheet->setCellValue('E' . $row_idx, $item['DSUI']);
        $sheet->setCellValue('F' . $row_idx, $item['UINS']);
        $sheet->setCellValue('G' . $row_idx, $item['CENV']);
        $sheet->setCellValue('H' . $row_idx, $item['ST01']);
        $sheet->setCellValue('I' . $row_idx, $item['ST02']);
        $sheet->setCellValue('J' . $row_idx, $item['ST03']);
        $sheet->setCellValue('K' . $row_idx, $item['ST04']);
        $sheet->setCellValue('L' . $row_idx, $item['ST05']);
        $sheet->setCellValue('M' . $row_idx, $item['ST07']); // ST07 corresponde a Stock Web

        $row_idx++;
    }

    // Dar formato a las celdas numéricas (stock y cantidad envase)
    $lastDataRow = $row_idx - 1;
    $firstDataRow = $row_idx - count($detalleStockUnidades);

    if (count($detalleStockUnidades) > 0) {
        // Formato para cantidad envase
        $sheet->getStyle('G' . $firstDataRow . ':G' . $lastDataRow)->getNumberFormat()
            ->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);

        // Formato para columnas de stock
        $sheet->getStyle('H' . $firstDataRow . ':M' . $lastDataRow)->getNumberFormat()
            ->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
    }

    // Ajustar ancho de columnas
    $sheet->getColumnDimension('A')->setWidth(12); // Código
    $sheet->getColumnDimension('B')->setWidth(30); // Descripción
    $sheet->getColumnDimension('C')->setWidth(15); // Marca
    $sheet->getColumnDimension('D')->setWidth(15); // Categoría
    $sheet->getColumnDimension('E')->setWidth(15); // Subcategoría
    $sheet->getColumnDimension('F')->setWidth(10); // Unidad
    $sheet->getColumnDimension('G')->setWidth(12); // Cant. Envase

    // Columnas de stock con mismo ancho
    for ($col = 'H'; $col <= 'M'; $col++) {
        $sheet->getColumnDimension($col)->setWidth(12);
    }
}

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
if (!in_array($tipo, ['productos', 'ventas', 'facturas', 'detalle_venta_neta', 'detalle_unidades_vendidas', 'detalle_sku_activos', 'detalle_stock_unidades', 'detalle_stock_valorizado'])) {
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
    ->setLastModifiedBy("Sistema B2B - " . date('d/m/Y H:i'))
    ->setTitle("Informe B2B de $tipo")
    ->setSubject("Informe B2B de $tipo - " . date('d/m/Y'))
    ->setDescription("Archivo exportado desde el sistema B2B con datos de $fechaInicio a $fechaFin")
    ->setKeywords("excel b2b informe $tipo stock ventas")
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
    case 'detalle_venta_neta':
        exportarDetalleVentaNeta($sheet, $row_idx, $proveedorRut, $fechaInicio, $fechaFin);
        break;
    case 'detalle_unidades_vendidas':
        exportarDetalleUnidadesVendidas($sheet, $row_idx, $proveedorRut, $fechaInicio, $fechaFin);
        break;
    case 'detalle_sku_activos':
        exportarDetalleSkuActivos($sheet, $row_idx, $proveedorRut);
        break;
    case 'detalle_stock_unidades':
        exportarDetalleStockUnidades($sheet, $row_idx, $proveedorRut, $fechaInicio, $fechaFin);
        break;
    case 'detalle_stock_valorizado':
        exportarDetalleStockValorizado($sheet, $row_idx, $proveedorRut, $fechaInicio, $fechaFin);
        break;
}

// Ajustar anchos de columnas automáticamente - algunas columnas ya se configuraron previamente
foreach (range('H', 'Z') as $col) {
    // Para las columnas de Venta y Stock usamos un ancho fijo para mejor visualización
    if (in_array($col, ['H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'W'])) {
        $sheet->getColumnDimension($col)->setWidth(10); // Ancho fijo para columnas numéricas
    } else {
        $sheet->getColumnDimension($col)->setAutoSize(true); // Autosize para el resto
    }
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
    // Captura el error y guardalo en un archivo de log dentro de la carpeta /public/tmp/
    $logFile = __DIR__ . '/tmp/error_exportar_' . date('Y-m-d_H-i-s') . '.log';
    file_put_contents($logFile, print_r(error_get_last(), true), FILE_APPEND);

    $_SESSION['error_message'] = 'Ha ocurrido un error al generar el archivo Excel. Por favor intente nuevamente.';
    header("Location: productos.php?error=1");
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
    header("Location: productos.php?error=2");
    exit;
}

/**
 * Función para exportar productos a Excel
 */
function exportarProductos($sheet, $row_idx, $proveedorRut)
{
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
        $sheet->mergeCells('A1:Z1');

        // Configurar subtítulo del informe
        $sheet->setCellValue('A2', 'Informe B2B');
        $sheet->mergeCells('A2:Z2');

        // Establecer fechas del informe
        $sheet->setCellValue('A3', 'Desde el ' . $fechaInicio . ' hasta el ' . $fechaFin);
        $sheet->mergeCells('A3:Z3');

        // Aplicar estilos al encabezado
        $headerStyle = [
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => [
                    'rgb' => 'FFFFFF',
                ],
            ],
            'alignment' => [
                'horizontal' => PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            'fill' => [
                'fillType' => PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '4D7B3B',
                    'alpha' => 100,
                    'argb' => 'FF4D7B3B',
                    'indexed' => 11,
                    'color' => [
                        'argb' => 'FF4D7B3B',
                    ],
                ],
            ],
        ];

        $sheet->getStyle('A1:Z3')->applyFromArray($headerStyle);

        // Saltar una fila para iniciar el contenido
        $row_idx = 5;

        // Texto "ORDENADO POR CÓDIGO"
        $sheet->setCellValue('A' . $row_idx, 'ORDENADO POR CÓDIGO');
        $sheet->mergeCells('A' . $row_idx . ':Z' . $row_idx);
        $sheet->getStyle('A' . $row_idx)->getFont()->setBold(true);

        // Crear encabezados de columna con sucursales agrupadas
        $row_idx++;
        $headerRow1 = $row_idx;

        // Primera fila del encabezado - Nombres generales y sucursales
        // Ajustamos el auto-size para estas columnas
        $sheet->getColumnDimension('A')->setAutoSize(true); // Código
        $sheet->getColumnDimension('B')->setAutoSize(true); // Código Barra
        $sheet->getColumnDimension('C')->setWidth(40);      // Descripción - ancho fijo más amplio
        $sheet->getColumnDimension('D')->setAutoSize(true); // Marca
        $sheet->getColumnDimension('E')->setAutoSize(true); // Categoría
        $sheet->getColumnDimension('F')->setAutoSize(true); // Subcategoría
        $sheet->getColumnDimension('G')->setAutoSize(true); // Kilo
        $sheet->getColumnDimension('H')->setAutoSize(true); // Unidad de Medida
        $sheet->getColumnDimension('I')->setAutoSize(true); // Venta Distribución

        $sheet->setCellValue('A' . $headerRow1, '');
        $sheet->setCellValue('B' . $headerRow1, '');
        $sheet->setCellValue('C' . $headerRow1, '');
        $sheet->setCellValue('D' . $headerRow1, '');
        $sheet->setCellValue('E' . $headerRow1, '');
        $sheet->setCellValue('F' . $headerRow1, '');
        $sheet->setCellValue('G' . $headerRow1, '');
        $sheet->setCellValue('H' . $headerRow1, '');

        // Campo de Venta Distribución
        $sheet->setCellValue('I' . $headerRow1, 'DISTRIB');

        // Campo de Venta Web
        $sheet->setCellValue('J' . $headerRow1, 'WEB');

        // Sucursales
        $sheet->setCellValue('K' . $headerRow1, 'VERGARA');
        $sheet->mergeCells('K' . $headerRow1 . ':L' . $headerRow1);

        $sheet->setCellValue('M' . $headerRow1, 'LAMPA');
        $sheet->mergeCells('M' . $headerRow1 . ':N' . $headerRow1);

        $sheet->setCellValue('O' . $headerRow1, 'PANAMERICANA');
        $sheet->mergeCells('O' . $headerRow1 . ':P' . $headerRow1);

        $sheet->setCellValue('Q' . $headerRow1, 'MATTA');
        $sheet->mergeCells('Q' . $headerRow1 . ':R' . $headerRow1);

        $sheet->setCellValue('S' . $headerRow1, 'PROVIDENCIA');
        $sheet->mergeCells('S' . $headerRow1 . ':T' . $headerRow1);

        // Totales
        $sheet->setCellValue('U' . $headerRow1, 'TOTALES');
        $sheet->mergeCells('U' . $headerRow1 . ':Z' . $headerRow1);

        // Segunda fila del encabezado - Detalle de columnas
        $row_idx++;
        $headerRow2 = $row_idx;

        // Datos básicos
        $sheet->setCellValue('A' . $headerRow2, 'Código');
        $sheet->setCellValue('B' . $headerRow2, 'Código Barra');
        $sheet->setCellValue('C' . $headerRow2, 'Descripción');
        $sheet->setCellValue('D' . $headerRow2, 'Marca');
        $sheet->setCellValue('E' . $headerRow2, 'Categoría');
        $sheet->setCellValue('F' . $headerRow2, 'Sub categoría');
        $sheet->setCellValue('G' . $headerRow2, 'Kilo');
        $sheet->setCellValue('H' . $headerRow2, 'Unidad de Medida');
        $sheet->setCellValue('I' . $headerRow2, 'Venta Distribución');
        $sheet->setCellValue('J' . $headerRow2, 'Venta Web');

        // Detalle de sucursales - Venta y Stock
        $sheet->setCellValue('K' . $headerRow2, 'Venta');
        $sheet->setCellValue('L' . $headerRow2, 'Stock');
        $sheet->setCellValue('M' . $headerRow2, 'Venta');
        $sheet->setCellValue('N' . $headerRow2, 'Stock');
        $sheet->setCellValue('O' . $headerRow2, 'Venta');
        $sheet->setCellValue('P' . $headerRow2, 'Stock');
        $sheet->setCellValue('Q' . $headerRow2, 'Venta');
        $sheet->setCellValue('R' . $headerRow2, 'Stock');
        $sheet->setCellValue('S' . $headerRow2, 'Venta');
        $sheet->setCellValue('T' . $headerRow2, 'Stock');

        // Totales - Con comentarios explicativos
        $sheet->setCellValue('U' . $headerRow2, 'Peso Total (KG)');
        $comentarioStockValorizado = $sheet->getComment('U' . $headerRow2);
        $comentarioStockValorizado->getText()->createTextRun('Peso total: Suma de todos los stocks multiplicado por el peso del producto');

        $sheet->setCellValue('V' . $headerRow2, 'Suma Ventas (U)');
        $comentarioSumaVentas = $sheet->getComment('V' . $headerRow2);
        $comentarioSumaVentas->getText()->createTextRun('Total de unidades vendidas en todas las sucursales');

        $sheet->setCellValue('W' . $headerRow2, 'Suma Stock (U)');
        $comentarioSumaStock = $sheet->getComment('W' . $headerRow2);
        $comentarioSumaStock->getText()->createTextRun('Total de unidades en stock en todas las sucursales');

        $sheet->setCellValue('X' . $headerRow2, 'Rotación');
        $comentarioRotacion = $sheet->getComment('X' . $headerRow2);
        $comentarioRotacion->getText()->createTextRun('Velocidad con que se vende el producto: Ventas / Stock');

        $sheet->setCellValue('Y' . $headerRow2, 'Precio último compra');
        $comentarioCostoValorizado = $sheet->getComment('Y' . $headerRow2);
        $comentarioCostoValorizado->getText()->createTextRun('Precio último compra del producto');

        $sheet->setCellValue('Z' . $headerRow2, 'Costo valorizado');
        $comentarioCostoValorizado = $sheet->getComment('Z' . $headerRow2);
        $comentarioCostoValorizado->getText()->createTextRun('Costo valorizado del producto: Precio de compra * Cantidad vendida');

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

        // Estilo para sucursales (primera fila)
        $sucursalHeaderStyle = [
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => [
                'horizontal' => PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'BDD7EE'],  // Azul claro
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];

        // Aplicar estilos a los encabezados
        $sheet->getStyle('A' . $headerRow1 . ':H' . $headerRow2)->applyFromArray($columnHeaderStyle);
        $sheet->getStyle('I' . $headerRow1 . ':S' . $headerRow1)->applyFromArray($sucursalHeaderStyle);
        $sheet->getStyle('I' . $headerRow2 . ':S' . $headerRow2)->applyFromArray($columnHeaderStyle);

        // Aplicar estilos a la sección de totales (T a Z)
        $sheet->getStyle('T' . $headerRow1 . ':Z' . $headerRow1)->applyFromArray($sucursalHeaderStyle);
        $sheet->getStyle('T' . $headerRow2 . ':Z' . $headerRow2)->applyFromArray($columnHeaderStyle);

        // Estilo del encabezado - Colores para sucursales y totales
        $sheet->getStyle('I' . $headerRow1)->getFill()
            ->setFillType(PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DDEBF7'); // Azul muy claro para distribución

        $sheet->getStyle('J' . $headerRow1 . ':K' . $headerRow1)->getFill()
            ->setFillType(PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('B8CCE4'); // Azul claro

        $sheet->getStyle('L' . $headerRow1 . ':M' . $headerRow1)->getFill()
            ->setFillType(PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E6B8B7'); // Rojo claro

        $sheet->getStyle('N' . $headerRow1 . ':O' . $headerRow1)->getFill()
            ->setFillType(PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('C5E0B3'); // Verde claro

        $sheet->getStyle('P' . $headerRow1 . ':Q' . $headerRow1)->getFill()
            ->setFillType(PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFE699'); // Amarillo claro

        $sheet->getStyle('R' . $headerRow1 . ':S' . $headerRow1)->getFill()
            ->setFillType(PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9D9D9'); // Gris claro

        $sheet->getStyle('T' . $headerRow1 . ':Z' . $headerRow1)->getFill()
            ->setFillType(PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F4B084'); // Naranja claro para toda la sección de totales

        // Aplicar fondo gris a las columnas G y H (Kilo y Unidad de Medida)
        $sheet->getStyle('G' . $headerRow2 . ':H' . $headerRow2)->getFill()
            ->setFillType(PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9D9D9'); // Gris claro

        // Iniciar las filas de datos
        $row_idx++;
        $startDataRow = $row_idx;

        // Llenar datos
        foreach ($productos as $producto) {
            $sheet->setCellValue('A' . $row_idx, $producto['PRODUCTO_CODIGO']);
            $sheet->setCellValueExplicit('B' . $row_idx, $producto['CODIGO_DE_BARRA'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('C' . $row_idx, $producto['PRODUCTO_DESCRIPCION']);
            $sheet->setCellValue('D' . $row_idx, $producto['MARCA_DESCRIPCION']);
            $sheet->setCellValue('E' . $row_idx, $producto['FAMILIA_DESCRIPCION']); // Categoría
            $sheet->setCellValue('F' . $row_idx, $producto['SUBFAMILIA_DESCRIPCION'] ?? ''); // Subcategoría
            $sheet->setCellValueExplicit('G' . $row_idx, $producto['KG'] ?? 'No disponible', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            // Nueva columna H: Unidad de Medida
            $sheet->setCellValue('H' . $row_idx, $producto['UNIDAD_DE_MEDIDA'] ?? '');

            // Sucursales y stock (usar los datos de la API o valores predeterminados si no están disponibles)
            // Venta Distribución y sucursales (usar los datos de la API o valores predeterminados si no están disponibles)
            // Convertimos todos los valores a numéricos para evitar errores
            $sheet->setCellValue('I' . $row_idx, floatval(str_replace(',', '.', $producto['VENTA_DISTRIBUCION'] ?? 0)));
            $sheet->setCellValue('J' . $row_idx, floatval($producto['VENTA_SUCURSAL07'] ?? 0));
            $sheet->setCellValue('K' . $row_idx, floatval($producto['VENTA_SUCURSAL01'] ?? 0));
            $sheet->setCellValue('L' . $row_idx, floatval($producto['STOCK_BODEGA01'] ?? 0));
            $sheet->setCellValue('M' . $row_idx, floatval($producto['VENTA_SUCURSAL02'] ?? 0));
            $sheet->setCellValue('N' . $row_idx, floatval($producto['STOCK_BODEGA02'] ?? 0));
            $sheet->setCellValue('O' . $row_idx, floatval($producto['VENTA_SUCURSAL03'] ?? 0));
            $sheet->setCellValue('P' . $row_idx, floatval($producto['STOCK_BODEGA03'] ?? 0));
            $sheet->setCellValue('Q' . $row_idx, floatval($producto['VENTA_SUCURSAL04'] ?? 0));
            $sheet->setCellValue('R' . $row_idx, floatval($producto['STOCK_BODEGA04'] ?? 0));
            $sheet->setCellValue('S' . $row_idx, floatval($producto['VENTA_SUCURSAL05'] ?? 0));
            $sheet->setCellValue('T' . $row_idx, floatval($producto['STOCK_BODEGA05'] ?? 0));

            // Valor unitario y datos de ventas - aseguramos que sean numéricos
            $valorUnitario = floatval($producto['PRECIO_VENTA'] ?? 0);
            $ventaDis = floatval($producto['VENTA_DISTRIBUCION'] ?? 0);
            $ventaSuc1 = floatval($producto['VENTA_SUCURSAL01'] ?? 0);
            $ventaSuc2 = floatval($producto['VENTA_SUCURSAL02'] ?? 0);
            $ventaSuc3 = floatval($producto['VENTA_SUCURSAL03'] ?? 0);
            $ventaSuc4 = floatval($producto['VENTA_SUCURSAL04'] ?? 0);
            $ventaSuc5 = floatval($producto['VENTA_SUCURSAL05'] ?? 0);
            $ventaWeb = floatval($producto['VENTA_SUCURSAL07'] ?? 0);

            // Fórmula U: Stock valorizado (suma de stocks * unidad de compra)
            // Calculamos directamente el valor en lugar de usar una fórmula para evitar problemas de formato
            // Aseguramos que todos los valores sean numéricos con floatval()
            $stockTotal = floatval($producto['STOCK_BODEGA01'] ?? 0) + floatval($producto['STOCK_BODEGA02'] ?? 0) +
                floatval($producto['STOCK_BODEGA03'] ?? 0) + floatval($producto['STOCK_BODEGA04'] ?? 0) +
                floatval($producto['STOCK_BODEGA05'] ?? 0);
            $pesoKG = floatval($producto['KG'] ?? 1);
            $pesoTotalKG = $stockTotal * $pesoKG;
            $sheet->setCellValue('U' . $row_idx, $pesoTotalKG);

            // Columna V: Suma de ventas por sucursal
            // Asignar directamente el valor numérico para evitar problemas con las fórmulas
            $sumaVentas = $ventaDis + $ventaSuc1 + $ventaSuc2 + $ventaSuc3 + $ventaSuc4 + $ventaSuc5;
            $sheet->setCellValue('V' . $row_idx, $sumaVentas);

            // Columna W: Suma Stock (total de stock en todas las sucursales)
            // Usamos el valor de $stockTotal que ya calculamos antes y convertimos a numérico
            $sumaStock = $stockTotal; // Ya es numérico porque lo calculamos arriba
            $sheet->setCellValue('W' . $row_idx, $sumaStock);

            // Columna X: Rotación (ventas / stock, evitando división por cero)
            if ($sumaStock != 0) {
                $rotacion = $sumaVentas / $sumaStock;
                $sheet->setCellValue('X' . $row_idx, number_format($rotacion, 2)); // Formateamos a 2 decimales para mejor legibilidad
            } else {
                $sheet->setCellValue('X' . $row_idx, 0);
            }

            // Columna Y: Precio último compra
            $sheet->setCellValue('Y' . $row_idx, floatval($producto['PRECIO_ULTIMA_COMPRA'] ?? 0));

            // Columna Z: Costo valorizado
            $sheet->setCellValue('Z' . $row_idx, floatval($producto['PRECIO_ULTIMA_COMPRA'] * $sumaStock ?? 0));
            $row_idx++;
        }

        // Dar formato a todas las celdas de datos
        $lastDataRow = $row_idx - 1;
        if ($lastDataRow >= $startDataRow) {
            // Aplicar bordes a los datos
            $sheet->getStyle('A' . $startDataRow . ':Z' . $lastDataRow)->getBorders()->getAllBorders()->setBorderStyle(
                PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );

            // Formato numérico para las columnas de stock (ajustadas por nueva columna H)
            $columnRanges = ['J:J', 'K:K', 'M:M', 'O:O', 'Q:Q', 'S:S', 'U:U', 'V:V'];
            foreach ($columnRanges as $range) {
                $sheet->getStyle($range . $startDataRow . ':' . $range . $lastDataRow)
                    ->getNumberFormat()->setFormatCode('#,##0');
            }

            // Formato moneda para valores (ajustado por nueva columna H)
            $sheet->getStyle('T' . $startDataRow . ':T' . $lastDataRow)
                ->getNumberFormat()->setFormatCode('_($* #,##0.00_);_($* (#,##0.00);_($* "-"??_);_(@_)');

            // Formato decimal para rotación (ajustado por nueva columna H)
            $sheet->getStyle('X' . $startDataRow . ':X' . $lastDataRow)
                ->getNumberFormat()->setFormatCode('0.00');

            // Formato moneda para precio último compra (ajustado por nueva columna H)
            $sheet->getStyle('Y' . $startDataRow . ':Y' . $lastDataRow)
                ->getNumberFormat()->setFormatCode('_($* #,##0.00_);_($* (#,##0.00);_($* "-"??_);_(@_)');

            // Formato moneda para costo valorizado (ajustado por nueva columna H)
            $sheet->getStyle('Z' . $startDataRow . ':Z' . $lastDataRow)
                ->getNumberFormat()->setFormatCode('_($* #,##0.00_);_($* (#,##0.00);_($* "-"??_);_(@_)');

        }

        // Ajustar anchos de columnas automáticamente
        foreach (range('A', 'Z') as $col) {
            if (in_array($col, ['U', 'V', 'W', 'Y', 'Z'])) {
                $sheet->getColumnDimension($col)->setWidth(30);
            } else {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
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
function exportarVentas($sheet, $row_idx, $proveedorRut, $fechaInicio, $fechaFin, $productoId)
{
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
function exportarFacturas($sheet, $row_idx, $proveedorRut, $fechaInicio, $fechaFin, $estado)
{
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

/**
 * Función para exportar detalle de venta neta a Excel
 */
function exportarDetalleVentaNeta($sheet, $row_idx, $proveedorRut, $fechaInicio, $fechaFin)
{
    // Establecer encabezados de columna
    $sheet->setCellValue('A' . $row_idx, 'Tipo');
    $sheet->setCellValue('B' . $row_idx, 'Folio');
    $sheet->setCellValue('C' . $row_idx, 'Fecha');
    $sheet->setCellValue('D' . $row_idx, 'Código');
    $sheet->setCellValue('E' . $row_idx, 'Producto');
    $sheet->setCellValue('F' . $row_idx, 'Marca');
    $sheet->setCellValue('G' . $row_idx, 'Cantidad');
    $sheet->setCellValue('H' . $row_idx, 'Precio Unit.');
    $sheet->setCellValue('I' . $row_idx, 'Total Neto');

    // Estilo para encabezados
    $sheet->getStyle('A' . $row_idx . ':I' . $row_idx)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4285F4'], // Color azul para coincidir con el modal
        ],
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN],
        ],
    ]);

    $row_idx++;

    // Incluir el archivo con la función getDetalleVentaNeta
    require_once __DIR__ . '/../includes/api_client.php';
    require_once __DIR__ . '/../includes/kpi_repository.php';

    // Obtener datos usando la misma función que en productos.php
    $detalleValorNeto = getDetalleVentaNetaMulti($fechaInicio, $fechaFin, $proveedorRut);

    // Llenar datos
    foreach ($detalleValorNeto as $item) {
        $sheet->setCellValue('A' . $row_idx, $item['TIPO']);
        // Formatear folios como texto para evitar notación científica
        $sheet->setCellValueExplicit('B' . $row_idx, $item['FOLIO'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('C' . $row_idx, $item['FECHA_DE_EMISION']);
        // Formatear códigos de producto como texto
        $sheet->setCellValueExplicit('D' . $row_idx, $item['PRODUCTO_CODIGO'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('E' . $row_idx, $item['PRODUCTO_DESCRIPCION']);
        $sheet->setCellValue('F' . $row_idx, $item['MARCA_DESCRIPCION']);
        $sheet->setCellValue('G' . $row_idx, $item['CANTIDAD']);
        $sheet->setCellValue('H' . $row_idx, $item['PRECIO_UNITARIO_NETO']);
        $sheet->setCellValue('I' . $row_idx, $item['TOTAL_NETO']);

        $row_idx++;
    }

    // Dar formato a las celdas numéricas
    $sheet->getStyle('G' . ($row_idx - count($detalleValorNeto)) . ':G' . ($row_idx - 1))->getNumberFormat()
        ->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
    $sheet->getStyle('H' . ($row_idx - count($detalleValorNeto)) . ':I' . ($row_idx - 1))->getNumberFormat()
        ->setFormatCode('$#,##0');
}

/**
 * Función para exportar detalle de unidades vendidas a Excel
 */
function exportarDetalleUnidadesVendidas($sheet, $row_idx, $proveedorRut, $fechaInicio, $fechaFin)
{
    // Establecer encabezados de columna
    $sheet->setCellValue('A' . $row_idx, 'Tipo');
    $sheet->setCellValue('B' . $row_idx, 'Folio');
    $sheet->setCellValue('C' . $row_idx, 'Fecha');
    $sheet->setCellValue('D' . $row_idx, 'Código');
    $sheet->setCellValue('E' . $row_idx, 'Producto');
    $sheet->setCellValue('F' . $row_idx, 'Marca');
    $sheet->setCellValue('G' . $row_idx, 'Cantidad');

    // Estilo para encabezados
    $sheet->getStyle('A' . $row_idx . ':G' . $row_idx)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '28A745'], // Color verde para coincidir con el modal
        ],
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN],
        ],
    ]);

    $row_idx++;

    // Incluir el archivo con la función getDetalleUnidadesVendidas
    require_once __DIR__ . '/../includes/api_client.php';
    require_once __DIR__ . '/../includes/kpi_repository.php';

    // Obtener datos usando la misma función que en productos.php
    $detalleUnidadesVendidas = getDetalleUnidadesVendidasMulti($fechaInicio, $fechaFin, [$proveedorRut]);

    // Llenar datos
    foreach ($detalleUnidadesVendidas as $item) {
        $sheet->setCellValue('A' . $row_idx, $item['TIPO']);
        // Formatear folios como texto para evitar notación científica
        $sheet->setCellValueExplicit('B' . $row_idx, $item['FOLIO'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('C' . $row_idx, $item['FECHA_DE_EMISION']);
        // Formatear códigos de producto como texto
        $sheet->setCellValueExplicit('D' . $row_idx, $item['PRODUCTO_CODIGO'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('E' . $row_idx, $item['PRODUCTO_DESCRIPCION']);
        $sheet->setCellValue('F' . $row_idx, $item['MARCA_DESCRIPCION']);
        $sheet->setCellValue('G' . $row_idx, $item['CANTIDAD']);

        $row_idx++;
    }

    // Dar formato a las celdas numéricas
    $sheet->getStyle('G' . ($row_idx - count($detalleUnidadesVendidas)) . ':G' . ($row_idx - 1))->getNumberFormat()
        ->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
}

/**
 * Función para exportar detalle de SKU activos a Excel
 */
function exportarDetalleSkuActivos($sheet, $row_idx, $proveedorRut)
{
    // Establecer encabezados de columna
    $sheet->setCellValue('A' . $row_idx, 'Código');
    $sheet->setCellValue('B' . $row_idx, 'Producto');
    $sheet->setCellValue('C' . $row_idx, 'Código de Barra');
    $sheet->setCellValue('D' . $row_idx, 'Marca');

    // Estilo para encabezados
    $sheet->getStyle('A' . $row_idx . ':D' . $row_idx)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '17A2B8'], // Color celeste para coincidir con el modal
        ],
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN],
        ],
    ]);

    $row_idx++;

    // Incluir el archivo con la función getDetalleSkuActivos
    require_once __DIR__ . '/../includes/kpi_repository.php';

    // Obtener datos usando la misma función que en productos.php
    $detalleSkuActivos = getDetalleSkuActivosMulti([$proveedorRut]);

    // Llenar datos
    foreach ($detalleSkuActivos as $item) {
        // Formatear código de producto como texto para evitar notación científica
        $sheet->setCellValueExplicit('A' . $row_idx, $item['PRODUCTO_CODIGO'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('B' . $row_idx, $item['PRODUCTO_DESCRIPCION']);
        // Añadir un apóstrofe al inicio para forzar formato de texto en Excel
        $sheet->setCellValueExplicit('C' . $row_idx, $item['CODIGO_DE_BARRA'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('D' . $row_idx, $item['MARCA_DESCRIPCION']);

        $row_idx++;
    }
}
