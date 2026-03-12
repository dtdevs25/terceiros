<?php
/**
 * Listagem de Funcionários
 * Sistema de Gerenciamento de Funcionários
 */

$page_title = 'Funcionários - ' . SITE_NAME;
require_once __DIR__ . "/../../includes/header.php";

// Verificar permissões
if (!verificarPermissao('read')) {
    header('Location: ' . SITE_URL . '/public/dashboard.php?erro=sem_permissao');
    exit();
}

// Buscar dados
try {
    $funcionarioModel = new Funcionario();
    $empresaModel = new Empresa();
    $postoModel = new PostoTrabalho();
    
    // Filtros
    $filtros = [
        'nome' => $_GET['nome'] ?? '',
        'cpf' => $_GET['cpf'] ?? '',
        'matricula' => $_GET['matricula'] ?? '',
        'empresa_id' => $_GET['empresa_id'] ?? '',
        'posto_id' => $_GET['posto_id'] ?? '',
        'status_aso' => $_GET['status_aso'] ?? ''
    ];
    
    // Buscar funcionários
    $funcionarios = $funcionarioModel->listar($filtros);
    
    // Buscar empresas para filtro
    $empresas = [];
    if ($_SESSION['hierarquia'] === 'gerente') {
        $empresas = $empresaModel->listarAtivas();
    } else {
        foreach ($_SESSION['empresas'] as $empresa_id) {
            $empresa = $empresaModel->buscarPorId($empresa_id);
            if ($empresa) {
                $empresas[] = $empresa;
            }
        }
    }
    
    // Buscar postos para filtro
    $postos = [];
    if (!empty($filtros['empresa_id'])) {
        $postos = $postoModel->listarPorEmpresa($filtros['empresa_id']);
    }
    
} catch (Exception $e) {
    $erro = "Erro ao carregar funcionários: " . $e->getMessage();
}
?>

