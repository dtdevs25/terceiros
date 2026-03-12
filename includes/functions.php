<?php
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/config.php'; // Incluir para ter acesso a APP_URL

// --- Funções de Autenticação ---

function is_logged_in() {
    // Inicia a sessão se ainda não estiver iniciada
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}

function is_admin() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'admin';
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function require_admin() {
    require_login(); // Garante que está logado primeiro
    if (!is_admin()) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['error_message'] = 'Acesso não autorizado.';
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    }
}

function attempt_login($email, $senha) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id, nome, email, senha, tipo, filiais_permitidas FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($senha, $user['senha'])) {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nome'] = $user['nome'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_tipo'] = $user['tipo'];
            $_SESSION['user_filiais'] = $user['filiais_permitidas'];
            return true;
        } else {
            return false;
        }
    } catch (PDOException $e) {
        error_log("Erro no login: " . $e->getMessage());
        // Lança exceção para ser tratada no login.php
        throw new Exception("Erro ao verificar credenciais.");
    }
}

function logout() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

function verificar_trabalho_hoje($pdo, $terceiro_id) {
    $hoje = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM log_atividades WHERE terceiro_id = ? AND DATE(data_liberacao) = ?");
    $stmt->execute([$terceiro_id, $hoje]);
    $count = $stmt->fetchColumn();
    return $count > 0;
}

// --- Funções de Cálculo de Status e Validade ---

function calcular_status_terceiro($terceiro_id) {
    global $pdo;
    $hoje = new DateTime();

    try {
        $stmt = $pdo->prepare("SELECT * FROM terceiros WHERE id = ?");
        $stmt->execute([$terceiro_id]);
        $terceiro = $stmt->fetch();

        if (!$terceiro) {
            return ['status' => 'ERRO', 'vencidos' => ['Terceiro não encontrado']];
        }

        $status = 'LIBERADO';
        $vencidos = [];

        $documentos = [
            'aso' => ['validade' => '+1 year', 'aplicavel' => $terceiro['aso_aplicavel'], 'data' => $terceiro['aso_data']],
            'nr10' => ['validade' => '+2 years', 'aplicavel' => $terceiro['nr10_aplicavel'], 'data' => $terceiro['nr10_data']],
            'nr11' => ['validade' => '+1 year', 'aplicavel' => $terceiro['nr11_apicavel'], 'data' => $terceiro['nr11_data']],
            'nr12' => ['validade' => '+1 year', 'aplicavel' => $terceiro['nr12_aplicavel'], 'data' => $terceiro['nr12_data']],
            'nr18' => ['validade' => '+1 year', 'aplicavel' => $terceiro['nr18_aplicavel'], 'data' => $terceiro['nr18_data']],
            'integracao' => ['validade' => '+1 year', 'aplicavel' => $terceiro['integracao_aplicavel'], 'data' => $terceiro['integracao_data']],
            'nr20' => ['validade' => '+1 year', 'aplicavel' => $terceiro['nr20_aplicavel'], 'data' => $terceiro['nr20_data']],
            'nr33' => ['validade' => '+1 year', 'aplicavel' => $terceiro['nr33_aplicavel'], 'data' => $terceiro['nr33_data']],
            'nr35' => ['validade' => '+2 years', 'aplicavel' => $terceiro['nr35_aplicavel'], 'data' => $terceiro['nr35_data']],
            'epi' => ['validade' => null, 'aplicavel' => $terceiro['epi_aplicavel'], 'data' => $terceiro['epi_data']],
        ];

        // Verificar EPI separadamente
        if ($documentos['epi']['aplicavel'] == 0) {
            $vencidos[] = 'Ficha de EPI (Pendente)';
        }

        foreach ($documentos as $nome => $doc) {
            // Ignorar EPI na verificação de status
            if ($nome === 'epi') {
                continue;
            }

            if ($doc['aplicavel']) {
                if (empty($doc['data'])) {
                    $status = 'BLOQUEADO';
                    $vencidos[] = strtoupper($nome) . ' (Pendente)';
                } elseif ($doc['validade'] !== null) {
                    try {
                        $data_doc = new DateTime($doc['data']);
                        $data_vencimento = clone $data_doc;
                        $data_vencimento->modify($doc['validade']);
                        if ($data_vencimento < $hoje) {
                            $status = 'BLOQUEADO';
                            $vencidos[] = strtoupper($nome) . ' (Vencido em ' . $data_vencimento->format('d/m/Y') . ')';
                        }
                    } catch (Exception $e) {
                        $status = 'BLOQUEADO';
                        $vencidos[] = strtoupper($nome) . ' (Data Inválida)';
                    }
                }
            }
        }

        return ['status' => $status, 'vencidos' => $vencidos];

    } catch (PDOException $e) {
        error_log("Erro ao calcular status: " . $e->getMessage());
        return ['status' => 'ERRO', 'vencidos' => ['Erro ao consultar banco de dados']];
    }
}

