<?php
/**
 * Sistema de autenticación para la aplicación B2B
 * 
 * Esta clase proporciona métodos para autenticar usuarios, generar y validar
 * tokens de sesión, controlar intentos de acceso y registrar eventos de inicio
 * de sesión utilizando el sistema Logger.
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

// Importar dependencias
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/../config/database.php';

class AuthHandler {
    // Constantes de la clase
    const MAX_LOGIN_ATTEMPTS = 5;          // Máximo de intentos fallidos
    const LOGIN_LOCKOUT_TIME = 1800;       // Tiempo de bloqueo en segundos (30 min)
    const SESSION_DURATION = 7200;         // Duración de sesión en segundos (2 horas)
    const REMEMBER_ME_DURATION = 2592000;  // Duración de "Recordarme" (30 días)
    
    // Propiedades de la clase
    private $db;
    private $userId = null;
    private $userRole = null;
    private $errorMessage = '';
    
    /**
     * Constructor de la clase de autenticación
     */
    public function __construct() {
        // Obtener conexión a la base de datos usando la función de config/database.php
        $this->db = getDbConnection();
        
        // Iniciar sesión si no está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Verificar si ya hay una sesión activa
        if (isset($_SESSION['user']) && isset($_SESSION['user']['id'])) {
            $this->userId = $_SESSION['user']['id'];
            $this->userRole = $_SESSION['user']['role'] ?? 'user';
        }
        
        // Verificar si hay una cookie de "recordarme" y no hay sesión activa
        if (!$this->userId && isset($_COOKIE['remember_token'])) {
            $this->loginWithRememberToken($_COOKIE['remember_token']);
        }
    }
    
    /**
     * Intenta autenticar al usuario con credenciales proporcionadas
     * 
     * @param string $username Nombre de usuario o correo electrónico
     * @param string $password Contraseña sin procesar
     * @param bool $rememberMe Si debe recordar la sesión del usuario
     * @return bool True si la autenticación fue exitosa, false en caso contrario
     */
    public function login($username, $password, $rememberMe = false) {
        // Verificar si el usuario está bloqueado por demasiados intentos fallidos
        if ($this->isUserLocked($username)) {
            $this->errorMessage = 'La cuenta está temporalmente bloqueada por múltiples intentos fallidos. Intente más tarde.';
            Logger::log(Logger::WARNING, "Intento de acceso a cuenta bloqueada: $username", Logger::AUTH, [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ]);
            return false;
        }
        
        try {
            // Consultar usuario por username o email
            $stmt = $this->db->prepare("
                SELECT id, username, email, password, role, status, failed_attempts, last_failed_attempt
                FROM usuarios
                WHERE (username = :username OR email = :username)
                LIMIT 1
            ");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                // Usuario no encontrado
                $this->logFailedAttempt($username);
                $this->errorMessage = 'Credenciales inválidas.';
                return false;
            }
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verificar si la cuenta está activa
            if ($user['status'] !== 'active') {
                $this->errorMessage = 'La cuenta no está activa. Contacte al administrador.';
                Logger::log(Logger::WARNING, "Intento de acceso a cuenta inactiva: $username", Logger::AUTH);
                return false;
            }
            
            // Verificar contraseña
            if (!password_verify($password, $user['password'])) {
                // Contraseña incorrecta
                $this->logFailedAttempt($username, $user['id']);
                $this->errorMessage = 'Credenciales inválidas.';
                return false;
            }
            
            // Actualizar hash de contraseña si es necesario (rehash)
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $this->updatePasswordHash($user['id'], $password);
            }
            
            // Resetear contador de intentos fallidos
            $this->resetFailedAttempts($user['id']);
            
            // Iniciar sesión
            $this->setUserSession($user);
            
            // Crear token "recordarme" si se solicita
            if ($rememberMe) {
                $this->createRememberToken($user['id']);
            }
            
            // Registrar inicio de sesión exitoso
            Logger::log(Logger::INFO, "Inicio de sesión exitoso: {$user['username']}", Logger::AUTH, [
                'user_id' => $user['id'],
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            
            return true;
        } catch (PDOException $e) {
            // Registrar error en el sistema de logs
            Logger::log(Logger::ERROR, "Error en autenticación: " . $e->getMessage(), Logger::AUTH);
            $this->errorMessage = 'Error en el sistema de autenticación. Intente nuevamente más tarde.';
            return false;
        }
    }
    
    /**
     * Cierra la sesión del usuario actual
     * 
     * @param bool $allSessions Si es true, cierra todas las sesiones del usuario (rememberMe)
     * @return bool
     */
    public function logout($allSessions = false) {
        // Registrar cierre de sesión
        if ($this->userId) {
            Logger::log(Logger::INFO, "Cierre de sesión: Usuario ID {$this->userId}", Logger::AUTH);
            
            // Si se solicita cerrar todas las sesiones, eliminar tokens de "recordarme"
            if ($allSessions) {
                $this->deleteAllRememberTokens($this->userId);
            }
        }
        
        // Eliminar cookie de recordarme
        if (isset($_COOKIE['remember_token'])) {
            $this->deleteRememberToken($_COOKIE['remember_token']);
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
        
        // Limpiar y destruir la sesión
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        session_destroy();
        
        // Resetear propiedades
        $this->userId = null;
        $this->userRole = null;
        
        return true;
    }
    
    /**
     * Registra un intento de inicio de sesión fallido
     * 
     * @param string $username Nombre de usuario o correo
     * @param int|null $userId ID del usuario si está disponible
     */
    private function logFailedAttempt($username, $userId = null) {
        try {
            // Si tenemos el ID, actualizar contador de intentos
            if ($userId) {
                $stmt = $this->db->prepare("
                    UPDATE usuarios
                    SET failed_attempts = failed_attempts + 1,
                        last_failed_attempt = NOW()
                    WHERE id = :id
                ");
                $stmt->bindParam(':id', $userId);
                $stmt->execute();
            }
            
            // Registrar el intento fallido en los logs
            Logger::log(Logger::WARNING, "Intento de inicio de sesión fallido: $username", Logger::AUTH, [
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ]);
        } catch (PDOException $e) {
            Logger::log(Logger::ERROR, "Error al registrar intento fallido: " . $e->getMessage(), Logger::AUTH);
        }
    }
    
    /**
     * Verifica si un usuario está bloqueado por múltiples intentos fallidos
     * 
     * @param string $username Nombre de usuario o correo
     * @return bool
     */
    private function isUserLocked($username) {
        try {
            $stmt = $this->db->prepare("
                SELECT failed_attempts, last_failed_attempt
                FROM usuarios
                WHERE (username = :username OR email = :username)
                LIMIT 1
            ");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verificar intentos fallidos y tiempo
                if ($user['failed_attempts'] >= self::MAX_LOGIN_ATTEMPTS) {
                    $lastAttempt = strtotime($user['last_failed_attempt']);
                    $timeSince = time() - $lastAttempt;
                    
                    // Si no ha pasado el tiempo de bloqueo, mantener bloqueado
                    if ($timeSince < self::LOGIN_LOCKOUT_TIME) {
                        return true;
                    }
                }
            }
            
            return false;
        } catch (PDOException $e) {
            Logger::log(Logger::ERROR, "Error al verificar bloqueo: " . $e->getMessage(), Logger::AUTH);
            return false;
        }
    }
    
    /**
     * Resetea el contador de intentos fallidos de un usuario
     * 
     * @param int $userId ID del usuario
     */
    private function resetFailedAttempts($userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE usuarios
                SET failed_attempts = 0,
                    last_failed_attempt = NULL
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $userId);
            $stmt->execute();
        } catch (PDOException $e) {
            Logger::log(Logger::ERROR, "Error al resetear intentos fallidos: " . $e->getMessage(), Logger::AUTH);
        }
    }
    
    /**
     * Establece la sesión del usuario después de un inicio de sesión exitoso
     * 
     * @param array $user Datos del usuario
     */
    private function setUserSession($user) {
        // Regenerar ID de sesión para prevenir session fixation
        session_regenerate_id(true);
        
        // Establecer variables de sesión
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'last_activity' => time()
        ];
        
        // Establecer propiedades de la clase
        $this->userId = $user['id'];
        $this->userRole = $user['role'];
    }
    
    /**
     * Crea un token "recordarme" para el usuario
     * 
     * @param int $userId ID del usuario
     * @return bool
     */
    private function createRememberToken($userId) {
        try {
            // Generar token único
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', time() + self::REMEMBER_ME_DURATION);
            
            // Guardar token en la base de datos
            $stmt = $this->db->prepare("
                INSERT INTO auth_tokens (user_id, token, expires, ip_address, user_agent)
                VALUES (:user_id, :token, :expires, :ip, :agent)
            ");
            
            $ip = $_SERVER['REMOTE_ADDR'];
            $agent = $_SERVER['HTTP_USER_AGENT'];
            
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':token', $tokenHash);
            $stmt->bindParam(':expires', $expires);
            $stmt->bindParam(':ip', $ip);
            $stmt->bindParam(':agent', $agent);
            $stmt->execute();
            
            // Establecer cookie
            setcookie(
                'remember_token',
                $token,
                time() + self::REMEMBER_ME_DURATION,
                '/',
                '',                  // Dominio
                isset($_SERVER['HTTPS']), // Seguro solo si es HTTPS
                true                 // HttpOnly
            );
            
            return true;
        } catch (Exception $e) {
            Logger::log(Logger::ERROR, "Error al crear token remember me: " . $e->getMessage(), Logger::AUTH);
            return false;
        }
    }
    
    /**
     * Elimina un token "recordarme" específico
     * 
     * @param string $token Token a eliminar
     * @return bool
     */
    private function deleteRememberToken($token) {
        try {
            $tokenHash = hash('sha256', $token);
            $stmt = $this->db->prepare("DELETE FROM auth_tokens WHERE token = :token");
            $stmt->bindParam(':token', $tokenHash);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            Logger::log(Logger::ERROR, "Error al eliminar remember token: " . $e->getMessage(), Logger::AUTH);
            return false;
        }
    }
    
    /**
     * Elimina todos los tokens "recordarme" de un usuario
     * 
     * @param int $userId ID del usuario
     * @return bool
     */
    private function deleteAllRememberTokens($userId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM auth_tokens WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            Logger::log(Logger::ERROR, "Error al eliminar todos los tokens: " . $e->getMessage(), Logger::AUTH);
            return false;
        }
    }
    
    /**
     * Intenta iniciar sesión usando un token "recordarme"
     * 
     * @param string $token Token de la cookie
     * @return bool
     */
    private function loginWithRememberToken($token) {
        try {
            $tokenHash = hash('sha256', $token);
            
            // Buscar token válido
            $stmt = $this->db->prepare("
                SELECT t.user_id, t.expires, u.username, u.email, u.role, u.status
                FROM auth_tokens t
                JOIN usuarios u ON u.id = t.user_id
                WHERE t.token = :token AND t.expires > NOW() AND u.status = 'active'
                LIMIT 1
            ");
            $stmt->bindParam(':token', $tokenHash);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                // Token no válido o expirado, eliminar cookie
                setcookie('remember_token', '', time() - 3600, '/', '', true, true);
                return false;
            }
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Iniciar sesión
            $this->setUserSession([
                'id' => $result['user_id'],
                'username' => $result['username'],
                'email' => $result['email'],
                'role' => $result['role'],
                'status' => $result['status']
            ]);
            
            // Renovar token
            $this->deleteRememberToken($token);
            $this->createRememberToken($result['user_id']);
            
            // Registrar inicio de sesión con token
            Logger::log(Logger::INFO, "Inicio de sesión con token remember: {$result['username']}", Logger::AUTH, [
                'user_id' => $result['user_id'],
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            
            return true;
        } catch (PDOException $e) {
            Logger::log(Logger::ERROR, "Error en login con token: " . $e->getMessage(), Logger::AUTH);
            return false;
        }
    }
    
    /**
     * Actualiza el hash de la contraseña de un usuario (por ejemplo, cuando se usa un algoritmo más seguro)
     * 
     * @param int $userId ID del usuario
     * @param string $plainPassword Contraseña en texto plano
     * @return bool
     */
    private function updatePasswordHash($userId, $plainPassword) {
        try {
            $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
            
            $stmt = $this->db->prepare("
                UPDATE usuarios
                SET password = :password
                WHERE id = :id
            ");
            
            $stmt->bindParam(':password', $newHash);
            $stmt->bindParam(':id', $userId);
            $stmt->execute();
            
            return true;
        } catch (PDOException $e) {
            Logger::log(Logger::ERROR, "Error al actualizar hash de contraseña: " . $e->getMessage(), Logger::AUTH);
            return false;
        }
    }
    
    /**
     * Verifica si el usuario actual tiene un rol específico
     * 
     * @param string|array $roles Rol o roles a verificar
     * @return bool
     */
    public function hasRole($roles) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        return in_array($this->userRole, $roles);
    }
    
    /**
     * Verifica si el usuario está autenticado
     * 
     * @return bool
     */
    public function isLoggedIn() {
        return $this->userId !== null;
    }
    
    /**
     * Devuelve el ID del usuario actual
     * 
     * @return int|null
     */
    public function getUserId() {
        return $this->userId;
    }
    
    /**
     * Devuelve el rol del usuario actual
     * 
     * @return string|null
     */
    public function getUserRole() {
        return $this->userRole;
    }
    
    /**
     * Devuelve información completa del usuario actual
     * 
     * @return array|null
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, role, status, created_at, last_login
                FROM usuarios
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->bindParam(':id', $this->userId);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::log(Logger::ERROR, "Error al obtener datos del usuario: " . $e->getMessage(), Logger::AUTH);
            return null;
        }
    }
    
    /**
     * Obtiene el último mensaje de error
     * 
     * @return string
     */
    public function getErrorMessage() {
        return $this->errorMessage;
    }
    
    /**
     * Verifica y actualiza el tiempo de actividad del usuario
     * Cierra la sesión si ha expirado
     * 
     * @return bool True si la sesión es válida, false si expiró
     */
    public function validateSession() {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $now = time();
        $lastActivity = $_SESSION['user']['last_activity'] ?? 0;
        
        // Verificar si la sesión ha expirado
        if ($now - $lastActivity > self::SESSION_DURATION) {
            // Sesión expirada, cerrar sesión
            $this->logout();
            $this->errorMessage = 'La sesión ha expirado por inactividad. Por favor, inicie sesión nuevamente.';
            return false;
        }
        
        // Actualizar tiempo de última actividad
        $_SESSION['user']['last_activity'] = $now;
        return true;
    }
    
    /**
     * Genera un token CSRF para formularios
     * 
     * @return string
     */
    public function generateCSRFToken() {
        // Asegurar que la sesión está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        
        // Registrar la creación del token para depuración
        Logger::debug("Token CSRF generado", Logger::SECURITY, [
            'token_hash' => hash('sha256', $token),
            'session_id' => session_id()
        ]);
        
        return $token;
    }
    
    /**
     * Valida un token CSRF
     * 
     * @param string $token Token a validar
     * @return bool
     */
    public function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        $valid = hash_equals($_SESSION['csrf_token'], $token);
        
        // Generar nuevo token después de validar (one-time use)
        $this->generateCSRFToken();
        
        return $valid;
    }
}
