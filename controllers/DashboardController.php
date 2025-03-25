<?php
/**
 * Controlador para el dashboard
 * Maneja la lógica de las estadísticas y elementos visualizados en el dashboard
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

require_once __DIR__ . '/../models/DashboardModel.php';
require_once __DIR__ . '/../includes/Logger.php';

class DashboardController {
    private $dashboardModel;
    
    /**
     * Constructor de la clase
     */
    public function __construct() {
        $this->dashboardModel = new DashboardModel();
    }
    
    /**
     * Obtiene todas las estadísticas necesarias para el dashboard
     * 
     * @param string|null $proveedorRut RUT del proveedor para filtrar (si aplica)
     * @return array Datos del dashboard
     */
    public function getDashboardData($proveedorRut = null) {
        try {
            $data = [
                'totalProductos' => $this->dashboardModel->getTotalProductos($proveedorRut),
                'totalVentas' => $this->dashboardModel->getTotalVentas($proveedorRut),
                'totalFacturas' => $this->dashboardModel->getTotalFacturas($proveedorRut),
                'facturasPendientes' => $this->dashboardModel->getFacturasPendientes($proveedorRut),
                'ventasRecientes' => $this->dashboardModel->getVentasRecientes(5, $proveedorRut)
            ];
            
            // Registrar la visualización del dashboard
            Logger::info(
                'dashboard_view', 
                'Usuario visualizó el dashboard', 
                ['provider_filter' => $proveedorRut ? true : false]
            );
            
            return [
                'success' => true,
                'data' => $data
            ];
        } catch (Exception $e) {
            Logger::error(
                'dashboard_error', 
                'Error al cargar datos del dashboard: ' . $e->getMessage(),
                ['error' => $e->getMessage()]
            );
            
            return [
                'success' => false,
                'message' => 'Error al cargar los datos del dashboard',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verifica si el usuario tiene permisos para ver el dashboard y obtiene 
     * los datos correspondientes según su rol
     * 
     * @param array $user Datos del usuario
     * @return array Datos del dashboard con permisos aplicados
     */
    public function getDashboardForUser($user) {
        // Si no hay usuario o no tiene rol, no tiene acceso
        if (!$user || !isset($user['rol'])) {
            return [
                'success' => false,
                'message' => 'Acceso no autorizado',
                'redirect' => 'login.php'
            ];
        }
        
        // Si es proveedor, filtrar por su RUT
        $proveedorRut = null;
        if ($user['rol'] === 'proveedor') {
            $proveedorRut = $user['rut'];
        }
        
        // Obtener datos del dashboard
        $dashboardData = $this->getDashboardData($proveedorRut);
        
        // Incluir información de permisos según el rol
        $dashboardData['data']['permisos'] = [
            'puedeExportar' => $user['rol'] === 'admin',
            'puedeVerTodosLosProveedores' => $user['rol'] === 'admin'
        ];
        
        return $dashboardData;
    }
}
