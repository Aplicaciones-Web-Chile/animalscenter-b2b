<?php
/**
 * Test unitario para verificar el funcionamiento del login
 * Este script prueba el inicio de sesión con las credenciales de prueba
 */

// Establecer variables de entorno manualmente
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_NAME'] = 'b2b_database';
$_ENV['DB_USER'] = 'root';
$_ENV['DB_PASS'] = '';
$_ENV['DB_PORT'] = '3306';
$_ENV['APP_ENV'] = 'development';

// Cargar dependencias
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/session.php';

// Clase para realizar pruebas de login
class LoginTest {
    private $credenciales = [
        'admin' => [
            'email' => 'admin@animalscenter.cl',
            'password' => 'AdminB2B123',
            'rol' => 'admin'
        ],
        'proveedor' => [
            'email' => 'proveedor@ejemplo.com',
            'password' => 'ProveedorAC2024',
            'rol' => 'proveedor'
        ]
    ];
    
    private $resultados = [
        'exitos' => 0,
        'fallos' => 0,
        'total' => 0
    ];
    
    /**
     * Ejecuta todas las pruebas
     */
    public function ejecutarPruebas() {
        echo "Iniciando pruebas de login...\n";
        echo "=============================================\n";
        
        // Probar login de administrador
        $this->probarLogin('admin');
        
        // Probar login de proveedor
        $this->probarLogin('proveedor');
        
        // Probar login con credenciales incorrectas
        $this->probarLoginIncorrecto();
        
        // Mostrar resultados
        echo "=============================================\n";
        echo "Resultados de las pruebas:\n";
        echo "✅ Pruebas exitosas: {$this->resultados['exitos']}\n";
        echo "❌ Pruebas fallidas: {$this->resultados['fallos']}\n";
        echo "📊 Total de pruebas: {$this->resultados['total']}\n";
        echo "=============================================\n";
    }
    
    /**
     * Prueba el login con las credenciales del tipo de usuario especificado
     * 
     * @param string $tipoUsuario Tipo de usuario (admin o proveedor)
     */
    private function probarLogin($tipoUsuario) {
        $this->resultados['total']++;
        
        $credenciales = $this->credenciales[$tipoUsuario];
        echo "Probando login como {$tipoUsuario} ({$credenciales['email']})...\n";
        
        try {
            // Buscar usuario en la base de datos
            $user = fetchOne("SELECT * FROM usuarios WHERE email = ?", [$credenciales['email']]);
            
            if (!$user) {
                echo "❌ FALLO: Usuario {$credenciales['email']} no encontrado en la base de datos.\n";
                $this->resultados['fallos']++;
                return;
            }
            
            // Verificar contraseña
            if (!password_verify($credenciales['password'], $user['password_hash'])) {
                echo "❌ FALLO: Contraseña incorrecta para el usuario {$credenciales['email']}.\n";
                $this->resultados['fallos']++;
                return;
            }
            
            // Verificar rol
            if ($user['rol'] !== $credenciales['rol']) {
                echo "❌ FALLO: El rol del usuario {$credenciales['email']} no coincide. Esperado: {$credenciales['rol']}, Actual: {$user['rol']}.\n";
                $this->resultados['fallos']++;
                return;
            }
            
            echo "✅ ÉXITO: Login correcto como {$tipoUsuario} ({$credenciales['email']}).\n";
            $this->resultados['exitos']++;
            
        } catch (Exception $e) {
            echo "❌ FALLO: Error al probar login como {$tipoUsuario}: " . $e->getMessage() . "\n";
            $this->resultados['fallos']++;
        }
    }
    
    /**
     * Prueba el login con credenciales incorrectas
     */
    private function probarLoginIncorrecto() {
        $this->resultados['total']++;
        
        $emailIncorrecto = 'usuario_inexistente@ejemplo.com';
        echo "Probando login con credenciales incorrectas ({$emailIncorrecto})...\n";
        
        try {
            // Buscar usuario en la base de datos
            $user = fetchOne("SELECT * FROM usuarios WHERE email = ?", [$emailIncorrecto]);
            
            if ($user) {
                echo "❌ FALLO: Se esperaba que el usuario {$emailIncorrecto} no existiera, pero fue encontrado.\n";
                $this->resultados['fallos']++;
                return;
            }
            
            echo "✅ ÉXITO: Login con credenciales incorrectas rechazado correctamente.\n";
            $this->resultados['exitos']++;
            
        } catch (Exception $e) {
            echo "❌ FALLO: Error al probar login con credenciales incorrectas: " . $e->getMessage() . "\n";
            $this->resultados['fallos']++;
        }
    }
}

// Ejecutar pruebas
$test = new LoginTest();
$test->ejecutarPruebas();
