<?php
/**
 * Modelo de Posto de Trabalho
 * Sistema de Gerenciamento de Funcionários
 */

require_once __DIR__ . '/../config/database.php';

class PostoTrabalho {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Lista todos os postos de trabalho
     */
    public function listar($filtros = []) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filtros['nome'])) {
            $where[] = "p.nome LIKE ?";
            $params[] = '%' . $filtros['nome'] . '%';
        }
        
        if (!empty($filtros['empresa_id'])) {
            $where[] = "p.empresa_id = ?";
            $params[] = $filtros['empresa_id'];
        }
        
        if (!empty($filtros['status'])) {
            $where[] = "p.status = ?";
            $params[] = $filtros['status'];
        }
        
        $sql = "SELECT p.*, e.razao_social as empresa_nome,
                (SELECT COUNT(*) FROM funcionario_postos fp WHERE fp.posto_id = p.id AND fp.status = 'ativo') as total_funcionarios
                FROM postos_trabalho p 
                INNER JOIN empresas e ON p.empresa_id = e.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY e.razao_social, p.nome";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Busca posto por ID
     */
    public function buscarPorId($id) {
        $sql = "SELECT p.*, e.razao_social as empresa_nome,
                (SELECT COUNT(*) FROM funcionario_postos fp WHERE fp.posto_id = p.id AND fp.status = 'ativo') as total_funcionarios
                FROM postos_trabalho p 
                INNER JOIN empresas e ON p.empresa_id = e.id
                WHERE p.id = ?";
        
        return $this->db->fetch($sql, [$id]);
    }
    
    /**
     * Cria novo posto de trabalho
     */
    public function criar($dados) {
        $this->validarDados($dados);
        
        $sql = "INSERT INTO postos_trabalho (nome, endereco, empresa_id, status) VALUES (?, ?, ?, ?)";
        $params = [
            $dados['nome'],
            $dados['endereco'] ?? null,
            $dados['empresa_id'],
            $dados['status'] ?? 'ativo'
        ];
        
        return $this->db->insert($sql, $params);
    }
    
    /**
     * Atualiza posto de trabalho
     */
    public function atualizar($id, $dados) {
        $this->validarDados($dados);
        
        $sql = "UPDATE postos_trabalho SET nome = ?, endereco = ?, empresa_id = ?, status = ? WHERE id = ?";
        $params = [
            $dados['nome'],
            $dados['endereco'] ?? null,
            $dados['empresa_id'],
            $dados['status'],
            $id
        ];
        
        return $this->db->execute($sql, $params) > 0;
    }
    
    /**
     * Exclui posto de trabalho
     */
    public function excluir($id) {
        // Verificar se há funcionários vinculados
        $funcionarios = $this->db->fetch("SELECT COUNT(*) as count FROM funcionario_postos WHERE posto_id = ?", [$id]);
        
        if ($funcionarios['count'] > 0) {
            throw new Exception('Não é possível excluir posto com funcionários vinculados');
        }
        
        return $this->db->execute("DELETE FROM postos_trabalho WHERE id = ?", [$id]) > 0;
    }
    
    /**
     * Lista postos ativos por empresa
     */
    public function listarPorEmpresa($empresa_id) {
        $sql = "SELECT id, nome FROM postos_trabalho WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome";
        return $this->db->fetchAll($sql, [$empresa_id]);
    }
    
    /**
     * Lista postos ativos para seleção
     */
    public function listarAtivos() {
        $sql = "SELECT p.id, p.nome, e.razao_social as empresa_nome 
                FROM postos_trabalho p 
                INNER JOIN empresas e ON p.empresa_id = e.id
                WHERE p.status = 'ativo' AND e.status = 'ativo'
                ORDER BY e.razao_social, p.nome";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Busca funcionários do posto
     */
    public function buscarFuncionarios($posto_id) {
        $sql = "SELECT f.*, fp.data_inicio, fp.data_fim, fp.status as vinculo_status
                FROM funcionarios f
                INNER JOIN funcionario_postos fp ON f.id = fp.funcionario_id
                WHERE fp.posto_id = ? AND fp.status = 'ativo' AND f.status = 'ativo'
                ORDER BY f.nome";
        
        return $this->db->fetchAll($sql, [$posto_id]);
    }
    
    /**
     * Vincula funcionário ao posto
     */
    public function vincularFuncionario($posto_id, $funcionario_id, $data_inicio) {
        // Verificar se já existe vínculo ativo
        $vinculo_existente = $this->db->fetch(
            "SELECT id FROM funcionario_postos WHERE funcionario_id = ? AND posto_id = ? AND status = 'ativo'",
            [$funcionario_id, $posto_id]
        );
        
        if ($vinculo_existente) {
            throw new Exception('Funcionário já está vinculado a este posto');
        }
        
        $sql = "INSERT INTO funcionario_postos (funcionario_id, posto_id, data_inicio, status) VALUES (?, ?, ?, 'ativo')";
        return $this->db->insert($sql, [$funcionario_id, $posto_id, $data_inicio]);
    }
    
    /**
     * Remove vínculo do funcionário com o posto
     */
    public function desvincularFuncionario($posto_id, $funcionario_id, $data_fim = null) {
        $data_fim = $data_fim ?? date('Y-m-d');
        
        $sql = "UPDATE funcionario_postos SET status = 'inativo', data_fim = ? 
                WHERE funcionario_id = ? AND posto_id = ? AND status = 'ativo'";
        
        return $this->db->execute($sql, [$data_fim, $funcionario_id, $posto_id]) > 0;
    }
    
    /**
     * Estatísticas do posto
     */
    public function estatisticas($posto_id) {
        $stats = [];
        
        // Total de funcionários ativos
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM funcionario_postos WHERE posto_id = ? AND status = 'ativo'", [$posto_id]);
        $stats['total_funcionarios'] = $result['count'];
        
        // Funcionários com ASO vencido
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM funcionarios f
                                   INNER JOIN funcionario_postos fp ON f.id = fp.funcionario_id
                                   WHERE fp.posto_id = ? AND fp.status = 'ativo' AND f.status = 'ativo' 
                                   AND f.aso_validade < CURDATE()", [$posto_id]);
        $stats['aso_vencidos'] = $result['count'];
        
        // Funcionários com treinamentos vencidos
        $result = $this->db->fetch("SELECT COUNT(DISTINCT f.id) as count FROM funcionarios f
                                   INNER JOIN funcionario_postos fp ON f.id = fp.funcionario_id
                                   INNER JOIN funcionario_treinamentos ft ON f.id = ft.funcionario_id
                                   WHERE fp.posto_id = ? AND fp.status = 'ativo' AND f.status = 'ativo' 
                                   AND ft.data_validade < CURDATE()", [$posto_id]);
        $stats['treinamentos_vencidos'] = $result['count'];
        
        return $stats;
    }
    
    /**
     * Valida dados do posto de trabalho
     */
    private function validarDados($dados) {
        if (empty($dados['nome'])) {
            throw new Exception('Nome do posto é obrigatório');
        }
        
        if (strlen($dados['nome']) > 255) {
            throw new Exception('Nome muito longo (máximo 255 caracteres)');
        }
        
        if (empty($dados['empresa_id'])) {
            throw new Exception('Empresa é obrigatória');
        }
        
        // Verificar se empresa existe
        $empresa = $this->db->fetch("SELECT id FROM empresas WHERE id = ? AND status = 'ativo'", [$dados['empresa_id']]);
        if (!$empresa) {
            throw new Exception('Empresa não encontrada ou inativa');
        }
    }
}
?>

