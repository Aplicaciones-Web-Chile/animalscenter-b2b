<?php
/**
 * Gestión de sesiones
 * 
 * Proporciona una interfaz orientada a objetos para el manejo de sesiones
 * reutilizando la funcionalidad existente en includes/session.php
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

namespace App\Core;

class Session {
    /**
     * Constructor que inicia la sesión si no está activa
     */
    public function __construct() {
        $this->start();
    }
    
    /**
     * Inicia la sesión si no está iniciada
     * 
     * @return bool True si se inició la sesión, false si ya estaba iniciada
     */
    public function start() {
        if (session_status() === PHP_SESSION_NONE) {
            // Configurar opciones de sesión
            $options = [
                'cookie_path' => '/',
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax'
            ];
            
            session_start($options);
            return true;
        }
        return false;
    }
    
    /**
     * Establece un valor en la sesión
     * 
     * @param string $key Clave
     * @param mixed $value Valor
     * @return void
     */
    public function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Obtiene un valor de la sesión
     * 
     * @param string $key Clave
     * @param mixed $default Valor por defecto
     * @return mixed Valor o valor por defecto
     */
    public function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Verifica si existe una clave en la sesión
     * 
     * @param string $key Clave
     * @return bool True si existe
     */
    public function has($key) {
        return isset($_SESSION[$key]);
    }
    
    /**
     * Elimina un valor de la sesión
     * 
     * @param string $key Clave
     * @return void
     */
    public function remove($key) {
        unset($_SESSION[$key]);
    }
    
    /**
     * Obtiene y elimina un valor de la sesión
     * 
     * @param string $key Clave
     * @param mixed $default Valor por defecto
     * @return mixed Valor o valor por defecto
     */
    public function pull($key, $default = null) {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
    }
    
    /**
     * Obtiene todos los datos de la sesión
     * 
     * @return array Datos de la sesión
     */
    public function all() {
        return $_SESSION;
    }
    
    /**
     * Establece un mensaje flash (disponible solo para la siguiente petición)
     * 
     * @param string $key Clave
     * @param mixed $value Valor
     * @return void
     */
    public function flash($key, $value) {
        $this->set('_flash_' . $key, $value);
    }
    
    /**
     * Obtiene un mensaje flash
     * 
     * @param string $key Clave
     * @param mixed $default Valor por defecto
     * @return mixed Valor o valor por defecto
     */
    public function getFlash($key, $default = null) {
        return $this->pull('_flash_' . $key, $default);
    }
    
    /**
     * Regenera el ID de sesión
     * 
     * @param bool $deleteOldSession Si se debe eliminar la sesión anterior
     * @return bool Resultado de la operación
     */
    public function regenerate($deleteOldSession = true) {
        return session_regenerate_id($deleteOldSession);
    }
    
    /**
     * Elimina todos los datos de la sesión
     * 
     * @return void
     */
    public function clear() {
        $_SESSION = [];
    }
    
    /**
     * Destruye la sesión
     * 
     * @return bool Resultado de la operación
     */
    public function destroy() {
        // Limpiar variables de sesión
        $this->clear();
        
        // Destruir la cookie de sesión si existe
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
        
        // Destruir la sesión
        return session_destroy();
    }
    
    /**
     * Inicia sesión para un usuario
     * 
     * @param array $user Datos del usuario (id, nombre, email, rol, rut)
     * @return bool True si se inició sesión correctamente
     */
    public function login($user) {
        // Iniciar sesión si no está iniciada
        $this->start();
        
        // Regenerar ID de sesión para prevenir ataques de fijación de sesión
        $this->regenerate(true);
        
        // Almacenar información del usuario en la sesión
        $this->set('user', [
            'id' => $user['id'],
            'nombre' => $user['nombre'],
            'email' => $user['email'],
            'rol' => $user['rol'],
            'rut' => $user['rut'],
            'last_activity' => time()
        ]);
        
        // Registrar el inicio de sesión
        $this->registerLoginActivity($user['id']);
        
        return true;
    }
    
    /**
     * Cierra la sesión del usuario actual
     * 
     * @return void
     */
    public function logout() {
        // Iniciar sesión si no está iniciada
        $this->start();
        
        // Registrar cierre de sesión si hay usuario
        if ($this->has('user') && isset($this->get('user')['id'])) {
            $this->registerLogoutActivity($this->get('user')['id']);
        }
        
        // Destruir la sesión
        $this->destroy();
    }
    
    /**
     * Verifica si el usuario está autenticado
     * 
     * @return bool True si el usuario está autenticado
     */
    public function isAuthenticated() {
        return $this->has('user') && isset($this->get('user')['id']);
    }
    
    /**
     * Verifica si la sesión ha expirado por inactividad
     * 
     * @param int $timeout Tiempo de inactividad en segundos (por defecto 30 minutos)
     * @return bool True si la sesión ha expirado
     */
    public function hasExpired($timeout = 1800) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $user = $this->get('user');
        $lastActivity = $user['last_activity'] ?? 0;
        
        if (time() - $lastActivity > $timeout) {
            return true;
        }
        
        // Actualizar tiempo de última actividad
        $user['last_activity'] = time();
        $this->set('user', $user);
        
        return false;
    }
    
    /**
     * Registra actividad de inicio de sesión
     * 
     * @param int $userId ID del usuario
     * @return void
     */
    private function registerLoginActivity($userId) {
        // Incluir el archivo de función si es necesario
        if (!function_exists('registerLoginActivity')) {
            require_once APP_ROOT . '/includes/session.php';
        }
        
        // Usar la función existente
        if (function_exists('registerLoginActivity')) {
            registerLoginActivity($userId);
        }
    }
    
    /**
     * Registra actividad de cierre de sesión
     * 
     * @param int $userId ID del usuario
     * @return void
     */
    private function registerLogoutActivity($userId) {
        // Incluir el archivo de función si es necesario
        if (!function_exists('registerLogoutActivity')) {
            require_once APP_ROOT . '/includes/session.php';
        }
        
        // Usar la función existente
        if (function_exists('registerLogoutActivity')) {
            registerLogoutActivity($userId);
        }
    }
    
    /**
     * Obtiene información del usuario actual
     * 
     * @return array|null Datos del usuario o null si no está autenticado
     */
    public function getUser() {
        return $this->isAuthenticated() ? $this->get('user') : null;
    }
    
    /**
     * Verifica si el usuario actual tiene un rol específico
     * 
     * @param string $role Rol a verificar
     * @return bool True si el usuario tiene el rol
     */
    public function hasRole($role) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $user = $this->get('user');
        return isset($user['rol']) && $user['rol'] === $role;
    }
    
    /**
     * Verifica si el usuario actual es administrador
     * 
     * @return bool True si el usuario es administrador
     */
    public function isAdmin() {
        return $this->hasRole('admin');
    }
}
