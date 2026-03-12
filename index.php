<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/functions.php';

// Verifica se o usuário está logado
if (is_logged_in()) {
    // Se logado, redireciona para o dashboard
    header('Location: dashboard.php');
} else {
    // Se não logado, redireciona para a página de login
    header('Location: login.php');
}
exit;
?>
