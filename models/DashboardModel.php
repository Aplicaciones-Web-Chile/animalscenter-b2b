<?php
/**
 * Modelo para el dashboard
 * Maneja todas las consultas relacionadas con las estadísticas del dashboard
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

class DashboardModel {
    private $db;

    /**
     * Constructor de la clase
     */
    public function __construct() {
        require_once __DIR__ . '/../config/database.php';
        $this->db = getDbConnection();
    }

    /**
     * Obtiene el total de productos con filtro opcional de proveedor
     * 
     * @param string|null $proveedorRut RUT del proveedor para filtrar
     * @return int Total de productos
     */
    public function getTotalProductos($proveedorRut = null) {
        $sql = "SELECT COUNT(*) as total FROM productos";
        $params = [];
        
        if ($proveedorRut) {
            $sql .= " WHERE proveedor_rut = ?";
            $params[] = $proveedorRut;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Obtiene el total de ventas con filtro opcional de proveedor
     * 
     * @param string|null $proveedorRut RUT del proveedor para filtrar
     * @return int Total de ventas
     */
    public function getTotalVentas($proveedorRut = null) {
        $sql = "SELECT COUNT(*) as total FROM ventas";
        $params = [];
        
        if ($proveedorRut) {
            $sql .= " WHERE proveedor_rut = ?";
            $params[] = $proveedorRut;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Obtiene el total de facturas con filtro opcional de proveedor
     * 
     * @param string|null $proveedorRut RUT del proveedor para filtrar
     * @return int Total de facturas
     */
    public function getTotalFacturas($proveedorRut = null) {
        $sql = "SELECT COUNT(*) as total FROM facturas";
        $params = [];
        
        if ($proveedorRut) {
            $sql .= " WHERE proveedor_rut = ?";
            $params[] = $proveedorRut;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Obtiene el total de facturas pendientes con filtro opcional de proveedor
     * 
     * @param string|null $proveedorRut RUT del proveedor para filtrar
     * @return int Total de facturas pendientes
     */
    public function getFacturasPendientes($proveedorRut = null) {
        $sql = "SELECT COUNT(*) as total FROM facturas WHERE estado = 'pendiente'";
        $params = [];
        
        if ($proveedorRut) {
            $sql .= " AND proveedor_rut = ?";
            $params[] = $proveedorRut;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Obtiene las ventas más recientes con filtro opcional de proveedor
     * 
     * @param int $limit Cantidad de ventas a obtener
     * @param string|null $proveedorRut RUT del proveedor para filtrar
     * @return array Ventas recientes
     */
    public function getVentasRecientes($limit = 5, $proveedorRut = null) {
        $sql = "SELECT v.id, p.nombre, v.cantidad, v.fecha 
                FROM ventas v 
                JOIN productos p ON v.producto_id = p.id";
        $params = [];
        
        if ($proveedorRut) {
            $sql .= " WHERE v.proveedor_rut = ?";
            $params[] = $proveedorRut;
        }
        
        $sql .= " ORDER BY v.fecha DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
