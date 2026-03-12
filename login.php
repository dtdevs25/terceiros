<?php

// Inicia a sessão para gerenciar o estado do usuário e mensagens
session_start();

// --- CONFIGURAÇÃO E DEPENDÊNCIAS ---
// Em um projeto real, você usaria um autoloader como o do Composer.
// require_once __DIR__ . '/vendor/autoload.php'; 
require_once __DIR__ . '/includes/connection.php'; // Sua conexão PDO. Garanta que este arquivo cria a variável $pdo.
require_once __DIR__ . '/includes/functions.php';  // Suas funções (is_logged_in, attempt_login, enviar_email, etc.)

// --- VARIÁVEIS DE CONTROLE ---
$error_message = $_SESSION['error_message'] ?? null;
$success_message = $_SESSION['success_message'] ?? null;

// Limpa as mensagens da sessão para que não apareçam novamente
unset($_SESSION['error_message'], $_SESSION['success_message']);

// --- LÓGICA DE NEGÓCIO ---

// 1. Redirecionar se o usuário já estiver logado
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

// 2. Processar as requisições do formulário (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $action = $_POST['action'] ?? 'login';

    try {
        // Garante que a variável $pdo da sua conexão está disponível
        if (!isset($pdo)) {
            throw new Exception("A conexão com o banco de dados não foi estabelecida.");
        }

        if ($action === 'login') {
            handle_login($pdo);
        } elseif ($action === 'forgot_password') {
            handle_forgot_password($pdo);
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Ocorreu um erro em nosso sistema. Por favor, tente novamente mais tarde.';
        // Em produção, logar o erro: error_log($e->getMessage());
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}


// --- FUNÇÕES DE MANIPULAÇÃO DE LÓGICA ---

/**
 * Manipula a tentativa de login.
 * @param PDO $pdo
 */
function handle_login(PDO $pdo): void {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $senha = $_POST['senha'] ?? null;

    if (!$email || empty($senha)) {
        throw new Exception('Por favor, preencha e-mail e senha válidos.');
    }
    
    // A função attempt_login (do seu arquivo functions.php) é chamada aqui
    if (attempt_login($email, $senha, $pdo)) { 
        header('Location: dashboard.php');
        exit;
    } else {
        throw new Exception('E-mail ou senha inválidos.');
    }
}

/**
 * Manipula a solicitação de recuperação de senha.
 * @param PDO $pdo
 */
function handle_forgot_password(PDO $pdo): void {
    $email = filter_input(INPUT_POST, 'email_recuperacao', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        throw new Exception('Por favor, insira um endereço de e-mail válido.');
    }

    $stmt = $pdo->prepare("SELECT id, nome_completo FROM usuarios WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $token = bin2hex(random_bytes(32));
        $token_expiracao = date('Y-m-d H:i:s', time() + 3600); 

        $stmt_token = $pdo->prepare("UPDATE usuarios SET token_recuperacao = :token, token_expiracao = :expiracao WHERE id = :id");
        $stmt_token->execute(['token' => $token, 'expiracao' => $token_expiracao, 'id' => $usuario['id']]);

        $link_recuperacao = "https://seusite.com/recuperar_senha.php?token=" . $token;
        $assunto = "Recuperação de Senha - Gestão de Terceiros";
        $mensagem = "Olá " . htmlspecialchars($usuario['nome_completo']) . ",\n\n"
                  . "Para redefinir sua senha, clique no link abaixo:\n"
                  . $link_recuperacao . "\n\n"
                  . "Este link é válido por 1 hora.\n\n"
                  . "Se você não solicitou a redefinição de senha, pode ignorar este e-mail.";

        if (enviar_email($email, $assunto, $mensagem)) {
            $_SESSION['success_message'] = 'Enviamos um link de recuperação para o seu e-mail. Verifique sua caixa de entrada e spam.';
        } else {
            throw new Exception('Erro ao enviar o e-mail de recuperação. Tente novamente.');
        }
    } else {
         $_SESSION['success_message'] = 'Se o e-mail estiver cadastrado, um link de recuperação será enviado. Verifique sua caixa de entrada e spam.';
    }
}

if (!function_exists('html_escape')) {
    function html_escape($string): string {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gestão de Terceiros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --royal-blue: #4169E1;
            --dark-text: #2c3e50;
            --light-bg: #f4f7fc;
            --white-color: #ffffff;
            --gray-border: #dce1e8;
            --gray-text: #8a95a5;
        }

        body {
            font-family: 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, var(--royal-blue) 0%, var(--light-bg) 80%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 1rem;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            perspective: 1000px;
        }
        
        .login-flipper {
            transition: transform 0.8s;
            transform-style: preserve-3d;
            position: relative;
        }
        
        .login-flipper.is-flipped {
            transform: rotateY(180deg);
        }

        .login-card {
            background-color: var(--white-color);
            color: var(--dark-text);
            border-radius: 15px;
            padding: 2.5rem;
            box-shadow: 0 15px 35px rgba(44, 62, 80, 0.15);
            border: 1px solid #e9ecef;
            position: absolute;
            width: 100%;
            backface-visibility: hidden;
            top: 0;
            left: 0;
        }
        
        .login-card.back {
            transform: rotateY(180deg);
        }

        .card-header-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .card-header-logo img {
            max-width: 110px;
        }

        .card-title {
            font-weight: 600;
            text-align: center;
            color: var(--dark-text);
            margin-bottom: 2rem;
        }

        .form-label {
            font-weight: 500;
        }

        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid var(--gray-border);
            border-right: none;
            color: var(--royal-blue);
        }

        .form-control {
            background-color: #f8f9fa;
            border: 1px solid var(--gray-border);
            color: var(--dark-text);
            border-radius: 0 8px 8px 0 !important;
            padding: 0.75rem 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .input-group .form-control:focus {
            z-index: 3;
        }

        .form-control:focus {
            background-color: var(--white-color);
            border-color: var(--royal-blue);
            box-shadow: 0 0 0 0.25rem rgba(65, 105, 225, 0.2);
            color: var(--dark-text);
        }

        .form-control::placeholder {
            color: var(--gray-text);
        }
        
        #togglePassword {
             border: 1px solid var(--gray-border);
             border-left: none;
             background-color: #f8f9fa;
        }

        .btn-primary {
            background-color: var(--royal-blue);
            border-color: var(--royal-blue);
            font-weight: 600;
            padding: 0.75rem;
            border-radius: 8px;
            transition: background-color 0.3s, transform 0.2s;
        }

        .btn-primary:hover {
            background-color: #3558c4;
            border-color: #3558c4;
            transform: translateY(-2px);
        }
        
        .form-text-link {
            color: var(--royal-blue) !important;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .form-text-link:hover {
            color: #3558c4 !important;
            text-decoration: underline;
        }
        
        .flipper-container {
            min-height: 550px;
        }
        
        .alert {
            font-size: 0.9rem;
        }

    </style>
</head>
<body>

<div class="login-container">
    <div class="flipper-container" id="flipperContainer">
      <div class="login-flipper" id="loginFlipper">
        
        <!-- Frente do Cartão: Formulário de Login -->
        <div class="login-card front">
            <div class="card-header-logo">
                <img src="https://encrypted-tbn3.gstatic.com/images?q=tbn:ANd9GcRVczuUk0fKPU-QSx8pFzbujgYW9yQso1wwTsPMrEOlicl1iaQo" alt="Logo">
            </div>
            <h3 class="card-title">Gestão de Terceiros</h3>

            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo html_escape($error_message); ?>
                </div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo html_escape($success_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo html_escape($_SERVER['PHP_SELF']); ?>">
                <input type="hidden" name="action" value="login">
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                        <input type="email" class="form-control" id="email" name="email" placeholder="seu.email@exemplo.com" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="senha" class="form-label">Senha</label>
                    <div class="input-group">
                         <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control" id="senha" name="senha" placeholder="••••••••" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="bi bi-eye-fill"></i>
                        </button>
                    </div>
                </div>
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary">Entrar</button>
                </div>
                <div class="text-center">
                    <a href="#" id="esqueceuSenhaLink" class="form-text-link">Esqueceu sua senha?</a>
                </div>
            </form>
        </div>

        <!-- Verso do Cartão: Formulário de Recuperação -->
        <div class="login-card back">
             <div class="card-header-logo">
                <img src="https://encrypted-tbn3.gstatic.com/images?q=tbn:ANd9GcRVczuUk0fKPU-QSx8pFzbujgYW9yQso1wwTsPMrEOlicl1iaQo" alt="Logo">
            </div>
            <h3 class="card-title">Recuperar Senha</h3>
            <p class="text-center text-secondary small mb-4">Insira seu e-mail para receber um link de redefinição.</p>
            
            <form method="POST" action="<?php echo html_escape($_SERVER['PHP_SELF']); ?>">
                 <input type="hidden" name="action" value="forgot_password">
                <div class="mb-3">
                    <label for="email_recuperacao" class="form-label">E-mail de Recuperação</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                        <input type="email" class="form-control" id="email_recuperacao" name="email_recuperacao" placeholder="seu.email@exemplo.com" required>
                    </div>
                </div>
                <div class="d-grid mb-3 mt-4">
                    <button type="submit" class="btn btn-primary">Enviar Link</button>
                </div>
                <div class="text-center">
                    <a href="#" id="voltarLoginLink" class="form-text-link">Voltar para o Login</a>
                </div>
            </form>
        </div>

      </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Lógica para virar o cartão
        const flipper = document.getElementById('loginFlipper');
        const flipperContainer = document.getElementById('flipperContainer');
        const showForgotCardLink = document.getElementById('esqueceuSenhaLink');
        const showLoginCardLink = document.getElementById('voltarLoginLink');
        const loginCard = document.querySelector('.login-card.front');
        const forgotCard = document.querySelector('.login-card.back');
        
        const adjustHeight = () => {
            requestAnimationFrame(() => {
                if (flipper.classList.contains('is-flipped')) {
                    flipperContainer.style.height = forgotCard.offsetHeight + 'px';
                } else {
                    flipperContainer.style.height = loginCard.offsetHeight + 'px';
                }
            });
        };

        showForgotCardLink.addEventListener('click', (event) => {
            event.preventDefault();
            flipper.classList.add('is-flipped');
            adjustHeight();
        });

        showLoginCardLink.addEventListener('click', (event) => {
            event.preventDefault();
            flipper.classList.remove('is-flipped');
            adjustHeight();
        });
        
        adjustHeight();
        window.onresize = adjustHeight;
        
        // Lógica para mostrar/ocultar senha
        const togglePassword = document.querySelector('#togglePassword');
        const passwordInput = document.querySelector('#senha');
        const eyeIcon = togglePassword.querySelector('i');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Alterna o ícone
            if (type === 'password') {
                eyeIcon.classList.remove('bi-eye-slash-fill');
                eyeIcon.classList.add('bi-eye-fill');
            } else {
                eyeIcon.classList.remove('bi-eye-fill');
                eyeIcon.classList.add('bi-eye-slash-fill');
            }
        });

        <?php if(isset($_POST['action']) && $_POST['action'] === 'forgot_password'): ?>
            flipper.classList.add('is-flipped');
            adjustHeight();
        <?php endif; ?>
    });
</script>
</body>
</html>
