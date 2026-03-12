<?php
// Inicia a sessão para poder destruí-la
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Limpa todas as variáveis da sessão
$_SESSION = array();

// Destrói a sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Define a URL de login (pegando do config se possível, senão um valor padrão)
$login_url = 'login.php'; // Valor padrão
if (file_exists(__DIR__ . '/includes/config.php')) {
    // Tenta incluir o config para pegar APP_URL, mas suprime erros caso não consiga
    @include_once __DIR__ . '/includes/config.php';
    if (defined('APP_URL')) {
        $login_url = APP_URL . '/login.php';
    }
}

// Define a mensagem e o tempo de redirecionamento
$message = "Você foi desconectado com sucesso. Obrigado por utilizar o sistema!";
$redirect_delay = 3; // Segundos

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - Gestão de Terceiros</title>
    <!-- Bootstrap CSS (para estilização mínima) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Meta refresh para redirecionamento -->
    <meta http-equiv="refresh" content="<?php echo $redirect_delay; ?>;url=<?php echo htmlspecialchars($login_url); ?>">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background-color: #f8f9fa; }
        .logout-box { text-align: center; padding: 40px; background-color: #fff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,.1); }
    </style>
</head>
<body>
    <div class="logout-box">
        <h1 class="h3 mb-3 fw-normal">Desconectado</h1>
        <p class="lead"><?php echo htmlspecialchars($message); ?></p>
        <p>Você será redirecionado para a página de login em <?php echo $redirect_delay; ?> segundos.</p>
        <p><a href="<?php echo htmlspecialchars($login_url); ?>">Clique aqui</a> se não for redirecionado automaticamente.</p>
        <div class="spinner-border text-primary mt-3" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
</body>
</html>

