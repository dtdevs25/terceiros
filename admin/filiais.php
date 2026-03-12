<?php
ob_start(); // Iniciar buffer de saída

// Incluir arquivos necessários
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/functions.php';

// Requer login e permissão de admin
require_admin();

$page_title = 'Gerenciar Filiais';
require_once __DIR__ . '/../includes/header.php';

global $pdo;

$filiais = [];
$edit_filial = null;
$error_message = null;
$success_message = null;

// Garante que a sessão está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Função para reindexar IDs das filiais (sem ALTER TABLE)
function reindexFiliais($pdo) {
    try {
        error_log("Iniciando reindexFiliais");
        $stmt = $pdo->query("SELECT id FROM filiais ORDER BY id");
        $current_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        error_log("IDs atuais: " . json_encode($current_ids));

        if (empty($current_ids)) {
            return 1; // Retorna próximo AUTO_INCREMENT
        }

        $pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_id_map");
        $pdo->exec("CREATE TEMPORARY TABLE temp_id_map (old_id INT, new_id INT)");
        error_log("Tabela temporária temp_id_map criada");

        $new_id = 1;
        foreach ($current_ids as $old_id) {
            $pdo->prepare("INSERT INTO temp_id_map (old_id, new_id) VALUES (?, ?)")->execute([$old_id, $new_id]);
            error_log("Mapeamento: old_id=$old_id, new_id=$new_id");
            $new_id++;
        }

        // Atualizar IDs em filiais
        $stmt = $pdo->query("SELECT old_id, new_id FROM temp_id_map");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare("UPDATE filiais SET id = ? WHERE id = ?")
                ->execute([$row['new_id'], $row['old_id']]);
            error_log("Atualizando filial: id de {$row['old_id']} para {$row['new_id']}");
        }

        // Atualizar referências em terceiros
        $stmt = $pdo->query("SELECT old_id, new_id FROM temp_id_map");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare("UPDATE terceiros SET filial_id = ? WHERE filial_id = ?")
                ->execute([$row['new_id'], $row['old_id']]);
            error_log("Atualizando terceiros: filial_id de {$row['old_id']} para {$row['new_id']}");
        }

        // Atualizar filiais_permitidas em usuarios
        $stmt = $pdo->query("SELECT old_id, new_id FROM temp_id_map");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare("UPDATE usuarios SET filiais_permitidas = REPLACE(filiais_permitidas, ?, ?) WHERE FIND_IN_SET(?, filiais_permitidas)")
                ->execute([$row['old_id'], $row['new_id'], $row['old_id']]);
            error_log("Atualizando usuarios.filiais_permitidas: substituindo {$row['old_id']} por {$row['new_id']}");
        }

        $pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_id_map");
        error_log("Tabela temporária removida");
        return $new_id; // Retorna próximo AUTO_INCREMENT
    } catch (PDOException $e) {
        error_log("Erro ao reindexar filiais: " . $e->getMessage());
        throw $e; // Propagar a exceção para a transação principal
    }
}

