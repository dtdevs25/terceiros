<?php
/**
 * Configuração do Banco de Dados
 * Sistema de Gerenciamento de Funcionários
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset = 'utf8mb4';
    public $conn;

    /**
     * Construtor - estabelece conexão com o banco
     */
    public function __construct() {
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db_name = getenv('DB_NAME') ?: 'sistema_funcionarios';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') ?: '';
        $this->getConnection();
    }

    /**
     * Estabelece conexão com o banco de dados
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $exception) {
            error_log("Erro de conexão: " . $exception->getMessage());
            throw new Exception("Erro ao conectar com o banco de dados");
        }

        return $this->conn;
    }

    /**
     * Executa uma query preparada
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $exception) {
            error_log("Erro na query: " . $exception->getMessage());
            throw new Exception("Erro ao executar consulta no banco de dados");
        }
    }

    /**
     * Busca um registro
     */
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Busca múltiplos registros
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Executa insert e retorna o ID
     */
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->conn->lastInsertId();
    }

    /**
     * Executa update/delete e retorna número de linhas afetadas
     */
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Inicia transação
     */
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    /**
     * Confirma transação
     */
    public function commit() {
        return $this->conn->commit();
    }

    /**
     * Desfaz transação
     */
    public function rollback() {
        return $this->conn->rollback();
    }

    /**
     * Define variáveis de sessão para auditoria
     */
    public function setAuditUser($userId, $userIp = null) {
        $this->query("SET @current_user_id = ?", [$userId]);
        if ($userIp) {
            $this->query("SET @current_user_ip = ?", [$userIp]);
        }
    }
}

