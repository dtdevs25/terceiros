<?php
ob_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_id'])) {
    $log_id = filter_input(INPUT_POST, 'log_id', FILTER_VALIDATE_INT);

    if ($log_id) {
        global $pdo;
        try {
            // Buscar o caminho do arquivo antes de excluir
            $stmt = $pdo->prepare("SELECT doc_path FROM log_atividades WHERE id = ?");
            $stmt->execute([$log_id]);
            $log = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($log && !empty($log['doc_path'])) {
                defined('UPLOADS_DIR') || define('UPLOADS_DIR', __DIR__ . '/Uploads/');
                $file_path = UPLOADS_DIR . $log['doc_path'];
                if (file_exists($file_path) && is_writable($file_path)) {
                    unlink($file_path); // Excluir o arquivo físico
                    error_log("Arquivo excluído: $file_path");
                } else {
                    error_log("Arquivo não encontrado ou sem permissão: $file_path");
                }
            }

            // Excluir o registro do banco
            $stmt = $pdo->prepare("DELETE FROM log_atividades WHERE id = ?");
            $stmt->execute([$log_id]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['success_message'] = 'Log excluído com sucesso.';
                error_log("Log ID $log_id excluído com sucesso");
            } else {
                $_SESSION['error_message'] = 'Erro ao excluir o log de atividade.';
                error_log("Erro: Nenhum log encontrado com ID $log_id");
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao excluir o log de atividade: ' . $e->getMessage();
            error_log('Erro ao excluir log ID ' . $log_id . ': ' . $e->getMessage());
        }
    } else {
        $_SESSION['error_message'] = 'ID do log inválido.';
        error_log('Erro: ID do log inválido em delete_log.php');
    }
} else {
    $_SESSION['error_message'] = 'Requisição inválida.';
    error_log('Erro: Requisição inválida em delete_log.php');
}

// Redirecionar de volta para a página de logs
header('Location: logs_atividades.php');
exit();
?>