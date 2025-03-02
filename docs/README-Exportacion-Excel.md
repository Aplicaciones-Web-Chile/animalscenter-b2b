# Implementación de Funcionalidad de Exportación a Excel

## Descripción
Este documento describe la implementación de la funcionalidad para generar reportes en formato Excel dentro del Sistema B2B de Animals Center. Esta característica permite a los usuarios, tanto administradores como proveedores, exportar datos relevantes sobre productos, ventas y stock en un formato estructurado y profesional.

## Archivos Implementados

### 1. `/includes/excel_export.php`
Contiene las funciones principales para la generación de archivos Excel utilizando la biblioteca PhpSpreadsheet:

- `generateB2BReport($rut_proveedor, $fecha_inicio, $fecha_fin)`: Genera un informe Excel con datos de productos, stock y ventas para un proveedor específico en un rango de fechas.
- `downloadFile($filepath, $filename)`: Gestiona la descarga del archivo generado.

### 2. `/public/exportar.php`
Controla la interfaz de usuario y la lógica para solicitar la exportación:

- Verifica autenticación y permisos
- Procesa parámetros (fechas, proveedor)
- Para administradores, muestra un formulario para seleccionar el proveedor
- Para proveedores, genera automáticamente el informe con sus datos

### 3. Actualizaciones en `/public/header.php`
Se agregó un enlace en el menú de navegación para acceder directamente a la funcionalidad de exportación.

### 4. Actualizaciones en `/public/dashboard.php`
Se agregaron elementos en el dashboard para acceder rápidamente a la exportación:
- Botón de exportación rápida
- Formulario para generar informes personalizados con selección de fechas

## Estructura del Informe Excel

El informe generado incluye:

1. **Encabezado**: 
   - Nombre de la empresa: "TIENDA DE MASCOTAS ANIMALS CENTER LTDA"
   - Título: "Informe B2B"
   - Periodo: Fechas seleccionadas

2. **Columnas de datos**:
   - Código de Barras (SKU)
   - Nombre del Producto
   - Sub Categoría
   - Stock Unidades
   - Ventas Unidades
   - Suma (valor de ventas)
   - Valorizado (valor del inventario)

3. **Características adicionales**:
   - Formato profesional con estilos y colores
   - Fila de totales
   - Filtros automáticos para facilitar el análisis
   - Formato monetario para columnas de valores

## Requisitos Técnicos

Para el correcto funcionamiento de esta característica, el sistema debe contar con:

1. **Dependencias**:
   - Biblioteca PhpSpreadsheet (instalada vía Composer)
   - Extensiones PHP: zip, xml, gd

2. **Permisos**:
   - Directorio `/tmp` con permisos de escritura para almacenar archivos temporales

## Uso

1. **Exportación rápida**: 
   - Desde el Dashboard, hacer clic en el botón "Exportar Informe Excel"
   - Esto generará un informe con el periodo del mes actual

2. **Exportación personalizada**:
   - Acceder al formulario en Dashboard o desde el menú "Exportar"
   - Seleccionar el rango de fechas deseado
   - Para administradores, seleccionar el proveedor
   - Hacer clic en "Generar"

## Limitaciones

- Actualmente solo se ha implementado el informe B2B de productos y ventas
- El tamaño de los archivos podría ser grande para proveedores con muchos productos
- Se recomienda generar informes para periodos específicos para mejorar el rendimiento

## Futuras Mejoras

- Implementar exportación en otros formatos (PDF, CSV)
- Agregar más tipos de informes
- Permitir selección de columnas personalizadas
- Agregar gráficos automáticos
- Implementar envío automático de informes por correo
