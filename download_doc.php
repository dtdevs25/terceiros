<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$file = filter_input(INPUT_GET, 'file', FILTER_SANITIZE_STRING);

if (!$file) {
    error_log("Erro: Nenhum arquivo especificado em download_doc.php");
    $_SESSION['error_message'] = 'Arquivo inválido.';
    header('Location: logs_atividades.php');
    exit;
}

// CORRIGIDO: Definição consistente do diretório de uploads (minúsculas)
defined('UPLOADS_DIR') || define('UPLOADS_DIR', __DIR__ . '/uploads/');
$file_path = UPLOADS_DIR . $file;

// ADICIONADO: Logs detalhados para diagnóstico
error_log("Download solicitado - Arquivo: $file, Caminho completo: $file_path");
error_log("Verificação de arquivo - Existe: " . (file_exists($file_path) ? 'Sim' : 'Não') . 
          ", Legível: " . (is_readable($file_path) ? 'Sim' : 'Não'));

if (!file_exists($file_path) || !is_readable($file_path)) {
    error_log("Erro: Arquivo não encontrado ou inacessível em '$file_path'");
    $_SESSION['error_message'] = 'Arquivo não encontrado ou inacessível.';
    header('Location: logs_atividades.php');
    exit;
}

$mime_types = [
    'pdf' => 'application/pdf',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc' => 'application/msword',
    'txt' => 'text/plain',
];

$extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$mime_type = isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';

// ADICIONADO: Log do tipo MIME e extensão
error_log("Download - Extensão: $extension, MIME Type: $mime_type");

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: must-revalidate');
header('Pragma: public');

readfile($file_path);
exit;
?>
