<?php
// Arquivo de Configuração - Mantenha fora do diretório web público em produção

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'ctdisystb52a4743_terceiros');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Configurações da Aplicação
define('APP_URL', getenv('APP_URL') ?: 'http://localhost');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// Iniciar sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>