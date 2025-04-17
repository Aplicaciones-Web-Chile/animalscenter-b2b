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
$fechaInicio        = $_GET['fecha_inicio'] ?? date('d/m/Y');
$fechaFin       = $_GET['fecha_fin'] ?? date('d/m/Y');
$productoId     = $_GET['producto_id'] ?? '';
$estado         = $_GET['estado'] ?? '';
$codigoProveedor = $_GET['proveedor'] ?? '78843490'; // Código del proveedor por defecto
$distribuidor   = $_GET['distribuidor'] ?? '001'; // Código del distribuidor por defecto

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
}

// Ajustar anchos de columnas automáticamente - algunas columnas ya se configuraron previamente
foreach (range('H', 'V') as $col) {
    // Para las columnas de Venta y Stock usamos un ancho fijo para mejor visualización
    if (in_array($col, ['H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V'])) {
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
        $sheet->mergeCells('A1:W1');
        
        // Configurar subtítulo del informe
        $sheet->setCellValue('A2', 'Informe B2B');
        $sheet->mergeCells('A2:W2');
        
        // Establecer fechas del informe
        $sheet->setCellValue('A3', 'Desde el ' . $fechaInicio . ' hasta el ' . $fechaFin);
        $sheet->mergeCells('A3:W3');
        
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
        
        $sheet->getStyle('A1:W3')->applyFromArray($headerStyle);
        
        // Saltar una fila para iniciar el contenido
        $row_idx = 5;
        
        // Texto "ORDENADO POR CÓDIGO"
        $sheet->setCellValue('A' . $row_idx, 'ORDENADO POR CÓDIGO');
        $sheet->mergeCells('A' . $row_idx . ':W' . $row_idx);
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
        $sheet->getColumnDimension('G')->setAutoSize(true); // Kilo (antes Unidad Compra)
        $sheet->getColumnDimension('H')->setAutoSize(true); // Venta Distribución
        
        $sheet->setCellValue('A' . $headerRow1, '');
        $sheet->setCellValue('B' . $headerRow1, '');
        $sheet->setCellValue('C' . $headerRow1, '');
        $sheet->setCellValue('D' . $headerRow1, '');
        $sheet->setCellValue('E' . $headerRow1, '');
        $sheet->setCellValue('F' . $headerRow1, '');
        $sheet->setCellValue('G' . $headerRow1, '');
        
        // Campo de Venta Distribución
        $sheet->setCellValue('H' . $headerRow1, 'DISTRIB');

        // Campo de Venta Web
        $sheet->setCellValue('I' . $headerRow1, 'WEB');

        // Sucursales
        $sheet->setCellValue('J' . $headerRow1, 'VERGARA');
        $sheet->mergeCells('J' . $headerRow1 . ':K' . $headerRow1);
        
        $sheet->setCellValue('L' . $headerRow1, 'LAMPA');
        $sheet->mergeCells('L' . $headerRow1 . ':M' . $headerRow1);
        
        $sheet->setCellValue('N' . $headerRow1, 'PANAMERICANA');
        $sheet->mergeCells('N' . $headerRow1 . ':O' . $headerRow1);
        
        $sheet->setCellValue('P' . $headerRow1, 'MATTA');
        $sheet->mergeCells('P' . $headerRow1 . ':Q' . $headerRow1);
        
        $sheet->setCellValue('R' . $headerRow1, 'PROVIDENCIA');
        $sheet->mergeCells('R' . $headerRow1 . ':S' . $headerRow1);

        // Totales
        $sheet->setCellValue('T' . $headerRow1, 'TOTALES');
        $sheet->mergeCells('T' . $headerRow1 . ':W' . $headerRow1);
        
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
        $sheet->setCellValue('H' . $headerRow2, 'Venta Distribución');
        $sheet->setCellValue('I' . $headerRow2, 'Venta Web');
        
        // Detalle de sucursales - Venta y Stock
        $sheet->setCellValue('J' . $headerRow2, 'Venta');
        $sheet->setCellValue('K' . $headerRow2, 'Stock');
        $sheet->setCellValue('L' . $headerRow2, 'Venta');
        $sheet->setCellValue('M' . $headerRow2, 'Stock');
        $sheet->setCellValue('N' . $headerRow2, 'Venta');
        $sheet->setCellValue('O' . $headerRow2, 'Stock');
        $sheet->setCellValue('P' . $headerRow2, 'Venta');
        $sheet->setCellValue('Q' . $headerRow2, 'Stock');
        $sheet->setCellValue('R' . $headerRow2, 'Venta');
        $sheet->setCellValue('S' . $headerRow2, 'Stock');
        
        // Totales - Con comentarios explicativos
        $sheet->setCellValue('T' . $headerRow2, 'Stock Valorizado');
        $comentarioStockValorizado = $sheet->getComment('T' . $headerRow2);
        $comentarioStockValorizado->getText()->createTextRun('Valor total del stock: Suma de todos los stocks multiplicado por el valor unitario');

        $sheet->setCellValue('U' . $headerRow2, 'Suma Ventas (U)');
        $comentarioSumaVentas = $sheet->getComment('U' . $headerRow2);
        $comentarioSumaVentas->getText()->createTextRun('Total de unidades vendidas en todas las sucursales');

        $sheet->setCellValue('V' . $headerRow2, 'Suma Stock (U)');
        $comentarioSumaStock = $sheet->getComment('V' . $headerRow2);
        $comentarioSumaStock->getText()->createTextRun('Total de unidades en stock en todas las sucursales');

        $sheet->setCellValue('W' . $headerRow2, 'Rotación');
        $comentarioRotacion = $sheet->getComment('W' . $headerRow2);
        $comentarioRotacion->getText()->createTextRun('Velocidad con que se vende el producto: Ventas / Stock');
        
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
        $sheet->getStyle('A' . $headerRow1 . ':G' . $headerRow2)->applyFromArray($columnHeaderStyle);
        $sheet->getStyle('H' . $headerRow1 . ':R' . $headerRow1)->applyFromArray($sucursalHeaderStyle);
        $sheet->getStyle('H' . $headerRow2 . ':R' . $headerRow2)->applyFromArray($columnHeaderStyle);
        
        // Aplicar colores específicos para la sección de totales
        $sheet->getStyle('S' . $headerRow1)->getFill()->getStartColor()->setRGB('FFFF00'); // Amarillo
        $sheet->getStyle('S' . $headerRow1 . ':V' . $headerRow1)->applyFromArray($sucursalHeaderStyle);
        $sheet->getStyle('S' . $headerRow1 . ':V' . $headerRow1)->getFill()->getStartColor()->setRGB('FFFF00'); // Amarillo
        
        // Estilo del encabezado - Colores para sucursales y totales
        $sheet->getStyle('H' . $headerRow1)->getFill()
            ->setFillType(PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DDEBF7'); // Azul muy claro para distribución
            
        $sheet->getStyle('I' . $headerRow1 . ':J' . $headerRow1)->getFill()
            ->setFillType(PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('B8CCE4'); // Azul claro
        
        $sheet->getStyle('K' . $headerRow1 . ':L' . $headerRow1)->getFill()
            ->setFillType(PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E6B8B7'); // Rojo claro
            
        $sheet->getStyle('M' . $headerRow1 . ':N' . $headerRow1)->getFill()
            ->setFillType(PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('C5E0B3'); // Verde claro
            
        $sheet->getStyle('O' . $headerRow1 . ':P' . $headerRow1)->getFill()
            ->setFillType(PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFE699'); // Amarillo claro
            
        $sheet->getStyle('Q' . $headerRow1 . ':R' . $headerRow1)->getFill()
            ->setFillType(PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9D9D9'); // Gris claro

        $sheet->getStyle('S' . $headerRow1 . ':V' . $headerRow1)->getFill()
            ->setFillType(PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F4B084'); // Naranja claro
            
        // Iniciar las filas de datos
        $row_idx++;
        $startDataRow = $row_idx;
        
        // Llenar datos
        foreach ($productos as $producto) {
            $sheet->setCellValue('A' . $row_idx, $producto['PRODUCTO_CODIGO']);
            $sheet->setCellValue('B' . $row_idx, $producto['CODIGO_DE_BARRA'] ?? '');
            $sheet->setCellValue('C' . $row_idx, $producto['PRODUCTO_DESCRIPCION']);
            $sheet->setCellValue('D' . $row_idx, $producto['MARCA_DESCRIPCION']);
            $sheet->setCellValue('E' . $row_idx, $producto['FAMILIA_DESCRIPCION']); // Categoría
            $sheet->setCellValue('F' . $row_idx, $producto['SUBFAMILIA_DESCRIPCION'] ?? ''); // Subcategoría
            $sheet->setCellValue('G' . $row_idx, $producto['KG'] ?? 'No disponible');
            
            // Sucursales y stock (usar los datos de la API o valores predeterminados si no están disponibles)
            // Venta Distribución y sucursales (usar los datos de la API o valores predeterminados si no están disponibles)
            // Convertimos todos los valores a numéricos para evitar errores
            $sheet->setCellValue('H' . $row_idx, floatval(str_replace(',', '.', $producto['VENTA_DISTRIBUCION'] ?? 0)));
            $sheet->setCellValue('I' . $row_idx, floatval($producto['VENTA_SUCURSAL07'] ?? 0));
            $sheet->setCellValue('J' . $row_idx, floatval($producto['VENTA_SUCURSAL01'] ?? 0));
            $sheet->setCellValue('K' . $row_idx, floatval($producto['STOCK_BODEGA01'] ?? 0));
            $sheet->setCellValue('L' . $row_idx, floatval($producto['VENTA_SUCURSAL02'] ?? 0));
            $sheet->setCellValue('M' . $row_idx, floatval($producto['STOCK_BODEGA02'] ?? 0));
            $sheet->setCellValue('N' . $row_idx, floatval($producto['VENTA_SUCURSAL03'] ?? 0));
            $sheet->setCellValue('O' . $row_idx, floatval($producto['STOCK_BODEGA03'] ?? 0));
            $sheet->setCellValue('P' . $row_idx, floatval($producto['VENTA_SUCURSAL04'] ?? 0));
            $sheet->setCellValue('Q' . $row_idx, floatval($producto['STOCK_BODEGA04'] ?? 0));
            $sheet->setCellValue('R' . $row_idx, floatval($producto['VENTA_SUCURSAL05'] ?? 0));
            $sheet->setCellValue('S' . $row_idx, floatval($producto['STOCK_BODEGA05'] ?? 0));
            
            // Valor unitario y datos de ventas - aseguramos que sean numéricos
            $valorUnitario = floatval($producto['PRECIO_VENTA'] ?? 0);
            $ventaSuc1 = floatval($producto['VENTA_SUCURSAL01'] ?? 0);
            $ventaSuc2 = floatval($producto['VENTA_SUCURSAL02'] ?? 0);
            $ventaSuc3 = floatval($producto['VENTA_SUCURSAL03'] ?? 0);
            $ventaSuc4 = floatval($producto['VENTA_SUCURSAL04'] ?? 0);
            $ventaSuc5 = floatval($producto['VENTA_SUCURSAL05'] ?? 0);
            $ventaWeb = floatval($producto['VENTA_SUCURSAL07'] ?? 0);
            
            // Fórmula T: Stock valorizado (suma de stocks * unidad de compra)
            // Calculamos directamente el valor en lugar de usar una fórmula para evitar problemas de formato
            // Aseguramos que todos los valores sean numéricos con floatval()
            $stockTotal = floatval($producto['STOCK_BODEGA01'] ?? 0) + floatval($producto['STOCK_BODEGA02'] ?? 0) + 
                        floatval($producto['STOCK_BODEGA03'] ?? 0) + floatval($producto['STOCK_BODEGA04'] ?? 0) + 
                        floatval($producto['STOCK_BODEGA05'] ?? 0);
            $unidadCompra = floatval($producto['UNIDAD_COMPRA'] ?? 1);
            $stockValorizado = $stockTotal * $unidadCompra;
            $sheet->setCellValue('T' . $row_idx, $stockValorizado);
            
            // Columna U: Suma de ventas por sucursal
            // Asignar directamente el valor numérico para evitar problemas con las fórmulas
            $sumaVentas = $ventaSuc1 + $ventaSuc2 + $ventaSuc3 + $ventaSuc4 + $ventaSuc5;
            $sheet->setCellValue('U' . $row_idx, $sumaVentas);
            
            // Columna V: Suma Stock (total de stock en todas las sucursales)
            // Usamos el valor de $stockTotal que ya calculamos antes y convertimos a numérico
            $sumaStock = $stockTotal; // Ya es numérico porque lo calculamos arriba
            $sheet->setCellValue('V' . $row_idx, $sumaStock);
            
            // Columna W: Rotación (ventas / stock, evitando división por cero)
            if ($sumaStock > 0) {
                $rotacion = $sumaVentas / $sumaStock;
                $sheet->setCellValue('W' . $row_idx, number_format($rotacion, 2)); // Formateamos a 2 decimales para mejor legibilidad
            } else {
                $sheet->setCellValue('W' . $row_idx, 0);
            }
            
            $row_idx++;
        }
        
        // Dar formato a todas las celdas de datos
        $lastDataRow = $row_idx - 1;
        if ($lastDataRow >= $startDataRow) {
            // Aplicar bordes a los datos
            $sheet->getStyle('A' . $startDataRow . ':W' . $lastDataRow)->getBorders()->getAllBorders()->setBorderStyle(
                PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
            
            // Formato numérico para las columnas de stock
            $columnRanges = ['I:I', 'J:J', 'L:L', 'N:N', 'P:P', 'R:R', 'T:T', 'U:U'];
            foreach ($columnRanges as $range) {
                $sheet->getStyle($range . $startDataRow . ':' . $range . $lastDataRow)
                    ->getNumberFormat()->setFormatCode('#,##0');
            }
            
            // Formato moneda para valores
            $sheet->getStyle('S' . $startDataRow . ':S' . $lastDataRow)
                ->getNumberFormat()->setFormatCode('_($* #,##0.00_);_($* (#,##0.00);_($* "-"??_);_(@_)');
            
            // Formato decimal para rotación
            $sheet->getStyle('V' . $startDataRow . ':V' . $lastDataRow)
                ->getNumberFormat()->setFormatCode('0.00');
        }
        
        // Ajustar anchos de columnas automáticamente
        foreach (range('A', 'W') as $col) {
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
