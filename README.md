# Sistema B2B - AnimalsCenter

Este sistema B2B permite a los proveedores acceder a información de sus productos, ventas, facturas y stock en tiempo real, integrado con el ERP existente de la empresa.

## Requisitos del Sistema

* PHP 7.4 o superior
* MySQL 5.7 o superior
* Servidor web (Apache/Nginx)
* Composer (para gestión de dependencias)
* SSL para seguridad en producción

## Instalación

1. Clonar el repositorio:
   ```
   git clone [URL_DEL_REPOSITORIO]
   cd b2b-app
   ```

2. Instalar dependencias con Composer:
   ```
   composer install
   ```

3. Configurar la base de datos:
   - Crear una base de datos MySQL
   - Copiar `.env.example` a `.env` y configurar los parámetros de conexión

4. Importar la estructura de la base de datos:
   ```
   mysql -u [usuario] -p [nombre_base_datos] < database/structure.sql
   ```

5. Configurar el servidor web para que apunte al directorio `public/`

6. Asegurarse de que los directorios de almacenamiento tengan permisos de escritura:
   ```
   chmod -R 775 storage/
   ```

## Uso del Sistema

### Acceso para Proveedores
Los proveedores pueden acceder al sistema mediante la URL designada utilizando sus credenciales de acceso (email y contraseña).

### Funcionalidades Principales
* Consulta de productos y stock en tiempo real
* Visualización de ventas realizadas
* Seguimiento de facturas y estados de pago
* Exportación de reportes en Excel

## Seguridad
* Comunicación segura a través de SSL
* Protección contra SQL Injection, XSS y CSRF
* Autenticación segura mediante sesiones en PHP
* Restricciones por IP para acceso a áreas sensibles

## Mantenimiento
Para actualizar el sistema a nuevas versiones:
```
git pull
composer update
```

## Licencia
Propiedad de AnimalsCenter. Todos los derechos reservados.
