<?php
require_once __DIR__ . 
'/includes/config.php';
require_once __DIR__ . 
'/includes/connection.php';
require_once __DIR__ . 
'/includes/functions.php';

// Ensure session is started for messages
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_admin(); // Only admins can perform actions

if (
$_SERVER[
'REQUEST_METHOD'] === 
'POST' && isset(
$_POST[
'action']) && 
$_POST[
'action'] === 
'delete') {
    
$terceiro_id = filter_input(INPUT_POST, 
'terceiro_id', FILTER_VALIDATE_INT);
    if (
$terceiro_id) {
        try {
            global 
$pdo; // Ensure PDO is accessible

            // Get photo path before deleting record
            
$stmt_foto = 
$pdo->prepare(
"SELECT foto_path FROM terceiros WHERE id = ?"
);
            
$stmt_foto->execute([
$terceiro_id]);
            
$foto_path = 
$stmt_foto->fetchColumn();

            // Delete record
            
$stmt = 
$pdo->prepare(
"DELETE FROM terceiros WHERE id = ?"
);
            
$stmt->execute([
$terceiro_id]);

            // Delete photo file if it exists
            // Ensure UPLOAD_DIR is defined in config.php and accessible
            if (
$foto_path && defined(
'UPLOAD_DIR') && file_exists(UPLOAD_DIR . 
$foto_path)) {
                @unlink(UPLOAD_DIR . 
$foto_path); // Use @ to suppress errors if file not found
            }

            
$_SESSION[
'success_message'] = 
'Terceiro excluído com sucesso!';

        } catch (PDOException 
$e) {
            
$_SESSION[
'error_message'] = 
'Erro ao excluir terceiro: ' . 
$e->getMessage();
            error_log(
'Erro Excluir Terceiro: ' . 
$e->getMessage());
        }
    } else {
         
$_SESSION[
'error_message'] = 
'ID do terceiro inválido.';
    }
} else {
     
$_SESSION[
'error_message'] = 
'Ação inválida.';
}

// Redirect back to the monitoring page
header(
'Location: monitoramento.php');
exit;
?>
