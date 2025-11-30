<?php
/**
 * Megabre StokMaster Pro
 * Database Class - PDO wrapper
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $charset = DB_CHARSET;
    private $pdo;
    private $stmt;
    private $error;
    private static $instance = null;
    private $query_count = 0;
    private $query_time = 0;
    
    /**
     * Singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
        $options = [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            die("Veritabanı bağlantısı başarısız: " . $this->error);
        }
    }
    
    /**
     * Prepare statement
     */
    public function query($sql) {
        $start_time = microtime(true);
        $this->stmt = $this->pdo->prepare($sql);
        $this->query_count++;
        $this->query_time += microtime(true) - $start_time;
        return $this;
    }
    
    /**
     * Bind values
     */
    public function bind($param, $value, $type = null) {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        
        $this->stmt->bindValue($param, $value, $type);
        return $this;
    }
    
    /**
     * Execute the prepared statement
     */
    public function execute() {
        $start_time = microtime(true);
        $result = $this->stmt->execute();
        $this->query_time += microtime(true) - $start_time;
        return $result;
    }
    
    /**
     * Get result set as array of objects
     */
    public function resultSet() {
        $this->execute();
        return $this->stmt->fetchAll();
    }
    
    /**
     * Get single record as object
     */
    public function single() {
        $this->execute();
        return $this->stmt->fetch();
    }
    
    /**
     * Get row count
     */
    public function rowCount() {
        return $this->stmt->rowCount();
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * End transaction
     */
    public function endTransaction() {
        return $this->pdo->commit();
    }
    
    /**
     * Cancel transaction
     */
    public function cancelTransaction() {
        if ($this->pdo->inTransaction()) {
            return $this->pdo->rollBack();
        }
        return false;
    }
    
    /**
     * Debug dump parameters
     */
    public function debugDumpParams() {
        return $this->stmt->debugDumpParams();
    }
    
    /**
     * Get query count
     */
    public function getQueryCount() {
        return $this->query_count;
    }
    
    /**
     * Get query execution time
     */
    public function getQueryTime() {
        return round($this->query_time, 4);
    }
    
    /**
     * Get the current query
     */
    public function getQuery() {
        return $this->stmt->queryString;
    }
    
    /**
     * Debug database connection
     */
    public function debugConnection() {
        try {
            $this->query("SELECT DATABASE()");
            $currentDb = $this->single();
            error_log("Current Database: " . print_r($currentDb, true));
            
            $this->query("SHOW TABLES");
            $tables = $this->resultSet();
            error_log("Available Tables: " . print_r($tables, true));
            
            // Tablo yapısını kontrol et
            if ($this->tableExists('stock_movements')) {
                $this->query("DESCRIBE stock_movements");
                $columns = $this->resultSet();
                error_log("Stock Movements Table Structure: " . print_r($columns, true));
            } else {
                error_log("stock_movements table does not exist!");
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Database Debug Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if table exists
     */
    public function tableExists($table) {
        // Sadece harf, rakam ve alt çizgi kabul et
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $this->query("SHOW TABLES LIKE '$table'");
        $this->execute();
        return $this->rowCount() > 0;
    }
    
    /**
     * Check if transaction is active
     */
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }
    
    /**
     * Get PDO instance (for advanced operations)
     */
    public function getPdo() {
        return $this->pdo;
    }
    
    /**
     * Get DB size in MB
     */
    public function getDatabaseSize() {
        $this->query("SELECT SUM(data_length + index_length) / 1024 / 1024 AS size FROM information_schema.TABLES WHERE table_schema = :db_name");
        $this->bind(':db_name', $this->db_name);
        $result = $this->single();
        return round($result['size'], 2);
    }
}