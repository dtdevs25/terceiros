<?php
/**
 * Controlador de Funcionários
 * Sistema de Gerenciamento de Funcionários
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Funcionario.php';

class FuncionarioController {
    private $funcionarioModel;
    private $auth;
    
    public function __construct() {
        $this->funcionarioModel = new Funcionario();
        $this->auth = new AuthController();
    }
    
    /**
     * Lista funcionários
     */
    public function listar() {
        $this->auth->verificarLogin();
        $this->auth->verificarPermissao('read', true);
        
        try {
            $filtros = [
                'nome' => $_GET['nome'] ?? '',
                'cpf' => $_GET['cpf'] ?? '',
                'matricula' => $_GET['matricula'] ?? '',
                'empresa_id' => $_GET['empresa_id'] ?? '',
                'posto_id' => $_GET['posto_id'] ?? '',
                'status_aso' => $_GET['status_aso'] ?? ''
            ];
            
            $funcionarios = $this->funcionarioModel->listar($filtros);
            
            enviarJSON([
                'success' => true,
                'funcionarios' => $funcionarios
            ]);
            
        } catch (Exception $e) {
            enviarJSON([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Busca funcionário por ID
     */
    public function buscar($id) {
        $this->auth->verificarLogin();
        $this->auth->verificarPermissao('read', true);
        
        try {
            $funcionario = $this->funcionarioModel->buscarPorId($id);
            
            if (!$funcionario) {
                enviarJSON([
                    'success' => false,
                    'message' => 'Funcionário não encontrado'
                ], 404);
            }
            
            // Buscar dados relacionados
            $postos = $this->funcionarioModel->buscarPostos($id);
            $treinamentos = $this->funcionarioModel->buscarTreinamentos($id);
            
            enviarJSON([
                'success' => true,
                'funcionario' => $funcionario,
                'postos' => $postos,
                'treinamentos' => $treinamentos
            ]);
            
        } catch (Exception $e) {
            enviarJSON([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Cria novo funcionário
     */
    public function criar() {
        $this->auth->verificarLogin();
        $this->auth->verificarPermissao('create', true);
        
        try {
            // Verificar token CSRF
            if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token de segurança inválido');
            }
            
            $dados = [
                'nome' => sanitizar($_POST['nome'] ?? ''),
                'cpf' => sanitizar($_POST['cpf'] ?? ''),
                'matricula' => sanitizar($_POST['matricula'] ?? ''),
                'aso_data' => $_POST['aso_data'] ?? '',
                'status' => $_POST['status'] ?? 'ativo'
            ];
            
            // Upload da foto se fornecida
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $dados['foto'] = uploadArquivo($_FILES['foto'], 'fotos');
            }
            
            // Postos de trabalho
            if (!empty($_POST['postos'])) {
                $dados['postos'] = $_POST['postos'];
            }
            
            // Treinamentos
            if (!empty($_POST['treinamentos'])) {
                $dados['treinamentos'] = [];
                foreach ($_POST['treinamentos'] as $i => $treinamento_id) {
                    if (!empty($treinamento_id) && !empty($_POST['treinamentos_data'][$i])) {
                        $dados['treinamentos'][] = [
                            'treinamento_id' => $treinamento_id,
                            'data_realizacao' => $_POST['treinamentos_data'][$i],
                            'certificado' => $_POST['treinamentos_certificado'][$i] ?? null
                        ];
                    }
                }
            }
            
            $id = $this->funcionarioModel->criar($dados);
            
            registrarAuditoria('funcionarios', $id, 'create', null, $dados);
            
            enviarJSON([
                'success' => true,
                'message' => 'Funcionário cadastrado com sucesso',
                'id' => $id
            ]);
            
        } catch (Exception $e) {
            enviarJSON([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Atualiza funcionário
     */
    public function atualizar($id) {
        $this->auth->verificarLogin();
        $this->auth->verificarPermissao('update', true);
        
        try {
            // Verificar token CSRF
            if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token de segurança inválido');
            }
            
            // Buscar dados anteriores para auditoria
            $funcionario_anterior = $this->funcionarioModel->buscarPorId($id);
            if (!$funcionario_anterior) {
                throw new Exception('Funcionário não encontrado');
            }
            
            $dados = [
                'nome' => sanitizar($_POST['nome'] ?? ''),
                'cpf' => sanitizar($_POST['cpf'] ?? ''),
                'matricula' => sanitizar($_POST['matricula'] ?? ''),
                'aso_data' => $_POST['aso_data'] ?? '',
                'status' => $_POST['status'] ?? 'ativo'
            ];
            
            // Upload da foto se fornecida
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $dados['foto'] = uploadArquivo($_FILES['foto'], 'fotos');
            }
            
            // Postos de trabalho
            if (isset($_POST['postos'])) {
                $dados['postos'] = $_POST['postos'];
            }
            
            $sucesso = $this->funcionarioModel->atualizar($id, $dados);
            
            if (!$sucesso) {
                throw new Exception('Erro ao atualizar funcionário');
            }
            
            registrarAuditoria('funcionarios', $id, 'update', $funcionario_anterior, $dados);
            
            enviarJSON([
                'success' => true,
                'message' => 'Funcionário atualizado com sucesso'
            ]);
            
        } catch (Exception $e) {
            enviarJSON([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Exclui funcionário
     */
    public function excluir($id) {
        $this->auth->verificarLogin();
        $this->auth->verificarPermissao('delete', true);
        
        try {
            // Buscar dados para auditoria
            $funcionario = $this->funcionarioModel->buscarPorId($id);
            if (!$funcionario) {
                throw new Exception('Funcionário não encontrado');
            }
            
            $sucesso = $this->funcionarioModel->excluir($id);
            
            if (!$sucesso) {
                throw new Exception('Erro ao excluir funcionário');
            }
            
            registrarAuditoria('funcionarios', $id, 'delete', $funcionario);
            
            enviarJSON([
                'success' => true,
                'message' => 'Funcionário excluído com sucesso'
            ]);
            
        } catch (Exception $e) {
            enviarJSON([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Atualiza ASO do funcionário
     */
    public function atualizarASO($id) {
        $this->auth->verificarLogin();
        $this->auth->verificarPermissao('update', true);
        
        try {
            // Verificar token CSRF
            if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token de segurança inválido');
            }
            
            $aso_data = $_POST['aso_data'] ?? '';
            
            if (empty($aso_data)) {
                throw new Exception('Data do ASO é obrigatória');
            }
            
            $sucesso = $this->funcionarioModel->atualizarASO($id, $aso_data);
            
            if (!$sucesso) {
                throw new Exception('Erro ao atualizar ASO');
            }
            
            registrarAuditoria('funcionarios', $id, 'update_aso', null, ['aso_data' => $aso_data]);
            
            enviarJSON([
                'success' => true,
                'message' => 'ASO atualizado com sucesso'
            ]);
            
        } catch (Exception $e) {
            enviarJSON([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Busca vencimentos de ASO
     */
    public function vencimentosASO() {
        $this->auth->verificarLogin();
        $this->auth->verificarPermissao('read', true);
        
        try {
            $dias = $_GET['dias'] ?? 30;
            $vencimentos = $this->funcionarioModel->buscarVencimentosASO($dias);
            
            enviarJSON([
                'success' => true,
                'vencimentos' => $vencimentos
            ]);
            
        } catch (Exception $e) {
            enviarJSON([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Estatísticas de funcionários
     */
    public function estatisticas() {
        $this->auth->verificarLogin();
        $this->auth->verificarPermissao('read', true);
        
        try {
            $empresa_id = $_GET['empresa_id'] ?? null;
            $stats = $this->funcionarioModel->estatisticas($empresa_id);
            
            enviarJSON([
                'success' => true,
                'estatisticas' => $stats
            ]);
            
        } catch (Exception $e) {
            enviarJSON([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

// Processar requisições
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $controller = new FuncionarioController();
    $action = $_GET['action'] ?? '';
    $id = $_GET['id'] ?? null;
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            switch ($action) {
                case 'buscar':
                    if ($id) {
                        $controller->buscar($id);
                    } else {
                        enviarJSON(['success' => false, 'message' => 'ID não fornecido'], 400);
                    }
                    break;
                case 'vencimentos_aso':
                    $controller->vencimentosASO();
                    break;
                case 'estatisticas':
                    $controller->estatisticas();
                    break;
                default:
                    $controller->listar();
            }
            break;
            
        case 'POST':
            $controller->criar();
            break;
            
        case 'PUT':
            if ($id) {
                $controller->atualizar($id);
            } else {
                enviarJSON(['success' => false, 'message' => 'ID não fornecido'], 400);
            }
            break;
            
        case 'DELETE':
            if ($id) {
                $controller->excluir($id);
            } else {
                enviarJSON(['success' => false, 'message' => 'ID não fornecido'], 400);
            }
            break;
            
        default:
            enviarJSON(['success' => false, 'message' => 'Método não permitido'], 405);
    }
} else {
    enviarJSON(['success' => false, 'message' => 'Método não permitido'], 405);
}
?>

