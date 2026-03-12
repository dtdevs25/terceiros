<?php
ob_start(); // Iniciar buffer de saída para evitar problemas com cabeçalhos

// Corrigido os caminhos para incluir arquivos da pasta ../includes/
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/functions.php';

// Requer login e permissão de admin
require_admin();

$page_title = 'Gerenciar Usuários';
require_once __DIR__ . '/../includes/header.php';

// $pdo já deve estar disponível globalmente via connection.php
global $pdo;

$usuarios = [];
$edit_usuario = null;
$error_message = null;
$success_message = null;
$todas_filiais = get_todas_filiais(); // Busca todas as filiais para o formulário

// Garante que a sessão está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Função para reindexar IDs dos usuários
function reindexUsuarios($pdo) {
    try {
        // Iniciar transação
        $pdo->beginTransaction();

        // Obter IDs atuais
        $stmt = $pdo->query("SELECT id FROM usuarios ORDER BY id");
        $current_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($current_ids)) {
            // Se não houver usuários, resetar AUTO_INCREMENT para 1
            $pdo->exec("ALTER TABLE usuarios AUTO_INCREMENT = 1");
            $pdo->commit();
            return;
        }

        // Criar tabela temporária para mapear IDs antigos para novos
        $pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_id_map");
        $pdo->exec("CREATE TEMPORARY TABLE temp_id_map (old_id INT, new_id INT)");

        // Mapear IDs antigos para novos (1, 2, 3, ...)
        $new_id = 1;
        foreach ($current_ids as $old_id) {
            $pdo->prepare("INSERT INTO temp_id_map (old_id, new_id) VALUES (?, ?)")->execute([$old_id, $new_id]);
            $new_id++;
        }

        // Atualizar IDs na tabela usuarios
        $stmt = $pdo->query("SELECT old_id, new_id FROM temp_id_map");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare("UPDATE usuarios SET id = ? WHERE id = ?")
                ->execute([$row['new_id'], $row['old_id']]);
        }

        // Ajustar o AUTO_INCREMENT para o próximo ID
        $pdo->exec("ALTER TABLE usuarios AUTO_INCREMENT = $new_id");

        // Remover tabela temporária
        $pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_id_map");

        // Confirmar transação
        $pdo->commit();
    } catch (PDOException $e) {
        // Reverter transação se houver erro
        try {
            $pdo->rollBack();
        } catch (PDOException $rollback_e) {
            error_log("Erro ao reverter transação de reindexação: " . $rollback_e->getMessage());
        }
        error_log("Erro ao reindexar usuários: " . $e->getMessage());
    }
}

// Processar ações (Adicionar, Editar, Deletar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $usuario_id = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT);
    $nome = trim($_POST['nome'] ?? '');
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $senha = $_POST['senha'] ?? null;
    $tipo = $_POST['tipo'] ?? 'comum';
    $filiais_permitidas_array = isset($_POST['filiais_permitidas']) && is_array($_POST['filiais_permitidas']) ? $_POST['filiais_permitidas'] : [];
    // Garante que os IDs são inteiros
    $filiais_permitidas_array = array_filter($filiais_permitidas_array, 'is_numeric');
    $filiais_permitidas_str = ($tipo === 'comum' && !empty($filiais_permitidas_array)) ? implode(',', $filiais_permitidas_array) : null;

    // Log para depuração
    error_log("Ação recebida: $action, Usuario ID: $usuario_id");

    try {
        if ($action === 'add') {
            if (empty($nome) || empty($email) || empty($senha) || !in_array($tipo, ['admin', 'comum'])) {
                $error_message = 'Preencha todos os campos obrigatórios (Nome, E-mail, Senha, Tipo).';
            } else {
                $pdo->beginTransaction();
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo, filiais_permitidas) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nome, $email, $senha_hash, $tipo, $filiais_permitidas_str]);
                $pdo->commit();

                // Reindexar IDs após adição
                reindexUsuarios($pdo);

                $_SESSION['success_message'] = 'Usuário adicionado com sucesso!';
            }
        } elseif ($action === 'update' && $usuario_id) {
            if (empty($nome) || empty($email) || !in_array($tipo, ['admin', 'comum'])) {
                $error_message = 'Preencha todos os campos obrigatórios (Nome, E-mail, Tipo).';
            } else {
                $pdo->beginTransaction();
                if (!empty($senha)) {
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, senha = ?, tipo = ?, filiais_permitidas = ? WHERE id = ?");
                    $stmt->execute([$nome, $email, $senha_hash, $tipo, $filiais_permitidas_str, $usuario_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, tipo = ?, filiais_permitidas = ? WHERE id = ?");
                    $stmt->execute([$nome, $email, $tipo, $filiais_permitidas_str, $usuario_id]);
                }
                $pdo->commit();
                $_SESSION['success_message'] = 'Usuário atualizado com sucesso!';
            }
        } elseif ($action === 'delete' && $usuario_id) {
            if ($usuario_id == $_SESSION['user_id']) {
                $_SESSION['error_message'] = 'Você não pode excluir sua própria conta.';
            } else {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$usuario_id]);
                $pdo->commit();

                // Reindexar IDs após exclusão
                reindexUsuarios($pdo);

                $_SESSION['success_message'] = 'Usuário excluído com sucesso!';
            }
        }

        // Redirecionar após a ação (se não houve erro de validação ou BD)
        if (!$error_message) {
            error_log("Redirecionando para usuarios.php após ação: $action");
            header('Location: usuarios.php');
            exit;
        }

    } catch (PDOException $e) {
        try {
            $pdo->rollBack();
        } catch (PDOException $rollback_e) {
            error_log("Erro ao reverter transação principal: " . $rollback_e->getMessage());
        }
        if ($e->getCode() == 23000) { // Chave única (email duplicado)
            $error_message = 'Erro: Este e-mail já está cadastrado.';
        } else {
            $error_message = 'Erro ao processar a solicitação: ' . $e->getMessage();
            error_log("Erro Usuarios PDO: " . $e->getMessage());
        }
    }
}