// --- Funções CRUD ---

function get_todas_empresas() {
    global $pdo;
    $stmt = $pdo->query("SELECT id, nome FROM empresas ORDER BY nome");
    return $stmt->fetchAll();
}

function get_todas_filiais() {
    global $pdo;
    $stmt = $pdo->query("SELECT id, nome FROM filiais ORDER BY nome");
    return $stmt->fetchAll();
}

// --- Funções Auxiliares ---

function html_escape($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

function formatar_data($data) {
    if (empty($data)) return '';
    try {
        $dt = new DateTime($data);
        return $dt->format('d/m/Y');
    } catch (Exception $e) {
        return 'Data inválida';
    }
}

// --- Funções de Recuperação de Senha ---

/**
 * Envia um e-mail de recuperação de senha para o usuário.
 *
 * @param string $email Endereço de e-mail do usuário.
 * @param string $assunto Assunto do e-mail.
 * @param string $mensagem Corpo do e-mail (em texto simples).
 * @return bool True se o e-mail foi enviado com sucesso, false em caso de erro.
 */
function enviar_email($email, $assunto, $mensagem) {
    // Implemente a lógica de envio de e-mail aqui.
    // Use a função mail() do PHP ou uma biblioteca como PHPMailer.
    // Este é apenas um exemplo básico usando a função mail() do PHP:
    $headers = "From: suporte@seusite.com\r\n" .  // Remetente (altere para o seu domínio)
        "Reply-To: suporte@seusite.com\r\n" .
        "X-Mailer: PHP/" . phpversion();
    return mail($email, $assunto, $mensagem, $headers);
    // Em produção, você deve usar uma biblioteca robusta como PHPMailer para
    // lidar com diferentes servidores de e-mail e garantir a entrega.
}

/**
 * Valida o token de recuperação de senha.
 *
 * @param string $token Token de recuperação de senha.
 * @return array|false Retorna um array com os dados do usuário (id, email) se o token for válido, false caso contrário.
 */
function validar_token_recuperacao($token) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id, email FROM usuarios WHERE token_recuperacao = ? AND token_expiracao > NOW()");
        $stmt->execute([$token]);
        $usuario = $stmt->fetch();
        return $usuario ? $usuario : false;
    } catch (PDOException $e) {
        error_log("Erro ao validar token: " . $e->getMessage());
        return false; // Retorna false em caso de erro no banco de dados
    }
}

/**
 * Atualiza a senha do usuário e invalida o token de recuperação.
 *
 * @param int $usuario_id ID do usuário.
 * @param string $nova_senha Nova senha a ser definida.
 * @return bool True se a senha foi atualizada com sucesso, false em caso de erro.
 */
function atualizar_senha($usuario_id, $nova_senha) {
    global $pdo;
    try {
        $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET senha = ?, token_recuperacao = NULL, token_expiracao = NULL WHERE id = ?");
        $stmt->execute([$senha_hash, $usuario_id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro ao atualizar senha: " . $e->getMessage());
        return false; // Retorna false em caso de erro no banco de dados
    }
}
?>
