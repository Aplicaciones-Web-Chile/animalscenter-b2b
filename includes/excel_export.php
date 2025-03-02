<?php
/**
 * Funciones para la generación y exportación de reportes en formato Excel
 */

// Incluir las librerías de PhpSpreadsheet si no están ya cargadas por Composer
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Genera un informe Excel B2B para un proveedor específico
 * 
 * @param string $rut_proveedor El RUT del proveedor
 * @param string $fecha_inicio Fecha de inicio para filtrar ventas (formato YYYY-MM-DD)
 * @param string $fecha_fin Fecha de fin para filtrar ventas (formato YYYY-MM-DD)
 * @return string La ruta al archivo Excel generado
 */
function generateB2BReport($rut_proveedor, $fecha_inicio, $fecha_fin) {
    // Crear una nueva instancia de Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Configurar el título y metadatos del documento
    $spreadsheet->getProperties()
        ->setCreator("TIENDA DE MASCOTAS ANIMALS CENTER LTDA")
        ->setLastModifiedBy("Sistema B2B")
        ->setTitle("Informe B2B")
        ->setSubject("Informe de Productos y Ventas")
        ->setDescription("Informe B2B generado para el proveedor con RUT $rut_proveedor")
        ->setKeywords("excel informe b2b productos ventas")
        ->setCategory("Informes");
    
    // Configurar el nombre de la hoja
    $sheet->setTitle('Informe B2B');
    
    // Establecer el encabezado con nombre de la empresa
    $sheet->setCellValue('A1', 'TIENDA DE MASCOTAS ANIMALS CENTER LTDA');
    $sheet->mergeCells('A1:G1');
    
    // Establecer el título del informe
    $sheet->setCellValue('A2', 'Informe B2B');
    $sheet->mergeCells('A2:G2');
    
    // Establecer el período del informe
    $sheet->setCellValue('A3', 'Período: ' . date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin)));
    $sheet->mergeCells('A3:G3');
    
    // Establecer estilos para el encabezado
    $headerStyle = [
        'font' => [
            'bold' => true,
            'size' => 14,
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => [
                'rgb' => 'C5D9F1',
            ],
        ],
    ];
    
    $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
    $sheet->getStyle('A2:G2')->applyFromArray($headerStyle);
    
    // Establecer estilos para el período
    $periodoStyle = [
        'font' => [
            'bold' => true,
            'size' => 12,
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
        ],
    ];
    
    $sheet->getStyle('A3:G3')->applyFromArray($periodoStyle);
    
    // Establecer los encabezados de columna
    $sheet->setCellValue('A5', 'COD BARRA');
    $sheet->setCellValue('B5', 'Nombre del Producto');
    $sheet->setCellValue('C5', 'Sub Categoría');
    $sheet->setCellValue('D5', 'Stock Un');
    $sheet->setCellValue('E5', 'Ventas Un');
    $sheet->setCellValue('F5', 'Suma');
    $sheet->setCellValue('G5', 'Valorizado');
    
    // Estilo para los encabezados de columna
    $columnHeaderStyle = [
        'font' => [
            'bold' => true,
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => [
                'rgb' => 'D9D9D9',
            ],
        ],
    ];
    
    $sheet->getStyle('A5:G5')->applyFromArray($columnHeaderStyle);
    
    // Conectar a la base de datos
    require_once __DIR__ . '/../config/database.php';
    
    // Consulta para obtener productos y su información asociada
    $sql = "SELECT 
                p.sku AS cod_barra,
                p.nombre AS nombre_producto,
                SUBSTRING_INDEX(p.nombre, ' ', 1) AS sub_categoria,
                p.stock AS stock_un,
                COALESCE(SUM(v.cantidad), 0) AS ventas_un,
                COALESCE(SUM(v.cantidad * p.precio), 0) AS suma,
                (p.stock * p.precio) AS valorizado
            FROM 
                productos p
            LEFT JOIN 
                ventas v ON p.id = v.producto_id AND v.fecha BETWEEN ? AND ?
            WHERE 
                p.proveedor_rut = ?
            GROUP BY 
                p.id
            ORDER BY 
                p.nombre";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $fecha_inicio, $fecha_fin, $rut_proveedor);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $row_idx = 6;
        
        // Configurar estilo para las celdas de datos
        $dataStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
        
        $numberFormatStyle = [
            'numberFormat' => [
                'formatCode' => '#,##0',
            ],
        ];
        
        $currencyFormatStyle = [
            'numberFormat' => [
                'formatCode' => '_("$"* #,##0_);_("$"* \(#,##0\);_("$"* "-"_);_(@_)',
            ],
        ];
        
        $totalSuma = 0;
        $totalValorizado = 0;
        
        // Insertar datos en el Excel
        while ($row = $result->fetch_assoc()) {
            $sheet->setCellValue('A' . $row_idx, $row['cod_barra']);
            $sheet->setCellValue('B' . $row_idx, $row['nombre_producto']);
            $sheet->setCellValue('C' . $row_idx, $row['sub_categoria']);
            $sheet->setCellValue('D' . $row_idx, $row['stock_un']);
            $sheet->setCellValue('E' . $row_idx, $row['ventas_un']);
            $sheet->setCellValue('F' . $row_idx, $row['suma']);
            $sheet->setCellValue('G' . $row_idx, $row['valorizado']);
            
            $totalSuma += $row['suma'];
            $totalValorizado += $row['valorizado'];
            
            $row_idx++;
        }
        
        // Aplicar estilo a todas las celdas de datos
        $sheet->getStyle('A6:G' . ($row_idx - 1))->applyFromArray($dataStyle);
        
        // Aplicar formato numérico a las columnas de Stock y Ventas
        $sheet->getStyle('D6:E' . ($row_idx - 1))->applyFromArray($numberFormatStyle);
        
        // Aplicar formato de moneda a las columnas de Suma y Valorizado
        $sheet->getStyle('F6:G' . ($row_idx - 1))->applyFromArray($currencyFormatStyle);
        
        // Agregar fila de totales
        $sheet->setCellValue('A' . $row_idx, 'TOTALES');
        $sheet->mergeCells('A' . $row_idx . ':E' . $row_idx);
        $sheet->setCellValue('F' . $row_idx, $totalSuma);
        $sheet->setCellValue('G' . $row_idx, $totalValorizado);
        
        // Estilo para la fila de totales
        $totalStyle = [
            'font' => [
                'bold' => true,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => 'D9D9D9',
                ],
            ],
        ];
        
        $sheet->getStyle('A' . $row_idx . ':G' . $row_idx)->applyFromArray($totalStyle);
        $sheet->getStyle('F' . $row_idx . ':G' . $row_idx)->applyFromArray($currencyFormatStyle);
        
        // Ajustar ancho de columnas automáticamente
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Aplicar autofilter
        $sheet->setAutoFilter('A5:G5');
        
        // Generar el archivo Excel
        $filename = 'informe_b2b_' . date('YmdHis') . '.xlsx';
        $filepath = __DIR__ . '/../tmp/' . $filename;
        
        // Asegurarse de que el directorio existe
        if (!file_exists(__DIR__ . '/../tmp/')) {
            mkdir(__DIR__ . '/../tmp/', 0777, true);
        }
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        
        return $filepath;
        
    } catch (Exception $e) {
        // Registrar el error
        error_log('Error al generar el informe Excel: ' . $e->getMessage());
        throw new Exception('Error al generar el informe Excel: ' . $e->getMessage());
    }
}

/**
 * Forza la descarga de un archivo
 * 
 * @param string $filepath Ruta completa al archivo
 * @param string $filename Nombre para el archivo de descarga
 */
function downloadFile($filepath, $filename = null) {
    if (!file_exists($filepath)) {
        throw new Exception('El archivo no existe: ' . $filepath);
    }
    
    if ($filename === null) {
        $filename = basename($filepath);
    }
    
    // Determinar el tipo MIME según la extensión
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $filepath);
    finfo_close($finfo);
    
    // Si no se puede determinar, usar tipo genérico
    if (!$mime_type) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === 'xlsx') {
            $mime_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } elseif ($extension === 'xls') {
            $mime_type = 'application/vnd.ms-excel';
        } else {
            $mime_type = 'application/octet-stream';
        }
    }
    
    // Configurar encabezados para la descarga
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    
    // Limpiar el búfer de salida
    ob_clean();
    flush();
    
    // Enviar el archivo
    readfile($filepath);
    
    // Eliminar el archivo temporal si existe en el directorio tmp
    if (strpos($filepath, '/tmp/') !== false) {
        unlink($filepath);
    }
    
    exit;
}
