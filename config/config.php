<?php
/**
 * Configurações Gerais do Sistema
 * Sistema de Gerenciamento de Funcionários
 */

// --- INÍCIO DA DEPURAÇÃO ---
error_log("DEBUG: AuthController.php sendo carregado.");
$configPath = __DIR__ . '/../config/config.php';
error_log("DEBUG: Tentando incluir config.php de: " . $configPath);
if (file_exists($configPath)) {
    error_log("DEBUG: config.php existe no caminho: " . $configPath);
} else {
    error_log("DEBUG: ERRO - config.php NÃO encontrado no caminho: " . $configPath);
}
// --- FIM DA DEPURAÇÃO ---

// Configurações de sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', getenv('HTTPS') === 'true' ? 1 : 0);
session_start();

// Configurações de erro
error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_DEBUG') === 'true' ? 1 : 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações do sistema
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost');

define('SITE_NAME', 'Sistema de Gerenciamento de Funcionários');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Configurações de segurança
define('HASH_ALGO', PASSWORD_DEFAULT);
define('SESSION_TIMEOUT', 3600); // 1 hora

// Configurações de email (para alertas)
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'noreply@sistema.com');
define('SMTP_FROM_NAME', 'Sistema de Funcionários');

// Hierarquias de usuário
define('HIERARQUIAS', [
    'visualizador' => 1,
    'controlador' => 2,
    'administrador' => 3,
    'gerente' => 4
]);

// Permissões por hierarquia
define('PERMISSOES', [
    'visualizador' => ['read'],
    'controlador' => ['read', 'update'],
    'administrador' => ['read', 'create', 'update', 'delete'],
    'gerente' => ['read', 'create', 'update', 'delete', 'admin']
]);

/**
 * Função para verificar se o usuário está logado
 */
function verificarLogin() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ' . SITE_URL . '/public/login.php');
        exit();
    }
    
    // Verificar timeout da sessão
    if (isset($_SESSION['ultimo_acesso']) && 
        (time() - $_SESSION['ultimo_acesso']) > SESSION_TIMEOUT) {
        session_destroy();
        header('Location: ' . SITE_URL . '/public/login.php?timeout=1');
        exit();
    }
    
    $_SESSION['ultimo_acesso'] = time();
}

/**
 * Função para verificar permissões
 */
function verificarPermissao($acao) {
    if (!isset($_SESSION['hierarquia'])) {
        return false;
    }
    
    $permissoes = PERMISSOES[$_SESSION['hierarquia']] ?? [];
    return in_array($acao, $permissoes);
}

/**
 * Função para sanitizar dados de entrada
 */
function sanitizar($data) {
    if (is_array($data)) {
        return array_map('sanitizar', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Função para validar CPF
 */
function validarCPF($cpf) {
    // Remove caracteres não numéricos
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Verifica se tem 11 dígitos
    if (strlen($cpf) != 11) {
        return false;
    }
    
    // Verifica se não é uma sequência de números iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    // Calcula os dígitos verificadores
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    
    return true;
}

/**
 * Função para validar CNPJ
 */
function validarCNPJ($cnpj) {
    // Remove caracteres não numéricos
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    // Verifica se tem 14 dígitos
    if (strlen($cnpj) != 14) {
        return false;
    }
    
    // Verifica se não é uma sequência de números iguais
    if (preg_match('/(\d)\1{13}/', $cnpj)) {
        return false;
    }
    
    // Calcula o primeiro dígito verificador
    $soma = 0;
    $peso = 5;
    for ($i = 0; $i < 12; $i++) {
        $soma += $cnpj[$i] * $peso;
        $peso = ($peso == 2) ? 9 : $peso - 1;
    }
    $resto = $soma % 11;
    $dv1 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Calcula o segundo dígito verificador
    $soma = 0;
    $peso = 6;
    for ($i = 0; $i < 13; $i++) {
        $soma += $cnpj[$i] * $peso;
        $peso = ($peso == 2) ? 9 : $peso - 1;
    }
    $resto = $soma % 11;
    $dv2 = ($resto < 2) ? 0 : 11 - $resto;
    
    return ($cnpj[12] == $dv1 && $cnpj[13] == $dv2);
}

/**
 * Função para formatar CPF
 */
function formatarCPF($cpf) {
    if (is_null($cpf)) return null;
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
}

/**
 * Função para formatar CNPJ
 */
function formatarCNPJ($cnpj) {
    if (is_null($cnpj)) return null;
    $cnpj = preg_replace("/[^0-9]/", "", $cnpj);
    return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "$1.$2.$3/$4-$5", $cnpj);
}

/**
 * Função para gerar hash de senha
 */
function gerarHashSenha($senha) {
    return password_hash($senha, HASH_ALGO);
}

/**
 * Função para verificar senha
 */
function verificarSenha($senha, $hash) {
    return password_verify($senha, $hash);
}

/**
 * Função para gerar token CSRF
 */
function gerarTokenCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Função para verificar token CSRF
 */
function verificarTokenCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Função para registrar log de auditoria
 */
function registrarAuditoria($tabela, $registro_id, $acao, $dados_anteriores = null, $dados_novos = null) {
    try {
        $db = new Database();
        $db->setAuditUser($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR']);
        
        $sql = "INSERT INTO auditoria (usuario_id, tabela, registro_id, acao, dados_anteriores, dados_novos, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $_SESSION['usuario_id'],
            $tabela,
            $registro_id,
            $acao,
            $dados_anteriores ? json_encode($dados_anteriores) : null,
            $dados_novos ? json_encode($dados_novos) : null,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        $db->query($sql, $params);
    } catch (Exception $e) {
        error_log("Erro ao registrar auditoria: " . $e->getMessage());
    }
}

/**
 * Função para enviar resposta JSON
 */
function enviarJSON($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Função para upload de arquivo
 */
function uploadArquivo($arquivo, $pasta = 'fotos') {
    if (!isset($arquivo) || $arquivo['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erro no upload do arquivo');
    }
    
    // Verificar tamanho
    if ($arquivo['size'] > MAX_FILE_SIZE) {
        throw new Exception('Arquivo muito grande. Máximo: ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB');
    }
    
    // Verificar tipo
    $tipos_permitidos = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    if (!in_array($arquivo['type'], $tipos_permitidos)) {
        throw new Exception('Tipo de arquivo não permitido');
    }
    
    // Gerar nome único
    $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
    $nome_arquivo = uniqid() . '.' . $extensao;
    $caminho_completo = UPLOAD_PATH . $pasta . '/' . $nome_arquivo;
    
    // Criar pasta se não existir
    if (!is_dir(dirname($caminho_completo))) {
        mkdir(dirname($caminho_completo), 0755, true);
    }
    
    // Mover arquivo
    if (!move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
        throw new Exception('Erro ao salvar arquivo');
    }
    
    return $pasta . '/' . $nome_arquivo;
}

// Incluir autoloader das classes
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../models/' . $class . '.php',
        __DIR__ . '/../controllers/' . $class . '.php'
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
});