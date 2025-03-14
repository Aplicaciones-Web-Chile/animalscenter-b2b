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

## Cómo levantar el proyecto

### Opción 1: Usando Docker (recomendado)

1. Asegúrate de tener Docker y Docker Compose instalados en tu sistema.

2. Desde la raíz del proyecto, ejecuta:
   ```
   docker-compose up -d
   ```

3. El sistema estará disponible en: http://localhost:8080

4. Para detener los contenedores:
   ```
   docker-compose down
   ```

### Opción 2: Servidor local (XAMPP, MAMP, etc.)

1. Configura un host virtual en tu servidor web local que apunte al directorio `public/` del proyecto.

2. Asegúrate de que PHP y MySQL estén en ejecución.

3. Accede al sistema a través de la URL configurada en tu host virtual.

### Opción 3: Servidor PHP integrado (solo para desarrollo)

1. Desde la raíz del proyecto, ejecuta:
   ```
   php -S localhost:8080 -t public/
   ```

2. El sistema estará disponible en: http://localhost:8080

## Credenciales de acceso

Para acceder al sistema puedes utilizar las siguientes credenciales de prueba:

### Administrador
- Email: admin@animalscenter.com
- Contraseña: password

### Proveedor de ejemplo
- Email: proveedor@ejemplo.com
- Contraseña: password

## Mantenimiento
Para actualizar el sistema a nuevas versiones:
```
git pull
composer update
```

## Licencia
Propiedad de AplicacionesWeb. Todos los derechos reservados.
