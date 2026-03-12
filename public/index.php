<?php
/**
 * Roteador Principal da Aplicação
 * Sistema de Gerenciamento de Funcionários
 */

require_once __DIR__ . "/../config/config.php";

// Captura a rota da URL
$request_uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$script_name = dirname($_SERVER["SCRIPT_NAME"]);

// Remove o diretório base do script da URI para obter a rota limpa
if (strpos($request_uri, $script_name) === 0) {
    $route = substr($request_uri, strlen($script_name));
} else {
    $route = $request_uri;
}

// Remove barras extras e garante que a rota comece com barra
$route = trim($route, "/");

// Mapeamento de rotas para arquivos
$routes = [
    "" => "dashboard.php", // Rota padrão para o dashboard
    "dashboard" => "dashboard.php",
    "funcionarios" => "funcionarios/index.php",
    "empresas" => "empresas/index.php",
    "postos" => "postos/index.php",
    "treinamentos" => "treinamentos/index.php",
    "usuarios" => "usuarios/index.php",
    "relatorios" => "relatorios/index.php",
    "login" => "login.php",
    "logout" => "logout.php",
    "404" => "404.php"
];

// Adiciona log para depuração
error_log("DEBUG: Rota detectada: " . $route);
error_log("DEBUG: DOCUMENT_ROOT: " . $_SERVER["DOCUMENT_ROOT"]);

// Inclui o arquivo correspondente à rota
if (array_key_exists($route, $routes)) {
    $file_to_include = __DIR__ . "/" . $routes[$route];
    error_log("DEBUG: Tentando incluir arquivo: " . $file_to_include);
    if (file_exists($file_to_include)) {
        require_once $file_to_include;
    } else {
        error_log("DEBUG: ERRO - Arquivo não encontrado: " . $file_to_include);
        require_once __DIR__ . "/404.php";
    }
} else {
    // Se a rota não for encontrada, redireciona para 404
    error_log("DEBUG: Rota não mapeada: " . $route);
    require_once __DIR__ . "/404.php";
}