<div class="funcionarios-page fade-in">
    <!-- Cabeçalho da página -->
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1 class="page-title">Funcionários</h1>
                <p class="page-subtitle">Gerenciar funcionários e seus treinamentos</p>
            </div>
            <div class="page-actions">
                <?php if (verificarPermissao('create')): ?>
                <a href="<?php echo SITE_URL; ?>/funcionarios/cadastro" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Novo Funcionário
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (isset($erro)): ?>
        <div class="alert alert-danger">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
            <?php echo htmlspecialchars($erro); ?>
        </div>
    <?php else: ?>
    
    <!-- Filtros -->
    <div class="filters">
        <form method="GET" class="filters-form">
            <div class="filters-row">
                <div class="form-group">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" placeholder="Buscar por nome" value="<?php echo htmlspecialchars($filtros['nome']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">CPF</label>
                    <input type="text" name="cpf" class="form-control" placeholder="000.000.000-00" data-mask="cpf" value="<?php echo htmlspecialchars($filtros['cpf']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Matrícula</label>
                    <input type="text" name="matricula" class="form-control" placeholder="Buscar por matrícula" value="<?php echo htmlspecialchars($filtros['matricula']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Empresa</label>
                    <select name="empresa_id" class="form-control form-select" onchange="carregarPostos(this.value)">
                        <option value="">Todas as empresas</option>
                        <?php foreach ($empresas as $empresa): ?>
                            <option value="<?php echo $empresa['id']; ?>" <?php echo $filtros['empresa_id'] == $empresa['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($empresa['razao_social']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Posto de Trabalho</label>
                    <select name="posto_id" class="form-control form-select" id="postoSelect">
                        <option value="">Todos os postos</option>
                        <?php foreach ($postos as $posto): ?>
                            <option value="<?php echo $posto['id']; ?>" <?php echo $filtros['posto_id'] == $posto['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($posto['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status ASO</label>
                    <select name="status_aso" class="form-control form-select">
                        <option value="">Todos</option>
                        <option value="valido" <?php echo $filtros['status_aso'] === 'valido' ? 'selected' : ''; ?>>Válido</option>
                        <option value="a_vencer" <?php echo $filtros['status_aso'] === 'a_vencer' ? 'selected' : ''; ?>>A vencer (30 dias)</option>
                        <option value="vencido" <?php echo $filtros['status_aso'] === 'vencido' ? 'selected' : ''; ?>>Vencido</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        Filtrar
                    </button>
                    <a href="<?php echo SITE_URL; ?>/public/funcionarios/" class="btn btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="1,4 1,10 7,10"></polyline>
                            <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                        </svg>
                        Limpar
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Resultados -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                Funcionários Cadastrados (<?php echo count($funcionarios); ?>)
            </h3>
            <div class="card-actions">
                <button class="btn btn-outline btn-sm" onclick="exportarDados()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7,10 12,15 17,10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Exportar
                </button>
            </div>
        </div>
        
        <?php if (empty($funcionarios)): ?>
            <div class="empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <h3>Nenhum funcionário encontrado</h3>
                <p>Não há funcionários cadastrados com os filtros selecionados.</p>
                <?php if (verificarPermissao('create')): ?>
                <a href="<?php echo SITE_URL; ?>/public/funcionarios/cadastro.php" class="btn btn-primary">
                    Cadastrar Primeiro Funcionário
                </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Funcionário</th>
                            <th>CPF</th>
                            <th>Matrícula</th>
                            <th>ASO</th>
                            <th>Treinamentos</th>
                            <th>Postos de Trabalho</th>
                            <th>Aptidão</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($funcionarios as $funcionario): ?>
                        <tr>
                            <td>
                                <span class="status-light <?php 
                                    if ($funcionario['aso_status'] === 'vencido' || $funcionario['treinamentos_vencidos'] > 0) {
                                        echo 'red';
                                    } elseif ($funcionario['aso_status'] === 'vence_15_dias' || $funcionario['treinamentos_a_vencer'] > 0) {
                                        echo 'orange';
                                    } elseif ($funcionario['aso_status'] === 'vence_30_dias') {
                                        echo 'yellow';
                                    } else {
                                        echo 'green';
                                    }
                                ?>"></span>
                            </td>
                            <td>
                                <div class="user-info">
                                    <?php if ($funcionario['foto']): ?>
                                        <img src="<?php echo SITE_URL . '/uploads/' . $funcionario['foto']; ?>" alt="Foto" class="user-avatar-small">
                                    <?php else: ?>
                                        <div class="user-avatar-small">
                                            <?php echo strtoupper(substr($funcionario['nome'], 0, 2)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo htmlspecialchars($funcionario['nome']); ?></strong>
                                        <small class="d-block text-muted">ID: <?php echo $funcionario['id']; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo formatarCPF($funcionario['cpf']); ?></td>
                            <td><?php echo htmlspecialchars($funcionario['matricula']); ?></td>
                            <td>
                                <div class="aso-info">
                                    <small class="d-block">Validade:</small>
                                    <strong><?php echo Utils::formatDate($funcionario['aso_data']); ?></strong>
                                    <span class="badge badge-<?php 
                                        echo $funcionario['aso_status'] === 'vencido' ? 'danger' : 
                                            ($funcionario['aso_status'] === 'vence_15_dias' ? 'warning' : 
                                            ($funcionario['aso_status'] === 'vence_30_dias' ? 'warning' : 'success')); 
                                    ?> badge-sm">
                                        <?php 
                                        switch ($funcionario['aso_status']) {
                                            case 'vencido':
                                                echo 'Vencido';
                                                break;
                                            case 'vence_15_dias':
                                                echo '≤ 15 dias';
                                                break;
                                            case 'vence_30_dias':
                                                echo '≤ 30 dias';
                                                break;
                                            default:
                                                echo 'Válido';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="treinamentos-info">
                                    <?php if ($funcionario['treinamentos_vencidos'] > 0): ?>
                                        <span class="badge badge-danger badge-sm">
                                            <?php echo $funcionario['treinamentos_vencidos']; ?> vencido(s)
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($funcionario['treinamentos_a_vencer'] > 0): ?>
                                        <span class="badge badge-warning badge-sm">
                                            <?php echo $funcionario['treinamentos_a_vencer']; ?> a vencer
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($funcionario['treinamentos_vencidos'] == 0 && $funcionario['treinamentos_a_vencer'] == 0): ?>
                                        <span class="badge badge-success badge-sm">Em dia</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars($funcionario['postos_trabalho'] ?? 'Nenhum'); ?></small>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $funcionario['aptidao_trabalho'] === 'apto' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($funcionario['aptidao_trabalho']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?php echo SITE_URL; ?>/public/funcionarios/detalhes.php?id=<?php echo $funcionario['id']; ?>" 
                                       class="btn btn-outline btn-sm" title="Ver detalhes">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </a>
                                    <?php if (verificarPermissao('update')): ?>
                                    <a href="<?php echo SITE_URL; ?>/public/funcionarios/editar.php?id=<?php echo $funcionario['id']; ?>" 
                                       class="btn btn-outline btn-sm" title="Editar">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (verificarPermissao('delete')): ?>
                                    <button type="button" class="btn btn-outline btn-sm text-danger" title="Excluir" 
                                            onclick="confirmarExclusao(<?php echo $funcionario['id']; ?>, '<?php echo htmlspecialchars($funcionario['nome']); ?>')">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3,6 5,6 21,6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            <line x1="10" y1="11" x2="10" y2="17"></line>
                                            <line x1="14" y1="11" x2="14" y2="17"></line>
                                        </svg>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de Confirmação de Exclusão -->
<div class="modal" id="confirmDeleteModal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h5 class="modal-title">Confirmar Exclusão</h5>
            <button type="button" class="modal-close" onclick="Modal.hide('confirmDeleteModal')">×</button>
        </div>
        <div class="modal-body">
            <p>Tem certeza que deseja excluir o funcionário "<strong id="funcionarioNomeExclusao"></strong>"?</p>
            <p class="text-danger">Esta ação não pode ser desfeita.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="Modal.hide('confirmDeleteModal')">Cancelar</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Excluir</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>

<script>
// Função para carregar postos de trabalho dinamicamente
function carregarPostos(empresaId) {
    const postoSelect = document.getElementById("postoSelect");
    postoSelect.innerHTML = '<option value="">Todos os postos</option>'; // Limpa e adiciona opção padrão

    if (empresaId) {
        fetch(`<?php echo SITE_URL; ?>/api/postos?empresa_id=${empresaId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.postos.forEach(posto => {
                        const option = document.createElement('option');
                        option.value = posto.id;
                        option.textContent = posto.nome;
                        postoSelect.appendChild(option);
                    });
                } else {
                    console.error('Erro ao carregar postos:', data.message);
                }
            })
            .catch(error => console.error('Erro na requisição de postos:', error));
    }
}

// Função para confirmar exclusão
function confirmarExclusao(id, nome) {
    document.getElementById('funcionarioNomeExclusao').textContent = nome;
    Modal.show('confirmDeleteModal');
    
    document.getElementById('confirmDeleteBtn').onclick = function() {
        Modal.hide('confirmDeleteModal');
        // Enviar requisição DELETE
        fetch(`<?php echo SITE_URL; ?>/api/funcionarios/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-Token': '<?php echo gerarTokenCSRF(); ?>'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Funcionário excluído com sucesso!');
                location.reload(); // Recarrega a página para atualizar a lista
            } else {
                alert('Erro ao excluir funcionário: ' + data.message);
            }
        })
        .catch(error => alert('Erro na requisição: ' + error));
    };
}

// Função para exportar dados (exemplo simples, pode ser expandido)
function exportarDados() {
    alert('Funcionalidade de exportar dados será implementada aqui.');
}
</script>
