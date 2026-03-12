<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$file = filter_input(INPUT_GET, 'file', FILTER_SANITIZE_STRING);

if (!$file) {
    error_log("view_log.php - Erro: Arquivo não especificado");
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Arquivo não especificado']);
    exit;
}

// Normalizar o caminho do arquivo, removendo prefixos indesejados
$file = ltrim(str_replace('/Uploads/', '', $file), '/\\');
error_log("view_log.php - Arquivo após normalização: $file");

// Definição do diretório de uploads
defined('UPLOADS_DIR') || define('UPLOADS_DIR', __DIR__ . '/Uploads/');
$file_path = UPLOADS_DIR . $file;

// Tentar subdiretório docs/ se o arquivo não existir diretamente
$docs_file_path = UPLOADS_DIR . 'docs/' . basename($file);

// Logs detalhados para diagnóstico
error_log("view_log.php - Caminho principal: $file_path");
error_log("view_log.php - Caminho docs/: $docs_file_path");
error_log("view_log.php - Verificação principal - Existe: " . (file_exists($file_path) ? 'Sim' : 'Não') . 
          ", Legível: " . (is_readable($file_path) ? 'Sim' : 'Não'));
error_log("view_log.php - Verificação docs/ - Existe: " . (file_exists($docs_file_path) ? 'Sim' : 'Não') . 
          ", Legível: " . (is_readable($docs_file_path) ? 'Sim' : 'Não'));

// Usar o caminho que existir e for legível
if (file_exists($docs_file_path) && is_readable($docs_file_path)) {
    $file_path = $docs_file_path;
    $file = 'docs/' . basename($file);
    error_log("view_log.php - Usando caminho docs/: $file_path");
} elseif (!file_exists($file_path) || !is_readable($file_path)) {
    error_log("view_log.php - Erro: Nenhum arquivo encontrado ou inacessível em $file_path ou $docs_file_path");
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Arquivo não encontrado ou inacessível']);
    exit;
}

$extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
error_log("view_log.php - Extensão do arquivo: $extension");

// Se for um arquivo de texto, retorna o conteúdo
if (in_array($extension, ['txt', 'log', 'csv', 'md'])) {
    $content = file_get_contents($file_path);
    error_log("view_log.php - Retornando conteúdo de texto para $file");
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'content' => $content, 'type' => 'text']);
    exit;
}

// Para outros tipos de arquivo, retorna o caminho relativo
$mime_types = [
    'pdf' => 'application/pdf',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
];

if (isset($mime_types[$extension])) {
    $relative_path = 'Uploads/' . $file;
    error_log("view_log.php - Retornando caminho para visualização: $relative_path");
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'path' => $relative_path, 
        'type' => $extension,
        'mime' => $mime_types[$extension]
    ]);
    exit;
}

// Para arquivos Word, retorna informação específica
if (in_array($extension, ['docx', 'doc'])) {
    error_log("view_log.php - Arquivo Word detectado: $file");
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'type' => 'word',
        'message' => 'Este é um arquivo do Microsoft Word. Use o botão "Baixar" para visualizar o conteúdo.'
    ]);
    exit;
}

// Para outros tipos de arquivo não suportados
error_log("view_log.php - Tipo de arquivo não suportado: $extension");
header('Content-Type: application/json');
echo json_encode([
    'success' => false, 
    'error' => 'Tipo de arquivo não suportado para visualização',
    'type' => $extension
]);
exit;
?>