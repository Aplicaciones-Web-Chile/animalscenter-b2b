<?php
/**
 * Pruebas unitarias para la interfaz de usuario del Sistema B2B
 * 
 * Verifica que las distintas páginas del sistema se muestren correctamente
 */

use PHPUnit\Framework\TestCase;

class InterfaceTest extends TestCase
{
    private static $baseUrl = 'http://localhost:8080';
    private static $cookies = [];

    /**
     * Realiza login y guarda las cookies para las pruebas
     */
    public static function setUpBeforeClass(): void
    {
        $ch = curl_init(self::$baseUrl . '/login.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'email' => 'admin@test.com',
            'password' => 'admin123',
            'csrf_token' => self::getCsrfToken()
        ]));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Extraer cookies de la respuesta
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches);
        foreach ($matches[1] as $cookie) {
            self::$cookies[] = $cookie;
        }
        
        curl_close($ch);
    }

    /**
     * Obtiene el token CSRF de la página de login
     */
    private static function getCsrfToken()
    {
        $ch = curl_init(self::$baseUrl . '/login.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        
        // Extraer token CSRF usando regex
        preg_match('/name="csrf_token" value="([^"]+)"/', $response, $matches);
        return $matches[1] ?? '';
    }

    /**
     * Realiza una petición a una página con las cookies de sesión
     */
    private function requestPage($url, $followRedirects = true)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        if ($followRedirects) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        }
        
        // Añadir cookies a la petición
        if (!empty(self::$cookies)) {
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', self::$cookies));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        curl_close($ch);
        
        return [
            'code' => $httpCode,
            'header' => $header,
            'body' => $body
        ];
    }

    /**
     * Prueba de existencia y funcionamiento de la página de login
     */
    public function testLoginPageWorks()
    {
        $response = $this->requestPage(self::$baseUrl . '/login.php');
        
        $this->assertEquals(200, $response['code'], 'La página de login debería estar disponible');
        $this->assertStringContainsString('Acceso al Sistema B2B', $response['body'], 'Contenido de login incorrecto');
        $this->assertStringContainsString('name="csrf_token"', $response['body'], 'Falta token CSRF');
        $this->assertStringContainsString('name="email"', $response['body'], 'Falta campo de email');
        $this->assertStringContainsString('name="password"', $response['body'], 'Falta campo de contraseña');
    }

    /**
     * Prueba de redirección de index a login o dashboard
     */
    public function testIndexRedirection()
    {
        $response = $this->requestPage(self::$baseUrl . '/', false);
        
        // Debería redirigir a login.php o dashboard.php
        $this->assertTrue(
            in_array($response['code'], [301, 302]), 
            'Index debería redirigir'
        );
        
        $this->assertTrue(
            strpos($response['header'], 'Location: login.php') !== false || 
            strpos($response['header'], 'Location: dashboard.php') !== false,
            'Redirección de index incorrecta'
        );
    }

    /**
     * Prueba de acceso al dashboard con sesión
     */
    public function testDashboardAccess()
    {
        if (empty(self::$cookies)) {
            $this->markTestSkipped('No se pudo obtener cookies de sesión para la prueba');
            return;
        }
        
        $response = $this->requestPage(self::$baseUrl . '/dashboard.php');
        
        // Si tenemos cookies válidas, debería mostrar el dashboard
        $this->assertEquals(200, $response['code'], 'Dashboard debería estar accesible con sesión');
        $this->assertStringContainsString('Dashboard', $response['body'], 'Contenido de dashboard incorrecto');
    }

    /**
     * Prueba de protección de dashboard sin sesión
     */
    public function testDashboardProtection()
    {
        // Intentar acceder sin cookies
        $ch = curl_init(self::$baseUrl . '/dashboard.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        // Debería redirigir a login
        $this->assertTrue(
            in_array($httpCode, [301, 302]), 
            'Dashboard sin sesión debería redirigir'
        );
        
        $this->assertStringContainsString('Location: login.php', $response, 'Redirección de dashboard incorrecta');
    }

    /**
     * Prueba de acceso a la página de productos
     */
    public function testProductosPageAccess()
    {
        if (empty(self::$cookies)) {
            $this->markTestSkipped('No se pudo obtener cookies de sesión para la prueba');
            return;
        }
        
        $response = $this->requestPage(self::$baseUrl . '/productos.php');
        
        $this->assertEquals(200, $response['code'], 'Página de productos debería estar accesible con sesión');
        $this->assertStringContainsString('Productos', $response['body'], 'Contenido de productos incorrecto');
    }

    /**
     * Prueba de acceso a la página de ventas
     */
    public function testVentasPageAccess()
    {
        if (empty(self::$cookies)) {
            $this->markTestSkipped('No se pudo obtener cookies de sesión para la prueba');
            return;
        }
        
        $response = $this->requestPage(self::$baseUrl . '/ventas.php');
        
        $this->assertEquals(200, $response['code'], 'Página de ventas debería estar accesible con sesión');
        $this->assertStringContainsString('Ventas', $response['body'], 'Contenido de ventas incorrecto');
    }

    /**
     * Prueba de acceso a la página de facturas
     */
    public function testFacturasPageAccess()
    {
        if (empty(self::$cookies)) {
            $this->markTestSkipped('No se pudo obtener cookies de sesión para la prueba');
            return;
        }
        
        $response = $this->requestPage(self::$baseUrl . '/facturas.php');
        
        $this->assertEquals(200, $response['code'], 'Página de facturas debería estar accesible con sesión');
        $this->assertStringContainsString('Facturas', $response['body'], 'Contenido de facturas incorrecto');
    }

    /**
     * Prueba de cierre de sesión
     */
    public function testLogout()
    {
        if (empty(self::$cookies)) {
            $this->markTestSkipped('No se pudo obtener cookies de sesión para la prueba');
            return;
        }
        
        $response = $this->requestPage(self::$baseUrl . '/logout.php', false);
        
        // Debería redirigir a login
        $this->assertTrue(
            in_array($response['code'], [301, 302]), 
            'Logout debería redirigir'
        );
        
        $this->assertStringContainsString('Location: login.php', $response['header'], 'Redirección de logout incorrecta');
    }
}
