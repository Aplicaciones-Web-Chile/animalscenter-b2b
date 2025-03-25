<?php
/**
 * Modelo para la gestión de productos
 * Maneja todas las operaciones relacionadas con productos en la base de datos
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

class ProductoModel {
    private $db;
    
    /**
     * Constructor de la clase
     */
    public function __construct() {
        require_once __DIR__ . '/../config/database.php';
        $this->db = getDbConnection();
    }
    
    /**
     * Obtiene el total de productos con filtros opcionales
     * 
     * @param string|null $proveedorRut RUT del proveedor para filtrar
     * @param string|null $busqueda Término de búsqueda para filtrar productos
     * @return int Total de productos
     */
    public function getTotalProductos($proveedorRut = null, $busqueda = null) {
        $sql = "SELECT COUNT(*) as total FROM productos";
        $params = [];
        $where = [];
        
        if ($proveedorRut) {
            $where[] = "proveedor_rut = ?";
            $params[] = $proveedorRut;
        }
        
        if ($busqueda) {
            $where[] = "(nombre LIKE ? OR sku LIKE ?)";
            $params[] = "%$busqueda%";
            $params[] = "%$busqueda%";
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
     * Obtiene listado de productos con paginación y filtros
     * 
     * @param int $offset Registros a saltar para paginación
     * @param int $limite Cantidad de registros a obtener
     * @param string $orden Campo para ordenar
     * @param string $direccion Dirección del ordenamiento (asc/desc)
     * @param string|null $proveedorRut RUT del proveedor para filtrar
     * @param string|null $busqueda Término de búsqueda para filtrar productos
     * @return array Listado de productos
     */
    public function getProductos($offset = 0, $limite = 10, $orden = 'nombre', $direccion = 'asc', $proveedorRut = null, $busqueda = null) {
        // Validación de parámetros de ordenamiento (evitar SQL Injection)
        $ordenValido = ['nombre', 'sku', 'stock', 'precio'];
        $direccionValida = ['asc', 'desc'];
        
        if (!in_array($orden, $ordenValido)) {
            $orden = 'nombre';
        }
        
        if (!in_array($direccion, $direccionValida)) {
            $direccion = 'asc';
        }
        
        $sql = "SELECT p.*, u.nombre as proveedor 
                FROM productos p 
                LEFT JOIN usuarios u ON p.proveedor_rut = u.rut";
                
        $params = [];
        $where = [];
        
        if ($proveedorRut) {
            $where[] = "p.proveedor_rut = ?";
            $params[] = $proveedorRut;
        }
        
        if ($busqueda) {
            $where[] = "(p.nombre LIKE ? OR p.sku LIKE ?)";
            $params[] = "%$busqueda%";
            $params[] = "%$busqueda%";
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY p.$orden $direccion LIMIT ?, ?";
        $params[] = (int)$offset;
        $params[] = (int)$limite;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene un producto por su ID
     * 
     * @param int $id ID del producto
     * @return array|false Datos del producto o false si no existe
     */
    public function getProductoPorId($id) {
        $sql = "SELECT p.*, u.nombre as proveedor 
                FROM productos p 
                LEFT JOIN usuarios u ON p.proveedor_rut = u.rut 
                WHERE p.id = ?";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Crea un nuevo producto
     * 
     * @param array $datos Datos del producto a crear
     * @return array Resultado de la operación
     */
    public function crearProducto($datos) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO productos (
                    sku, nombre, descripcion, precio, stock, 
                    proveedor_rut, categoria, created_at, updated_at
                ) VALUES (
                    :sku, :nombre, :descripcion, :precio, :stock, 
                    :proveedor_rut, :categoria, NOW(), NOW()
                )
            ");
            
            $resultado = $stmt->execute([
                'sku' => $datos['sku'],
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'] ?? '',
                'precio' => $datos['precio'],
                'stock' => $datos['stock'],
                'proveedor_rut' => $datos['proveedor_rut'],
                'categoria' => $datos['categoria'] ?? null
            ]);
            
            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Producto creado correctamente',
                    'id' => $this->db->lastInsertId()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al crear el producto'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error al crear el producto: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Actualiza un producto existente
     * 
     * @param int $id ID del producto a actualizar
     * @param array $datos Datos del producto a actualizar
     * @return array Resultado de la operación
     */
    public function actualizarProducto($id, $datos) {
        try {
            $stmt = $this->db->prepare("
                UPDATE productos SET 
                    sku = :sku,
                    nombre = :nombre,
                    descripcion = :descripcion,
                    precio = :precio,
                    stock = :stock,
                    proveedor_rut = :proveedor_rut,
                    categoria = :categoria,
                    updated_at = NOW()
                WHERE id = :id
            ");
            
            $resultado = $stmt->execute([
                'sku' => $datos['sku'],
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'] ?? '',
                'precio' => $datos['precio'],
                'stock' => $datos['stock'],
                'proveedor_rut' => $datos['proveedor_rut'],
                'categoria' => $datos['categoria'] ?? null,
                'id' => $id
            ]);
            
            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Producto actualizado correctamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al actualizar el producto'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar el producto: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Elimina un producto
     * 
     * @param int $id ID del producto a eliminar
     * @return array Resultado de la operación
     */
    public function eliminarProducto($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM productos WHERE id = ?");
            $resultado = $stmt->execute([$id]);
            
            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Producto eliminado correctamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al eliminar el producto'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar el producto: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Actualiza el stock de un producto
     * 
     * @param int $id ID del producto
     * @param int $cantidad Cantidad a sumar o restar del stock
     * @return array Resultado de la operación
     */
    public function actualizarStock($id, $cantidad) {
        try {
            $stmt = $this->db->prepare("
                UPDATE productos 
                SET stock = stock + (:cantidad), 
                    updated_at = NOW() 
                WHERE id = :id
            ");
            
            $resultado = $stmt->execute([
                'cantidad' => $cantidad,
                'id' => $id
            ]);
            
            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Stock actualizado correctamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al actualizar el stock'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar el stock: ' . $e->getMessage()
            ];
        }
    }
}
