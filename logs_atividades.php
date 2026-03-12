<?php
ob_start();

// Garantir que a sessão esteja ativa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$page_title = 'Logs de Atividades';
require_once __DIR__ . '/includes/header.php';

global $pdo;

// Define o caminho do diretório Uploads
defined('UPLOADS_DIR') || define('UPLOADS_DIR', __DIR__ . '/Uploads/');

// Verificar se os diretórios existem e têm permissões corretas
$upload_dir = __DIR__ . '/Uploads/';
$docs_dir = $upload_dir . 'docs/';
error_log("Verificando diretórios - Upload: " . (is_dir($upload_dir) ? 'Existe' : 'Não existe') . 
          ", Docs: " . (is_dir($docs_dir) ? 'Existe' : 'Não existe'));

// Verificar permissões de administrador para debug
error_log('logs_atividades.php - Usuário: ' . ($_SESSION['user_nome'] ?? 'N/A') . ', is_admin: ' . (is_admin() ? 'true' : 'false'));
error_log('logs_atividades.php - Sessão: ' . json_encode($_SESSION));

try {
    $stmt_filiais = $pdo->query("SELECT id, nome FROM filiais ORDER BY nome ASC");
    $filiais = $stmt_filiais->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Erro ao buscar filiais.';
    error_log('Erro ao buscar filiais: ' . $e->getMessage());
    $filiais = [];
}

$filial_id = filter_input(INPUT_GET, 'filial_id', FILTER_VALIDATE_INT);
$colaborador = filter_input(INPUT_GET, 'colaborador', FILTER_SANITIZE_STRING);
$data = filter_input(INPUT_GET, 'data', FILTER_SANITIZE_STRING);

$where_clauses = [];
$params = [];

if ($filial_id) {
    $where_clauses[] = 't.filial_id = ?';
    $params[] = $filial_id;
}
if (!empty($colaborador)) {
    $where_clauses[] = 'l.nome_colaborador LIKE ?';
    $params[] = "%$colaborador%";
}
if (!empty($data)) {
    $where_clauses[] = 'DATE(l.data_liberacao) = ?';
    $params[] = $data;
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

try {
    $query = "
        SELECT l.id, l.nome_colaborador, l.data_liberacao, l.doc_path, l.assinatura, f.nome as filial_nome
        FROM log_atividades l
        JOIN terceiros t ON l.terceiro_id = t.id
        JOIN filiais f ON t.filial_id = f.id
        $where_sql
        ORDER BY l.data_liberacao DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Erro ao buscar logs de atividades.';
    error_log('Erro ao buscar logs: ' . $e->getMessage());
    $logs = [];
}

if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show">' . html_escape($_SESSION['success_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show">' . html_escape($_SESSION['error_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['error_message']);
}
?>

<style>
/* Estilos aprimorados para botões modernos */
.btn-modern {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 12px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.16), 0 3px 6px rgba(0, 0, 0, 0.23);
    cursor: pointer;
    position: relative;
    overflow: hidden;
    margin: 0 4px;
}

/* Efeito de onda ao clicar */
.btn-modern::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 5px;
    height: 5px;
    background: rgba(255, 255, 255, 0.5);
    opacity: 0;
    border-radius: 100%;
    transform: scale(1, 1) translate(-50%, -50%);
    transform-origin: 50% 50%;
}

.btn-modern:active::after {
    animation: ripple 0.6s ease-out;
}

/* Efeito hover aprimorado */
.btn-modern:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2), 0 4px 8px rgba(0, 0, 0, 0.1);
}

