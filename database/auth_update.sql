-- Script de actualización para sistema de autenticación
-- AnimalsCenter B2B

-- Modificar la tabla de usuarios para agregar campos de seguridad
ALTER TABLE usuarios 
ADD COLUMN username VARCHAR(50) UNIQUE NULL COMMENT 'Nombre de usuario para iniciar sesión',
ADD COLUMN status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active' COMMENT 'Estado de la cuenta',
ADD COLUMN failed_attempts INT NOT NULL DEFAULT 0 COMMENT 'Número de intentos fallidos de inicio de sesión',
ADD COLUMN last_failed_attempt DATETIME NULL COMMENT 'Fecha y hora del último intento fallido',
ADD COLUMN last_login DATETIME NULL COMMENT 'Fecha y hora del último inicio de sesión exitoso';

-- Actualizar usuarios existentes para establecer un nombre de usuario
UPDATE usuarios SET username = CONCAT(SUBSTRING_INDEX(email, '@', 1), '_', id) WHERE username IS NULL;

-- Modificar el campo username para que no sea NULL después de actualizar los datos existentes
ALTER TABLE usuarios 
MODIFY COLUMN username VARCHAR(50) NOT NULL COMMENT 'Nombre de usuario para iniciar sesión',
MODIFY COLUMN password_hash VARCHAR(255) NOT NULL COMMENT 'Hash de la contraseña',
CHANGE COLUMN rol role ENUM('admin', 'proveedor', 'usuario') NOT NULL COMMENT 'Rol del usuario';

-- Crear tabla para tokens de autenticación (función "Recordarme")
CREATE TABLE auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'ID del usuario',
    token VARCHAR(64) NOT NULL COMMENT 'Token de autenticación (hash)',
    expires DATETIME NOT NULL COMMENT 'Fecha y hora de expiración',
    ip_address VARCHAR(45) NULL COMMENT 'Dirección IP del cliente',
    user_agent TEXT NULL COMMENT 'Agente de usuario del cliente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación',
    UNIQUE (token),
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) COMMENT 'Tokens para la función Recordarme';

-- Crear tabla para registrar eventos de autenticación
CREATE TABLE auth_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'ID del usuario (NULL si es intento fallido sin usuario existente)',
    event_type ENUM('login', 'logout', 'failed_attempt', 'password_reset', 'token_login') NOT NULL COMMENT 'Tipo de evento',
    ip_address VARCHAR(45) NULL COMMENT 'Dirección IP del cliente',
    user_agent TEXT NULL COMMENT 'Agente de usuario del cliente',
    details TEXT NULL COMMENT 'Detalles adicionales del evento',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora del evento',
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL
) COMMENT 'Registro de eventos de autenticación';

-- Crear índices para mejorar el rendimiento de las consultas
CREATE INDEX idx_auth_tokens_user_id ON auth_tokens(user_id);
CREATE INDEX idx_auth_tokens_expires ON auth_tokens(expires);
CREATE INDEX idx_auth_logs_user_id ON auth_logs(user_id);
CREATE INDEX idx_auth_logs_event_type ON auth_logs(event_type);
CREATE INDEX idx_auth_logs_created_at ON auth_logs(created_at);
