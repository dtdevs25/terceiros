<?php
require_once __DIR__ . '/config.php';

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
     // Em produção, logar o erro e exibir mensagem genérica
     error_log('Erro de conexão com o banco de dados: ' . $e->getMessage());
     // É importante ter uma página de erro amigável ou um tratamento melhor aqui
     die("Erro interno do servidor ao conectar com o banco de dados. Por favor, contate o administrador.");
}

?>
