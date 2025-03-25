<?php
/**
 * Sistema de enrutamiento
 * 
 * Gestiona la definición y resolución de rutas para mapear URLs a controladores y acciones.
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

namespace App\Core;

class Router {
    protected $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => []
    ];
    
    /**
     * Registra una ruta GET
     * 
     * @param string $uri URI a mapear
     * @param array|callable $action Controlador y método o función anónima
     * @return Router Instancia del router (para encadenamiento)
     */
    public function get($uri, $action) {
        return $this->addRoute('GET', $uri, $action);
    }
    
    /**
     * Registra una ruta POST
     * 
     * @param string $uri URI a mapear
     * @param array|callable $action Controlador y método o función anónima
     * @return Router Instancia del router (para encadenamiento)
     */
    public function post($uri, $action) {
        return $this->addRoute('POST', $uri, $action);
    }
    
    /**
     * Registra una ruta PUT
     * 
     * @param string $uri URI a mapear
     * @param array|callable $action Controlador y método o función anónima
     * @return Router Instancia del router (para encadenamiento)
     */
    public function put($uri, $action) {
        return $this->addRoute('PUT', $uri, $action);
    }
    
    /**
     * Registra una ruta DELETE
     * 
     * @param string $uri URI a mapear
     * @param array|callable $action Controlador y método o función anónima
     * @return Router Instancia del router (para encadenamiento)
     */
    public function delete($uri, $action) {
        return $this->addRoute('DELETE', $uri, $action);
    }
    
    /**
     * Agrega una ruta al registro de rutas
     * 
     * @param string $method Método HTTP
     * @param string $uri URI a mapear
     * @param mixed $action Controlador/acción o función anónima
     * @return Router Instancia del router (para encadenamiento)
     */
    protected function addRoute($method, $uri, $action) {
        // Normalizar la URI
        $uri = trim($uri, '/');
        
        // Convertir acción a formato estándar
        if (is_callable($action)) {
            $action = ['controller' => $action, 'action' => null];
        } elseif (is_string($action)) {
            $segments = explode('@', $action);
            $controller = $segments[0];
            $actionName = $segments[1] ?? 'index';
            $action = ['controller' => $controller, 'action' => $actionName];
        }
        
        // Registrar la ruta
        $this->routes[$method][$uri] = $action;
        
        return $this;
    }
    
    /**
     * Resuelve una URI a un controlador y acción
     * 
     * @param string $uri URI solicitada
     * @param string $method Método HTTP
     * @return array|bool Datos de la ruta o false si no se encuentra
     */
    public function resolve($uri, $method) {
        // Normalizar URI
        $uri = trim($uri, '/');
        
        // Verificar rutas exactas
        if (isset($this->routes[$method][$uri])) {
            return $this->prepareRoute($this->routes[$method][$uri]);
        }
        
        // Verificar rutas con parámetros
        foreach ($this->routes[$method] as $route => $action) {
            if ($this->matchesPattern($route, $uri, $params)) {
                $routeData = $this->prepareRoute($action);
                $routeData['params'] = $params;
                return $routeData;
            }
        }
        
        // No se encontró la ruta
        return false;
    }
    
    /**
     * Prepara los datos de la ruta en un formato estándar
     * 
     * @param mixed $action Acción de la ruta
     * @return array Datos de la ruta normalizados
     */
    protected function prepareRoute($action) {
        if (is_callable($action)) {
            return [
                'controller' => $action,
                'action' => null
            ];
        }
        
        if (is_array($action) && isset($action['controller'])) {
            // Si es un controlador como cadena, agregar el namespace
            if (is_string($action['controller']) && !class_exists($action['controller'])) {
                // Verificar si es un controlador de API
                if (strpos($action['controller'], 'Api\\') === 0) {
                    $controllerClass = "\\App\\Api\\" . substr($action['controller'], 4);
                } else {
                    $controllerClass = "\\App\\Controllers\\" . $action['controller'];
                }
                $action['controller'] = $controllerClass;
            }
            
            return $action;
        }
        
        return [
            'controller' => '\\App\\Controllers\\ErrorController',
            'action' => 'notFound'
        ];
    }
    
    /**
     * Comprueba si una URI coincide con un patrón de ruta
     * 
     * @param string $pattern Patrón de la ruta
     * @param string $uri URI a comprobar
     * @param array &$params Parámetros extraídos (pasado por referencia)
     * @return bool True si coincide
     */
    protected function matchesPattern($pattern, $uri, &$params) {
        $params = [];
        
        // Si es una ruta exacta, verificar igualdad
        if (strpos($pattern, '{') === false) {
            return $pattern === $uri;
        }
        
        // Preparar patrón para expresión regular
        $patternSegments = explode('/', $pattern);
        $uriSegments = explode('/', $uri);
        
        // Si no tienen el mismo número de segmentos, no coinciden
        if (count($patternSegments) !== count($uriSegments)) {
            return false;
        }
        
        // Verificar cada segmento
        foreach ($patternSegments as $index => $segment) {
            // Si es un parámetro
            if (preg_match('/^\{([a-zA-Z0-9_]+)\}$/', $segment, $matches)) {
                $paramName = $matches[1];
                $params[$paramName] = $uriSegments[$index];
                continue;
            }
            
            // Si es un segmento estático pero no coincide
            if ($segment !== $uriSegments[$index]) {
                return false;
            }
        }
        
        return true;
    }
}
