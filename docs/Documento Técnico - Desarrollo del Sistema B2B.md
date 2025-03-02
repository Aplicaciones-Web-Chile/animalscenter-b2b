# **Documento Técnico - Desarrollo del Sistema B2B**

## **1. Introducción**

Este documento detalla la arquitectura, tecnologías, y especificaciones técnicas necesarias para la implementación del sistema B2B. Su propósito es proporcionar directrices claras para su desarrollo de manera profesional y eficiente.

El sistema B2B debe ser desarrollado utilizando un enfoque de arquitectura limpia y modularidad.


## **2. Tecnologías y Lenguajes**

* **Backend:** PHP puro  
* **Base de datos:** MySQL  
* **Frontend:** HTML, CSS, JavaScript  
* **Herramientas de UX/UI:** Bootstrap y CSS Tailwind para mejorar la experiencia de usuario con diseños modernos y responsivos.  
* **API de integración con ERP:** API REST con formato JSON  
* **Autenticación:** Sesiones en PHP  
* **Hosting:** Servidor propio en la empresa  
* **Seguridad:** SSL, validaciones contra SQL Injection, XSS, CSRF, firewall y restricciones por IP  
* **Gestión de dependencias:** Uso de Composer para librerías, incluyendo la generación de reportes en Excel (PhpSpreadsheet)

## **3. Arquitectura del Sistema**

### **3.1. Estructura de Usuarios**

* **Usuarios proveedores:** Pueden acceder a la información de sus propios productos, ventas, facturas y stock.  
* **Administradores:** Tienen acceso global a toda la información.  
* **Autenticación y sesiones:** Manejo de sesión en PHP con control de acceso basado en roles.

### **3.2. Base de Datos (Esquema Detallado)**

#### **Estructura de Tablas y Relaciones**

CREATE TABLE usuarios (

    id INT AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(100) NOT NULL,

    email VARCHAR(100) UNIQUE NOT NULL,

    password_hash VARCHAR(255) NOT NULL,

    rol ENUM('admin', 'proveedor') NOT NULL,

    rut VARCHAR(12) UNIQUE NOT NULL,

    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);

CREATE TABLE productos (

    id INT AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(255) NOT NULL,

    sku VARCHAR(50) UNIQUE NOT NULL,

    stock INT NOT NULL,

    precio DECIMAL(10,2) NOT NULL,

    proveedor_rut VARCHAR(12) NOT NULL,

    FOREIGN KEY (proveedor_rut) REFERENCES usuarios(rut) ON DELETE CASCADE

);

CREATE TABLE ventas (

    id INT AUTO_INCREMENT PRIMARY KEY,

    producto_id INT NOT NULL,

    cantidad INT NOT NULL,

    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    proveedor_rut VARCHAR(12) NOT NULL,

    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,

    FOREIGN KEY (proveedor_rut) REFERENCES usuarios(rut) ON DELETE CASCADE

);

CREATE TABLE facturas (

    id INT AUTO_INCREMENT PRIMARY KEY,

    venta_id INT NOT NULL,

    monto DECIMAL(10,2) NOT NULL,

    estado ENUM('pendiente', 'pagada', 'vencida') NOT NULL,

    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    proveedor_rut VARCHAR(12) NOT NULL,

    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,

    FOREIGN KEY (proveedor_rut) REFERENCES usuarios(rut) ON DELETE CASCADE

);

## **4. API REST para Integración con ERP**

### **4.1. Consideraciones**

* Solo se realizarán consultas de lectura al ERP.  
* Los datos serán recuperados en tiempo real.  
* La relación entre usuarios del B2B y el ERP se hará a través del RUT del proveedor.

### **4.2. Endpoints de la API**

#### **Autenticación**

* `POST /api/login` (email, contraseña) → Devuelve sesión activa  
* `POST /api/logout` → Cierra sesión

#### **Productos**

* `GET /api/productos` → Lista todos los productos del usuario logueado  
* `GET /api/productos/{id}` → Obtiene detalles de un producto

#### **Stock**

* `GET /api/stock` → Muestra stock actual de los productos del usuario

#### **Ventas**

* `GET /api/ventas` → Lista ventas por rango de fecha y producto  
* `GET /api/ventas/{id}` → Obtiene detalles de una venta

#### **Facturas**

* `GET /api/facturas` → Lista facturas asociadas al usuario  
* `GET /api/facturas/{id}` → Obtiene detalles de una factura

## **5. Estructura de Archivos**

b2b-system/

├── public/                   # Archivos públicos (CSS, JS, imágenes, etc.)

│   ├── css/

│   │   ├── bootstrap.min.css

│   │   ├── tailwind.min.css

│   ├── js/

│   │   ├── app.js            # Funciones JS generales

│   │   ├── ajax.js           # Manejo de peticiones AJAX

│   ├── index.php             # Página principal del sistema

│   ├── login.php             # Página de login

│   ├── dashboard.php         # Dashboard de métricas

│   ├── productos.php         # Listado de productos

│   ├── ventas.php            # Listado de ventas

│   ├── facturas.php          # Listado de facturas

│   ├── exportar.php          # Genera exportaciones Excel

│   ├── header.php            # Plantilla de encabezado

│   ├── footer.php            # Plantilla de pie de página

│

├── api/                      # Endpoints de la API REST

│   ├── api.php               # Controlador principal de API

│   ├── auth.php              # Autenticación

│   ├── productos.php         # Endpoints de productos

