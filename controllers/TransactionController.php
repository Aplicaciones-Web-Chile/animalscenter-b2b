<?php
/**
 * Controlador de Transacciones
 * 
 * Este controlador maneja todas las operaciones relacionadas con las transacciones
 * en el sistema B2B de AnimalsCenter, siguiendo el patrón MVC.
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

require_once __DIR__ . '/../models/TransactionModel.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../includes/Logger.php';

class TransactionController {
    private $transactionModel;
    private $userModel;
    
    /**
     * Constructor del controlador
     */
    public function __construct() {
        $this->transactionModel = new TransactionModel();
        $this->userModel = new UserModel();
    }
    
    /**
     * Crea una nueva transacción en el sistema
     * 
     * @param array $data Datos de la transacción
     * @return array Resultado de la operación
     */
    public function createTransaction($data) {
        try {
            // Validar datos básicos
            if (empty($data['usuario_id']) || empty($data['tipo']) || empty($data['monto'])) {
                return [
                    'success' => false,
                    'message' => 'Faltan datos obligatorios para crear la transacción'
                ];
            }
            
            // Verificar que el usuario exista
            $user = $this->userModel->getUserById($data['usuario_id']);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'El usuario especificado no existe'
                ];
            }
            
            // Validar el tipo de transacción
            $allowedTypes = ['compra', 'venta', 'abono', 'cargo', 'reembolso'];
            if (!in_array($data['tipo'], $allowedTypes)) {
                return [
                    'success' => false,
                    'message' => 'Tipo de transacción no válido'
                ];
            }
            
            // Validar monto
            if (!is_numeric($data['monto']) || $data['monto'] <= 0) {
                return [
                    'success' => false,
                    'message' => 'El monto debe ser un valor numérico positivo'
                ];
            }
            
            // Validar productos si es una compra o venta
            if (in_array($data['tipo'], ['compra', 'venta']) && empty($data['productos'])) {
                return [
                    'success' => false,
                    'message' => 'Debe especificar al menos un producto para este tipo de transacción'
                ];
            }
            
            // Registrar la transacción
            $transactionId = $this->transactionModel->createTransaction($data);
            
            if ($transactionId) {
                // Registrar en el log
                Logger::info("Transacción creada exitosamente", Logger::TRANSACTION, [
                    'transaction_id' => $transactionId,
                    'user_id' => $data['usuario_id'],
                    'tipo' => $data['tipo'],
                    'monto' => $data['monto']
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Transacción creada exitosamente',
                    'transaction_id' => $transactionId
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al crear la transacción. Intente nuevamente.'
                ];
            }
        } catch (Exception $e) {
            Logger::error("Error al procesar creación de transacción: " . $e->getMessage(), 
                Logger::TRANSACTION, [
                    'trace' => $e->getTraceAsString()
                ]);
            
            return [
                'success' => false,
                'message' => 'Ocurrió un error inesperado al procesar la transacción'
            ];
        }
    }
    
    /**
     * Actualiza el estado de una transacción
     * 
     * @param int $transactionId ID de la transacción
     * @param string $status Nuevo estado
     * @param array $additionalData Datos adicionales para actualizar
     * @return array Resultado de la operación
     */
    public function updateTransactionStatus($transactionId, $status, $additionalData = []) {
        try {
            // Validar que la transacción exista
            $transaction = $this->transactionModel->getTransactionById($transactionId);
            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => 'La transacción especificada no existe'
                ];
            }
            
            // Validar el estado
            $allowedStatuses = ['pendiente', 'procesando', 'completada', 'fallida', 'cancelada', 'reembolsada'];
            if (!in_array($status, $allowedStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Estado de transacción no válido'
                ];
            }
            
            // Validar transición de estado
            if ($transaction['estado'] === 'completada' && !in_array($status, ['reembolsada', 'cancelada'])) {
                return [
                    'success' => false,
                    'message' => 'No se puede cambiar el estado de una transacción completada excepto a reembolsada o cancelada'
                ];
            }
            
            // Actualizar el estado
            $result = $this->transactionModel->updateTransactionStatus($transactionId, $status, $additionalData);
            
            if ($result) {
                // Registrar en el log
                Logger::info("Actualización de estado de transacción", Logger::TRANSACTION, [
                    'transaction_id' => $transactionId,
                    'estado_anterior' => $transaction['estado'],
                    'estado_nuevo' => $status
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Estado de transacción actualizado correctamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al actualizar el estado de la transacción. Intente nuevamente.'
                ];
            }
        } catch (Exception $e) {
            Logger::error("Error al actualizar estado de transacción: " . $e->getMessage(), 
                Logger::TRANSACTION, [
                    'transaction_id' => $transactionId,
                    'trace' => $e->getTraceAsString()
                ]);
            
            return [
                'success' => false,
                'message' => 'Ocurrió un error inesperado al actualizar la transacción'
            ];
        }
    }
    
    /**
     * Obtiene una transacción por su ID
     * 
     * @param int $transactionId ID de la transacción
     * @return array Resultado de la operación y datos de la transacción
     */
    public function getTransaction($transactionId) {
        try {
            $transaction = $this->transactionModel->getTransactionById($transactionId);
            
            if ($transaction) {
                return [
                    'success' => true,
                    'transaction' => $transaction
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'La transacción especificada no existe o no se pudo encontrar'
                ];
            }
        } catch (Exception $e) {
            Logger::error("Error al obtener transacción: " . $e->getMessage(), 
                Logger::TRANSACTION, [
                    'transaction_id' => $transactionId,
                    'trace' => $e->getTraceAsString()
                ]);
            
            return [
                'success' => false,
                'message' => 'Ocurrió un error inesperado al obtener la transacción'
            ];
        }
    }
    
    /**
     * Obtiene lista de transacciones con filtros y paginación
     * 
     * @param array $filters Filtros a aplicar
     * @param int $page Número de página
     * @param int $perPage Elementos por página
     * @return array Lista de transacciones y metadatos
     */
    public function getTransactions($filters = [], $page = 1, $perPage = 20) {
        try {
            $result = $this->transactionModel->getTransactions($filters, $page, $perPage);
            
            return [
                'success' => true,
                'data' => $result
            ];
        } catch (Exception $e) {
            Logger::error("Error al obtener lista de transacciones: " . $e->getMessage(), 
                Logger::TRANSACTION, [
                    'filters' => json_encode($filters),
                    'trace' => $e->getTraceAsString()
                ]);
            
            return [
                'success' => false,
                'message' => 'Ocurrió un error inesperado al obtener las transacciones',
                'data' => [
                    'transactions' => [],
                    'total' => 0,
                    'page' => $page,
                    'perPage' => $perPage,
                    'totalPages' => 0
                ]
            ];
        }
    }
}
