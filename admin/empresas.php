<?php
// Habilitar exibição de erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Corrigido os caminhos para incluir arquivos da pasta ../includes/
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/functions.php';

// Requer login e permissão de admin
require_admin();

$page_title = 'Gerenciar Empresas';
require_once __DIR__ . '/../includes/header.php';

// $pdo já deve estar disponível globalmente via connection.php
global $pdo;

$empresas = [];
$edit_empresa = null;
$error_message = null;
$success_message = null;

// Garante que a sessão está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Função para reindexar IDs das empresas
function reindexEmpresas($pdo) {
    try {
        // Iniciar transação específica para reindexação
        $pdo->beginTransaction();

        // Obter IDs atuais
        $stmt = $pdo->query("SELECT id FROM empresas ORDER BY id");
        $current_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($current_ids)) {
            $pdo->commit();
            return; // Nenhuma empresa para reindexar
        }

        // Criar tabela temporária
        $pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_id_map");
        $pdo->exec("CREATE TEMPORARY TABLE temp_id_map (old_id INT, new_id INT)");

        // Mapear IDs antigos para novos
        $new_id = 1;
        foreach ($current_ids as $old_id) {
            $pdo->prepare("INSERT INTO temp_id_map (old_id, new_id) VALUES (?, ?)")->execute([$old_id, $new_id]);
            $new_id++;
        }

        // Atualizar referências na tabela terceiros
        $stmt = $pdo->query("SELECT old_id, new_id FROM temp_id_map");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare("UPDATE terceiros SET empresa_id = ? WHERE empresa_id = ?")
                ->execute([$row['new_id'], $row['old_id']]);
        }

        // Atualizar IDs na tabela empresas
        $stmt = $pdo->query("SELECT old_id, new_id FROM temp_id_map");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare("UPDATE empresas SET id = ? WHERE id = ?")
                ->execute([$row['new_id'], $row['old_id']]);
        }

        // Ajustar o AUTO_INCREMENT
        $pdo->exec("ALTER TABLE empresas AUTO_INCREMENT = $new_id");

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
        // Logar erro, mas não exibir ao usuário
        error_log("Erro ao reindexar empresas: " . $e->getMessage());
    }
}

