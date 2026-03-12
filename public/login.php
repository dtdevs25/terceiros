<?php
/**
 * Página de Login
 * Sistema de Gerenciamento de Funcionários
 */

require_once __DIR__ . '/../config/config.php';

// Se já estiver logado, redirecionar para dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . '/public/dashboard.php');
    exit();
}

$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['erro']);

$logout = $_GET['logout'] ?? '';
$timeout = $_GET['timeout'] ?? '';

$page_title = 'Login - ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo gerarTokenCSRF(); ?>">
    <title><?php echo $page_title; ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/responsive.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/assets/images/favicon.ico">
    
    <style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    
    .login-container {
        background: var(--white);
        border-radius: var(--border-radius-large);
        box-shadow: var(--shadow-heavy);
        padding: 40px;
        width: 100%;
        max-width: 400px;
        animation: fadeIn 0.5s ease-out;
    }
    
    .login-header {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .login-logo {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        color: var(--white);
        font-size: 2rem;
        font-weight: bold;
    }
    
    .login-title {
        font-size: 1.8rem;
        font-weight: 600;
        color: var(--primary-dark);
        margin: 0 0 10px 0;
    }
    
    .login-subtitle {
        color: var(--gray);
        font-size: 0.95rem;
        margin: 0;
    }
    
    .login-form {
        margin-bottom: 20px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-control {
        padding: 15px 20px;
        font-size: 1rem;
        border-radius: var(--border-radius-large);
    }
    
    .btn-login {
        width: 100%;
        padding: 15px;
        font-size: 1.1rem;
        font-weight: 600;
        border-radius: var(--border-radius-large);
        margin-top: 10px;
    }
    
    .login-footer {
        text-align: center;
        color: var(--gray);
        font-size: 0.9rem;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid var(--light-gray);
    }
    
    .alert {
        margin-bottom: 20px;
        border-radius: var(--border-radius-large);
    }
    
    @media (max-width: 575px) {
        .login-container {
            padding: 30px 20px;
        }
        
        .login-title {
            font-size: 1.5rem;
        }
        
        .form-control {
            padding: 12px 16px;
        }
        
        .btn-login {
            padding: 12px;
            font-size: 1rem;
        }
    }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </div>
            <h1 class="login-title">Bem-vindo</h1>
            <p class="login-subtitle">Faça login para acessar o sistema</p>
        </div>
        
        <!-- Alertas -->
        <?php if ($logout): ?>
            <div class="alert alert-success">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20,6 9,17 4,12"></polyline>
                </svg>
                Logout realizado com sucesso!
            </div>
        <?php endif; ?>
        
        <?php if ($timeout): ?>
            <div class="alert alert-warning">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                Sua sessão expirou. Faça login novamente.
            </div>
        <?php endif; ?>
        
        <?php if ($erro): ?>
            <div class="alert alert-danger">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>
        
        <!-- Formulário de Login -->
        <form class="login-form" method="POST" action="<?php echo SITE_URL; ?>/public/index.php?route=auth&action=login" data-validate="true" data-ajax="true">
            <div class="form-group">
                <label class="form-label required">E-mail</label>
                <input type="email" name="email" class="form-control" placeholder="Digite seu e-mail" data-validate="required|email" required autofocus>
            </div>
            
            <div class="form-group">
                <label class="form-label required">Senha</label>
                <input type="password" name="senha" class="form-control" placeholder="Digite sua senha" data-validate="required" required>
            </div>
            
            <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
            <input type="hidden" name="ajax" value="true">
            
            <button type="submit" class="btn btn-primary btn-login">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                    <polyline points="10,17 15,12 10,7"></polyline>
                    <line x1="15" y1="12" x2="3" y2="12"></line>
                </svg>
                Entrar
            </button>
        </form>
        
        <div class="login-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?></p>
            <p>Sistema de Gerenciamento de Funcionários</p>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    
    <script>
    // Configurar Config.baseUrl para o JavaScript
    Config.baseUrl = '<?php echo SITE_URL; ?>';
    
    // Focar no primeiro campo com erro
    document.addEventListener('DOMContentLoaded', function() {
        const errorField = document.querySelector('.form-control.error');
        if (errorField) {
            errorField.focus();
        }
    });
    
    // Limpar alertas após alguns segundos
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 300);
        });
    }, 5000);
    </script>
</body>
</html>
