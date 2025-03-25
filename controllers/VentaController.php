<?php
/**
 * Controlador para la gestión de ventas
 * Maneja la lógica de las operaciones de ventas
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

require_once __DIR__ . '/../models/VentaModel.php';
require_once __DIR__ . '/../includes/Logger.php';

class VentaController {
    private $ventaModel;
    
    /**
     * Constructor de la clase
     */
    public function __construct() {
        $this->ventaModel = new VentaModel();
    }
    
    /**
     * Obtiene todas las ventas con paginación
     * 
     * @param array $params Parámetros de paginación y filtros
     * @return array Datos de las ventas
     */
    public function getVentas($params) {
        try {
            $offset = $params['offset'] ?? 0;
            $limite = $params['limite'] ?? 10;
            $proveedorRut = $params['proveedorRut'] ?? null;
            
            $ventas = $this->ventaModel->getVentas($offset, $limite, $proveedorRut);
            $totalVentas = $this->ventaModel->getTotalVentas($proveedorRut);
            
            return [
                'success' => true,
                'data' => [
                    'ventas' => $ventas,
                    'total' => $totalVentas
                ]
            ];
        } catch (Exception $e) {
            Logger::error(
                'ventas_error', 
                'Error al obtener ventas: ' . $e->getMessage(),
                ['error' => $e->getMessage()]
            );
            
            return [
                'success' => false,
                'message' => 'Error al obtener las ventas',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Crea una nueva venta
     * 
     * @param array $datos Datos de la venta a crear
     * @return array Resultado de la operación
     */
    public function crearVenta($datos) {
        try {
            $resultado = $this->ventaModel->crearVenta($datos);
            
            if ($resultado['success']) {
                Logger::info(
                    'venta_creada', 
                    'Venta creada correctamente',
                    ['venta_id' => $resultado['id']]
                );
            }
            
            return $resultado;
        } catch (Exception $e) {
            Logger::error(
                'venta_creacion_error', 
                'Error al crear la venta: ' . $e->getMessage(),
                ['error' => $e->getMessage()]
            );
            
            return [
                'success' => false,
                'message' => 'Error al crear la venta',
                'error' => $e->getMessage()
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
            $resultado = $this->ventaModel->actualizarVenta($id, $datos);
            
            if ($resultado['success']) {
                Logger::info(
                    'venta_actualizada', 
                    'Venta actualizada correctamente',
                    ['venta_id' => $id]
                );
            }
            
            return $resultado;
        } catch (Exception $e) {
            Logger::error(
                'venta_actualizacion_error', 
                'Error al actualizar la venta: ' . $e->getMessage(),
                ['error' => $e->getMessage()]
            );
            
            return [
                'success' => false,
                'message' => 'Error al actualizar la venta',
                'error' => $e->getMessage()
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
            $resultado = $this->ventaModel->eliminarVenta($id);
            
            if ($resultado['success']) {
                Logger::info(
                    'venta_eliminada', 
                    'Venta eliminada correctamente',
                    ['venta_id' => $id]
                );
            }
            
            return $resultado;
        } catch (Exception $e) {
            Logger::error(
                'venta_eliminacion_error', 
                'Error al eliminar la venta: ' . $e->getMessage(),
                ['error' => $e->getMessage()]
            );
            
            return [
                'success' => false,
                'message' => 'Error al eliminar la venta',
                'error' => $e->getMessage()
            ];
        }
    }
}
