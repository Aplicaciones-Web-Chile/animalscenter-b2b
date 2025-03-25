<?php
/**
 * Pruebas unitarias para el controlador de Autenticación
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/UserModel.php';

use PHPUnit\Framework\TestCase;

class AuthControllerTest extends TestCase {
    private $authController;
    private $userModel;
    private $db;
    private $testUserId;
    private $testUsername;
    private $testEmail;
    private $testPassword = 'password123';
    
    /**
     * Configuración inicial para las pruebas
     */
    protected function setUp(): void {
        // Iniciar sesión si no está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Inicializar el controlador y el modelo
        $this->authController = new AuthController();
        $this->userModel = new UserModel();
        $this->db = getDbConnection();
        
        // Crear un usuario de prueba
        $this->createTestUser();
    }
    
    /**
     * Limpieza después de las pruebas
     */
    protected function tearDown(): void {
        // Eliminar el usuario de prueba si existe
        if ($this->testUserId) {
            $this->removeTestUser();
        }
        
        // Limpiar sesión
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
    
    /**
     * Crea un usuario temporal para las pruebas
     */
    private function createTestUser() {
        $this->testUsername = 'test_user_' . time();
        $this->testEmail = 'test_' . time() . '@example.com';
        $passwordHash = password_hash($this->testPassword, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("
            INSERT INTO usuarios (
                username, email, nombre, password_hash, status, 
                created_at, updated_at
            ) VALUES (
                :username, :email, 'Usuario de Prueba', :password_hash, 
                'active', NOW(), NOW()
            )
        ");
        
        $stmt->execute([
            'username' => $this->testUsername,
            'email' => $this->testEmail,
            'password_hash' => $passwordHash
        ]);
        
        $this->testUserId = $this->db->lastInsertId();
    }
    
    /**
     * Elimina el usuario temporal de prueba
     */
    private function removeTestUser() {
        $stmt = $this->db->prepare("DELETE FROM usuarios WHERE id = :id");
        $stmt->execute(['id' => $this->testUserId]);
    }
    
    /**
     * Prueba el método generateCSRFToken
     */
    public function testGenerateCSRFToken() {
        // Generar token CSRF
        $token = $this->authController->generateCSRFToken();
        
        // Verificar que el token se generó
        $this->assertNotEmpty($token);
        
        // Verificar que el token se almacenó en la sesión
        $this->assertArrayHasKey('csrf_token', $_SESSION);
        $this->assertEquals($token, $_SESSION['csrf_token']);
    }
    
    /**
     * Prueba el método validateCSRFToken con un token válido
     */
    public function testValidateCSRFTokenValid() {
        // Generar un token CSRF
        $token = $this->authController->generateCSRFToken();
        
        // Validar el token
        $result = $this->authController->validateCSRFToken($token);
        
        // Verificar que la validación fue exitosa
        $this->assertTrue($result);
    }
    
    /**
     * Prueba el método validateCSRFToken con un token inválido
     */
    public function testValidateCSRFTokenInvalid() {
        // Generar un token CSRF
        $this->authController->generateCSRFToken();
        
        // Intentar validar un token incorrecto
        $result = $this->authController->validateCSRFToken('token_incorrecto');
        
        // Verificar que la validación falló
        $this->assertFalse($result);
    }
    
    /**
     * Prueba el método login con credenciales válidas
     */
    public function testLoginWithValidCredentials() {
        // Intentar iniciar sesión con credenciales correctas
        $result = $this->authController->login($this->testUsername, $this->testPassword);
        
        // Verificar que el inicio de sesión fue exitoso
        $this->assertTrue($result['success']);
        $this->assertIsArray($result['user']);
        $this->assertEquals($this->testUserId, $result['user']['id']);
        
        // Verificar que se estableció la sesión
        $this->assertArrayHasKey('user_id', $_SESSION);
        $this->assertEquals($this->testUserId, $_SESSION['user_id']);
    }
    
    /**
     * Prueba el método login con nombre de usuario incorrecto
     */
    public function testLoginWithInvalidUsername() {
        // Intentar iniciar sesión con nombre de usuario incorrecto
        $result = $this->authController->login('usuario_inexistente', $this->testPassword);
        
        // Verificar que el inicio de sesión falló
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('credenciales', $result['message']);
    }
    
    /**
     * Prueba el método login con contraseña incorrecta
     */
    public function testLoginWithInvalidPassword() {
        // Intentar iniciar sesión con contraseña incorrecta
        $result = $this->authController->login($this->testUsername, 'contraseña_incorrecta');
        
        // Verificar que el inicio de sesión falló
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('credenciales', $result['message']);
        
        // Verificar que se incrementó el contador de intentos fallidos
        $user = $this->userModel->findByUsernameOrEmail($this->testUsername);
        $this->assertGreaterThan(0, $user['failed_attempts']);
    }
    
    /**
     * Prueba el método isLoggedIn después de un login exitoso
     */
    public function testIsLoggedInAfterLogin() {
        // Primero hacer login
        $this->authController->login($this->testUsername, $this->testPassword);
        
        // Verificar que isLoggedIn devuelve true
        $result = $this->authController->isLoggedIn();
        $this->assertTrue($result);
    }
    
    /**
     * Prueba el método isLoggedIn sin estar autenticado
     */
    public function testIsLoggedInWithoutLogin() {
        // Asegurarse de que no hay sesión activa
        $_SESSION = [];
        
        // Verificar que isLoggedIn devuelve false
        $result = $this->authController->isLoggedIn();
        $this->assertFalse($result);
    }
    
    /**
     * Prueba el método logout
     */
    public function testLogout() {
        // Primero hacer login
        $this->authController->login($this->testUsername, $this->testPassword);
        
        // Luego hacer logout
        $result = $this->authController->logout();
        
        // Verificar que el logout fue exitoso
        $this->assertTrue($result['success']);
        
        // Verificar que se eliminó la sesión
        $this->assertArrayNotHasKey('user_id', $_SESSION);
        
        // Verificar que isLoggedIn devuelve false
        $this->assertFalse($this->authController->isLoggedIn());
    }
}
