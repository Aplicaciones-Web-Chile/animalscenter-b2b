<?php
/**
 * Prueba específica para el problema con token CSRF
 * Este test verifica si los tokens CSRF se están generando y validando correctamente
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../controllers/AuthController.php';
// No incluir bootstrap.php para evitar conflictos de redeclaración de funciones

use PHPUnit\Framework\TestCase;

class CSRFTest extends TestCase
{
    private $authController;
    
    protected function setUp(): void
    {
        // Asegurar que la sesión está limpia
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // Inicializar el controlador de autenticación
        $this->authController = new AuthController();
    }
    
    protected function tearDown(): void
    {
        // Limpiar sesión después de cada prueba
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
    
    /**
     * Prueba que se genera un token CSRF y se almacena en sesión
     */
    public function testGenerateCSRFToken()
    {
        // Generar token CSRF
        $token = $this->authController->generateCSRFToken();
        
        // Verificar que el token se generó
        $this->assertNotEmpty($token);
        
        // Verificar que el token se almacenó en la sesión
        $this->assertArrayHasKey('csrf_token', $_SESSION);
        $this->assertEquals($token, $_SESSION['csrf_token']);
        
        // Log para depuración
        echo "Token generado: " . $token . "\n";
        echo "Token en sesión: " . $_SESSION['csrf_token'] . "\n";
    }
    
    /**
     * Prueba la validación del token CSRF
     */
    public function testValidateCSRFToken()
    {
        // Generar un token
        $token = $this->authController->generateCSRFToken();
        
        // Verificar token válido
        $result = $this->authController->validateCSRFToken($token);
        $this->assertTrue($result);
        
        // Verificar token inválido
        $invalidResult = $this->authController->validateCSRFToken('token_incorrecto');
        $this->assertFalse($invalidResult);
    }
    
    /**
     * Prueba la persistencia del token CSRF entre peticiones
     */
    public function testCSRFTokenPersistenceBetweenRequests()
    {
        // Generar token
        $token = $this->authController->generateCSRFToken();
        $sessionId = session_id();
        
        // Guardar token y ID de sesión
        $storedToken = $_SESSION['csrf_token'];
        
        // Simular una nueva petición manteniendo la misma sesión
        session_write_close();
        session_id($sessionId);
        session_start();
        
        // Verificar que el token sigue disponible
        $this->assertArrayHasKey('csrf_token', $_SESSION);
        $this->assertEquals($storedToken, $_SESSION['csrf_token']);
        
        // Verificar que el token anterior sigue siendo válido
        $result = $this->authController->validateCSRFToken($token);
        $this->assertTrue($result);
    }
    
    /**
     * Prueba el comportamiento del formulario de login con token CSRF
     */
    public function testLoginFormWithCSRF()
    {
        // Simular proceso de login
        // 1. Generar token en la página de login
        $token = $this->authController->generateCSRFToken();
        
        // 2. Intentar login con token válido
        $_POST['username'] = 'admin@animalscenter.cl';
        $_POST['password'] = 'AdminB2B123';
        $_POST['csrf_token'] = $token;
        
        // Comprobar validación de token
        $tokenValid = $this->authController->validateCSRFToken($_POST['csrf_token']);
        $this->assertTrue($tokenValid);
        
        // 3. Intentar login con token inválido
        $_POST['csrf_token'] = 'token_incorrecto';
        $tokenInvalid = $this->authController->validateCSRFToken($_POST['csrf_token']);
        $this->assertFalse($tokenInvalid);
    }
    
    /**
     * Prueba para simular el escenario específico del problema reportado
     */
    public function testDashboardRedirectScenario()
    {
        // 1. Simular inicio de sesión (generar token)
        $token = $this->authController->generateCSRFToken();
        
        // Imprimir información de depuración
        echo "Estado de la sesión antes del login:\n";
        var_dump($_SESSION);
        
        // 2. Comprobar si el token está correctamente almacenado
        $this->assertArrayHasKey('csrf_token', $_SESSION);
        
        // 3. Guardar la sesión actual y simular redirección a dashboard
        $sessionData = $_SESSION;
        $sessionId = session_id();
        
        // 4. Simular una nueva petición a dashboard
        session_write_close();
        session_id($sessionId);
        session_start();
        
        // Imprimir información de depuración
        echo "Estado de la sesión después de redirección:\n";
        var_dump($_SESSION);
        
        // 5. Verificar si el token se mantiene entre peticiones
        $this->assertArrayHasKey('csrf_token', $_SESSION);
        $this->assertEquals($sessionData['csrf_token'], $_SESSION['csrf_token']);
    }
}
