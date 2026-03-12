<?php
/**
 * Controlador de Autenticação
 * Sistema de Gerenciamento de Funcionários
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Usuario.php';

class AuthController {
    private $usuarioModel;
    
    public function __construct() {
        $this->usuarioModel = new Usuario();
    }
    
    /**
     * Processa login
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Verificar token CSRF
                if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
                    throw new Exception('Token de segurança inválido');
                }
                
                $email = sanitizar($_POST['email'] ?? '');
                $senha = $_POST['senha'] ?? '';
                
                if (empty($email) || empty($senha)) {
                    throw new Exception('E-mail e senha são obrigatórios');
                }
                
                $usuario = $this->usuarioModel->autenticar($email, $senha);
                
                if (!$usuario) {
                    throw new Exception('E-mail ou senha incorretos');
                }
                
                // Criar sessão
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['hierarquia'] = $usuario['hierarquia'];
                $_SESSION['empresas'] = $usuario['empresas'];
                $_SESSION['ultimo_acesso'] = time();
                
                // Registrar log de login
                registrarAuditoria('usuarios', $usuario['id'], 'login');
                
                // Resposta de sucesso
                if (isset($_POST['ajax'])) {
                    enviarJSON(['success' => true, 'redirect' => SITE_URL . '/public/dashboard.php']);
                } else {
                    header('Location: ' . SITE_URL . '/public/dashboard.php');
                    exit();
                }
                
            } catch (Exception $e) {
                if (isset($_POST['ajax'])) {
                    enviarJSON(['success' => false, 'message' => $e->getMessage()], 400);
                } else {
                    $_SESSION['erro'] = $e->getMessage();
                    header('Location: ' . SITE_URL . '/public/login.php');
                    exit();
                }
            }
        }
    }
    
    /**
     * Processa logout
     */
    public function logout() {
        if (isset($_SESSION['usuario_id'])) {
            registrarAuditoria('usuarios', $_SESSION['usuario_id'], 'logout');
        }
        
        session_destroy();
        header('Location: ' . SITE_URL . '/public/login.php?logout=1');
        exit();
    }
    
    /**
     * Verifica se usuário está logado
     */
    public function verificarLogin() {
        if (!isset($_SESSION['usuario_id'])) {
            if (isset($_POST['ajax']) || isset($_GET['ajax'])) {
                enviarJSON(['success' => false, 'message' => 'Sessão expirada'], 401);
            } else {
                header('Location: ' . SITE_URL . '/public/login.php');
                exit();
            }
        }
        
        // Verificar timeout da sessão
        if (isset($_SESSION['ultimo_acesso']) && 
            (time() - $_SESSION['ultimo_acesso']) > SESSION_TIMEOUT) {
            $this->logout();
        }
        
        $_SESSION['ultimo_acesso'] = time();
    }
    
    /**
     * Verifica permissões do usuário
     */
    public function verificarPermissao($acao, $retornar_json = false) {
        if (!isset($_SESSION['hierarquia'])) {
            if ($retornar_json) {
                enviarJSON(['success' => false, 'message' => 'Acesso negado'], 403);
            } else {
                header('Location: ' . SITE_URL . '/public/dashboard.php?erro=acesso_negado');
                exit();
            }
        }
        
        $permissoes = PERMISSOES[$_SESSION['hierarquia']] ?? [];
        
        if (!in_array($acao, $permissoes)) {
            if ($retornar_json) {
                enviarJSON(['success' => false, 'message' => 'Você não tem permissão para esta ação'], 403);
            } else {
                header('Location: ' . SITE_URL . '/public/dashboard.php?erro=sem_permissao');
                exit();
            }
        }
        
        return true;
    }
    
    /**
     * Verifica se usuário tem acesso à empresa
     */
    public function verificarAcessoEmpresa($empresa_id, $retornar_json = false) {
        // Gerentes têm acesso a todas as empresas
        if ($_SESSION['hierarquia'] === 'gerente') {
            return true;
        }
        
        if (!in_array($empresa_id, $_SESSION['empresas'])) {
            if ($retornar_json) {
                enviarJSON(['success' => false, 'message' => 'Acesso negado a esta empresa'], 403);
            } else {
                header('Location: ' . SITE_URL . '/public/dashboard.php?erro=acesso_empresa');
                exit();
            }
        }
        
        return true;
    }
    
    /**
     * Altera senha do usuário logado
     */
    public function alterarSenha() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $this->verificarLogin();
                
                // Verificar token CSRF
                if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
                    throw new Exception('Token de segurança inválido');
                }
                
                $senha_atual = $_POST['senha_atual'] ?? '';
                $senha_nova = $_POST['senha_nova'] ?? '';
                $confirmar_senha = $_POST['confirmar_senha'] ?? '';
                
                if (empty($senha_atual) || empty($senha_nova) || empty($confirmar_senha)) {
                    throw new Exception('Todos os campos são obrigatórios');
                }
                
                if ($senha_nova !== $confirmar_senha) {
                    throw new Exception('Nova senha e confirmação não conferem');
                }
                
                if (strlen($senha_nova) < 6) {
                    throw new Exception('Nova senha deve ter pelo menos 6 caracteres');
                }
                
                $sucesso = $this->usuarioModel->alterarSenha($_SESSION['usuario_id'], $senha_atual, $senha_nova);
                
                if (!$sucesso) {
                    throw new Exception('Erro ao alterar senha');
                }
                
                registrarAuditoria('usuarios', $_SESSION['usuario_id'], 'alterar_senha');
                
                enviarJSON(['success' => true, 'message' => 'Senha alterada com sucesso']);
                
            } catch (Exception $e) {
                enviarJSON(['success' => false, 'message' => $e->getMessage()], 400);
            }
        }
    }
    
    /**
     * Retorna dados do usuário logado
     */
    public function dadosUsuario() {
        $this->verificarLogin();
        
        return [
            'id' => $_SESSION['usuario_id'],
            'nome' => $_SESSION['usuario_nome'],
            'email' => $_SESSION['usuario_email'],
            'hierarquia' => $_SESSION['hierarquia'],
            'empresas' => $_SESSION['empresas']
        ];
    }
    
    /**
     * Verifica se sessão está ativa (para AJAX)
     */
    public function verificarSessao() {
        if (isset($_SESSION['usuario_id'])) {
            enviarJSON(['success' => true, 'ativo' => true]);
        } else {
            enviarJSON(['success' => true, 'ativo' => false]);
        }
    }
}

// Processar requisições AJAX
if (isset($_GET['action']) || isset($_POST['action'])) {
    $auth = new AuthController();
    $action = $_GET['action'] ?? $_POST['action'];
    
    switch ($action) {
        case 'login':
            $auth->login();
            break;
        case 'logout':
            $auth->logout();
            break;
        case 'alterar_senha':
            $auth->alterarSenha();
            break;
        case 'verificar_sessao':
            $auth->verificarSessao();
            break;
        default:
            enviarJSON(['success' => false, 'message' => 'Ação inválida'], 400);
    }
}
?>

