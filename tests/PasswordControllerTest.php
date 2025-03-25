<?php
/**
 * Pruebas unitarias para el controlador de Contraseñas
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/PasswordController.php';
require_once __DIR__ . '/../models/UserModel.php';

use PHPUnit\Framework\TestCase;

class PasswordControllerTest extends TestCase {
    private $passwordController;
    private $userModel;
    private $db;
    private $testUserId;
    private $testEmail;
    private $testResetToken;
    
    /**
     * Configuración inicial para las pruebas
     */
    protected function setUp(): void {
        // Inicializar el controlador y el modelo
        $this->passwordController = new PasswordController();
        $this->userModel = new UserModel();
        $this->db = getDbConnection();
        
        // Crear un usuario de prueba
        $this->createTestUser();
        
        // Crear un token de restablecimiento para el usuario
        $this->createTestResetToken();
    }
    
    /**
     * Limpieza después de las pruebas
     */
    protected function tearDown(): void {
        // Eliminar el token de restablecimiento si existe
        if ($this->testResetToken) {
            $this->removeTestResetToken();
        }
        
        // Eliminar el usuario de prueba si existe
        if ($this->testUserId) {
            $this->removeTestUser();
        }
    }
    
    /**
     * Crea un usuario temporal para las pruebas
     */
    private function createTestUser() {
        $username = 'test_user_' . time();
        $this->testEmail = 'test_' . time() . '@example.com';
        $passwordHash = password_hash('password123', PASSWORD_DEFAULT);
        
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
            'username' => $username,
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
     * Crea un token de restablecimiento para el usuario de prueba
     */
    private function createTestResetToken() {
        $this->testResetToken = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hora
        
        $stmt = $this->db->prepare("
            INSERT INTO password_resets (
                email, token, expires_at, used, created_at
            ) VALUES (
                :email, :token, :expires_at, 0, NOW()
            )
        ");
        
        $stmt->execute([
            'email' => $this->testEmail,
            'token' => $this->testResetToken,
            'expires_at' => $expires
        ]);
    }
    
    /**
     * Elimina el token de restablecimiento de prueba
     */
    private function removeTestResetToken() {
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE token = :token");
        $stmt->execute(['token' => $this->testResetToken]);
    }
    
    /**
     * Prueba el método requestPasswordReset con email válido
     */
    public function testRequestPasswordResetValidEmail() {
        // Solicitar restablecimiento de contraseña
        $result = $this->passwordController->requestPasswordReset($this->testEmail);
        
        // Verificar que la solicitud fue exitosa
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('enviado', $result['message']);
        
        // Verificar que se creó un token de restablecimiento
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM password_resets 
            WHERE email = :email AND used = 0 AND token != :old_token
        ");
        $stmt->execute([
            'email' => $this->testEmail,
            'old_token' => $this->testResetToken
        ]);
        $count = $stmt->fetchColumn();
        
        $this->assertEquals(1, $count);
    }
    
    /**
     * Prueba el método requestPasswordReset con email inválido
     */
    public function testRequestPasswordResetInvalidEmail() {
        // Solicitar restablecimiento con email mal formado
        $result = $this->passwordController->requestPasswordReset('email_invalido');
        
        // Verificar que la solicitud falló por formato de email
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('válido', $result['message']);
    }
    
    /**
     * Prueba el método requestPasswordReset con email inexistente
     */
    public function testRequestPasswordResetNonexistentEmail() {
        // Solicitar restablecimiento con email inexistente
        $result = $this->passwordController->requestPasswordReset('no_existe@example.com');
        
        // Verificar que aparentemente fue exitoso (por seguridad)
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Si su correo', $result['message']);
    }
    
    /**
     * Prueba el método validateResetToken con token válido
     */
    public function testValidateResetTokenValid() {
        // Validar el token creado
        $result = $this->passwordController->validateResetToken($this->testResetToken);
        
        // Verificar que la validación fue exitosa
        $this->assertTrue($result['success']);
        $this->assertIsArray($result['data']);
        $this->assertEquals($this->testEmail, $result['data']['email']);
    }
    
    /**
     * Prueba el método validateResetToken con token inválido
     */
    public function testValidateResetTokenInvalid() {
        // Validar un token inexistente
        $result = $this->passwordController->validateResetToken('token_inexistente');
        
        // Verificar que la validación falló
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('inválido', $result['message']);
    }
    
    /**
     * Prueba el método resetPassword con datos válidos
     */
    public function testResetPasswordValid() {
        // Restablecer la contraseña
        $newPassword = 'nueva_password123';
        $result = $this->passwordController->resetPassword(
            $this->testResetToken, 
            $newPassword, 
            $newPassword
        );
        
        // Verificar que el restablecimiento fue exitoso
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('actualizada', $result['message']);
        
        // Verificar que el token fue marcado como utilizado
        $stmt = $this->db->prepare("
            SELECT used FROM password_resets WHERE token = :token
        ");
        $stmt->execute(['token' => $this->testResetToken]);
        $used = $stmt->fetchColumn();
        
        $this->assertEquals(1, $used);
        
        // Verificar que la contraseña fue actualizada
        $user = $this->userModel->findByUsernameOrEmail($this->testEmail);
        $this->assertTrue(password_verify($newPassword, $user['password_hash']));
    }
    
    /**
     * Prueba el método resetPassword con contraseñas que no coinciden
     */
    public function testResetPasswordMismatch() {
        // Intentar restablecer con contraseñas diferentes
        $result = $this->passwordController->resetPassword(
            $this->testResetToken, 
            'password1', 
            'password2'
        );
        
        // Verificar que el restablecimiento falló
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no coinciden', $result['message']);
    }
    
    /**
     * Prueba el método resetPassword con contraseña demasiado corta
     */
    public function testResetPasswordTooShort() {
        // Intentar restablecer con contraseña corta
        $result = $this->passwordController->resetPassword(
            $this->testResetToken, 
            '123', 
            '123'
        );
        
        // Verificar que el restablecimiento falló
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('8 caracteres', $result['message']);
    }
}
