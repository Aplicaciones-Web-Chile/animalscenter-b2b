-- Estructura de la Base de Datos para el Sistema B2B
-- AnimalsCenter

-- Eliminar tablas si existen para recrearlas
DROP TABLE IF EXISTS facturas;
DROP TABLE IF EXISTS ventas;
DROP TABLE IF EXISTS productos;
DROP TABLE IF EXISTS usuarios;

-- Tabla de usuarios
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'proveedor') NOT NULL,
    rut VARCHAR(12) UNIQUE NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de productos
CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    sku VARCHAR(50) UNIQUE NOT NULL,
    stock INT NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    proveedor_rut VARCHAR(12) NOT NULL,
    FOREIGN KEY (proveedor_rut) REFERENCES usuarios(rut) ON DELETE CASCADE
);

-- Tabla de ventas
CREATE TABLE ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    proveedor_rut VARCHAR(12) NOT NULL,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (proveedor_rut) REFERENCES usuarios(rut) ON DELETE CASCADE
);

-- Tabla de facturas
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

-- Crear tabla de proveedores_marcas si no existe
CREATE TABLE IF NOT EXISTS proveedores_marcas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id INT NOT NULL,
    marca_id VARCHAR(10) NOT NULL,
    marca_nombre VARCHAR(100) NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_proveedor_marca (proveedor_id, marca_id),
    FOREIGN KEY (proveedor_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Datos de ejemplo (opcional)
INSERT INTO usuarios (nombre, email, password_hash, rol, rut) VALUES 
('Administrador', 'admin@animalscenter.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '11.111.111-1'),
('Proveedor Ejemplo', 'proveedor@ejemplo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'proveedor', '22.222.222-2');

-- Nota: El hash de contraseña es 'password' para ambos usuarios (solo para desarrollo)
