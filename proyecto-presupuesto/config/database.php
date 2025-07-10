<?php

require_once __DIR__ . '/config.php';

class Database
{
    private $conn;
    private static $instance = null;
    private $lastError = null; 

    private function __construct()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log("Error de conexión: " . $e->getMessage());
            die("Error crítico de conexión. Por favor, intente más tarde.");
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    public function query($sql, $params = [])
    {
        try {
            $this->lastError = null; 
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage(); 
            error_log("Error en consulta: " . $e->getMessage());
            return false;
        }
    }

    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function fetchOne($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : null;
    }

    public function insert($table, $data)
    {
        try {
            $this->lastError = null;
            $columns = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
            $stmt = $this->query($sql, $data);
            return $stmt ? $this->conn->lastInsertId() : false;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            error_log("Error en insert: " . $e->getMessage());
            return false;
        }
    }

    public function update($table, $data, $where, $whereParams = [])
    {
        try {
            $this->lastError = null; 
            $setParts = [];
            foreach ($data as $key => $value) {
                $setParts[] = "{$key} = :{$key}";
            }
            $setClause = implode(', ', $setParts);
            $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
            
            $updateParams = [];
            foreach ($data as $key => $value) {
                $updateParams[$key] = $value;
            }
            
            for ($i = 0; $i < count($whereParams); $i++) {
                $updateParams['where_param_' . $i] = $whereParams[$i];
            }
            
            $whereParamCount = 0;
            $modifiedWhere = preg_replace_callback('/\?/', function($matches) use (&$whereParamCount) {
                return ':where_param_' . ($whereParamCount++);
            }, $where);
            
            $finalSql = "UPDATE {$table} SET {$setClause} WHERE {$modifiedWhere}";
            
            error_log("SQL ejecutado: " . $finalSql);
            error_log("Parámetros: " . print_r($updateParams, true));
            
            $stmt = $this->conn->prepare($finalSql);
            $result = $stmt->execute($updateParams);
            
            return $result;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            error_log("Error en update: " . $e->getMessage());
            return false;
        }
    }

    public function delete($table, $where, $params = [])
    {
        try {
            $this->lastError = null; 
            $sql = "DELETE FROM {$table} WHERE {$where}";
            return $this->query($sql, $params);
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            error_log("Error en delete: " . $e->getMessage());
            return false;
        }
    }

    public function beginTransaction()
    {
        return $this->conn->beginTransaction();
    }

    public function commit()
    {
        return $this->conn->commit();
    }

    public function rollback()
    {
        return $this->conn->rollback();
    }

    public function getConnection()
    {
        return $this->conn;
    }
}


$db = Database::getInstance();