<?php
/**
 * Pruebas unitarias para la API del Sistema B2B
 * 
 * Verifica todas las funcionalidades de los endpoints y autenticación
 */

use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    private static $baseUrl = 'http://localhost:8080';
    private static $adminToken = '';
    private static $proveedorToken = '';

    /**
     * Preparar entorno para todas las pruebas
     */
    public static function setUpBeforeClass(): void
    {
        // Intentar obtener token de admin para las pruebas
        self::$adminToken = self::getAuthToken('admin@test.com', 'admin123');
        self::$proveedorToken = self::getAuthToken('proveedor@test.com', 'prov123');
    }

    /**
     * Obtiene un token de autenticación
     */
    private static function getAuthToken($email, $password)
    {
        $ch = curl_init(self::$baseUrl . '/api/auth.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'email' => $email,
            'password' => $password
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        return $data['token'] ?? '';
    }

    /**
     * Realiza petición a la API
     */
    private function makeRequest($endpoint, $method = 'GET', $data = [], $token = null)
    {
        $ch = curl_init(self::$baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $headers = ['Content-Type: application/json'];
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'code' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }

    /**
     * Prueba de conexión a la página de login
     */
    public function testLoginPageWorks()
    {
        $ch = curl_init(self::$baseUrl . '/login.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $this->assertEquals(200, $httpCode, 'La página de login debería estar disponible');
    }

    /**
     * Prueba de autenticación con credenciales válidas
     */
    public function testAuthenticationWithValidCredentials()
    {
        $response = $this->makeRequest('/api/auth.php', 'POST', [
            'email' => 'admin@test.com',
            'password' => 'admin123'
        ]);
        
        $this->assertEquals(200, $response['code'], 'Debería autenticar con credenciales válidas');
        $this->assertArrayHasKey('token', $response['data'], 'Debería devolver un token');
        $this->assertNotEmpty($response['data']['token'], 'El token no debería estar vacío');
    }

    /**
     * Prueba de autenticación con credenciales inválidas
     */
    public function testAuthenticationWithInvalidCredentials()
    {
        $response = $this->makeRequest('/api/auth.php', 'POST', [
            'email' => 'admin@test.com',
            'password' => 'wrongpassword'
        ]);
        
        $this->assertEquals(401, $response['code'], 'No debería autenticar con credenciales inválidas');
        $this->assertArrayHasKey('error', $response['data'], 'Debería devolver un mensaje de error');
    }

    /**
     * Prueba de obtención de productos para un proveedor
     */
    public function testGetProductosForProveedor()
    {
        if (empty(self::$proveedorToken)) {
            $this->markTestSkipped('No se pudo obtener token de proveedor para la prueba');
        }
        
        $response = $this->makeRequest('/api/productos.php', 'GET', [], self::$proveedorToken);
        
        $this->assertEquals(200, $response['code'], 'Debería obtener productos');
        $this->assertIsArray($response['data'], 'La respuesta debería ser un array');
        
        // Verificar estructura de los productos
        if (!empty($response['data'])) {
            $this->assertArrayHasKey('id', $response['data'][0], 'El producto debería tener ID');
            $this->assertArrayHasKey('nombre', $response['data'][0], 'El producto debería tener nombre');
            $this->assertArrayHasKey('sku', $response['data'][0], 'El producto debería tener SKU');
            $this->assertArrayHasKey('stock', $response['data'][0], 'El producto debería tener stock');
            $this->assertArrayHasKey('precio', $response['data'][0], 'El producto debería tener precio');
        }
    }

    /**
     * Prueba de obtención de ventas para un proveedor
     */
    public function testGetVentasForProveedor()
    {
        if (empty(self::$proveedorToken)) {
            $this->markTestSkipped('No se pudo obtener token de proveedor para la prueba');
        }
        
        $response = $this->makeRequest('/api/ventas.php', 'GET', [], self::$proveedorToken);
        
        $this->assertEquals(200, $response['code'], 'Debería obtener ventas');
        $this->assertIsArray($response['data'], 'La respuesta debería ser un array');
        
        // Verificar estructura de las ventas
        if (!empty($response['data'])) {
            $this->assertArrayHasKey('id', $response['data'][0], 'La venta debería tener ID');
            $this->assertArrayHasKey('producto_id', $response['data'][0], 'La venta debería tener producto_id');
            $this->assertArrayHasKey('cantidad', $response['data'][0], 'La venta debería tener cantidad');
            $this->assertArrayHasKey('fecha', $response['data'][0], 'La venta debería tener fecha');
        }
    }

    /**
     * Prueba de obtención de facturas para un proveedor
     */
    public function testGetFacturasForProveedor()
    {
        if (empty(self::$proveedorToken)) {
            $this->markTestSkipped('No se pudo obtener token de proveedor para la prueba');
        }
        
        $response = $this->makeRequest('/api/facturas.php', 'GET', [], self::$proveedorToken);
        
        $this->assertEquals(200, $response['code'], 'Debería obtener facturas');
        $this->assertIsArray($response['data'], 'La respuesta debería ser un array');
        
        // Verificar estructura de las facturas
        if (!empty($response['data'])) {
            $this->assertArrayHasKey('id', $response['data'][0], 'La factura debería tener ID');
            $this->assertArrayHasKey('venta_id', $response['data'][0], 'La factura debería tener venta_id');
            $this->assertArrayHasKey('monto', $response['data'][0], 'La factura debería tener monto');
            $this->assertArrayHasKey('estado', $response['data'][0], 'La factura debería tener estado');
            $this->assertArrayHasKey('fecha', $response['data'][0], 'La factura debería tener fecha');
        }
    }

    /**
     * Prueba de acceso a rutas protegidas sin autenticación
     */
    public function testUnauthorizedAccessToProtectedRoutes()
    {
        $endpoints = [
            '/api/productos.php',
            '/api/ventas.php',
            '/api/facturas.php'
        ];
        
        foreach ($endpoints as $endpoint) {
            $response = $this->makeRequest($endpoint);
            $this->assertEquals(401, $response['code'], "Debería denegar acceso sin token a $endpoint");
        }
    }

    /**
     * Prueba de dashboard con sesión válida
     */
    public function testDashboardWithSession()
    {
        // Esta prueba requeriría una implementación más compleja con manejo de cookies
        // Por ahora marcamos como incompleta
        $this->markTestIncomplete('Prueba de dashboard requiere manejo de sesiones');
    }
}
