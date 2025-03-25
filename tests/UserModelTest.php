<?php
/**
 * Pruebas unitarias para el modelo de Usuario
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/UserModel.php';

use PHPUnit\Framework\TestCase;

class UserModelTest extends TestCase {
    private $userModel;
    private $db;
    private $testUserId;
    
    /**
     * Configuración inicial para las pruebas
     */
    protected function setUp(): void {
        // Inicializar el modelo y la conexión a la base de datos
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
    }
    
    /**
     * Crea un usuario temporal para las pruebas
     */
    private function createTestUser() {
        $username = 'test_user_' . time();
        $email = 'test_' . time() . '@example.com';
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
            'email' => $email,
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
     * Prueba el método findById
     */
    public function testFindById() {
        // Buscar el usuario creado
        $user = $this->userModel->findById($this->testUserId);
        
        // Verificar que se encontró el usuario
        $this->assertIsArray($user);
        $this->assertEquals($this->testUserId, $user['id']);
    }
    
    /**
     * Prueba el método getUserById (alias de findById)
     */
    public function testGetUserById() {
        // Buscar el usuario creado
        $user = $this->userModel->getUserById($this->testUserId);
        
        // Verificar que se encontró el usuario
        $this->assertIsArray($user);
        $this->assertEquals($this->testUserId, $user['id']);
    }
    
    /**
     * Prueba el método findByUsernameOrEmail con nombre de usuario
     */
    public function testFindByUsername() {
        // Obtener el nombre de usuario del usuario de prueba
        $stmt = $this->db->prepare("SELECT username FROM usuarios WHERE id = :id");
        $stmt->execute(['id' => $this->testUserId]);
        $username = $stmt->fetchColumn();
        
        // Buscar el usuario por nombre de usuario
        $user = $this->userModel->findByUsernameOrEmail($username);
        
        // Verificar que se encontró el usuario
        $this->assertIsArray($user);
        $this->assertEquals($this->testUserId, $user['id']);
    }
    
    /**
     * Prueba el método findByUsernameOrEmail con email
     */
    public function testFindByEmail() {
        // Obtener el email del usuario de prueba
        $stmt = $this->db->prepare("SELECT email FROM usuarios WHERE id = :id");
        $stmt->execute(['id' => $this->testUserId]);
        $email = $stmt->fetchColumn();
        
        // Buscar el usuario por email
        $user = $this->userModel->findByUsernameOrEmail($email);
        
        // Verificar que se encontró el usuario
        $this->assertIsArray($user);
        $this->assertEquals($this->testUserId, $user['id']);
    }
    
    /**
     * Prueba el método updateFailedAttempts
     */
    public function testUpdateFailedAttempts() {
        // Actualizar los intentos fallidos
        $result = $this->userModel->updateFailedAttempts($this->testUserId, 3);
        
        // Verificar que la actualización fue exitosa
        $this->assertTrue($result);
        
        // Verificar que se actualizaron los intentos fallidos
        $stmt = $this->db->prepare("SELECT failed_attempts FROM usuarios WHERE id = :id");
        $stmt->execute(['id' => $this->testUserId]);
        $attempts = $stmt->fetchColumn();
        
        $this->assertEquals(3, $attempts);
    }
    
    /**
     * Prueba el método resetFailedAttempts
     */
    public function testResetFailedAttempts() {
        // Primero establecer algunos intentos fallidos
        $this->userModel->updateFailedAttempts($this->testUserId, 3);
        
        // Luego resetear los intentos
        $result = $this->userModel->resetFailedAttempts($this->testUserId);
        
        // Verificar que el reseteo fue exitoso
        $this->assertTrue($result);
        
        // Verificar que los intentos fallidos se restablecieron a 0
        $stmt = $this->db->prepare("SELECT failed_attempts FROM usuarios WHERE id = :id");
        $stmt->execute(['id' => $this->testUserId]);
        $attempts = $stmt->fetchColumn();
        
        $this->assertEquals(0, $attempts);
    }
    
    /**
     * Prueba el método updateRememberToken
     */
    public function testUpdateRememberToken() {
        // Crear un token de prueba
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 86400); // 1 día
        
        // Actualizar el token
        $result = $this->userModel->updateRememberToken($this->testUserId, $token, $expires);
        
        // Verificar que la actualización fue exitosa
        $this->assertTrue($result);
        
        // Verificar que se actualizó el token
        $stmt = $this->db->prepare("SELECT remember_token FROM usuarios WHERE id = :id");
        $stmt->execute(['id' => $this->testUserId]);
        $savedToken = $stmt->fetchColumn();
        
        $this->assertEquals($token, $savedToken);
    }
    
    /**
     * Prueba el método findByRememberToken
     */
    public function testFindByRememberToken() {
        // Crear un token de prueba
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 86400); // 1 día
        
        // Actualizar el token directamente en la base de datos
        $stmt = $this->db->prepare("
            UPDATE usuarios SET 
            remember_token = :token, 
            remember_token_expires = :expires 
            WHERE id = :id
        ");
        $stmt->execute([
            'token' => $token,
            'expires' => $expires,
            'id' => $this->testUserId
        ]);
        
        // Buscar el usuario por el token
        $user = $this->userModel->findByRememberToken($token);
        
        // Verificar que se encontró el usuario
        $this->assertIsArray($user);
        $this->assertEquals($this->testUserId, $user['id']);
    }
}
