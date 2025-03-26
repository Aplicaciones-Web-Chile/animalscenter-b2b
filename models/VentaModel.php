<?php
/**
 * Modelo para la gestión de ventas
 * Maneja todas las operaciones relacionadas con ventas en la base de datos
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

class VentaModel {
    private $db;
    
    /**
     * Constructor de la clase
     */
    public function __construct() {
        require_once __DIR__ . '/../config/database.php';
        $this->db = getDbConnection();
    }
    
    /**
     * Obtiene el total de ventas con filtros opcionales
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
     * Obtiene listado de ventas con paginación y filtros
     * 
     * @param int $offset Registros a saltar para paginación
     * @param int $limite Cantidad de registros a obtener
     * @param string|null $proveedorRut RUT del proveedor para filtrar
     * @return array Listado de ventas
     */
    public function getVentas($offset = 0, $limite = 10, $proveedorRut = null) {
        $sql = "SELECT v.*, p.nombre as producto 
                FROM ventas v 
                JOIN productos p ON v.producto_id = p.id";
        $params = [];
        
        if ($proveedorRut) {
            $sql .= " WHERE v.proveedor_rut = ?";
            $params[] = $proveedorRut;
        }
        
        $sql .= " ORDER BY v.fecha DESC LIMIT ?, ?";
        $params[] = (int)$offset;
        $params[] = (int)$limite;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Crea una nueva venta
     * 
     * @param array $datos Datos de la venta a crear
     * @return array Resultado de la operación
     */
    public function crearVenta($datos) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ventas (
                    producto_id, cantidad, precio_total, fecha, 
                    proveedor_rut, created_at, updated_at
                ) VALUES (
                    :producto_id, :cantidad, :precio_total, NOW(), 
                    :proveedor_rut, NOW(), NOW()
                )
            ");
            
            $resultado = $stmt->execute([
                'producto_id' => $datos['producto_id'],
                'cantidad' => $datos['cantidad'],
                'precio_total' => $datos['precio_total'],
                'proveedor_rut' => $datos['proveedor_rut']
            ]);
            
            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Venta creada correctamente',
                    'id' => $this->db->lastInsertId()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al crear la venta'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error al crear la venta: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Actualiza una venta existente
     * 
     * @param int $id ID de la venta a actualizar
     * @param array $datos Datos de la venta a actualizar
     * @return array Resultado de la operación
     */
    public function actualizarVenta($id, $datos) {
        try {
            $stmt = $this->db->prepare("
                UPDATE ventas SET 
                    producto_id = :producto_id,
                    cantidad = :cantidad,
                    precio_total = :precio_total,
                    proveedor_rut = :proveedor_rut,
                    updated_at = NOW()
                WHERE id = :id
            ");
            
            $resultado = $stmt->execute([
                'producto_id' => $datos['producto_id'],
                'cantidad' => $datos['cantidad'],
                'precio_total' => $datos['precio_total'],
                'proveedor_rut' => $datos['proveedor_rut'],
                'id' => $id
            ]);
            
            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Venta actualizada correctamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al actualizar la venta'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar la venta: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Elimina una venta
     * 
     * @param int $id ID de la venta a eliminar
     * @return array Resultado de la operación
     */
    public function eliminarVenta($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM ventas WHERE id = ?");
            $resultado = $stmt->execute([$id]);
            
            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Venta eliminada correctamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al eliminar la venta'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar la venta: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene el total de unidades vendidas
     * 
     * @param string|null $proveedorRut RUT del proveedor para filtrar
     * @return int Total de unidades vendidas
     */
    public function getTotalUnidades($proveedorRut = null) {
        $sql = "SELECT SUM(cantidad) as total FROM ventas";
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
}
