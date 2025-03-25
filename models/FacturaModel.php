<?php
/**
 * Modelo para la gestión de facturas
 * Maneja todas las operaciones relacionadas con facturas en la base de datos
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

class FacturaModel {
    private $db;
    
    /**
     * Constructor de la clase
     */
    public function __construct() {
        require_once __DIR__ . '/../config/database.php';
        $this->db = getDbConnection();
    }
    
    /**
     * Obtiene el total de facturas con filtros opcionales
     * 
     * @param string|null $proveedorRut RUT del proveedor para filtrar
     * @param string|null $estado Estado de la factura para filtrar
     * @return int Total de facturas
     */
    public function getTotalFacturas($proveedorRut = null, $estado = null) {
        $sql = "SELECT COUNT(*) as total FROM facturas";
        $params = [];
        $where = [];
        
        if ($proveedorRut) {
            $where[] = "proveedor_rut = ?";
            $params[] = $proveedorRut;
        }
        
        if ($estado) {
            $where[] = "estado = ?";
            $params[] = $estado;
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Obtiene listado de facturas con paginación y filtros
     * 
     * @param int $offset Registros a saltar para paginación
     * @param int $limite Cantidad de registros a obtener
     * @param string|null $proveedorRut RUT del proveedor para filtrar
     * @param string|null $estado Estado de la factura para filtrar
     * @return array Listado de facturas
     */
    public function getFacturas($offset = 0, $limite = 10, $proveedorRut = null, $estado = null) {
        $sql = "SELECT f.*, u.nombre as proveedor 
                FROM facturas f 
                LEFT JOIN usuarios u ON f.proveedor_rut = u.rut";
        $params = [];
        $where = [];
        
        if ($proveedorRut) {
            $where[] = "f.proveedor_rut = ?";
            $params[] = $proveedorRut;
        }
        
        if ($estado) {
            $where[] = "f.estado = ?";
            $params[] = $estado;
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY f.fecha DESC LIMIT ?, ?";
        $params[] = (int)$offset;
        $params[] = (int)$limite;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Crea una nueva factura
     * 
     * @param array $datos Datos de la factura a crear
     * @return array Resultado de la operación
     */
    public function crearFactura($datos) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO facturas (
                    numero, fecha, total, estado, 
                    proveedor_rut, created_at, updated_at
                ) VALUES (
                    :numero, NOW(), :total, :estado, 
                    :proveedor_rut, NOW(), NOW()
                )
            ");
            
            $resultado = $stmt->execute([
                'numero' => $datos['numero'],
                'total' => $datos['total'],
                'estado' => $datos['estado'],
                'proveedor_rut' => $datos['proveedor_rut']
            ]);
            
            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Factura creada correctamente',
                    'id' => $this->db->lastInsertId()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al crear la factura'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error al crear la factura: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Actualiza una factura existente
     * 
     * @param int $id ID de la factura a actualizar
     * @param array $datos Datos de la factura a actualizar
     * @return array Resultado de la operación
     */
    public function actualizarFactura($id, $datos) {
        try {
            $stmt = $this->db->prepare("
                UPDATE facturas SET 
                    numero = :numero,
                    total = :total,
                    estado = :estado,
                    proveedor_rut = :proveedor_rut,
                    updated_at = NOW()
                WHERE id = :id
            ");
            
            $resultado = $stmt->execute([
                'numero' => $datos['numero'],
                'total' => $datos['total'],
                'estado' => $datos['estado'],
                'proveedor_rut' => $datos['proveedor_rut'],
                'id' => $id
            ]);
            
            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Factura actualizada correctamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al actualizar la factura'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar la factura: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Elimina una factura
     * 
     * @param int $id ID de la factura a eliminar
     * @return array Resultado de la operación
     */
    public function eliminarFactura($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM facturas WHERE id = ?");
            $resultado = $stmt->execute([$id]);
            
            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Factura eliminada correctamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al eliminar la factura'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar la factura: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene el total de monto de las facturas
     * 
     * @param string|null $proveedorRut RUT del proveedor para filtrar
     * @return float Total de monto de las facturas
     */
    public function getTotalMonto($proveedorRut = null) {
        try {
            $sql = "SELECT SUM(total) as total_monto FROM facturas";
            $params = [];
            
            if ($proveedorRut) {
                $sql .= " WHERE proveedor_rut = ?";
                $params[] = $proveedorRut;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? (float)$result['total_monto'] : 0;
        } catch (PDOException $e) {
            error_log("Error al obtener el total de facturas: " . $e->getMessage());
            return 0;
        }
    }
}
