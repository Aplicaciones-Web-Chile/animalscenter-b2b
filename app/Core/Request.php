<?php
/**
 * Abstracción de peticiones HTTP
 * 
 * Encapsula todos los datos de una petición HTTP y proporciona
 * métodos para acceder a ellos de manera segura.
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

namespace App\Core;

class Request {
    /**
     * Obtiene la URI de la petición
     * 
     * @return string URI normalizada
     */
    public function getUri() {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Eliminar parámetros de consulta
        $uri = parse_url($uri, PHP_URL_PATH);
        
        // Normalizar
        $uri = trim($uri, '/');
        
        return $uri;
    }
    
    /**
     * Obtiene el método HTTP de la petición
     * 
     * @return string Método HTTP (GET, POST, etc.)
     */
    public function getMethod() {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
    
    /**
     * Obtiene un valor de la petición GET
     * 
     * @param string $key Clave del parámetro
     * @param mixed $default Valor por defecto
     * @return mixed Valor del parámetro o valor por defecto
     */
    public function get($key = null, $default = null) {
        if ($key === null) {
            return $_GET;
        }
        
        return isset($_GET[$key]) ? $this->sanitize($_GET[$key]) : $default;
    }
    
    /**
     * Obtiene un valor de la petición POST
     * 
     * @param string $key Clave del parámetro
     * @param mixed $default Valor por defecto
     * @return mixed Valor del parámetro o valor por defecto
     */
    public function post($key = null, $default = null) {
        if ($key === null) {
            return $_POST;
        }
        
        return isset($_POST[$key]) ? $this->sanitize($_POST[$key]) : $default;
    }
    
    /**
     * Obtiene un valor de cualquier método (GET, POST, etc)
     * 
     * @param string $key Clave del parámetro
     * @param mixed $default Valor por defecto
     * @return mixed Valor del parámetro o valor por defecto
     */
    public function input($key = null, $default = null) {
        $input = array_merge($_GET, $_POST);
        
        if ($key === null) {
            return $input;
        }
        
        return isset($input[$key]) ? $this->sanitize($input[$key]) : $default;
    }
    
    /**
     * Verifica si existe un parámetro
     * 
     * @param string $key Clave del parámetro
     * @return bool True si existe
     */
    public function has($key) {
        $input = array_merge($_GET, $_POST);
        return isset($input[$key]);
    }
    
    /**
     * Obtiene todos los parámetros de la petición
     * 
     * @return array Parámetros
     */
    public function all() {
        return array_merge($_GET, $_POST);
    }
    
    /**
     * Obtiene solo los parámetros especificados
     * 
     * @param array $keys Claves de los parámetros
     * @return array Parámetros filtrados
     */
    public function only(array $keys) {
        $results = [];
        $input = $this->all();
        
        foreach ($keys as $key) {
            if (isset($input[$key])) {
                $results[$key] = $this->sanitize($input[$key]);
            }
        }
        
        return $results;
    }
    
    /**
     * Obtiene todos los parámetros excepto los especificados
     * 
     * @param array $keys Claves de los parámetros a excluir
     * @return array Parámetros filtrados
     */
    public function except(array $keys) {
        $results = $this->all();
        
        foreach ($keys as $key) {
            unset($results[$key]);
        }
        
        return $results;
    }
    
    /**
     * Verifica si la petición es AJAX
     * 
     * @return bool True si es AJAX
     */
    public function isAjax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Obtiene una cabecera HTTP
     * 
     * @param string $key Nombre de la cabecera
     * @param mixed $default Valor por defecto
     * @return mixed Valor de la cabecera o valor por defecto
     */
    public function header($key, $default = null) {
        $headerKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return isset($_SERVER[$headerKey]) ? $_SERVER[$headerKey] : $default;
    }
    
    /**
     * Verifica si la petición es sobre HTTPS
     * 
     * @return bool True si es HTTPS
     */
    public function isSecure() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               $_SERVER['SERVER_PORT'] == 443;
    }
    
    /**
     * Obtiene la IP del cliente
     * 
     * @return string Dirección IP
     */
    public function ip() {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Sanitiza un valor para prevenir XSS
     * 
     * @param mixed $value Valor a sanitizar
     * @return mixed Valor sanitizado
     */
    private function sanitize($value) {
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = $this->sanitize($val);
            }
            return $value;
        }
        
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
