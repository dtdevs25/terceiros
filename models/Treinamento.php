<?php
/**
 * Modelo de Treinamento
 * Sistema de Gerenciamento de Funcionários
 */

require_once __DIR__ . '/../config/database.php';

class Treinamento {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Lista todos os treinamentos
     */
    public function listar($filtros = []) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filtros['nome'])) {
            $where[] = "nome LIKE ?";
            $params[] = '%' . $filtros['nome'] . '%';
        }
        
        if (!empty($filtros['status'])) {
            $where[] = "status = ?";
            $params[] = $filtros['status'];
        }
        
        $sql = "SELECT t.*,
                (SELECT COUNT(*) FROM funcionario_treinamento ft WHERE ft.treinamento_id = t.id) as total_funcionarios,
                (SELECT COUNT(*) FROM funcionario_treinamento ft WHERE ft.treinamento_id = t.id AND ft.data_validade < CURDATE()) as vencidos
                FROM treinamentos t 
                WHERE " . implode(' AND ', $where) . "
                ORDER BY t.nome";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Busca treinamento por ID
     */
    public function buscarPorId($id) {
        $sql = "SELECT t.*,
                (SELECT COUNT(*) FROM funcionario_treinamento ft WHERE ft.treinamento_id = t.id) as total_funcionarios,
                (SELECT COUNT(*) FROM funcionario_treinamento ft WHERE ft.treinamento_id = t.id AND ft.data_validade < CURDATE()) as vencidos
                FROM treinamentos t 
                WHERE t.id = ?";
        
        return $this->db->fetch($sql, [$id]);
    }
    
    /**
     * Cria novo treinamento
     */
    public function criar($dados) {
        $this->validarDados($dados);
        
        $sql = "INSERT INTO treinamentos (nome, carga_horaria, prazo_validade, descricao, status) VALUES (?, ?, ?, ?, ?)";
        $params = [
            $dados['nome'],
            $dados['carga_horaria'],
            $dados['prazo_validade'],
            $dados['descricao'] ?? null,
            $dados['status'] ?? 'ativo'
        ];
        
        return $this->db->insert($sql, $params);
    }
    
    /**
     * Atualiza treinamento
     */
    public function atualizar($id, $dados) {
        $this->validarDados($dados);
        
        $sql = "UPDATE treinamentos SET nome = ?, carga_horaria = ?, prazo_validade = ?, descricao = ?, status = ? WHERE id = ?";
        $params = [
            $dados['nome'],
            $dados['carga_horaria'],
            $dados['prazo_validade'],
            $dados['descricao'] ?? null,
            $dados['status'],
            $id
        ];
        
        return $this->db->execute($sql, $params) > 0;
    }
    
    /**
     * Exclui treinamento
     */
    public function excluir($id) {
        // Verificar se há funcionários com este treinamento
        $funcionarios = $this->db->fetch("SELECT COUNT(*) as count FROM funcionario_treinamento WHERE treinamento_id = ?", [$id]);
        
        if ($funcionarios['count'] > 0) {
            throw new Exception('Não é possível excluir treinamento com funcionários vinculados');
        }
        
        return $this->db->execute("DELETE FROM treinamentos WHERE id = ?", [$id]) > 0;
    }
    
    /**
     * Lista treinamentos ativos para seleção
     */
    public function listarAtivos() {
        $sql = "SELECT id, nome, carga_horaria, prazo_validade FROM treinamentos WHERE status = 'ativo' ORDER BY nome";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Busca funcionários com o treinamento
     */
    public function buscarFuncionarios($treinamento_id, $filtros = []) {
        $where = ["ft.treinamento_id = ?"];
        $params = [$treinamento_id];
        
        if (!empty($filtros['status_validade'])) {
            switch ($filtros['status_validade']) {
                case 'valido':
                    $where[] = "ft.data_validade >= CURDATE()";
                    break;
                case 'vencido':
                    $where[] = "ft.data_validade < CURDATE()";
                    break;
                case 'a_vencer':
                    $where[] = "ft.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
                    break;
            }
        }
        
        $sql = "SELECT f.*, ft.data_realizacao, ft.data_validade, ft.certificado,
                CASE 
                    WHEN ft.data_validade < CURDATE() THEN 'vencido'
                    WHEN ft.data_validade <= DATE_ADD(CURDATE(), INTERVAL 15 DAY) THEN 'vence_15_dias'
                    WHEN ft.data_validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'vence_30_dias'
                    ELSE 'valido'
                END as status_validade
                FROM funcionarios f
                INNER JOIN funcionario_treinamento ft ON f.id = ft.funcionario_id
                WHERE " . implode(' AND ', $where) . " AND f.status = 'ativo'
                ORDER BY f.nome";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Vincula treinamento ao funcionário
     */
    public function vincularFuncionario($treinamento_id, $funcionario_id, $data_realizacao, $certificado = null) {
        // Buscar dados do treinamento para calcular validade
        $treinamento = $this->buscarPorId($treinamento_id);
        if (!$treinamento) {
            throw new Exception('Treinamento não encontrado');
        }
        
        // Calcular data de validade
        $data_validade = date('Y-m-d', strtotime($data_realizacao . ' + ' . $treinamento['prazo_validade'] . ' months'));
        
        // Verificar se já existe treinamento ativo para o funcionário
        $existente = $this->db->fetch(
            "SELECT id FROM funcionario_treinamento WHERE funcionario_id = ? AND treinamento_id = ? AND data_validade >= CURDATE()",
            [$funcionario_id, $treinamento_id]
        );
        
        if ($existente) {
            throw new Exception('Funcionário já possui este treinamento válido');
        }
        
        $sql = "INSERT INTO funcionario_treinamento (funcionario_id, treinamento_id, data_realizacao, data_validade, certificado, status) 
                VALUES (?, ?, ?, ?, ?, 'valido')";
        
        return $this->db->insert($sql, [$funcionario_id, $treinamento_id, $data_realizacao, $data_validade, $certificado]);
    }
    
    /**
     * Atualiza treinamento do funcionário
     */
    public function atualizarTreinamentoFuncionario($id, $data_realizacao, $certificado = null) {
        // Buscar o treinamento para recalcular validade
        $ft = $this->db->fetch("SELECT treinamento_id FROM funcionario_treinamento WHERE id = ?", [$id]);
        if (!$ft) {
            throw new Exception('Registro não encontrado');
        }
        
        $treinamento = $this->buscarPorId($ft['treinamento_id']);
        $data_validade = date('Y-m-d', strtotime($data_realizacao . ' + ' . $treinamento['prazo_validade'] . ' months'));
        
        $sql = "UPDATE funcionario_treinamento SET data_realizacao = ?, data_validade = ?, certificado = ?, 
                status = CASE WHEN ? >= CURDATE() THEN 'valido' ELSE 'vencido' END
                WHERE id = ?";
        
        return $this->db->execute($sql, [$data_realizacao, $data_validade, $certificado, $data_validade, $id]) > 0;
    }
    
    /**
     * Remove treinamento do funcionário
     */
    public function removerTreinamentoFuncionario($id) {
        return $this->db->execute("DELETE FROM funcionario_treinamento WHERE id = ?", [$id]) > 0;
    }
    
    /**
     * Busca treinamentos vencidos ou a vencer
     */
    public function buscarVencimentos($dias = 30) {
        $sql = "SELECT f.nome as funcionario_nome, f.cpf, f.matricula, t.nome as treinamento_nome,
                ft.data_realizacao, ft.data_validade,
                CASE 
                    WHEN ft.data_validade < CURDATE() THEN 'vencido'
                    WHEN ft.data_validade <= DATE_ADD(CURDATE(), INTERVAL 15 DAY) THEN 'vence_15_dias'
                    WHEN ft.data_validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'vence_30_dias'
                    ELSE 'valido'
                END as status_validade,
                DATEDIFF(ft.data_validade, CURDATE()) as dias_restantes
                FROM funcionario_treinamento ft
                INNER JOIN funcionarios f ON ft.funcionario_id = f.id
                INNER JOIN treinamentos t ON ft.treinamento_id = t.id
                WHERE f.status = 'ativo' AND t.status = 'ativo'
                AND ft.data_validade <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY ft.data_validade ASC, f.nome";
        
        return $this->db->fetchAll($sql, [$dias]);
    }
    
    /**
     * Estatísticas de treinamentos
     */
    public function estatisticas() {
        $stats = [];
        
        // Total de treinamentos ativos
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM treinamentos WHERE status = 'ativo'");
        $stats['total_treinamentos'] = $result['count'];
        
        // Total de certificações ativas
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM funcionario_treinamento WHERE data_validade >= CURDATE()");
        $stats['certificacoes_ativas'] = $result['count'];
        
        // Certificações vencidas
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM funcionario_treinamento WHERE data_validade < CURDATE()");
        $stats['certificacoes_vencidas'] = $result['count'];
        
        // Certificações que vencem em 30 dias
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM funcionario_treinamento 
                                   WHERE data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
        $stats['certificacoes_a_vencer'] = $result['count'];
        
        return $stats;
    }
    
    /**
     * Valida dados do treinamento
     */
    private function validarDados($dados) {
        if (empty($dados['nome'])) {
            throw new Exception('Nome do treinamento é obrigatório');
        }
        
        if (strlen($dados['nome']) > 255) {
            throw new Exception('Nome muito longo (máximo 255 caracteres)');
        }
        
        if (empty($dados['carga_horaria']) || !is_numeric($dados['carga_horaria']) || $dados['carga_horaria'] <= 0) {
            throw new Exception('Carga horária deve ser um número positivo');
        }
        
        if (empty($dados['prazo_validade']) || !is_numeric($dados['prazo_validade']) || $dados['prazo_validade'] <= 0) {
            throw new Exception('Prazo de validade deve ser um número positivo (em meses)');
        }
    }
}
?>