│   ├── ventas.php            # Endpoints de ventas

│   ├── facturas.php          # Endpoints de facturas

│

├── config/                   # Configuración del sistema

│   ├── database.php          # Conexión a MySQL

│   ├── config.php            # Variables globales

│

├── includes/                 # Funciones reutilizables

│   ├── session.php           # Manejo de sesiones

│   ├── security.php          # Funciones de seguridad

│   ├── excel_export.php      # Funciones para generar reportes Excel

│

├── vendor/                   # Librerías gestionadas por Composer

│

├── logs/                     # Registros de eventos

│   ├── access.log

│   ├── error.log

│

├── backups/                  # Carpeta para backups de BD

│

└── README.md                 # Documentación del sistema

## **5.1. Descripción del Funcionamiento de Cada Archivo**

### **Frontend (Interfaz de Usuario)**

* **header.php:** Contiene la estructura del encabezado, incluyendo el menú de navegación y enlaces a los archivos CSS y JS.  
* **footer.php:** Contiene el pie de página, con información de la empresa y scripts adicionales.  
* **index.php:** Página principal del sistema, muestra un resumen general con accesos directos a secciones clave.  
* **dashboard.php:** Página con métricas y gráficos del sistema.  
* **productos.php:** Página que muestra el listado de productos disponibles.  
* **ventas.php:** Página que muestra el historial de ventas del proveedor logueado.  
* **facturas.php:** Página que muestra el estado de las facturas.  
* **exportar.php:** Archivo que genera reportes en formato Excel usando PhpSpreadsheet.

### **API (Comunicación con el ERP y Backend)**

* **api.php:** Punto de entrada principal para la API REST.  
* **auth.php:** Manejo de autenticación de usuarios (login, logout y validación de sesión).  
* **productos.php:** Endpoint que maneja la consulta de productos desde el ERP.  
* **ventas.php:** Endpoint que maneja la consulta de ventas.  
* **facturas.php:** Endpoint que maneja la consulta de facturas.

### **Backend (Lógica de Negocio y Configuración)**

* **config/database.php:** Archivo de conexión a la base de datos MySQL.  
* **config/config.php:** Variables de configuración globales.  
* **includes/session.php:** Manejo de sesiones en PHP.  
* **includes/security.php:** Validaciones de seguridad contra SQL Injection, XSS y CSRF.  
* **includes/excel_export.php:** Funciones para generar reportes Excel con PhpSpreadsheet.
* **vendor/** Carpeta donde Composer almacena las librerías externas.

### **Otros Archivos y Directorios**

* **public/css/** Contiene los archivos CSS de Bootstrap y Tailwind CSS.  
* **public/js/app.js:** Contiene funciones generales de la interfaz de usuario.  
* **public/js/ajax.js:** Contiene las peticiones AJAX para mejorar la interactividad.  
* **logs/** Carpeta donde se almacenan logs de errores y accesos.  
* **backups/** Carpeta donde se almacenan backups de la base de datos.  
* **README.md:** Documentación general del sistema.

## **6. Funcionalidades de Exportación a Excel**

### **6.1. Descripción del Informe B2B**

El sistema debe permitir la exportación de datos a formato Excel, generando un informe B2B con las siguientes características:

* **Encabezado:** Logo y nombre de la empresa "TIENDA DE MASCOTAS ANIMALS CENTER LTDA"
* **Título del informe:** "Informe B2B"
* **Periodo:** Fecha de generación del informe

### **6.2. Contenido del Informe**

El informe debe contener información detallada para cada producto del proveedor actualmente logueado, con las siguientes columnas:

* **Código de Barras** (COD BARRA): Identificador único del producto
* **Nombre del Producto**: Descripción completa del producto
* **Sub Categoría**: Clasificación secundaria del producto
* **Stock Un**: Unidades disponibles en inventario
* **Ventas Un**: Unidades vendidas en el periodo seleccionado
* **Suma**: Valor total de las ventas
* **Valorizado**: Valor del inventario actual

### **6.3. Implementación Técnica**

Para la generación de informes Excel, se utilizará la librería **PhpSpreadsheet** con el siguiente proceso:

1. Instalar PhpSpreadsheet vía Composer
2. Implementar una función dedicada para la generación del informe
3. Agregar formato profesional (bordes, colores, fuentes)
4. Aplicar fórmulas para cálculos automáticos
5. Implementar filtros para facilitarle al usuario la visualización de datos
6. Generar el archivo Excel para descarga directa

### **6.4. Endpoints para Exportación**

Se creará un endpoint específico para manejar la exportación de datos:

* `GET /exportar.php?tipo=informe&fecha_inicio=YYYY-MM-DD&fecha_fin=YYYY-MM-DD` → Genera el informe B2B en formato Excel

## **7. Infraestructura y Despliegue**

* **Hosting:** Servidor propio en la empresa.  
* **Backups:** Copias de seguridad automáticas diarias.  
* **Gestión de logs:** Registros en archivos locales en el servidor.

## **8. Mantenimiento y Soporte**

* **Control de versiones con Git.**  
* **Pruebas en entorno de desarrollo antes de producción.**  
* **Sistema de tickets y documentación para usuarios.**

## **9. Conclusión**

Este documento define los lineamientos clave para el desarrollo del sistema B2B, garantizando una implementación segura, eficiente y escalable. Se recomienda seguir estas especificaciones para asegurar la calidad del software.