.btn-modern:active {
    transform: translateY(1px) scale(0.98);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

/* Botão de edição com gradiente mais suave e ícone melhorado */
.btn-edit {
    background: linear-gradient(135deg, #4481eb, #04befe);
    color: white;
    min-width: 36px;
    min-height: 36px;
}

.btn-edit i {
    font-size: 0.9rem;
    transition: transform 0.3s ease;
}

.btn-edit:hover i {
    transform: rotate(15deg);
}

/* Botão de exclusão com gradiente mais suave e ícone melhorado */
.btn-delete {
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
    color: white;
    min-width: 36px;
    min-height: 36px;
}

.btn-delete i {
    font-size: 0.9rem;
    transition: transform 0.3s ease;
}

.btn-delete:hover i {
    transform: rotate(-15deg);
}

/* Botão de visualização com gradiente azul */
.btn-view {
    background: linear-gradient(135deg, #3a7bd5, #00d2ff);
    color: white;
    min-width: 36px;
    min-height: 36px;
}

.btn-view i {
    font-size: 0.9rem;
    transition: transform 0.3s ease;
}

.btn-view:hover i {
    transform: scale(1.15);
}

/* Botão de download com gradiente verde */
.btn-download {
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: white;
    min-width: 36px;
    min-height: 36px;
}

.btn-download i {
    font-size: 0.9rem;
    transition: transform 0.3s ease;
}

.btn-download:hover i {
    transform: translateY(2px);
}

/* Animação de pulso refinada */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.08); }
    100% { transform: scale(1.05); }
}

/* Animação de onda ao clicar */
@keyframes ripple {
    0% {
        transform: scale(0, 0);
        opacity: 0.5;
    }
    20% {
        transform: scale(25, 25);
        opacity: 0.5;
    }
    100% {
        opacity: 0;
        transform: scale(40, 40);
    }
}

/* Botão principal (submit) aprimorado */
.btn-primary {
    background: linear-gradient(135deg, #6a11cb, #2575fc);
    color: white;
    padding: 10px 16px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.16), 0 3px 6px rgba(0, 0, 0, 0.23);
    position: relative;
    overflow: hidden;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2), 0 4px 8px rgba(0, 0, 0, 0.1);
}

.btn-primary:active {
    transform: translateY(1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

.btn-primary i {
    margin-right: 8px;
    transition: transform 0.3s ease;
}

.btn-primary:hover i {
    transform: translateX(-3px);
}

/* Botão secundário (cancelar) aprimorado */
.btn-secondary {
    background: linear-gradient(135deg, #8e9eab, #eef2f3);
    color: #333;
    padding: 10px 16px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.16), 0 3px 6px rgba(0, 0, 0, 0.23);
    margin-left: 10px;
    position: relative;
    overflow: hidden;
}

.btn-secondary:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2), 0 4px 8px rgba(0, 0, 0, 0.1);
}

.btn-secondary:active {
    transform: translateY(1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

.btn-secondary i {
    margin-right: 8px;
    transition: transform 0.3s ease;
}

.btn-secondary:hover i {
    transform: translateX(-3px);
}

/* Botão de perigo (danger) aprimorado */
.btn-danger {
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
    color: white;
    padding: 10px 16px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.16), 0 3px 6px rgba(0, 0, 0, 0.23);
    position: relative;
    overflow: hidden;
}

.btn-danger:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2), 0 4px 8px rgba(0, 0, 0, 0.1);
}

.btn-danger:active {
    transform: translateY(1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

/* Ajustes para responsividade */
@media (max-width: 768px) {
    .btn-modern {
        padding: 7px 10px;
        min-width: 32px;
        min-height: 32px;
    }
    
    .btn-modern i {
        font-size: 0.85rem;
    }
    
    .btn-primary, .btn-secondary, .btn-danger {
        padding: 8px 14px;
        font-size: 0.95rem;
    }
}

@media (max-width: 576px) {
    .btn-modern {
        padding: 6px 8px;
        min-width: 30px;
        min-height: 30px;
        margin: 0 2px;
    }
    
    .btn-modern i {
        font-size: 0.8rem;
    }
    
    .btn-primary, .btn-secondary, .btn-danger {
        padding: 7px 12px;
        font-size: 0.9rem;
    }
    
    .d-flex.gap-2 {
        gap: 0.3rem !important;
    }
}

/* Acessibilidade - foco visível */
.btn-modern:focus, .btn-primary:focus, .btn-secondary:focus, .btn-danger:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5);
}

