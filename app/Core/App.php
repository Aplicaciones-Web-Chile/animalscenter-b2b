<?php
/**
 * Clase principal de la aplicación
 * 
 * Actúa como el punto de entrada principal para la aplicación,
 * coordinando todos los componentes y manejando el ciclo de vida de la solicitud.
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

namespace App\Core;

class App {
    private static $instance = null;
    private $router;
    private $request;
    private $response;
    private $config = [];
    
    /**
     * Constructor privado (patrón Singleton)
     */
    private function __construct() {
        $this->router = new Router();
        $this->request = new Request();
        $this->response = new Response();
        $this->loadConfig();
    }
    
    /**
     * Obtiene la instancia única de la aplicación (Singleton)
     * 
     * @return App Instancia de la aplicación
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inicia la aplicación
     */
    public function run() {
        // Configurar el entorno
        $this->bootstrap();
        
        // Procesar la solicitud
        $this->processRequest();
    }
    
    /**
     * Configura el entorno de la aplicación
     */
    private function bootstrap() {
        // Cargar configuración
        $this->loadConfig();
        
        // Configurar errores según el entorno
        $this->configureErrorHandling();
        
        // Iniciar sesión si no está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Cargar rutas
        $this->loadRoutes();
    }
    
    /**
     * Carga la configuración de la aplicación
     */
    private function loadConfig() {
        // Cargar configuración principal
        if (file_exists(APP_ROOT . '/config/app.php')) {
            $this->config = array_merge($this->config, require APP_ROOT . '/config/app.php');
        }
        
        // Cargar configuración de base de datos
        if (file_exists(APP_ROOT . '/config/database.php')) {
            $this->config['database'] = require APP_ROOT . '/config/database.php';
        }
    }
    
    /**
     * Configura el manejo de errores según el entorno
     */
    private function configureErrorHandling() {
        $env = $this->config['app_env'] ?? 'production';
        
        if ($env === 'development') {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', 0);
            ini_set('display_startup_errors', 0);
            error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
        }
    }
    
    /**
     * Carga las rutas definidas
     */
    private function loadRoutes() {
        // Cargar archivo de rutas
        if (file_exists(APP_ROOT . '/config/routes.php')) {
            require APP_ROOT . '/config/routes.php';
        }
    }
    
    /**
     * Procesa la solicitud y envía la respuesta
     */
    private function processRequest() {
        try {
            // Obtener la ruta solicitada
            $uri = $this->request->getUri();
            
            // Buscar controlador y acción para la ruta
            $route = $this->router->resolve($uri, $this->request->getMethod());
            
            if ($route) {
                // Ejecutar el controlador
                $controller = $route['controller'];
                $action = $route['action'];
                $params = $route['params'] ?? [];
                
                // Instanciar el controlador
                $controllerInstance = new $controller();
                
                // Llamar a la acción con los parámetros
                $result = call_user_func_array([$controllerInstance, $action], $params);
                
                // Enviar la respuesta
                $this->response->send($result);
            } else {
                // Ruta no encontrada
                $this->response->setStatusCode(404);
                $this->response->send($this->renderErrorPage(404));
            }
        } catch (\Exception $e) {
            // Error de aplicación
            $this->handleException($e);
        }
    }
    
    /**
     * Maneja las excepciones de la aplicación
     * 
     * @param \Exception $e Excepción capturada
     */
    private function handleException(\Exception $e) {
        // Registrar el error
        error_log($e->getMessage());
        
        // En desarrollo mostramos el error, en producción mensaje genérico
        if ($this->config['app_env'] === 'development') {
            $this->response->setStatusCode(500);
            $this->response->send($e->getMessage());
        } else {
            $this->response->setStatusCode(500);
            $this->response->send($this->renderErrorPage(500));
        }
    }
    
    /**
     * Renderiza una página de error
     * 
     * @param int $code Código de error HTTP
     * @return string Contenido HTML de la página de error
     */
    private function renderErrorPage($code) {
        $errorFile = APP_ROOT . "/app/Views/error/{$code}.php";
        
        if (file_exists($errorFile)) {
            ob_start();
            require $errorFile;
            return ob_get_clean();
        }
        
        return "Error {$code}";
    }
    
    /**
     * Obtiene la configuración
     * 
     * @param string $key Clave de configuración
     * @param mixed $default Valor por defecto
     * @return mixed Valor de configuración
     */
    public function getConfig($key = null, $default = null) {
        if ($key === null) {
            return $this->config;
        }
        
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Obtiene el router
     * 
     * @return Router Instancia del router
     */
    public function getRouter() {
        return $this->router;
    }
    
    /**
     * Obtiene la solicitud actual
     * 
     * @return Request Instancia de la solicitud
     */
    public function getRequest() {
        return $this->request;
    }
    
    /**
     * Obtiene la respuesta actual
     * 
     * @return Response Instancia de la respuesta
     */
    public function getResponse() {
        return $this->response;
    }
}
