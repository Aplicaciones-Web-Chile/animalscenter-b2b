<?php
/**
 * Abstracción de respuestas HTTP
 * 
 * Encapsula la generación de respuestas HTTP, permitiendo
 * establecer cabeceras, código de estado y contenido.
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

namespace App\Core;

class Response {
    protected $headers = [];
    protected $statusCode = 200;
    protected $content = '';
    
    /**
     * Establece una cabecera HTTP
     * 
     * @param string $name Nombre de la cabecera
     * @param string $value Valor de la cabecera
     * @return Response Instancia de respuesta (para encadenamiento)
     */
    public function setHeader($name, $value) {
        $this->headers[$name] = $value;
        return $this;
    }
    
    /**
     * Establece múltiples cabeceras HTTP
     * 
     * @param array $headers Cabeceras en formato [nombre => valor]
     * @return Response Instancia de respuesta (para encadenamiento)
     */
    public function setHeaders(array $headers) {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
        return $this;
    }
    
    /**
     * Establece el código de estado HTTP
     * 
     * @param int $code Código de estado HTTP
     * @return Response Instancia de respuesta (para encadenamiento)
     */
    public function setStatusCode($code) {
        $this->statusCode = $code;
        return $this;
    }
    
    /**
     * Establece el contenido de la respuesta
     * 
     * @param string $content Contenido de la respuesta
     * @return Response Instancia de respuesta (para encadenamiento)
     */
    public function setContent($content) {
        $this->content = $content;
        return $this;
    }
    
    /**
     * Envía la respuesta al cliente
     * 
     * @param string $content Contenido opcional para establecer antes de enviar
     * @return void
     */
    public function send($content = null) {
        if ($content !== null) {
            $this->setContent($content);
        }
        
        // Establecer código de estado
        http_response_code($this->statusCode);
        
        // Establecer cabeceras
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        
        // Enviar contenido
        echo $this->content;
        exit;
    }
    
    /**
     * Envía una respuesta JSON
     * 
     * @param mixed $data Datos a convertir a JSON
     * @param int $statusCode Código de estado HTTP
     * @return void
     */
    public function json($data, $statusCode = null) {
        if ($statusCode !== null) {
            $this->setStatusCode($statusCode);
        }
        
        $this->setHeader('Content-Type', 'application/json');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        
        if ($json === false) {
            // Error en la codificación JSON
            $this->setStatusCode(500);
            $json = json_encode(['error' => 'Error en la codificación JSON']);
        }
        
        $this->send($json);
    }
    
    /**
     * Redirige a otra URL
     * 
     * @param string $url URL de destino
     * @param int $statusCode Código de estado HTTP (301 o 302)
     * @return void
     */
    public function redirect($url, $statusCode = 302) {
        $this->setStatusCode($statusCode);
        $this->setHeader('Location', $url);
        $this->send();
    }
    
    /**
     * Crea una respuesta con error 404
     * 
     * @param string $message Mensaje opcional
     * @return void
     */
    public function notFound($message = 'Página no encontrada') {
        $this->setStatusCode(404);
        $this->send($message);
    }
    
    /**
     * Crea una respuesta con error 403
     * 
     * @param string $message Mensaje opcional
     * @return void
     */
    public function forbidden($message = 'Acceso denegado') {
        $this->setStatusCode(403);
        $this->send($message);
    }
    
    /**
     * Crea una respuesta con error 500
     * 
     * @param string $message Mensaje opcional
     * @return void
     */
    public function serverError($message = 'Error interno del servidor') {
        $this->setStatusCode(500);
        $this->send($message);
    }
    
    /**
     * Renderiza una vista y la envía como respuesta
     * 
     * @param string $view Ruta de la vista (relativa a app/Views)
     * @param array $data Datos para la vista
     * @param int $statusCode Código de estado HTTP
     * @return void
     */
    public function view($view, $data = [], $statusCode = 200) {
        $this->setStatusCode($statusCode);
        
        // Extraer variables para la vista
        extract($data);
        
        // Capturar la salida
        ob_start();
        
        // Incluir la vista
        $viewPath = APP_ROOT . "/app/Views/$view.php";
        
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            // Vista no encontrada
            throw new \Exception("Vista no encontrada: $view");
        }
        
        $content = ob_get_clean();
        $this->send($content);
    }
}
