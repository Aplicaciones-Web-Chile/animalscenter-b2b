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

## **5. Estructura de Archivos (MVC)**

b2b-system/

├── public/                   # Punto de entrada de la aplicación (Document Root)
│   ├── index.php             # Front Controller - Único punto de entrada
│   ├── css/                  # Archivos CSS
│   │   ├── bootstrap.min.css
│   │   ├── tailwind.min.css
│   │   └── style.css         # Estilos personalizados
│   ├── js/                   # Archivos JavaScript
│   │   ├── app.js            # Funciones JS generales
│   │   ├── ajax.js           # Manejo de peticiones AJAX
│   │   └── forms.js          # Validación de formularios
│   ├── img/                  # Imágenes
│   └── .htaccess             # Configuración para redireccionar todo a index.php
│
├── app/                      # Código principal de la aplicación
│   ├── Controllers/          # Controladores MVC
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── FacturaController.php
│   │   ├── PasswordController.php
│   │   ├── ProductoController.php
│   │   ├── TransactionController.php
│   │   ├── VentaController.php
│   │   └── Controller.php    # Clase base abstracta para controladores
│   │
│   ├── Models/               # Modelos MVC
│   │   ├── DashboardModel.php
│   │   ├── FacturaModel.php
│   │   ├── ProductoModel.php
│   │   ├── TransactionModel.php
│   │   ├── UserModel.php
│   │   ├── VentaModel.php
│   │   └── Model.php         # Clase base abstracta para modelos
│   │
│   ├── Views/                # Vistas MVC (Plantillas de interfaz)
│   │   ├── auth/
│   │   │   ├── login.php
│   │   │   ├── recuperar-password.php
│   │   │   └── restablecer-password.php
│   │   ├── dashboard/
│   │   │   └── index.php
│   │   ├── facturas/
│   │   │   ├── index.php
│   │   │   └── detalle.php
│   │   ├── productos/
│   │   │   ├── index.php
│   │   │   └── detalle.php
│   │   ├── ventas/
│   │   │   ├── index.php
│   │   │   └── detalle.php
│   │   ├── layout/
│   │   │   ├── header.php
│   │   │   ├── footer.php
│   │   │   ├── sidebar.php
│   │   │   └── master.php    # Plantilla principal
│   │   ├── export/
│   │   │   ├── excel.php
│   │   │   └── pdf.php
│   │   └── error/
│   │       ├── 404.php
│   │       └── 500.php
│   │
│   ├── Services/             # Servicios y lógica de negocio compleja
│   │   ├── AuthService.php   # Servicio de autenticación
│   │   ├── ExportService.php # Servicio de exportación Excel/PDF
│   │   ├── ApiService.php    # Cliente para comunicación con APIs externas
│   │   └── ValidationService.php # Validación de datos
│   │
│   ├── Core/                 # Componentes del núcleo de la aplicación
│   │   ├── Router.php        # Sistema de rutas
│   │   ├── Request.php       # Abstracción de peticiones HTTP
│   │   ├── Response.php      # Abstracción de respuestas HTTP
│   │   ├── Session.php       # Gestión de sesiones
│   │   ├── Database.php      # Conexión a base de datos
│   │   └── App.php           # Clase principal de la aplicación
│   │
│   └── Helpers/              # Funciones auxiliares
│       ├── AuthHelper.php    # Funciones de autenticación
│       ├── SecurityHelper.php # Funciones de seguridad
│       ├── ExcelHelper.php   # Generación de archivos Excel
│       └── ValidationHelper.php # Validación de datos
│
├── api/                      # API REST del sistema
│   ├── index.php             # Front Controller para la API
│   ├── endpoints/
│   │   ├── AuthEndpoint.php
│   │   ├── ProductosEndpoint.php
│   │   ├── VentasEndpoint.php
│   │   └── FacturasEndpoint.php
│   └── .htaccess             # Configuración para la API
│
├── config/                   # Configuración del sistema
│   ├── app.php               # Configuración general
│   ├── database.php          # Configuración de base de datos
│   ├── routes.php            # Definición de rutas
│   └── middleware.php        # Configuración de middleware
│
├── database/                 # Archivos relacionados con la base de datos
│   ├── migrations/           # Migraciones de base de datos
│   └── seeds/                # Datos de prueba
│
├── storage/                  # Almacenamiento de archivos
│   ├── logs/                 # Registros de eventos
│   │   ├── access.log
│   │   └── error.log
│   ├── cache/                # Archivos de caché
│   ├── uploads/              # Archivos subidos por usuarios
│   └── exports/              # Archivos exportados temporales
│
├── tests/                    # Pruebas automatizadas
│   ├── Unit/                 # Pruebas unitarias
│   └── Integration/          # Pruebas de integración
│
├── vendor/                   # Librerías gestionadas por Composer
│
├── .env                      # Variables de entorno
├── .env.example              # Ejemplo de variables de entorno
├── composer.json             # Dependencias de Composer
├── composer.lock             # Versiones bloqueadas de dependencias
├── README.md                 # Documentación
├── phpunit.xml               # Configuración de pruebas
└── .gitignore                # Archivos ignorados por Git

## **5.1. Descripción del Funcionamiento de Cada Archivo**

### **Estructura MVC**

#### **Capa de Presentación (Vista)**