// Processar ações (Adicionar, Editar, Deletar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $filial_id = filter_input(INPUT_POST, 'filial_id', FILTER_VALIDATE_INT);
    $nome = trim($_POST['nome'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '') ?: null;

    error_log("Ação recebida: $action, Filial ID: " . ($filial_id ?: 'null'));

    try {
        $pdo->beginTransaction();
        error_log("Transação iniciada");

        $next_id = null; // Inicializa next_id

        if ($action === 'add' && !empty($nome)) {
            $stmt = $pdo->prepare("INSERT INTO filiais (nome, endereco) VALUES (?, ?)");
            $stmt->execute([$nome, $endereco]);
            error_log("Filial adicionada: nome=$nome, endereco=" . ($endereco ?: 'null'));
            $next_id = reindexFiliais($pdo); // Chama reindexFiliais dentro da transação
            $success_message = 'Filial adicionada com sucesso!';
        } elseif ($action === 'update' && $filial_id && !empty($nome)) {
            $stmt = $pdo->prepare("UPDATE filiais SET nome = ?, endereco = ? WHERE id = ?");
            $stmt->execute([$nome, $endereco, $filial_id]);
            error_log("Filial atualizada: id=$filial_id, nome=$nome, endereco=" . ($endereco ?: 'null'));
            $success_message = 'Filial atualizada com sucesso!';
        } elseif ($action === 'delete' && $filial_id) {
            // Verificar se a filial está associada a terceiros
            $stmt_check_terceiro = $pdo->prepare("SELECT COUNT(*) FROM terceiros WHERE filial_id = ?");
            $stmt_check_terceiro->execute([$filial_id]);
            if ($stmt_check_terceiro->fetchColumn() > 0) {
                $error_message = 'Não é possível excluir a filial, pois ela está associada a terceiros.';
                error_log("Erro: filial $filial_id associada a terceiros");
            } else {
                // Remover filial_id de usuarios.filiais_permitidas
                $stmt = $pdo->prepare("SELECT id, filiais_permitidas FROM usuarios WHERE FIND_IN_SET(?, filiais_permitidas)");
                $stmt->execute([$filial_id]);
                $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Usuários com filial $filial_id: " . count($usuarios));

                foreach ($usuarios as $usuario) {
                    $filiais_array = explode(',', $usuario['filiais_permitidas']);
                    $filiais_array = array_filter($filiais_array, fn($id) => $id != $filial_id);
                    $new_filiais = !empty($filiais_array) ? implode(',', $filiais_array) : null;
                    $stmt_update = $pdo->prepare("UPDATE usuarios SET filiais_permitidas = ? WHERE id = ?");
                    $stmt_update->execute([$new_filiais, $usuario['id']]);
                    error_log("Atualizando usuario ID {$usuario['id']}: filiais_permitidas de {$usuario['filiais_permitidas']} para " . ($new_filiais ?: 'null'));
                }

                // Excluir a filial
                $stmt = $pdo->prepare("DELETE FROM filiais WHERE id = ?");
                $stmt->execute([$filial_id]);
                error_log("Filial $filial_id excluída");
                $next_id = reindexFiliais($pdo); // Chama reindexFiliais dentro da transação
                $success_message = 'Filial excluída com sucesso!';
            }
        } else {
            $error_message = 'Ação inválida ou dados incompletos.';
            error_log("Erro: ação inválida ou dados incompletos");
        }

        // Se houver erro, reverter a transação
        if ($error_message) {
            error_log("Erro na ação $action: $error_message");
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
                error_log("Transação revertida devido a erro");
            }
            $_SESSION['error_message'] = $error_message;
            header('Location: filiais.php');
            exit;
        }

        // Verificar se a transação ainda está ativa antes de commit
        if ($pdo->inTransaction()) {
            error_log("Transação ativa, tentando commit");
            $pdo->commit();
            error_log("Transação commitada com sucesso");
        } else {
            error_log("Nenhuma transação ativa para commit");
            throw new PDOException("Transação não está ativa antes do commit");
        }

        // Ajustar AUTO_INCREMENT após o commit, fora da transação
        if ($next_id && is_int($next_id) && $next_id > 0) {
            try {
                $pdo->exec("ALTER TABLE filiais AUTO_INCREMENT = $next_id");
                error_log("AUTO_INCREMENT definido para $next_id");
            } catch (PDOException $e) {
                error_log("Erro ao definir AUTO_INCREMENT: " . $e->getMessage());
                $_SESSION['error_message'] = 'Erro ao reindexar IDs: ' . $e->getMessage();
                header('Location: filiais.php');
                exit;
            }
        }

        $_SESSION['success_message'] = $success_message;
        header('Location: filiais.php');
        exit;

    } catch (PDOException $e) {
        error_log("Exceção capturada: " . $e->getMessage());
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("Transação revertida no catch");
        }
        $_SESSION['error_message'] = 'Erro ao processar a solicitação: ' . $e->getMessage();
        error_log("Erro Filiais PDO: " . $e->getMessage());
        header('Location: filiais.php');
        exit;
    }
}

