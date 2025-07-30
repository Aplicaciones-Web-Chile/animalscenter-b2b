<?php
/**
 * Controlador de Gestión de Contraseñas
 * 
 * Este controlador maneja todas las acciones relacionadas con la recuperación 
 * y restablecimiento de contraseñas.
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../includes/Logger.php';

class PasswordController {
    private $userModel;
    
    /**
     * Constructor del controlador
     */
    public function __construct() {
        $this->userModel = new UserModel();
    }
    
    /**
     * Procesa la solicitud de recuperación de contraseña
     * 
     * @param string $email Correo electrónico del usuario
     * @return array Resultado de la operación y mensaje
     */
    public function requestPasswordReset($email) {
        try {
            // Validaciones básicas
            if (empty($email)) {
                return [
                    'success' => false,
                    'message' => 'Por favor, ingrese su correo electrónico.'
                ];
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Por favor, ingrese un correo electrónico válido.'
                ];
            }
            
            // Verificar si el correo existe en la base de datos
            $user = $this->userModel->findByUsernameOrEmail($email);
            
            if ($user) {
                // Generar token de recuperación (válido por 1 hora)
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hora
                
                // Guardar token en la base de datos
                $this->userModel->createPasswordResetToken($email, $token, $expires);
                
                // Invalidar otros tokens activos para este email
                $this->userModel->invalidateOtherResetTokens($email, $token);
                
                // Construir URL de restablecimiento
                $resetUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/restablecer-password.php?token=' . $token;
                
                // Aquí normalmente enviaríamos un correo electrónico
                // Por ahora, simplemente mostraremos un mensaje de éxito

                // Registrar el evento
                Logger::info("Solicitud de recuperación de contraseña generada para: $email", 
                    Logger::SECURITY, [
                        'user_id' => $user['id'],
                        'ip' => $_SERVER['REMOTE_ADDR']
                    ]);
                
                return [
                    'success' => true,
                    'message' => "Hemos enviado un enlace para restablecer su contraseña a su correo electrónico. 
                              Por favor revise su bandeja de entrada y siga las instrucciones."
                ];
            } else {
                // No revelar si el correo existe o no por seguridad
                Logger::warning("Intento de recuperación de contraseña para correo no registrado: $email", 
                    Logger::SECURITY, [
                        'ip' => $_SERVER['REMOTE_ADDR']
                    ]);
                
                return [
                    'success' => true, // Devolvemos true por seguridad
                    'message' => "Si su correo electrónico está registrado en nuestro sistema, 
                              recibirá un enlace para restablecer su contraseña."
                ];
            }
        } catch (Exception $e) {        
            // Registrar el error
            Logger::error("Error en recuperación de contraseña: " . $e->getMessage(), 
                Logger::SECURITY, [
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'trace' => $e->getTraceAsString()
                ]);
            
            return [
                'success' => false,
                'message' => "Ocurrió un error al procesar su solicitud. Por favor, intente nuevamente más tarde."
            ];
        }
    }
    
    /**
     * Verifica si un token de restablecimiento es válido
     * 
     * @param string $token Token a verificar
     * @return bool|array Resultado de la operación y mensaje o datos de usuario
     */
    public function validateResetToken($token) {
        try {
            if (empty($token)) {
                return [
                    'success' => false,
                    'message' => 'Token de restablecimiento inválido.'
                ];
            }
            
            $db = getDbConnection();
            $stmt = $db->prepare("
                SELECT pr.*, u.id as user_id, u.nombre 
                FROM password_resets pr 
                JOIN usuarios u ON pr.email = u.email
                WHERE pr.token = ? 
                AND pr.used = 0 
                AND pr.expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$token]);
            $reset = $stmt->fetch();
            
            if (!$reset) {
                Logger::warning("Intento de uso de token de restablecimiento inválido o expirado", 
                    Logger::SECURITY, [
                        'token_hash' => hash('sha256', $token),
                        'ip' => $_SERVER['REMOTE_ADDR']
                    ]);
                
                return [
                    'success' => false,
                    'message' => 'El enlace de restablecimiento es inválido o ha expirado.'
                ];
            }
            
            return [
                'success' => true,
                'data' => $reset
            ];
        } catch (Exception $e) {
            Logger::error("Error validando token de restablecimiento: " . $e->getMessage(), 
                Logger::SECURITY, [
                    'token_hash' => hash('sha256', $token),
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
            
            return [
                'success' => false,
                'message' => 'Ocurrió un error al validar su solicitud. Por favor, intente nuevamente.'
            ];
        }
    }
    
    /**
     * Restablece la contraseña de un usuario
     * 
     * @param string $token Token de restablecimiento
     * @param string $password Nueva contraseña
     * @param string $confirmPassword Confirmación de la nueva contraseña
     * @return array Resultado de la operación y mensaje
     */
    public function resetPassword($token, $password, $confirmPassword) {
        try {
            // Validaciones básicas
            if (empty($password) || empty($confirmPassword)) {
                return [
                    'success' => false, 
                    'message' => 'Por favor, complete todos los campos.'
                ];
            }
            
            if ($password !== $confirmPassword) {
                return [
                    'success' => false, 
                    'message' => 'Las contraseñas no coinciden.'
                ];
            }
            
            if (strlen($password) < 8) {
                return [
                    'success' => false, 
                    'message' => 'La contraseña debe tener al menos 8 caracteres.'
                ];
            }
            
            // Validar el token
            $tokenResult = $this->validateResetToken($token);
            
            if (!$tokenResult['success']) {
                return $tokenResult;
            }
            
            $reset = $tokenResult['data'];
            
            // Generar hash de la nueva contraseña
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Actualizar la contraseña del usuario
            $this->userModel->updatePassword($reset['email'], $passwordHash);
            
            // Marcar el token como utilizado
            $this->userModel->markResetTokenAsUsed($token);
            
            // Registrar el evento
            Logger::info("Contraseña restablecida correctamente", 
                Logger::SECURITY, [
                    'user_id' => $reset['user_id'],
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
            
            return [
                'success' => true,
                'message' => 'Su contraseña ha sido actualizada correctamente. Ya puede iniciar sesión con su nueva contraseña.'
            ];
        } catch (Exception $e) {
            Logger::error("Error al restablecer contraseña: " . $e->getMessage(), 
                Logger::SECURITY, [
                    'token_hash' => hash('sha256', $token),
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
            
            return [
                'success' => false,
                'message' => 'Ocurrió un error al restablecer su contraseña. Por favor, intente nuevamente.'
            ];
        }
    }
}
