<?php
/**
 * Modelo de Transacciones
 * 
 * Esta clase gestiona todas las operaciones relacionadas con las transacciones
 * en la base de datos, siguiendo el patrón MVC.
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

class TransactionModel {
    private $db;
    
    /**
     * Constructor que inicializa la conexión a la base de datos
     */
    public function __construct() {
        $this->db = getDbConnection();
    }
    
    /**
     * Registra una nueva transacción en el sistema
     * 
     * @param array $data Datos de la transacción
     * @return int|false ID de la transacción creada o false en caso de error
     */
    public function createTransaction($data) {
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("
                INSERT INTO transacciones (
                    usuario_id, tipo, monto, estado, referencia_externa,
                    detalles, created_at, updated_at
                ) VALUES (
                    :usuario_id, :tipo, :monto, :estado, :referencia_externa,
                    :detalles, NOW(), NOW()
                )
            ");
            
            $params = [
                'usuario_id' => $data['usuario_id'],
                'tipo' => $data['tipo'],
                'monto' => $data['monto'],
                'estado' => $data['estado'] ?? 'pendiente',
                'referencia_externa' => $data['referencia_externa'] ?? null,
                'detalles' => json_encode($data['detalles'] ?? []),
            ];
            
            $stmt->execute($params);
            $transactionId = $this->db->lastInsertId();
            
            // Si hay productos, registrarlos
            if (!empty($data['productos'])) {
                foreach ($data['productos'] as $producto) {
                    $stmt = $this->db->prepare("
                        INSERT INTO transaccion_productos (
                            transaccion_id, producto_id, cantidad, precio_unitario,
                            descuento, subtotal
                        ) VALUES (
                            :transaccion_id, :producto_id, :cantidad, :precio_unitario,
                            :descuento, :subtotal
                        )
                    ");
                    
                    $stmt->execute([
                        'transaccion_id' => $transactionId,
                        'producto_id' => $producto['producto_id'],
                        'cantidad' => $producto['cantidad'],
                        'precio_unitario' => $producto['precio_unitario'],
                        'descuento' => $producto['descuento'] ?? 0,
                        'subtotal' => $producto['subtotal']
                    ]);
                }
            }
            
            $this->db->commit();
            return $transactionId;
        } catch (Exception $e) {
            $this->db->rollBack();
            Logger::error("Error al crear transacción: " . $e->getMessage(), Logger::TRANSACTION);
            return false;
        }
    }
    
    /**
     * Actualiza el estado de una transacción
     * 
     * @param int $transactionId ID de la transacción
     * @param string $status Nuevo estado
     * @param array $additionalData Datos adicionales para actualizar
     * @return bool Resultado de la operación
     */
    public function updateTransactionStatus($transactionId, $status, $additionalData = []) {
        try {
            $sql = "UPDATE transacciones SET estado = :estado, updated_at = NOW()";
            $params = ['estado' => $status, 'id' => $transactionId];
            
            // Agregar campos adicionales si existen
            foreach ($additionalData as $field => $value) {
                if (in_array($field, ['referencia_externa', 'detalles'])) {
                    $sql .= ", $field = :$field";
                    $params[$field] = $field === 'detalles' ? json_encode($value) : $value;
                }
            }
            
            $sql .= " WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (Exception $e) {
            Logger::error("Error al actualizar estado de transacción: " . $e->getMessage(), Logger::TRANSACTION);
            return false;
        }
    }
    
    /**
     * Obtiene una transacción por su ID
     * 
     * @param int $transactionId ID de la transacción
     * @return array|false Datos de la transacción o false si no se encuentra
     */
    public function getTransactionById($transactionId) {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, u.nombre as usuario_nombre, u.email as usuario_email
                FROM transacciones t
                JOIN usuarios u ON t.usuario_id = u.id
                WHERE t.id = :id
            ");
            
            $stmt->execute(['id' => $transactionId]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($transaction) {
                // Obtener productos de la transacción
                $stmt = $this->db->prepare("
                    SELECT tp.*, p.nombre as producto_nombre, p.sku
                    FROM transaccion_productos tp
                    JOIN productos p ON tp.producto_id = p.id
                    WHERE tp.transaccion_id = :transaccion_id
                ");
                
                $stmt->execute(['transaccion_id' => $transactionId]);
                $transaction['productos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Decodificar detalles si es JSON
                if (isset($transaction['detalles']) && is_string($transaction['detalles'])) {
                    $transaction['detalles'] = json_decode($transaction['detalles'], true);
                }
            }
            
            return $transaction;
        } catch (Exception $e) {
            Logger::error("Error al obtener transacción: " . $e->getMessage(), Logger::TRANSACTION);
            return false;
        }
    }
    
    /**
     * Obtiene transacciones con filtros y paginación
     * 
     * @param array $filters Filtros a aplicar
     * @param int $page Número de página
     * @param int $perPage Elementos por página
     * @return array Lista de transacciones y total
     */
    public function getTransactions($filters = [], $page = 1, $perPage = 20) {
        try {
            $conditions = [];
            $params = [];
            
            // Construir condiciones basadas en filtros
            if (!empty($filters['usuario_id'])) {
                $conditions[] = "t.usuario_id = :usuario_id";
                $params['usuario_id'] = $filters['usuario_id'];
            }
            
            if (!empty($filters['estado'])) {
                $conditions[] = "t.estado = :estado";
                $params['estado'] = $filters['estado'];
            }
            
            if (!empty($filters['tipo'])) {
                $conditions[] = "t.tipo = :tipo";
                $params['tipo'] = $filters['tipo'];
            }
            
            if (!empty($filters['fecha_desde'])) {
                $conditions[] = "t.created_at >= :fecha_desde";
                $params['fecha_desde'] = $filters['fecha_desde'];
            }
            
            if (!empty($filters['fecha_hasta'])) {
                $conditions[] = "t.created_at <= :fecha_hasta";
                $params['fecha_hasta'] = $filters['fecha_hasta'];
            }
            
            // Construir consulta SQL
            $sql = "
                SELECT t.*, u.nombre as usuario_nombre
                FROM transacciones t
                JOIN usuarios u ON t.usuario_id = u.id
            ";
            
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $sql .= " ORDER BY t.created_at DESC";
            
            // Calcular el total
            $countSql = "SELECT COUNT(*) FROM transacciones t";
            if (!empty($conditions)) {
                $countSql .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $stmt = $this->db->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            // Agregar paginación
            $offset = ($page - 1) * $perPage;
            $sql .= " LIMIT :offset, :limit";
            $params['offset'] = $offset;
            $params['limit'] = $perPage;
            
            $stmt = $this->db->prepare($sql);
            
            // Bind de parámetros específicos para LIMIT
            foreach ($params as $key => $value) {
                if ($key === 'offset' || $key === 'limit') {
                    $stmt->bindValue(":$key", $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue(":$key", $value);
                }
            }
            
            $stmt->execute();
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'transactions' => $transactions,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => ceil($total / $perPage)
            ];
        } catch (Exception $e) {
            Logger::error("Error al obtener transacciones: " . $e->getMessage(), Logger::TRANSACTION);
            return [
                'transactions' => [],
                'total' => 0,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
}