// Buscar usuário para edição
if (isset($_GET['edit_id'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id) {
        try {
            $stmt = $pdo->prepare("SELECT id, nome, email, tipo, filiais_permitidas FROM usuarios WHERE id = ?");
            $stmt->execute([$edit_id]);
            $edit_usuario = $stmt->fetch();
            if ($edit_usuario) {
                $edit_usuario['filiais_array'] = !empty($edit_usuario['filiais_permitidas']) ? explode(',', $edit_usuario['filiais_permitidas']) : [];
            }
        } catch (PDOException $e) {
            $error_message = "Erro ao buscar usuário para edição: " . $e->getMessage();
            error_log("Erro Buscar Usuario Edit PDO: " . $e->getMessage());
        }
    }
}

// Buscar todos os usuários para listar
try {
    $stmt = $pdo->query("SELECT id, nome, email, tipo FROM usuarios ORDER BY id ASC");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erro ao listar usuários: " . $e->getMessage();
    error_log("Erro Listar Usuarios PDO: " . $e->getMessage());
    $usuarios = [];
}

// Recuperar mensagens da sessão e limpar
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
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
    
    .btn-primary, .btn-secondary {
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
    
    .btn-primary, .btn-secondary {
        padding: 7px 12px;
        font-size: 0.9rem;
    }
    
    .d-flex.gap-2 {
        gap: 0.3rem !important;
    }
}

/* Acessibilidade - foco visível */
.btn-modern:focus, .btn-primary:focus, .btn-secondary:focus {
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

/* Estilo para o botão de visualizar senha - CORRIGIDO */
.password-field-container {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: #6c757d;
    transition: color 0.3s ease;
    z-index: 5;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
}

.password-toggle:hover {
    color: #495057;
}

/* Ajuste para o campo de senha ter padding à direita para o ícone */
.password-field-container input[type="password"],
.password-field-container input[type="text"] {
    padding-right: 40px;
}
</style>

<div class="row mb-4">
    <div class="col-md-8">
        <h3><?php echo $edit_usuario ? 'Editar Usuário' : 'Adicionar Novo Usuário'; ?></h3>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo html_escape($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo html_escape($success_message); ?></div>
        <?php endif; ?>

        <form id="usuario-form" method="POST" action="usuarios.php">
            <input type="hidden" name="action" value="<?php echo $edit_usuario ? 'update' : 'add'; ?>">
            <?php if ($edit_usuario): ?>
                <input type="hidden" name="usuario_id" value="<?php echo $edit_usuario['id']; ?>">
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nome" class="form-label">Nome Completo</label>
                    <input type="text" class="form-control" id="nome" name="nome" value="<?php echo isset($_GET['edit_id']) && $edit_usuario ? html_escape($edit_usuario['nome']) : ''; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_GET['edit_id']) && $edit_usuario ? html_escape($edit_usuario['email']) : ''; ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3 password-field-container">
                    <label for="senha" class="form-label">Senha <?php echo $edit_usuario ? '(Deixe em branco para não alterar)' : ''; ?></label>
                    <input type="password" class="form-control" id="senha" name="senha" <?php echo !$edit_usuario ? 'required' : ''; ?>>
                    <button type="button" class="password-toggle" id="toggleSenha">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="tipo" class="form-label">Tipo de Usuário</label>
                    <select class="form-select" id="tipo" name="tipo" required>
                        <option value="">Selecione...</option>
                        <option value="comum" <?php echo (isset($_GET['edit_id']) && isset($edit_usuario['tipo']) && $edit_usuario['tipo'] == 'comum') ? 'selected' : ''; ?>>Comum</option>
                        <option value="admin" <?php echo (isset($_GET['edit_id']) && isset($edit_usuario['tipo']) && $edit_usuario['tipo'] == 'admin') ? 'selected' : ''; ?>>Administrador</option>
                    </select>
                </div>
            </div>

            <div class="mb-3" id="filiais-permitidas-group" style="<?php echo (isset($edit_usuario['tipo']) && $edit_usuario['tipo'] == 'admin') ? 'display: none;' : ''; ?>">
                <label class="form-label">Filiais Permitidas (para usuário comum)</label>
                <?php if (empty($todas_filiais)): ?>
                    <p class="text-muted">Nenhuma filial cadastrada para selecionar.</p>
                <?php else: ?>
                    <?php foreach ($todas_filiais as $filial): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="filiais_permitidas[]" value="<?php echo $filial['id']; ?>" id="filial_<?php echo $filial['id']; ?>"
                                <?php echo (isset($_GET['edit_id']) && isset($edit_usuario['filiais_array']) && in_array($filial['id'], $edit_usuario['filiais_array'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="filial_<?php echo $filial['id']; ?>">
                                <?php echo html_escape($filial['nome']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary"><?php echo $edit_usuario ? 'Atualizar Usuário' : 'Adicionar Usuário'; ?></button>
            <?php if ($edit_usuario): ?>
                <a href="usuarios.php" class="btn btn-secondary">Cancelar Edição</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<hr>

<h3>Usuários Cadastrados</h3>

<?php if (empty($usuarios)): ?>
    <p>Nenhum usuário cadastrado ainda.</p>
<?php else: ?>
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Tipo</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $usuario): ?>
            <tr>
                <td><?php echo html_escape($usuario['id']); ?></td>
                <td><?php echo html_escape($usuario['nome']); ?></td>
                <td><?php echo html_escape($usuario['email']); ?></td>
                <td><?php echo ucfirst(html_escape($usuario['tipo'])); ?></td>
                <td>
                    <div class="d-flex gap-2">
                        <a href="usuarios.php?edit_id=<?php echo $usuario['id']; ?>" class="btn-modern btn-edit" title="Editar">
                            <i class="fa-solid fa-pen-nib fs-6"></i>
                        </a>
                        <?php if ($usuario['id'] != $_SESSION['user_id']): ?>
                        <button type="button" class="btn-modern btn-delete" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-usuario-id="<?php echo $usuario['id']; ?>" title="Excluir">
                            <i class="fa-solid fa-trash-arrow-up fs-6"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- Modal de Confirmação de Exclusão -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteLabel">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                Tem certeza que deseja excluir este usuário? Esta ação não pode ser desfeita.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form id="deleteForm" method="POST" action="usuarios.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="usuario_id" id="deleteUsuarioId" value="">
                    <button type="submit" class="btn btn-danger">Excluir</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configurar o modal de confirmação de exclusão
    const confirmDeleteModal = document.getElementById('confirmDeleteModal');
    if (confirmDeleteModal) {
        confirmDeleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const usuarioId = button.getAttribute('data-usuario-id');
            document.getElementById('deleteUsuarioId').value = usuarioId;
        });
    }
    
    // Configurar o botão de visualizar senha
    const toggleSenha = document.getElementById('toggleSenha');
    const senhaInput = document.getElementById('senha');
    
    if (toggleSenha && senhaInput) {
        toggleSenha.addEventListener('click', function() {
            const type = senhaInput.getAttribute('type') === 'password' ? 'text' : 'password';
            senhaInput.setAttribute('type', type);
            
            // Alternar ícone
            const icon = toggleSenha.querySelector('i');
            if (type === 'text') {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    }
    
    // Mostra/esconde o campo de filiais permitidas baseado no tipo de usuário
    document.getElementById('tipo').addEventListener('change', function() {
        var filiaisGroup = document.getElementById('filiais-permitidas-group');
        if (this.value === 'comum') {
            filiaisGroup.style.display = 'block';
        } else {
            filiaisGroup.style.display = 'none';
        }
    });

    // Garante que o estado inicial está correto ao carregar a página
    window.addEventListener('DOMContentLoaded', (event) => {
        var tipoSelect = document.getElementById('tipo');
        var filiaisGroup = document.getElementById('filiais-permitidas-group');
        if (tipoSelect.value === 'admin') {
            filiaisGroup.style.display = 'none';
        } else if (tipoSelect.value === 'comum') {
            filiaisGroup.style.display = 'block';
        } else {
            filiaisGroup.style.display = 'none';
        }
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
ob_end_flush(); // Liberar buffer de saída
?>