/* Tooltip personalizado para melhorar a experiência do usuário */
.btn-modern[title]:hover::before {
    content: attr(title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 5px 10px;
    background: rgba(0, 0, 0, 0.8);
    color: white;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    pointer-events: none;
    opacity: 0;
    animation: fadeIn 0.3s ease forwards;
    z-index: 10;
}

@keyframes fadeIn {
    to {
        opacity: 1;
        transform: translate(-50%, -5px);
    }
}
</style>

<div class="container mt-4">
    <h3>Logs de Atividades</h3>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-list text-primary me-2"></i> Registro de Termos Assinados</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end mb-4">
                <div class="col-md-3">
                    <label for="filial_id" class="form-label">Filtrar por Unidade:</label>
                    <select name="filial_id" id="filial_id" class="form-select">
                        <option value="">Todas as Unidades</option>
                        <?php foreach ($filiais as $filial): ?>
                            <option value="<?= $filial['id']; ?>" <?= $filial_id == $filial['id'] ? 'selected' : ''; ?>>
                                <?= html_escape($filial['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="colaborador" class="form-label">Colaborador:</label>
                    <input type="text" class="form-control" name="colaborador" id="colaborador" value="<?= html_escape($colaborador ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label for="data" class="form-label">Data:</label>
                    <input type="date" class="form-control" name="data" id="data" value="<?= html_escape($data ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i> Filtrar</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle" id="logs-table">
                    <thead class="table-light">
                        <tr>
                            <th>Colaborador</th>
                            <th>Unidade</th>
                            <th>Data de Liberação</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="4" class="text-center">Nenhum log encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                $doc_path = $log['doc_path'] ?? '';
                                $file_path = UPLOADS_DIR . $doc_path;
                                $file_exists = $doc_path && file_exists($file_path);
                                $file_extension = $doc_path ? strtolower(pathinfo($doc_path, PATHINFO_EXTENSION)) : '';
                                error_log("Log ID: {$log['id']}, doc_path: '$doc_path', file_path: '$file_path', exists: " . ($file_exists ? 'true' : 'false') . ", extension: '$file_extension'");
                                ?>
                                <tr id="log-row-<?= $log['id']; ?>">
                                    <td><?= html_escape($log['nome_colaborador']); ?></td>
                                    <td><?= html_escape($log['filial_nome']); ?></td>
                                    <td><?= date('d/m/Y H:i:s', strtotime($log['data_liberacao'])); ?></td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <!-- Botão para visualizar o log -->
                                            <button type="button" 
                                                    class="btn-modern btn-view" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewLogModal" 
                                                    data-log-path="<?= $doc_path; ?>"
                                                    title="Visualizar">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <!-- Botão para baixar o log -->
                                            <a href="download_doc.php?file=<?= urlencode($doc_path); ?>" 
                                               class="btn-modern btn-download" 
                                               <?= !$file_exists ? 'aria-disabled="true" disabled' : ''; ?>
                                               title="Baixar">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            
                                            <!-- Botão para excluir o log -->
                                            <button type="button" 
                                                    class="btn-modern btn-delete" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#confirmDeleteLogModal" 
                                                    data-log-id="<?= $log['id']; ?>"
                                                    title="Excluir">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para visualização de logs -->
    <div class="modal fade" id="viewLogModal" tabindex="-1" aria-labelledby="viewLogModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewLogModalLabel">Visualizar Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="logContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="mt-2">Carregando conteúdo do log...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <a href="#" class="btn btn-primary" id="downloadLogBtn">Baixar</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmação para exclusão de log -->
    <div class="modal fade" id="confirmDeleteLogModal" tabindex="-1" aria-labelledby="confirmDeleteLogModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmDeleteLogModalLabel">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Tem certeza que deseja excluir este registro de log? Esta ação não pode ser desfeita.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" action="delete_log.php" id="deleteLogForm">
                        <input type="hidden" name="log_id" id="deleteLogId" value="">
                        <button type="submit" class="btn btn-danger">Confirmar Exclusão</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar todos os dropdowns do Bootstrap manualmente
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    dropdownElementList.map(function(dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl);
    });

    // Configurar botões de exclusão de log
    const deleteLogBtns = document.querySelectorAll('.btn-delete');
    deleteLogBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const logId = this.getAttribute('data-log-id');
            document.getElementById('deleteLogId').value = logId;
        });
    });
    
    // Configurar botões de visualização de log
    const viewLogBtns = document.querySelectorAll('.btn-view');
    console.log('Botões view-log-btn encontrados:', viewLogBtns.length); // Log para depuração
    viewLogBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            console.log('Botão de visualização clicado:', this.getAttribute('data-log-path')); // Log para depuração
            const logPath = this.getAttribute('data-log-path');
            const downloadBtn = document.getElementById('downloadLogBtn');
            const logContent = document.getElementById('logContent');
            
            // Atualizar o link de download
            downloadBtn.href = 'download_doc.php?file=' + encodeURIComponent(logPath);
            
            // Verificar a extensão do arquivo
            const extension = logPath ? logPath.split('.').pop().toLowerCase() : '';
            console.log('Extensão do arquivo:', extension); // Log para depuração
            
            // Limpar o conteúdo anterior
            logContent.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div><p class="mt-2">Carregando conteúdo do log...</p></div>';
            
            if (!logPath) {
                logContent.innerHTML = '<div class="alert alert-danger">Caminho do arquivo inválido.</div>';
                return;
            }
            
            if (extension === 'docx') {
                // Para arquivos Word, mostrar mensagem informativa
                logContent.innerHTML = '<div class="alert alert-info">Este é um arquivo do Microsoft Word. Use o botão "Baixar" para visualizar o conteúdo.</div>';
            } else if (extension === 'pdf' || ['png', 'jpg', 'jpeg', 'gif'].includes(extension)) {
                // Para PDFs e imagens, usar fetch para obter o caminho correto
                fetch('view_log.php?file=' + encodeURIComponent(logPath))
                    .then(response => {
                        console.log('Status da resposta:', response.status, response.statusText); // Log para depuração
                        if (!response.ok) throw new Error('Erro na requisição: ' + response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Resposta do view_log.php:', data); // Log para depuração
                        if (data.success && data.path) {
                            console.log('Carregando arquivo com src:', data.path); // Log para depuração
                            if (extension === 'pdf') {
                                logContent.innerHTML = '<iframe src="' + data.path + '" style="width: 100%; height: 500px; border: none;"></iframe>';
                            } else {
                                logContent.innerHTML = '<img src="' + data.path + '" class="img-fluid" alt="Log Image">';
                            }
                        } else {
                            logContent.innerHTML = '<div class="alert alert-danger">' + (data.error || 'Erro ao carregar o arquivo') + '</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao carregar arquivo:', error); // Log para depuração
                        logContent.innerHTML = '<div class="alert alert-danger">Erro ao carregar o arquivo: ' + error.message + '</div>';
                    });
            } else if (['txt', 'log', 'csv'].includes(extension)) {
                // Para arquivos de texto, carregar o conteúdo
                fetch('view_log.php?file=' + encodeURIComponent(logPath))
                    .then(response => {
                        console.log('Status da resposta:', response.status, response.statusText); // Log para depuração
                        if (!response.ok) throw new Error('Erro na requisição: ' + response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Resposta do view_log.php:', data); // Log para depuração
                        if (data.success && data.content) {
                            logContent.innerHTML = '<pre class="log-content">' + escapeHtml(data.content) + '</pre>';
                        } else {
                            logContent.innerHTML = '<div class="alert alert-danger">' + (data.error || 'Erro ao carregar o arquivo') + '</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao carregar texto:', error); // Log para depuração
                        logContent.innerHTML = '<div class="alert alert-danger">Erro ao carregar o arquivo: ' + error.message + '</div>';
                    });
            } else {
                // Para outros tipos não suportados
                logContent.innerHTML = '<div class="alert alert-warning">Este tipo de arquivo não pode ser visualizado diretamente. Use o botão "Baixar" para acessar o conteúdo.</div>';
            }
        });
    });
});

// Função para escapar HTML e prevenir XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