* **app/Views/**: Contiene todas las plantillas organizadas por secciones.
  * **app/Views/layout/master.php:** Plantilla principal que estructura todas las páginas.
  * **app/Views/layout/header.php:** Encabezado con menú de navegación.
  * **app/Views/layout/footer.php:** Pie de página con información corporativa.
  * **app/Views/layout/sidebar.php:** Barra lateral con menú de navegación secundario.
  * **app/Views/auth/**: Plantillas para autenticación (login, recuperación de contraseña).
  * **app/Views/dashboard/**: Plantillas para el panel de control y visualización de métricas.
  * **app/Views/productos/**: Plantillas para gestión de productos.
  * **app/Views/ventas/**: Plantillas para gestión y visualización de ventas.
  * **app/Views/facturas/**: Plantillas para visualización y gestión de facturas.
  * **app/Views/export/**: Plantillas para exportación de datos en diferentes formatos.
  * **app/Views/error/**: Plantillas para páginas de error (404, 500, etc.).

#### **Capa de Control (Controlador)**

* **app/Controllers/Controller.php:** Clase base abstracta que define la estructura para todos los controladores.
* **app/Controllers/AuthController.php:** Gestiona la autenticación de usuarios y seguridad.
* **app/Controllers/DashboardController.php:** Administra la visualización de métricas del panel principal.
* **app/Controllers/ProductoController.php:** Controla la gestión de productos.
* **app/Controllers/VentaController.php:** Administra las ventas y su visualización.
* **app/Controllers/FacturaController.php:** Controla la gestión de facturas.
* **app/Controllers/TransactionController.php:** Administra las transacciones financieras.
* **app/Controllers/PasswordController.php:** Gestiona la recuperación y cambio de contraseñas.

#### **Capa de Modelo (Datos)**

* **app/Models/Model.php:** Clase base abstracta que define la estructura para todos los modelos.
* **app/Models/UserModel.php:** Gestiona operaciones relacionadas con usuarios en la base de datos.
* **app/Models/ProductoModel.php:** Administra operaciones de productos en la base de datos.
* **app/Models/VentaModel.php:** Gestiona operaciones relacionadas con ventas.
* **app/Models/FacturaModel.php:** Administra operaciones relacionadas con facturas.
* **app/Models/TransactionModel.php:** Gestiona operaciones relacionadas con transacciones financieras.
* **app/Models/DashboardModel.php:** Maneja consultas para métricas del panel de control.

### **Capas de Servicio**

* **app/Services/**: Contiene servicios que implementan lógica de negocio compleja.
  * **app/Services/AuthService.php:** Implementa la lógica de autenticación y autorización.
  * **app/Services/ExportService.php:** Gestiona la generación de exportaciones en diferentes formatos.
  * **app/Services/ApiService.php:** Proporciona funcionalidad para comunicarse con APIs externas.
  * **app/Services/ValidationService.php:** Implementa validación avanzada de datos de formularios.

### **Núcleo de la Aplicación**

* **app/Core/**: Componentes críticos del sistema MVC.
  * **app/Core/App.php:** Clase principal que inicializa y coordina toda la aplicación.
  * **app/Core/Router.php:** Sistema de enrutamiento que mapea URLs a controladores y acciones.
  * **app/Core/Request.php:** Abstracción de peticiones HTTP entrantes.
  * **app/Core/Response.php:** Abstracción de respuestas HTTP salientes.
  * **app/Core/Session.php:** Gestión de sesiones de usuario.
  * **app/Core/Database.php:** Gestión de conexiones a base de datos.

### **API REST**

* **api/index.php:** Punto de entrada único para todas las solicitudes a la API.
* **api/endpoints/**: Contiene los controladores específicos para la API.
  * **api/endpoints/AuthEndpoint.php:** Gestiona autenticación API mediante tokens.
  * **api/endpoints/ProductosEndpoint.php:** Maneja operaciones de productos vía API.
  * **api/endpoints/VentasEndpoint.php:** Gestiona operaciones relacionadas con ventas vía API.
  * **api/endpoints/FacturasEndpoint.php:** Administra operaciones de facturas vía API.

### **Configuración**

* **config/app.php:** Configuración general de la aplicación.
* **config/database.php:** Configuración de conexión a la base de datos.
* **config/routes.php:** Definición de rutas URL de la aplicación.
* **config/middleware.php:** Configuración de middleware para procesamiento de peticiones.
* **.env:** Variables de entorno y configuración sensible (no versionada).
* **.env.example:** Plantilla de configuración de entorno para desarrollo.

### **Archivos Públicos**

* **public/index.php:** Único punto de entrada a la aplicación web (Front Controller).
* **public/css/**: Archivos CSS para estilizar la interfaz.
* **public/js/**: Archivos JavaScript para funcionalidad del lado del cliente.
* **public/img/**: Imágenes y recursos gráficos.
* **public/.htaccess:** Configuración para redirigir todas las peticiones a index.php.

### **Almacenamiento**

* **storage/logs/**: Almacena registros de eventos y errores.
* **storage/cache/**: Almacena archivos de caché para mejorar el rendimiento.
* **storage/uploads/**: Almacena archivos subidos por usuarios.
* **storage/exports/**: Almacena archivos generados para exportación.

### **Pruebas**

* **tests/Unit/**: Pruebas unitarias para componentes individuales.
* **tests/Integration/**: Pruebas de integración para verificar la interacción entre componentes.
* **phpunit.xml:** Configuración para el framework de pruebas PHPUnit.

### **Otros**

* **database/migrations/**: Scripts para la creación y modificación de la estructura de la base de datos.
* **database/seeds/**: Scripts para poblar la base de datos con datos iniciales o de prueba.
* **vendor/**: Librerías de terceros gestionadas por Composer.
* **composer.json:** Configuración de dependencias del proyecto.
* **README.md:** Documentación general del sistema y guía de instalación.

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
