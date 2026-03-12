<?php
/**
 * Listagem de Funcionários
 * Sistema de Gerenciamento de Funcionários
 */

require_once __DIR__ . "/../../config/config.php";

// Verificar permissões
if (!verificarPermissao('read')) {
    header('Location: ' . SITE_URL . '/public/dashboard.php?erro=sem_permissao');
    exit();
}

$page_title = 'Funcionários - ' . SITE_NAME;

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
    $erro_dados = "Erro ao carregar funcionários: " . $e->getMessage();
}

require_once __DIR__ . "/../../includes/header.php";
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
                <a href="<?php echo SITE_URL; ?>/public/funcionarios/cadastro.php" class="btn btn-primary">
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
    
    <?php if (isset($erro_dados)): ?>
        <div class="alert alert-danger">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
            <?php echo htmlspecialchars($erro_dados); ?>
        </div>
    <?php endif; ?>
    
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
                    <select name="empresa_id" class="form-control form-select" onchange="this.form.submit()">
                        <option value="">Todas as empresas</option>
                        <?php foreach ($empresas as $empresa): ?>
                            <option value="<?php echo $empresa['id']; ?>" <?php echo $filtros['empresa_id'] == $empresa['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($empresa['razao_social']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="<?php echo SITE_URL; ?>/public/funcionarios/" class="btn btn-secondary">Limpar</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabela de Resultados -->
    <div class="card">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>Matrícula</th>
                        <th>ASO</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($funcionarios)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Nenhum funcionário encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($funcionarios as $funcionario): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($funcionario['nome']); ?></td>
                            <td><?php echo htmlspecialchars($funcionario['cpf']); ?></td>
                            <td><?php echo htmlspecialchars($funcionario['matricula']); ?></td>
                            <td><?php echo Utils::formatDate($funcionario['aso_data']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $funcionario['aso_status'] === 'vencido' ? 'danger' : 'success'; ?>">
                                    <?php echo $funcionario['aso_status']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="detalhes.php?id=<?php echo $funcionario['id']; ?>" class="btn btn-sm btn-outline">Ver</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>
