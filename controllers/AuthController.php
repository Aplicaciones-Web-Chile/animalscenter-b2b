<?php
/**
 * Controlador de Autenticación Simplificado
 * 
 * Este controlador maneja las acciones básicas de autenticación
 * del sistema B2B de AnimalsCenter.
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 2.0
 */

require_once __DIR__ . '/../models/UserModel.php';

class AuthController {
    private $userModel;
    
    /**
     * Constructor del controlador
     */
    public function __construct() {
        $this->userModel = new UserModel();
    }
    
    /**
     * Procesa el intento de inicio de sesión
     * 
     * @param string $email Email del usuario
     * @param string $password Contraseña
     * @return array Resultado de la operación y mensaje
     */
    public function login($email, $password) {
        try {
            // Validaciones básicas
            if (empty($email) || empty($password)) {
                return [
                    'success' => false, 
                    'message' => 'Debe proporcionar email y contraseña'
                ];
            }
            
            // Buscar usuario por email
            $user = $this->userModel->findByUsernameOrEmail($email);
            
            // Verificar si el usuario existe y la contraseña es correcta
            if (!$user || !password_verify($password, $user['password_hash'])) {
                return [
                    'success' => false, 
                    'message' => 'Credenciales inválidas'
                ];
            }
            
            // Iniciar sesión
            require_once __DIR__ . '/../includes/session.php';
            startSession();
            
            // Almacenar datos en sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['rol'] = $user['rol'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['logged_in'] = true;
            
            // Guardar el RUT del proveedor en la sesión si el usuario es proveedor
            if ($user['rol'] === 'proveedor' && isset($user['rut'])) {
                $_SESSION['rut_proveedor'] = $user['rut'];
            }
            
            return [
                'success' => true,
                'message' => 'Bienvenido ' . $user['nombre'],
                'redirect' => 'dashboard.php'
            ];
            
        } catch (\Exception $e) {
            error_log("Error en proceso de login: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error interno del sistema'
            ];
        }
    }
    
    // Se han eliminado las funciones de CSRF para simplificar la autenticación
    
    /**
     * Genera token para "recordarme" y lo almacena
     * 
     * @param int $userId ID del usuario
     * @return void
     */
    private function handleRememberMe($userId) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $this->userModel->updateRememberToken($userId, $token, $expires);
        
        // Establecer cookie segura
        $cookieName = 'remember_token';
        $path = '/';
        $domain = '';
        $secure = isset($_SERVER['HTTPS']); 
        $httpOnly = true;
        
        setcookie(
            $cookieName,
            $token,
            strtotime('+30 days'),
            $path,
            $domain,
            $secure,
            $httpOnly
        );
    }
    
    /**
     * Procesa el cierre de sesión
     * 
     * @return array Resultado de la operación
     */
    public function logout() {
        // Eliminar cookie de "recordarme" si existe
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        // Registrar evento
        if (isset($_SESSION['user_id'])) {
            Logger::info("Cierre de sesión", Logger::AUTH, [
                'user_id' => $_SESSION['user_id']
            ]);
        }
        
        // Destruir sesión
        session_unset();
        session_destroy();
        
        return [
            'success' => true,
            'message' => 'Sesión finalizada correctamente'
        ];
    }
    
    /**
     * Verifica si el usuario está autenticado
     * 
     * @return bool Estado de autenticación
     */
    public function isLoggedIn() {
        // Si ya hay una sesión activa
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            return true;
        }
        
        // Verificar cookie "recordarme"
        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $user = $this->userModel->findByRememberToken($token);
            
            if ($user) {
                // Crear sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                $_SESSION['last_activity'] = time();
                
                Logger::info("Inicio de sesión automático (remember me)", 
                    Logger::AUTH, ['user_id' => $user['id']]);
                
                return true;
            }
        }
        
        return false;
    }
}
