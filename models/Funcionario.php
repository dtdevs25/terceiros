<?php
/**
 * Modelo de Funcionário
 * Sistema de Gerenciamento de Funcionários
 */

require_once __DIR__ . "/../config/database.php";

class Funcionario {
    private $conn;
    private $table_name = "funcionarios";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Lista todos os funcionários com filtros.
     */
    public function listar($filtros = []) {
        $query = "SELECT id, nome, cpf, matricula, foto, aso_data, aptidao_trabalho, status FROM " . $this->table_name . " WHERE 1=1 ";
        $params = [];

        if (!empty($filtros["nome"])) {
            $query .= " AND nome LIKE :nome";
            $params[":nome"] = "%" . $filtros["nome"] . "%";
        }
        if (!empty($filtros["cpf"])) {
            $query .= " AND cpf LIKE :cpf";
            $params[":cpf"] = "%" . $filtros["cpf"] . "%";
        }
        if (!empty($filtros["status"])) {
            $query .= " AND status = :status";
            $params[":status"] = $filtros["status"];
        }
        // Filtro por status do ASO
        if (!empty($filtros["aso_status"])) {
            if ($filtros["aso_status"] == "vencido") {
                $query .= " AND aso_data < CURDATE()";
            } elseif ($filtros["aso_status"] == "a_vencer") {
                $query .= " AND aso_data BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
            } elseif ($filtros["aso_status"] == "valido") {
                $query .= " AND aso_data >= CURDATE()";
            }
        }

        $query .= " ORDER BY nome ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um funcionário por ID.
     */
    public function buscarPorId($id) {
        $query = "SELECT id, nome, cpf, matricula, foto, aso_data, aptidao_trabalho, status FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Cria um novo funcionário.
     */
    public function criar($dados) {
        $query = "INSERT INTO " . $this->table_name . " SET nome=:nome, cpf=:cpf, matricula=:matricula, foto=:foto, aso_data=:aso_data, aptidao_trabalho=:aptidao_trabalho, status=:status";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":nome", $dados["nome"]);
        $stmt->bindParam(":cpf", $dados["cpf"]);
        $stmt->bindParam(":matricula", $dados["matricula"]);
        $stmt->bindParam(":foto", $dados["foto"]);
        $stmt->bindParam(":aso_data", $dados["aso_data"]); // Usar aso_data
        $stmt->bindParam(":aptidao_trabalho", $dados["aptidao_trabalho"]);
        $stmt->bindParam(":status", $dados["status"]);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    /**
     * Atualiza um funcionário existente.
     */
    public function atualizar($id, $dados) {
        $query = "UPDATE " . $this->table_name . " SET nome=:nome, cpf=:cpf, matricula=:matricula, foto=:foto, aso_data=:aso_data, aptidao_trabalho=:aptidao_trabalho, status=:status WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":nome", $dados["nome"]);
        $stmt->bindParam(":cpf", $dados["cpf"]);
        $stmt->bindParam(":matricula", $dados["matricula"]);
        $stmt->bindParam(":foto", $dados["foto"]);
        $stmt->bindParam(":aso_data", $dados["aso_data"]); // Usar aso_data
        $stmt->bindParam(":aptidao_trabalho", $dados["aptidao_trabalho"]);
        $stmt->bindParam(":status", $dados["status"]);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }

    /**
     * Exclui um funcionário.
     */
    public function excluir($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }

    /**
     * Retorna dados para o dashboard (ASO e Treinamentos).
     */
    public function getVencimentosASO() {
        // Contagem de ASO vencidos
        $queryVencidos = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE aso_data < CURDATE() AND status = 'ativo'";
        $stmtVencidos = $this->conn->query($queryVencidos);
        $asoVencidos = $stmtVencidos->fetch(PDO::FETCH_ASSOC)["total"];

        // Contagem de ASO a vencer (próximos 30 dias)
        $queryAVencer = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE aso_data BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'ativo'";
        $stmtAVencer = $this->conn->query($queryAVencer);
        $asoAVencer = $stmtAVencer->fetch(PDO::FETCH_ASSOC)["total"];

        // Contagem de ASO válidos
        $queryValidos = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE aso_data >= CURDATE() AND status = 'ativo'";
        $stmtValidos = $this->conn->query($queryValidos);
        $asoValidos = $stmtValidos->fetch(PDO::FETCH_ASSOC)["total"];

        // Funcionários com ASO crítico (vencido ou a vencer em 30 dias)
        $queryCritico = "SELECT id, nome, cpf, matricula, aso_data, 
                                CASE 
                                    WHEN aso_data < CURDATE() THEN 'Vencido'
                                    WHEN aso_data BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'vence_30_dias'
                                    ELSE 'valido'
                                END as aso_status,
                                DATEDIFF(aso_data, CURDATE()) as aso_dias_restantes
                         FROM " . $this->table_name . " 
                         WHERE (aso_data < CURDATE() OR aso_data BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) AND status = 'ativo'
                         ORDER BY aso_data ASC LIMIT 10";
        $stmtCritico = $this->conn->query($queryCritico);
        $funcionariosAsoCritico = $stmtCritico->fetchAll(PDO::FETCH_ASSOC);

        // Contagem de treinamentos (exemplo, você precisará de um modelo de Treinamento mais completo)
        // Por enquanto, vamos usar dados mock ou uma query simples
        $treinamentosVencidos = 0; // Substituir por query real
        $treinamentosAVencer = 0; // Substituir por query real
        $treinamentosValidos = 0; // Substituir por query real

        return [
            "asoVencidos" => $asoVencidos,
            "asoAVencer" => $asoAVencer,
            "asoValidos" => $asoValidos,
            "funcionariosAsoCritico" => $funcionariosAsoCritico,
            "treinamentosVencidos" => $treinamentosVencidos,
            "treinamentosAVencer" => $treinamentosAVencer,
            "treinamentosValidos" => $treinamentosValidos
        ];
    }

    /**
     * Retorna estatísticas gerais de funcionários e empresas.
     */
    public function getEstatisticasFuncionarios() {
        $queryTotalFuncionarios = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE status = 'ativo'";
        $stmtTotalFuncionarios = $this->conn->query($queryTotalFuncionarios);
        $totalFuncionarios = $stmtTotalFuncionarios->fetch(PDO::FETCH_ASSOC)["total"];

        $queryTotalEmpresas = "SELECT COUNT(*) as total FROM empresas WHERE status = 'ativa'";
        $stmtTotalEmpresas = $this->conn->query($queryTotalEmpresas);
        $totalEmpresas = $stmtTotalEmpresas->fetch(PDO::FETCH_ASSOC)["total"];

        return [
            "totalFuncionarios" => $totalFuncionarios,
            "totalEmpresas" => $totalEmpresas
        ];
    }

    /**
     * Retorna estatísticas para o dashboard.
     */
    public function estatisticas() {
        $queryTotalFuncionarios = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE status = 'ativo'";
        $stmtTotalFuncionarios = $this->conn->query($queryTotalFuncionarios);
        $totalFuncionarios = $stmtTotalFuncionarios->fetch(PDO::FETCH_ASSOC)["total"];

        $queryTotalEmpresas = "SELECT COUNT(*) as total FROM empresas WHERE status = 'ativa'";
        $stmtTotalEmpresas = $this->conn->query($queryTotalEmpresas);
        $totalEmpresas = $stmtTotalEmpresas->fetch(PDO::FETCH_ASSOC)["total"];

        // Contagem de ASO vencidos
        $queryAsoVencidos = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE aso_data < CURDATE() AND status = 'ativo'";
        $stmtAsoVencidos = $this->conn->query($queryAsoVencidos);
        $asoVencidos = $stmtAsoVencidos->fetch(PDO::FETCH_ASSOC)["total"];

        // Contagem de ASO a vencer (próximos 30 dias)
        $queryAsoAVencer = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE aso_data BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'ativo'";
        $stmtAsoAVencer = $this->conn->query($queryAsoAVencer);
        $asoAVencer = $stmtAsoAVencer->fetch(PDO::FETCH_ASSOC)["total"];

        // Funcionários aptos (ASO válido)
        $queryFuncionariosAptos = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE aso_data >= CURDATE() AND status = 'ativo'";
        $stmtFuncionariosAptos = $this->conn->query($queryFuncionariosAptos);
        $funcionariosAptos = $stmtFuncionariosAptos->fetch(PDO::FETCH_ASSOC)["total"];

        return [
            "total_funcionarios" => $totalFuncionarios,
            "total_empresas" => $totalEmpresas,
            "funcionarios_aptos" => $funcionariosAptos,
            "aso_vencidos" => $asoVencidos,
            "aso_a_vencer" => $asoAVencer
        ];
    }
}