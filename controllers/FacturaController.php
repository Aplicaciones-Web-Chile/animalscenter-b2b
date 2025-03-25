<?php
/**
 * Controlador para la gestión de facturas
 * Maneja la lógica de las operaciones de facturación
 * 
 * @autor AnimalsCenter B2B Development Team
 * @version 1.0
 */

require_once __DIR__ . '/../models/FacturaModel.php';
require_once __DIR__ . '/../includes/Logger.php';

class FacturaController {
    private $facturaModel;
    
    /**
     * Constructor de la clase
     */
    public function __construct() {
        $this->facturaModel = new FacturaModel();
    }
    
    /**
     * Obtiene todas las facturas con paginación
     * 
     * @param array $params Parámetros de paginación y filtros
     * @return array Datos de las facturas
     */
    public function getFacturas($params) {
        try {
            $offset = $params['offset'] ?? 0;
            $limite = $params['limite'] ?? 10;
            $proveedorRut = $params['proveedorRut'] ?? null;
            $estado = $params['estado'] ?? null;
            
            $facturas = $this->facturaModel->getFacturas($offset, $limite, $proveedorRut, $estado);
            $totalFacturas = $this->facturaModel->getTotalFacturas($proveedorRut, $estado);
            
            return [
                'success' => true,
                'data' => [
                    'facturas' => $facturas,
                    'total' => $totalFacturas
                ]
            ];
        } catch (Exception $e) {
            Logger::error(
                'facturas_error', 
                'Error al obtener facturas: ' . $e->getMessage(),
                ['error' => $e->getMessage()]
            );
            
            return [
                'success' => false,
                'message' => 'Error al obtener las facturas',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Crea una nueva factura
     * 
     * @param array $datos Datos de la factura a crear
     * @return array Resultado de la operación
     */
    public function crearFactura($datos) {
        try {
            $resultado = $this->facturaModel->crearFactura($datos);
            
            if ($resultado['success']) {
                Logger::info(
                    'factura_creada', 
                    'Factura creada correctamente',
                    ['factura_id' => $resultado['id']]
                );
            }
            
            return $resultado;
        } catch (Exception $e) {
            Logger::error(
                'factura_creacion_error', 
                'Error al crear la factura: ' . $e->getMessage(),
                ['error' => $e->getMessage()]
            );
            
            return [
                'success' => false,
                'message' => 'Error al crear la factura',
                'error' => $e->getMessage()
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
            $resultado = $this->facturaModel->actualizarFactura($id, $datos);
            
            if ($resultado['success']) {
                Logger::info(
                    'factura_actualizada', 
                    'Factura actualizada correctamente',
                    ['factura_id' => $id]
                );
            }
            
            return $resultado;
        } catch (Exception $e) {
            Logger::error(
                'factura_actualizacion_error', 
                'Error al actualizar la factura: ' . $e->getMessage(),
                ['error' => $e->getMessage()]
            );
            
            return [
                'success' => false,
                'message' => 'Error al actualizar la factura',
                'error' => $e->getMessage()
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
            $resultado = $this->facturaModel->eliminarFactura($id);
            
            if ($resultado['success']) {
                Logger::info(
                    'factura_eliminada', 
                    'Factura eliminada correctamente',
                    ['factura_id' => $id]
                );
            }
            
            return $resultado;
        } catch (Exception $e) {
            Logger::error(
                'factura_eliminacion_error', 
                'Error al eliminar la factura: ' . $e->getMessage(),
                ['error' => $e->getMessage()]
            );
            
            return [
                'success' => false,
                'message' => 'Error al eliminar la factura',
                'error' => $e->getMessage()
            ];
        }
    }
}
