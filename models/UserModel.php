<?php
/**
 * Modelo de Usuario
 * 
 * Esta clase gestiona todas las operaciones relacionadas con los usuarios
 * en la base de datos, siguiendo el patrón MVC.
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

// Cargar la clase Database del Core
require_once __DIR__ . '/../app/Core/Database.php';

use App\Core\Database;

class UserModel {
    private $db;
    
    /**
     * Constructor que inicializa la conexión a la base de datos
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Busca un usuario por nombre de usuario o email
     * 
     * @param string $usernameOrEmail Nombre de usuario o email
     * @return array|false Datos del usuario o false si no se encuentra
     */
    public function findByUsernameOrEmail($usernameOrEmail) {
        $stmt = $this->db->prepare("
            SELECT * FROM usuarios 
            WHERE email = :identifier
            LIMIT 1
        ");
        $stmt->execute(['identifier' => $usernameOrEmail]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Actualiza el contador de intentos fallidos de un usuario
     * 
     * @param int $userId ID del usuario
     * @param int $attempts Número de intentos fallidos
     * @return bool Resultado de la operación
     */
    public function updateFailedAttempts($userId, $attempts) {
        $stmt = $this->db->prepare("
            UPDATE usuarios SET 
            failed_attempts = :attempts, 
            last_failed_attempt = NOW() 
            WHERE id = :id
        ");
        return $stmt->execute([
            'attempts' => $attempts,
            'id' => $userId
        ]);
    }
    
    /**
     * Resetea los intentos fallidos de un usuario tras un login exitoso
     * 
     * @param int $userId ID del usuario
     * @return bool Resultado de la operación
     */
    public function resetFailedAttempts($userId) {
        $stmt = $this->db->prepare("
            UPDATE usuarios SET 
            failed_attempts = 0, 
            last_failed_attempt = NULL 
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $userId]);
    }
    
    /**
     * Actualiza el token de "recordarme" de un usuario
     * 
     * @param int $userId ID del usuario
     * @param string $token Token generado
     * @param string $expires Fecha de expiración
     * @return bool Resultado de la operación
     */
    public function updateRememberToken($userId, $token, $expires) {
        $stmt = $this->db->prepare("
            UPDATE usuarios SET 
            remember_token = :token, 
            remember_token_expires = :expires 
            WHERE id = :id
        ");
        return $stmt->execute([
            'token' => $token,
            'expires' => $expires,
            'id' => $userId
        ]);
    }
    
    /**
     * Busca un usuario por su token de "recordarme"
     * 
     * @param string $token Token a buscar
     * @return array|false Datos del usuario o false si no se encuentra
     */
    public function findByRememberToken($token) {
        $stmt = $this->db->prepare("
            SELECT * FROM usuarios 
            WHERE remember_token = :token 
            AND remember_token_expires > NOW() 
            LIMIT 1
        ");
        $stmt->execute(['token' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca un usuario por su ID
     * 
     * @param int $userId ID del usuario
     * @return array|false Datos del usuario o false si no se encuentra
     */
    public function findById($userId) {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene un usuario por su ID (alias de findById)
     * 
     * @param int $userId ID del usuario
     * @return array|false Datos del usuario o false si no se encuentra
     */
    public function getUserById($userId) {
        return $this->findById($userId);
    }
    
    /**
     * Crea una solicitud de restablecimiento de contraseña
     * 
     * @param string $email Email del usuario
     * @param string $token Token generado
     * @param string $expiresAt Fecha de expiración
     * @return bool Resultado de la operación
     */
    public function createPasswordResetToken($email, $token, $expiresAt) {
        $stmt = $this->db->prepare("
            INSERT INTO password_resets (email, token, expires_at, created_at) 
            VALUES (:email, :token, :expires_at, NOW())
        ");
        return $stmt->execute([
            'email' => $email,
            'token' => $token,
            'expires_at' => $expiresAt
        ]);
    }
    
    /**
     * Actualiza la contraseña de un usuario
     * 
     * @param string $email Email del usuario
     * @param string $passwordHash Nueva contraseña (hash)
     * @return bool Resultado de la operación
     */
    public function updatePassword($email, $passwordHash) {
        $stmt = $this->db->prepare("
            UPDATE usuarios SET 
            password_hash = :password_hash, 
            status = 'active', 
            failed_attempts = 0, 
            last_failed_attempt = NULL, 
            updated_at = NOW() 
            WHERE email = :email
        ");
        return $stmt->execute([
            'password_hash' => $passwordHash,
            'email' => $email
        ]);
    }
    
    /**
     * Marca un token de restablecimiento como utilizado
     * 
     * @param string $token Token a marcar
     * @return bool Resultado de la operación
     */
    public function markResetTokenAsUsed($token) {
        $stmt = $this->db->prepare("
            UPDATE password_resets SET 
            used = 1, 
            used_at = NOW() 
            WHERE token = :token
        ");
        return $stmt->execute(['token' => $token]);
    }
    
    /**
     * Invalida todos los tokens de restablecimiento para un email excepto uno
     * 
     * @param string $email Email del usuario
     * @param string $exceptToken Token a excluir
     * @return bool Resultado de la operación
     */
    public function invalidateOtherResetTokens($email, $exceptToken) {
        $stmt = $this->db->prepare("
            UPDATE password_resets SET 
            used = 1, 
            used_at = NOW() 
            WHERE email = :email 
            AND token != :token 
            AND used = 0
        ");
        return $stmt->execute([
            'email' => $email,
            'token' => $exceptToken
        ]);
    }
}
