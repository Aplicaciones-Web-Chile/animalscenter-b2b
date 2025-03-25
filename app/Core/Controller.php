<?php
/**
 * Controlador base
 * 
 * Proporciona funcionalidades comunes para todos los controladores
 * de la aplicación, como renderizado de vistas y acceso a servicios
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

namespace App\Core;

abstract class Controller {
    protected $request;
    protected $response;
    protected $session;
    protected $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->request = new Request();
        $this->response = new Response();
        $this->session = new Session();
        $this->db = Database::getInstance();
    }
    
    /**
     * Renderiza una vista
     * 
     * @param string $view Ruta de la vista
     * @param array $data Datos para la vista
     * @param int $statusCode Código de estado HTTP
     * @return void
     */
    protected function view($view, $data = [], $statusCode = 200) {
        // Agregar algunas variables globales a todas las vistas
        $data['session'] = $this->session;
        $data['currentUser'] = $this->session->getUser();
        $data['isAuthenticated'] = $this->session->isAuthenticated();
        
        $this->response->view($view, $data, $statusCode);
    }
    
    /**
     * Renderiza una vista dentro de un layout
     * 
     * @param string $view Ruta de la vista
     * @param string $layout Ruta del layout
     * @param array $data Datos para la vista
     * @param int $statusCode Código de estado HTTP
     * @return void
     */
    protected function viewWithLayout($view, $layout = 'layout/main', $data = [], $statusCode = 200) {
        // Capturar la salida de la vista
        ob_start();
        
        // Extraer variables para la vista
        extract($data);
        
        // Incluir la vista
        $viewPath = APP_ROOT . "/app/Views/$view.php";
        
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            throw new \Exception("Vista no encontrada: $view");
        }
        
        // Obtener el contenido de la vista
        $content = ob_get_clean();
        
        // Agregar el contenido a los datos para el layout
        $data['content'] = $content;
        
        // Renderizar el layout
        $this->view($layout, $data, $statusCode);
    }
    
    /**
     * Envía una respuesta JSON
     * 
     * @param mixed $data Datos a enviar como JSON
     * @param int $statusCode Código de estado HTTP
     * @return void
     */
    protected function json($data, $statusCode = 200) {
        $this->response->json($data, $statusCode);
    }
    
    /**
     * Redirige a otra URL
     * 
     * @param string $url URL de destino
     * @param int $statusCode Código de estado HTTP
     * @return void
     */
    protected function redirect($url, $statusCode = 302) {
        $this->response->redirect($url, $statusCode);
    }
    
    /**
     * Respuesta de éxito
     * 
     * @param string $message Mensaje de éxito
     * @param mixed $data Datos adicionales
     * @param int $statusCode Código de estado HTTP
     * @return void
     */
    protected function success($message, $data = null, $statusCode = 200) {
        $response = ['success' => true, 'message' => $message];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        $this->json($response, $statusCode);
    }
    
    /**
     * Respuesta de error
     * 
     * @param string $message Mensaje de error
     * @param mixed $errors Errores detallados
     * @param int $statusCode Código de estado HTTP
     * @return void
     */
    protected function error($message, $errors = null, $statusCode = 400) {
        $response = ['success' => false, 'message' => $message];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        $this->json($response, $statusCode);
    }
    
    /**
     * Verifica si el usuario está autenticado, redirige a login si no lo está
     * 
     * @return bool True si está autenticado
     */
    protected function requireAuth() {
        if (!$this->session->isAuthenticated()) {
            // Guardar URL actual para redirección después del login
            $this->session->set('redirect_after_login', $_SERVER['REQUEST_URI']);
            
            // Redirigir a login
            $this->redirect('/login');
            return false;
        }
        
        return true;
    }
    
    /**
     * Verifica si el usuario tiene un rol específico
     * 
     * @param string|array $roles Rol o roles permitidos
     * @return bool True si tiene permiso
     */
    protected function requireRole($roles) {
        if (!$this->requireAuth()) {
            return false;
        }
        
        // Convertir a array si es un solo rol
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        $userRole = $this->session->get('user')['rol'] ?? '';
        
        if (!in_array($userRole, $roles)) {
            // No tiene permiso
            $this->response->forbidden('No tiene permiso para acceder a esta página');
            return false;
        }
        
        return true;
    }
}
