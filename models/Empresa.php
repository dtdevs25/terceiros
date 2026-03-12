<?php
/**
 * Modelo de Empresa
 * Sistema de Gerenciamento de Funcionários
 */

require_once __DIR__ . '/../config/database.php';

class Empresa {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Lista todas as empresas
     */
    public function listar($filtros = []) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filtros['razao_social'])) {
            $where[] = "razao_social LIKE ?";
            $params[] = '%' . $filtros['razao_social'] . '%';
        }
        
        if (!empty($filtros['cnpj'])) {
            $where[] = "cnpj LIKE ?";
            $params[] = '%' . $filtros['cnpj'] . '%';
        }
        
        if (!empty($filtros['status'])) {
            $where[] = "status = ?";
            $params[] = $filtros['status'];
        }
        
        $sql = "SELECT e.*, 
                (SELECT COUNT(*) FROM postos_trabalho p WHERE p.empresa_id = e.id AND p.status = 'ativo') as total_postos,
                (SELECT COUNT(DISTINCT fp.funcionario_id) FROM funcionario_postos fp 
                 INNER JOIN postos_trabalho p ON fp.posto_id = p.id 
                 WHERE p.empresa_id = e.id AND fp.status = 'ativo') as total_funcionarios
                FROM empresas e 
                WHERE " . implode(' AND ', $where) . "
                ORDER BY e.razao_social";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Busca empresa por ID
     */
    public function buscarPorId($id) {
        $sql = "SELECT e.*, 
                (SELECT COUNT(*) FROM postos_trabalho p WHERE p.empresa_id = e.id AND p.status = 'ativo') as total_postos,
                (SELECT COUNT(DISTINCT fp.funcionario_id) FROM funcionario_postos fp 
                 INNER JOIN postos_trabalho p ON fp.posto_id = p.id 
                 WHERE p.empresa_id = e.id AND fp.status = 'ativo') as total_funcionarios
                FROM empresas e 
                WHERE e.id = ?";
        
        return $this->db->fetch($sql, [$id]);
    }
    
    /**
     * Cria nova empresa
     */
    public function criar($dados) {
        $this->validarDados($dados);
        
        // Verificar se CNPJ já existe (se fornecido)
        if (!empty($dados['cnpj']) && $this->cnpjExiste($dados['cnpj'])) {
            throw new Exception('CNPJ já cadastrado');
        }
        
        $sql = "INSERT INTO empresas (razao_social, cnpj, endereco, status) VALUES (?, ?, ?, ?)";
        $params = [
            $dados['razao_social'],
            $dados['cnpj'] ?? null,
            $dados['endereco'] ?? null,
            $dados['status'] ?? 'ativo'
        ];
        
        return $this->db->insert($sql, $params);
    }
    
    /**
     * Atualiza empresa
     */
    public function atualizar($id, $dados) {
        $this->validarDados($dados);
        
        // Verificar se CNPJ já existe (exceto para a própria empresa)
        if (!empty($dados['cnpj']) && $this->cnpjExiste($dados['cnpj'], $id)) {
            throw new Exception('CNPJ já cadastrado');
        }
        
        $sql = "UPDATE empresas SET razao_social = ?, cnpj = ?, endereco = ?, status = ? WHERE id = ?";
        $params = [
            $dados['razao_social'],
            $dados['cnpj'] ?? null,
            $dados['endereco'] ?? null,
            $dados['status'],
            $id
        ];
        
        return $this->db->execute($sql, $params) > 0;
    }
    
    /**
     * Exclui empresa
     */
    public function excluir($id) {
        // Verificar se há postos de trabalho vinculados
        $postos = $this->db->fetch("SELECT COUNT(*) as count FROM postos_trabalho WHERE empresa_id = ?", [$id]);
        
        if ($postos['count'] > 0) {
            throw new Exception('Não é possível excluir empresa com postos de trabalho cadastrados');
        }
        
        return $this->db->execute("DELETE FROM empresas WHERE id = ?", [$id]) > 0;
    }
    
    /**
     * Lista empresas ativas para seleção
     */
    public function listarAtivas() {
        $sql = "SELECT id, razao_social FROM empresas WHERE status = 'ativo' ORDER BY razao_social";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Busca postos de trabalho da empresa
     */
    public function buscarPostos($empresa_id) {
        $sql = "SELECT * FROM postos_trabalho WHERE empresa_id = ? ORDER BY nome";
        return $this->db->fetchAll($sql, [$empresa_id]);
    }
    
    /**
     * Busca funcionários da empresa
     */
    public function buscarFuncionarios($empresa_id) {
        $sql = "SELECT DISTINCT f.* FROM funcionarios f
                INNER JOIN funcionario_postos fp ON f.id = fp.funcionario_id
                INNER JOIN postos_trabalho p ON fp.posto_id = p.id
                WHERE p.empresa_id = ? AND fp.status = 'ativo' AND f.status = 'ativo'
                ORDER BY f.nome";
        
        return $this->db->fetchAll($sql, [$empresa_id]);
    }
    
    /**
     * Estatísticas da empresa
     */
    public function estatisticas($empresa_id) {
        $stats = [];
        
        // Total de postos
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM postos_trabalho WHERE empresa_id = ? AND status = 'ativo'", [$empresa_id]);
        $stats['total_postos'] = $result['count'];
        
        // Total de funcionários
        $result = $this->db->fetch("SELECT COUNT(DISTINCT fp.funcionario_id) as count FROM funcionario_postos fp 
                                   INNER JOIN postos_trabalho p ON fp.posto_id = p.id 
                                   WHERE p.empresa_id = ? AND fp.status = 'ativo'", [$empresa_id]);
        $stats['total_funcionarios'] = $result['count'];
        
        // Funcionários com ASO vencido
        $result = $this->db->fetch("SELECT COUNT(DISTINCT f.id) as count FROM funcionarios f
                                   INNER JOIN funcionario_postos fp ON f.id = fp.funcionario_id
                                   INNER JOIN postos_trabalho p ON fp.posto_id = p.id
                                   WHERE p.empresa_id = ? AND fp.status = 'ativo' AND f.status = 'ativo' 
                                   AND f.aso_validade < CURDATE()", [$empresa_id]);
        $stats['aso_vencidos'] = $result['count'];
        
        // Funcionários com treinamentos vencidos
        $result = $this->db->fetch("SELECT COUNT(DISTINCT f.id) as count FROM funcionarios f
                                   INNER JOIN funcionario_postos fp ON f.id = fp.funcionario_id
                                   INNER JOIN postos_trabalho p ON fp.posto_id = p.id
                                   INNER JOIN funcionario_treinamentos ft ON f.id = ft.funcionario_id
                                   WHERE p.empresa_id = ? AND fp.status = 'ativo' AND f.status = 'ativo' 
                                   AND ft.data_validade < CURDATE()", [$empresa_id]);
        $stats['treinamentos_vencidos'] = $result['count'];
        
        return $stats;
    }
    
    /**
     * Valida dados da empresa
     */
    private function validarDados($dados) {
        if (empty($dados['razao_social'])) {
            throw new Exception('Razão social é obrigatória');
        }
        
        if (strlen($dados['razao_social']) > 255) {
            throw new Exception('Razão social muito longa (máximo 255 caracteres)');
        }
        
        // Validar CNPJ se fornecido
        if (!empty($dados['cnpj']) && !validarCNPJ($dados['cnpj'])) {
            throw new Exception('CNPJ inválido');
        }
    }
    
    /**
     * Verifica se CNPJ já existe
     */
    private function cnpjExiste($cnpj, $excluir_id = null) {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        $sql = "SELECT COUNT(*) as count FROM empresas WHERE REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', '') = ?";
        $params = [$cnpj];
        
        if ($excluir_id) {
            $sql .= " AND id != ?";
            $params[] = $excluir_id;
        }
        
        $result = $this->db->fetch($sql, $params);
        return $result['count'] > 0;
    }
}
?>