// Processar ações (Adicionar, Editar, Deletar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $empresa_id = filter_input(INPUT_POST, 'empresa_id', FILTER_VALIDATE_INT);
    $nome = trim($_POST['nome'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    $cep = trim($_POST['cep'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email_contato = filter_input(INPUT_POST, 'email_contato', FILTER_VALIDATE_EMAIL) ?: null;

    // Validação básica
    if ($action === 'add' || $action === 'update') {
        if (empty($nome)) {
            $error_message = 'O nome da empresa é obrigatório.';
        } elseif ($action === 'add' && empty($cnpj)) {
            $error_message = 'O CNPJ é obrigatório para novas empresas.';
        }
    }

    if (!$error_message) {
        try {
            // Iniciar transação para a ação principal
            $pdo->beginTransaction();

            if ($action === 'add') {
                // Verificar unicidade do CNPJ
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM empresas WHERE cnpj = ?");
                $stmt_check->execute([$cnpj]);
                if ($stmt_check->fetchColumn() > 0) {
                    throw new PDOException('CNPJ já cadastrado.', 23000);
                }

                // Inserir a nova empresa
                $sql = "INSERT INTO empresas (nome, cnpj, endereco, numero, complemento, bairro, cidade, estado, cep, telefone, email_contato) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nome, $cnpj, $endereco, $numero, $complemento, $bairro, $cidade, $estado, $cep, $telefone, $email_contato]);

                // Confirmar transação do cadastro
                $pdo->commit();

                // Reindexar IDs em uma transação separada
                reindexEmpresas($pdo);

                $success_message = 'Empresa adicionada com sucesso!';
                $_POST = []; // Limpa os dados do POST
            } elseif ($action === 'update' && $empresa_id) {
                $sql = "UPDATE empresas SET nome = ?, cnpj = ?, endereco = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, estado = ?, cep = ?, telefone = ?, email_contato = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nome, $cnpj, $endereco, $numero, $complemento, $bairro, $cidade, $estado, $cep, $telefone, $email_contato, $empresa_id]);
                $pdo->commit();
                $success_message = 'Empresa atualizada com sucesso!';
                $_POST = [];
                $edit_empresa = null; // Sai do modo de edição
            } elseif ($action === 'delete' && $empresa_id) {
                // Verificar se a empresa está em uso antes de deletar
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM terceiros WHERE empresa_id = ?");
                $stmt_check->execute([$empresa_id]);
                if ($stmt_check->fetchColumn() > 0) {
                    $error_message = 'Não é possível excluir a empresa, pois ela está associada a terceiros.';
                    $pdo->rollBack();
                } else {
                    $stmt = $pdo->prepare("DELETE FROM empresas WHERE id = ?");
                    $stmt->execute([$empresa_id]);
                    $pdo->commit();
                    $success_message = 'Empresa excluída com sucesso!';
                }
                $_POST = []; // Limpa os dados do POST
            }
        } catch (PDOException $e) {
            // Reverter transação se houver erro
            try {
                $pdo->rollBack();
            } catch (PDOException $rollback_e) {
                error_log("Erro ao reverter transação principal: " . $rollback_e->getMessage());
            }
            if ($e->getCode() == 23000) {
                $error_message = 'Erro: Já existe uma empresa com este nome ou CNPJ.';
            } else {
                $error_message = 'Erro ao processar a solicitação: ' . $e->getMessage();
                error_log("Erro Empresas PDO: " . $e->getMessage());
            }
        }
    }
}

// Buscar empresa para edição
if (isset($_GET['edit_id'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
            $stmt->execute([$edit_id]);
            $edit_empresa = $stmt->fetch();
            if (!$edit_empresa) {
                $error_message = 'Empresa não encontrada.';
            }
        } catch (PDOException $e) {
            $error_message = 'Erro ao buscar empresa para edição: ' . $e->getMessage();
            error_log("Erro Buscar Empresa Edit PDO: " . $e->getMessage());
        }
    }
}

// Buscar todas as empresas para listar
try {
    $stmt = $pdo->query("SELECT * FROM empresas ORDER BY id ASC");
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'Erro ao listar empresas: ' . $e->getMessage();
    error_log("Erro Listar Empresas PDO: " . $e->getMessage());
    $empresas = [];
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
        <h3><?php echo $edit_empresa ? 'Editar Empresa' : 'Adicionar Nova Empresa'; ?></h3>
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" id="errorAlert"><?php echo html_escape($error_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" id="successAlert"><?php echo html_escape($success_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <form id="empresa-form" method="POST" action="empresas.php">
            <input type="hidden" name="action" value="<?php echo $edit_empresa ? 'update' : 'add'; ?>">
            <?php if ($edit_empresa): ?>
                <input type="hidden" name="empresa_id" value="<?php echo $edit_empresa['id']; ?>">
            <?php endif; ?>
            <div class="mb-3">
                <label for="nome" class="form-label">Nome da Empresa <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nome" name="nome" value="<?php echo html_escape($edit_empresa['nome'] ?? ''); ?>" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="cnpj" class="form-label">CNPJ <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="cnpj" name="cnpj" value="<?php echo html_escape($edit_empresa['cnpj'] ?? ''); ?>" placeholder="00.000.000/0000-00" maxlength="18" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="telefone" class="form-label">Telefone</label>
                    <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo html_escape($edit_empresa['telefone'] ?? ''); ?>" placeholder="(00) 00000-0000">
                </div>
            </div>
            <div class="mb-3">
                <label for="email_contato" class="form-label">Email de Contato</label>
                <input type="email" class="form-control" id="email_contato" name="email_contato" value="<?php echo html_escape($edit_empresa['email_contato'] ?? ''); ?>">
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="cep" class="form-label">CEP</label>
                    <input type="text" class="form-control" id="cep" name="cep" value="<?php echo html_escape($edit_empresa['cep'] ?? ''); ?>" placeholder="00000-000">
                </div>
                <div class="col-md-7 mb-3">
                    <label for="endereco" class="form-label">Endereço</label>
                    <input type="text" class="form-control" id="endereco" name="endereco" value="<?php echo html_escape($edit_empresa['endereco'] ?? ''); ?>">
                </div>
                <div class="col-md-2 mb-3">
                    <label for="numero" class="form-label">Número</label>
                    <input type="text" class="form-control" id="numero" name="numero" value="<?php echo html_escape($edit_empresa['numero'] ?? ''); ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="complemento" class="form-label">Complemento</label>
                    <input type="text" class="form-control" id="complemento" name="complemento" value="<?php echo html_escape($edit_empresa['complemento'] ?? ''); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="bairro" class="form-label">Bairro</label>
                    <input type="text" class="form-control" id="bairro" name="bairro" value="<?php echo html_escape($edit_empresa['bairro'] ?? ''); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="cidade" class="form-label">Cidade</label>
                    <input type="text" class="form-control" id="cidade" name="cidade" value="<?php echo html_escape($edit_empresa['cidade'] ?? ''); ?>">
                </div>
                <div class="col-md-1 mb-3">
                    <label for="estado" class="form-label">UF</label>
                    <input type="text" class="form-control" id="estado" name="estado" value="<?php echo html_escape($edit_empresa['estado'] ?? ''); ?>" maxlength="2">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i><?php echo $edit_empresa ? 'Atualizar' : 'Adicionar'; ?></button>
            <?php if ($edit_empresa): ?>
                <a href="empresas.php" class="btn btn-secondary"><i class="fa-solid fa-cancel me-1"></i>Cancelar Edição</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<hr>

<h3>Empresas Cadastradas</h3>

<?php if (empty($empresas)): ?>
    <p>Nenhuma empresa cadastrada ainda.</p>
<?php else: ?>
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($empresas as $empresa): ?>
                <tr>
                    <td><?php echo html_escape($empresa['id']); ?></td>
                    <td><?php echo html_escape($empresa['nome']); ?></td>
                    <td>
                        <div class="d-flex gap-2">
                            <a href="empresas.php?edit_id=<?php echo $empresa['id']; ?>" class="btn-modern btn-edit" title="Editar">
                                <i class="fa-solid fa-pen-nib fs-6"></i>
                            </a>
                            <button type="button" class="btn-modern btn-delete" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-empresa-id="<?php echo $empresa['id']; ?>" title="Excluir">
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
                Tem certeza que deseja excluir esta empresa? Esta ação não pode ser desfeita.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form id="deleteForm" method="POST" action="empresas.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="empresa_id" id="empresaIdToDelete" value="">
                    <button type="submit" class="btn btn-danger">Excluir</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configurar o modal de confirmação de exclusão
    document.querySelectorAll('.btn-delete').forEach(button => {
        button.addEventListener('click', (event) => {
            event.preventDefault(); // Evita comportamento padrão
            const empresaId = button.getAttribute('data-empresa-id');
            const input = document.getElementById('empresaIdToDelete');
            if (input) {
                input.value = empresaId;
            } else {
                console.error('Elemento empresaIdToDelete não encontrado');
            }
        });
    });

    // Depuração para botão de editar
    document.querySelectorAll('.btn-edit').forEach(button => {
        button.addEventListener('click', (event) => {
            const href = button.getAttribute('href');
            console.log('Botão Editar clicado, redirecionando para:', href);
        });
    });

    // Resetar formulário após sucesso
    <?php if ($success_message): ?>
        document.getElementById('empresa-form').reset();
    <?php endif; ?>
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
