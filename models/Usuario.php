<?php
/**
 * Modelo de Usuário
 * Sistema de Gerenciamento de Funcionários
 */

require_once __DIR__ . "/../config/database.php";

class Usuario {
    private $conn;
    private $table_name = "usuarios";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Autentica um usuário
     */
    public function autenticar($email, $senha) {
        $query = "SELECT id, nome, email, senha, hierarquia, empresas, status FROM " . $this->table_name . " WHERE email = :email LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($senha, $usuario["senha"])) {
            // Se o status for inativo, não permite login
            if ($usuario["status"] === "inativo") {
                throw new Exception("Sua conta está inativa. Entre em contato com o administrador.");
            }

            // Atualiza último login
            $this->atualizarUltimoLogin($usuario["id"]);

            // Decodifica as empresas JSON
            $usuario["empresas"] = $usuario["empresas"] ? json_decode($usuario["empresas"]) : [];

            return $usuario;
        }

        return false;
    }

    /**
     * Busca um usuário por ID
     */
    public function buscarPorId($id) {
        $query = "SELECT id, nome, email, hierarquia, empresas, status FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            $usuario["empresas"] = $usuario["empresas"] ? json_decode($usuario["empresas"]) : [];
        }

        return $usuario;
    }

    /**
     * Lista usuários
     */
    public function listar($filtros = []) {
        $query = "SELECT id, nome, email, hierarquia, status, ultimo_login FROM " . $this->table_name . " WHERE 1=1 ";
        $params = [];

        if (!empty($filtros["nome"])) {
            $query .= " AND nome LIKE :nome";
            $params[":nome"] = "%" . $filtros["nome"] . "%";
        }
        if (!empty($filtros["email"])) {
            $query .= " AND email LIKE :email";
            $params[":email"] = "%" . $filtros["email"] . "%";
        }
        if (!empty($filtros["hierarquia"])) {
            $query .= " AND hierarquia = :hierarquia";
            $params[":hierarquia"] = $filtros["hierarquia"];
        }
        if (!empty($filtros["status"])) {
            $query .= " AND status = :status";
            $params[":status"] = $filtros["status"];
        }

        $query .= " ORDER BY nome ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cria um novo usuário
     */
    public function criar($dados) {
        $query = "INSERT INTO " . $this->table_name . " SET nome=:nome, email=:email, senha=:senha, hierarquia=:hierarquia, empresas=:empresas, status=:status";

        $stmt = $this->conn->prepare($query);

        $dados["senha"] = password_hash($dados["senha"], PASSWORD_DEFAULT);
        $dados["empresas"] = json_encode($dados["empresas"] ?? []);

        $stmt->bindParam(":nome", $dados["nome"]);
        $stmt->bindParam(":email", $dados["email"]);
        $stmt->bindParam(":senha", $dados["senha"]);
        $stmt->bindParam(":hierarquia", $dados["hierarquia"]);
        $stmt->bindParam(":empresas", $dados["empresas"]);
        $stmt->bindParam(":status", $dados["status"]);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    /**
     * Atualiza um usuário
     */
    public function atualizar($id, $dados) {
        $query = "UPDATE " . $this->table_name . " SET nome=:nome, email=:email, hierarquia=:hierarquia, empresas=:empresas, status=:status WHERE id = :id";

        // Se a senha for fornecida, atualiza
        if (!empty($dados["senha"])) {
            $query = "UPDATE " . $this->table_name . " SET nome=:nome, email=:email, senha=:senha, hierarquia=:hierarquia, empresas=:empresas, status=:status WHERE id = :id";
            $dados["senha"] = password_hash($dados["senha"], PASSWORD_DEFAULT);
        }

        $stmt = $this->conn->prepare($query);

        $dados["empresas"] = json_encode($dados["empresas"] ?? []);

        $stmt->bindParam(":nome", $dados["nome"]);
        $stmt->bindParam(":email", $dados["email"]);
        if (!empty($dados["senha"])) {
            $stmt->bindParam(":senha", $dados["senha"]);
        }
        $stmt->bindParam(":hierarquia", $dados["hierarquia"]);
        $stmt->bindParam(":empresas", $dados["empresas"]);
        $stmt->bindParam(":status", $dados["status"]);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }

    /**
     * Exclui um usuário
     */
    public function excluir($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }

    /**
     * Atualiza o último login do usuário
     */
    public function atualizarUltimoLogin($usuario_id) {
        $query = "UPDATE " . $this->table_name . " SET ultimo_login = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $usuario_id);
        $stmt->execute();
    }

    /**
     * Altera a senha de um usuário
     */
    public function alterarSenha($usuario_id, $senha_antiga, $senha_nova) {
        $query = "SELECT senha FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $usuario_id);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario || !password_verify($senha_antiga, $usuario["senha"])) {
            throw new Exception("Senha atual incorreta.");
        }

        $nova_senha_hash = password_hash($senha_nova, PASSWORD_DEFAULT);
        $query = "UPDATE " . $this->table_name . " SET senha = :senha_nova WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":senha_nova", $nova_senha_hash);
        $stmt->bindParam(":id", $usuario_id);

        return $stmt->execute();
    }
}
