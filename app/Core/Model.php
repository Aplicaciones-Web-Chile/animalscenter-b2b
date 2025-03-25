<?php
/**
 * Modelo base
 * 
 * Proporciona funcionalidades comunes para todos los modelos
 * de la aplicación, como acceso a la base de datos y operaciones CRUD
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

namespace App\Core;

abstract class Model {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $hidden = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Busca un registro por su clave primaria
     * 
     * @param mixed $id Valor de la clave primaria
     * @return array|null Registro encontrado o null
     */
    public function find($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }
    
    /**
     * Busca todos los registros
     * 
     * @return array Registros encontrados
     */
    public function all() {
        $sql = "SELECT * FROM {$this->table}";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Busca registros con condiciones
     * 
     * @param array $conditions Condiciones en formato [campo => valor]
     * @param array $options Opciones adicionales (order, limit, offset)
     * @return array Registros encontrados
     */
    public function where($conditions, $options = []) {
        $sql = "SELECT * FROM {$this->table}";
        
        // Construir cláusula WHERE
        if (!empty($conditions)) {
            $whereClauses = [];
            $params = [];
            
            foreach ($conditions as $field => $value) {
                $whereClauses[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
            
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        } else {
            $params = [];
        }
        
        // Aplicar opciones adicionales
        if (!empty($options['order'])) {
            $sql .= " ORDER BY {$options['order']}";
        }
        
        if (!empty($options['limit'])) {
            $sql .= " LIMIT {$options['limit']}";
            
            if (!empty($options['offset'])) {
                $sql .= " OFFSET {$options['offset']}";
            }
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Busca un registro con condiciones
     * 
     * @param array $conditions Condiciones en formato [campo => valor]
     * @return array|null Registro encontrado o null
     */
    public function first($conditions) {
        return $this->where($conditions, ['limit' => 1])[0] ?? null;
    }
    
    /**
     * Cuenta registros con condiciones
     * 
     * @param array $conditions Condiciones en formato [campo => valor]
     * @return int Número de registros
     */
    public function count($conditions = []) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        
        // Construir cláusula WHERE
        if (!empty($conditions)) {
            $whereClauses = [];
            $params = [];
            
            foreach ($conditions as $field => $value) {
                $whereClauses[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
            
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        } else {
            $params = [];
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return (int) ($result['count'] ?? 0);
    }
    
    /**
     * Inserta un nuevo registro
     * 
     * @param array $data Datos del registro
     * @return int|bool ID del registro insertado o false
     */
    public function insert($data) {
        // Filtrar campos permitidos
        $data = $this->filterData($data);
        
        if (empty($data)) {
            return false;
        }
        
        // Construir consulta
        $fields = array_keys($data);
        $placeholders = array_map(function($field) {
            return ":{$field}";
        }, $fields);
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        // Ejecutar consulta
        $stmt = $this->db->query($sql, $data);
        
        if ($stmt) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Actualiza un registro
     * 
     * @param mixed $id Valor de la clave primaria
     * @param array $data Datos a actualizar
     * @return bool Resultado de la operación
     */
    public function update($id, $data) {
        // Filtrar campos permitidos
        $data = $this->filterData($data);
        
        if (empty($data)) {
            return false;
        }
        
        // Construir consulta
        $setClauses = array_map(function($field) {
            return "{$field} = :{$field}";
        }, array_keys($data));
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses) . " WHERE {$this->primaryKey} = :id";
        
        // Agregar ID a los parámetros
        $data['id'] = $id;
        
        // Ejecutar consulta
        $stmt = $this->db->query($sql, $data);
        
        return $stmt ? true : false;
    }
    
    /**
     * Elimina un registro
     * 
     * @param mixed $id Valor de la clave primaria
     * @return bool Resultado de la operación
     */
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->query($sql, ['id' => $id]);
        
        return $stmt ? true : false;
    }
    
    /**
     * Filtra los datos según los campos permitidos
     * 
     * @param array $data Datos a filtrar
     * @return array Datos filtrados
     */
    protected function filterData($data) {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }
    
    /**
     * Oculta campos protegidos de un registro
     * 
     * @param array $record Registro a procesar
     * @return array Registro con campos ocultos eliminados
     */
    protected function hideFields($record) {
        if (empty($this->hidden) || empty($record)) {
            return $record;
        }
        
        foreach ($this->hidden as $field) {
            unset($record[$field]);
        }
        
        return $record;
    }
    
    /**
     * Búsqueda personalizada
     * 
     * @param string $sql Consulta SQL
     * @param array $params Parámetros
     * @return array Resultados
     */
    protected function query($sql, $params = []) {
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Búsqueda personalizada de un solo registro
     * 
     * @param string $sql Consulta SQL
     * @param array $params Parámetros
     * @return array|null Resultado o null
     */
    protected function queryOne($sql, $params = []) {
        return $this->db->fetchOne($sql, $params);
    }
    
    /**
     * Ejecuta una consulta SQL personalizada
     * 
     * @param string $sql Consulta SQL
     * @param array $params Parámetros
     * @return bool Resultado de la operación
     */
    protected function execute($sql, $params = []) {
        $stmt = $this->db->query($sql, $params);
        return $stmt ? true : false;
    }
    
    /**
     * Inicia una transacción
     * 
     * @return bool Resultado de la operación
     */
    protected function beginTransaction() {
        return $this->db->beginTransaction();
    }
    
    /**
     * Confirma una transacción
     * 
     * @return bool Resultado de la operación
     */
    protected function commit() {
        return $this->db->commit();
    }
    
    /**
     * Revierte una transacción
     * 
     * @return bool Resultado de la operación
     */
    protected function rollBack() {
        return $this->db->rollBack();
    }
}