// Buscar filial para edição
if (isset($_GET['edit_id'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id) {
        try {
            $stmt = $pdo->prepare("SELECT id, nome, endereco FROM filiais WHERE id = ?");
            $stmt->execute([$edit_id]);
            $edit_filial = $stmt->fetch();
        } catch (PDOException $e) {
            $error_message = "Erro ao buscar filial para edição: " . $e->getMessage();
            error_log("Erro Buscar Filial Edit PDO: " . $e->getMessage());
        }
    }
}

// Buscar todas as filiais para listar
try {
    $stmt = $pdo->prepare("SELECT id, nome, endereco FROM filiais ORDER BY id ASC");
    $stmt->execute();
    $filiais = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erro ao listar filiais: " . $e->getMessage();
    error_log("Erro Listar Filiais PDO: " . $e->getMessage());
    $filiais = [];
}

// Recuperar mensagens da sessão
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
</style>

<div class="row mb-4">
    <div class="col-md-6">
        <h3><?php echo $edit_filial ? 'Editar Filial' : 'Adicionar Nova Filial'; ?></h3>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo html_escape($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo html_escape($success_message); ?></div>
        <?php endif; ?>

        <form id="filial-form" method="POST" action="filiais.php">
            <input type="hidden" name="action" value="<?php echo $edit_filial ? 'update' : 'add'; ?>">
            <?php if ($edit_filial): ?>
                <input type="hidden" name="filial_id" value="<?php echo $edit_filial['id']; ?>">
            <?php endif; ?>
            <div class="mb-3">
                <label for="nome" class="form-label">Nome da Filial</label>
                <input type="text" class="form-control" id="nome" name="nome" value="<?php echo html_escape($edit_filial['nome'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="endereco" class="form-label">Endereço (Opcional)</label>
                <input type="text" class="form-control" id="endereco" name="endereco" value="<?php echo html_escape($edit_filial['endereco'] ?? ''); ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i><?php echo $edit_filial ? "Atualizar" : "Adicionar"; ?></button>
            <?php if ($edit_filial): ?>
                <a href="filiais.php" class="btn btn-secondary"><i class="fa-solid fa-cancel me-1"></i>Cancelar Edição</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<hr>

<h3>Filiais Cadastradas</h3>

<?php if (empty($filiais)): ?>
    <p>Nenhuma filial cadastrada ainda.</p>
<?php else: ?>
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Endereço</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filiais as $filial): ?>
            <tr>
                <td><?php echo html_escape($filial['id']); ?></td>
                <td><?php echo html_escape($filial['nome']); ?></td>
                <td>
                    <?php
                    error_log("Filial ID {$filial['id']} endereco: " . var_export($filial['endereco'], true));
                    echo html_escape($filial['endereco'] !== null && $filial['endereco'] !== '' ? $filial['endereco'] : 'N/A');
                    ?>
                </td>
                <td>
                    <div class="d-flex gap-2">
                        <a href="filiais.php?edit_id=<?php echo $filial['id']; ?>" class="btn-modern btn-edit" title="Editar">
                            <i class="fa-solid fa-pen-nib fs-6"></i>
                        </a>
                        <button type="button" class="btn-modern btn-delete" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-filial-id="<?php echo $filial['id']; ?>" title="Excluir">
                            <i class="fa-solid fa-trash-arrow-up fs-6"></i>
                        </button>
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
                Tem certeza que deseja excluir esta filial? Isso removerá a filial das permissões de usuários associados.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form id="deleteForm" method="POST" action="filiais.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="filial_id" id="deleteFilialId" value="">
                    <button type="submit" class="btn btn-danger">Excluir</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Função para confirmação de exclusão
document.addEventListener('DOMContentLoaded', function() {
    // Configurar o modal de confirmação de exclusão
    const confirmDeleteModal = document.getElementById('confirmDeleteModal');
    if (confirmDeleteModal) {
        confirmDeleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const filialId = button.getAttribute('data-filial-id');
            document.getElementById('deleteFilialId').value = filialId;
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
ob_end_flush();
?>
