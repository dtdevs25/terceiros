<?php
/**
 * Dashboard Principal
 * Sistema de Gerenciamento de Funcionários
 */

$page_title = 'Dashboard - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';

// Buscar estatísticas
try {
    $funcionarioModel = new Funcionario();
    $treinamentoModel = new Treinamento();
    $empresaModel = new Empresa();
    
    // Estatísticas gerais
    $stats = $funcionarioModel->estatisticas();
    $stats_treinamentos = ['certificacoes_vencidas' => 0, 'certificacoes_a_vencer' => 0]; // Temporariamente vazio até corrigir a tabela
    
    // Funcionários com status crítico
    $funcionarios_criticos = $funcionarioModel->getVencimentosASO()["funcionariosAsoCritico"];
    $treinamentos_criticos = []; // Temporariamente vazio até corrigir a tabela
    
    // Empresas (se o usuário tiver acesso)
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
    
} catch (Exception $e) {
    $erro = "Erro ao carregar dados: " . $e->getMessage();
}
?>

<div class="dashboard fade-in">
    <!-- Cabeçalho da página -->
    <div class="page-header">
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Visão geral do sistema de gerenciamento de funcionários</p>
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
    
    <!-- Cards de estatísticas -->
    <div class="dashboard-stats">
        <!-- Total de funcionários -->
        <div class="stat-card">
            <div class="stat-icon primary">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($stats['total_funcionarios']); ?></h3>
                <p>Total de Funcionários</p>
            </div>
        </div>
        
        <!-- Funcionários aptos -->
        <div class="stat-card">
            <div class="stat-icon success">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20,6 9,17 4,12"></polyline>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($stats['funcionarios_aptos']); ?></h3>
                <p>Funcionários Aptos</p>
            </div>
        </div>
        
        <!-- ASOs vencidos -->
        <div class="stat-card">
            <div class="stat-icon danger">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($stats['aso_vencidos']); ?></h3>
                <p>ASOs Vencidos</p>
            </div>
        </div>
        
        <!-- ASOs a vencer -->
        <div class="stat-card">
            <div class="stat-icon warning">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($stats['aso_a_vencer']); ?></h3>
                <p>ASOs a Vencer (30 dias)</p>
            </div>
        </div>
        
        <!-- Treinamentos vencidos -->
        <div class="stat-card">
            <div class="stat-icon danger">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($stats_treinamentos['certificacoes_vencidas']); ?></h3>
                <p>Treinamentos Vencidos</p>
            </div>
        </div>
        
        <!-- Treinamentos a vencer -->
        <div class="stat-card">
            <div class="stat-icon warning">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($stats_treinamentos['certificacoes_a_vencer']); ?></h3>
                <p>Treinamentos a Vencer (30 dias)</p>
            </div>
        </div>
    </div>
    
    <div class="dashboard-content">
        <div class="row">
            <!-- Coluna esquerda -->
            <div class="col-lg-8">
                <!-- Funcionários com status crítico -->
                <?php if (!empty($funcionarios_criticos)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            ASOs Vencidos ou a Vencer
                        </h3>
                        <div class="card-actions">
                            <a href="<?php echo SITE_URL; ?>/public/funcionarios/" class="btn btn-outline btn-sm">Ver Todos</a>
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Funcionário</th>
                                    <th>CPF</th>
                                    <th>Matrícula</th>
                                    <th>Validade ASO</th>
                                    <th>Status</th>
                                    <th>Postos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($funcionarios_criticos, 0, 10) as $funcionario): ?>
                                <tr>
                                <td><?php echo htmlspecialchars(isset($funcionario['nome']) ? $funcionario['nome'] : ''); ?></td>
                                    <td><?php echo formatarCPF(isset($funcionario["cpf"]) ? $funcionario["cpf"] : ""); ?></td>
                                    <td><?php echo htmlspecialchars(isset($funcionario["matricula"]) ? $funcionario["matricula"] : ""); ?></td>
                                    <td><?php echo htmlspecialchars(isset($funcionario["aso_data"]) ? Utils::formatDate($funcionario["aso_data"]) : ""); ?></td>
                                    <td>
                                        <span class="status-light <?php 
                                            echo $funcionario['aso_status'] === 'vencido' ? 'red' : 
                                                ($funcionario['aso_status'] === 'vence_15_dias' ? 'orange' : 'yellow'); 
                                        ?>"></span>
                                        <span class="badge badge-<?php 
                                            echo $funcionario["aso_status"] === "vencido" ? "danger" : "warning"; 
                                        ?>">
                                            <?php 
                                            if ($funcionario["aso_status"] === "vencido") {
                                                echo "Vencido";
                                            } elseif (isset($funcionario["aso_dias_restantes"]) && $funcionario["aso_dias_restantes"] <= 30) {
                                                echo $funcionario["aso_dias_restantes"] . " dias";
                                            } else {
                                                echo "Válido";
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($funcionario['postos_trabalho'] ?? 'Nenhum'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Treinamentos com status crítico -->
                <?php if (!empty($treinamentos_criticos)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                            </svg>
                            Treinamentos Vencidos ou a Vencer
                        </h3>
                        <div class="card-actions">
                            <a href="<?php echo SITE_URL; ?>/public/treinamentos/" class="btn btn-outline btn-sm">Ver Todos</a>
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Funcionário</th>
                                    <th>Treinamento</th>
                                    <th>Realização</th>
                                    <th>Validade</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($treinamentos_criticos, 0, 10) as $treinamento): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($treinamento['funcionario_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($treinamento['treinamento_nome']); ?></td>
                                    <td><?php echo Utils::formatDate($treinamento['data_realizacao']); ?></td>
                                    <td><?php echo Utils::formatDate($treinamento['data_validade']); ?></td>
                                    <td>
                                        <span class="status-light <?php 
                                            echo $treinamento['status_validade'] === 'vencido' ? 'red' : 
                                                ($treinamento['status_validade'] === 'vence_15_dias' ? 'orange' : 'yellow'); 
                                        ?>"></span>
                                        <span class="badge badge-<?php 
                                            echo $treinamento['status_validade'] === 'vencido' ? 'danger' : 'warning'; 
                                        ?>">
                                            <?php 
                                            if ($treinamento['status_validade'] === 'vencido') {
                                                echo 'Vencido';
                                            } elseif ($treinamento['dias_restantes'] <= 15) {
                                                echo $treinamento['dias_restantes'] . ' dias';
                                            } else {
                                                echo $treinamento['dias_restantes'] . ' dias';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Coluna direita -->
            <div class="col-lg-4">
                <!-- Empresas -->
                <?php if (!empty($empresas)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 21h18"></path>
                                <path d="M5 21V7l8-4v18"></path>
                                <path d="M19 21V11l-6-4"></path>
                            </svg>
                            Empresas
                        </h3>
                    </div>
                    <div class="empresa-list">
                        <?php foreach ($empresas as $empresa): ?>
                        <div class="empresa-item">
                            <div class="empresa-info">
                                <h4><?php echo htmlspecialchars($empresa['razao_social']); ?></h4>
                                <?php if ($empresa['cnpj']): ?>
                                    <p class="empresa-cnpj"><?php echo formatarCNPJ($empresa['cnpj']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="empresa-stats">
                                <span class="stat-item">
                                    <strong><?php echo $empresa['total_postos'] ?? 0; ?></strong>
                                    <small>Postos</small>
                                </span>
                                <span class="stat-item">
                                    <strong><?php echo $empresa['total_funcionarios'] ?? 0; ?></strong>
                                    <small>Funcionários</small>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Ações rápidas -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                            Ações Rápidas
                        </h3>
                    </div>
                    <div class="quick-actions">
                        <?php if (verificarPermissao('create')): ?>
                        <a href="<?php echo SITE_URL; ?>/public/funcionarios/cadastro.php" class="quick-action-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <line x1="19" y1="8" x2="19" y2="14"></line>
                                <line x1="22" y1="11" x2="16" y2="11"></line>
                            </svg>
                            Novo Funcionário
                        </a>
                        
                        <a href="<?php echo SITE_URL; ?>/public/empresas/cadastro.php" class="quick-action-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 21h18"></path>
                                <path d="M5 21V7l8-4v18"></path>
                                <path d="M19 21V11l-6-4"></path>
                            </svg>
                            Nova Empresa
                        </a>
                        
                        <a href="<?php echo SITE_URL; ?>/public/treinamentos/cadastro.php" class="quick-action-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                            </svg>
                            Novo Treinamento
                        </a>
                        <?php endif; ?>
                        
                        <a href="<?php echo SITE_URL; ?>/public/relatorios/" class="quick-action-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14,2 14,8 20,8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10,9 9,9 8,9"></polyline>
                            </svg>
                            Gerar Relatório
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<style>
.page-header {
    margin-bottom: 30px;
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary-dark);
    margin: 0 0 10px 0;
}

.page-subtitle {
    color: var(--gray);
    font-size: 1.1rem;
    margin: 0;
}

.row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 25px;
}

.col-lg-8 {
    grid-column: 1;
}

.col-lg-4 {
    grid-column: 1;
}

@media (min-width: 992px) {
    .row {
        grid-template-columns: 2fr 1fr;
    }
    
    .col-lg-8 {
        grid-column: 1;
    }
    
    .col-lg-4 {
        grid-column: 2;
    }
}

.empresa-list {
    padding: 0;
}

.empresa-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid var(--light-gray);
}

.empresa-item:last-child {
    border-bottom: none;
}

.empresa-info h4 {
    margin: 0 0 5px 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--dark-gray);
}

.empresa-cnpj {
    margin: 0;
    font-size: 0.85rem;
    color: var(--gray);
}

.empresa-stats {
    display: flex;
    gap: 15px;
}

.stat-item {
    text-align: center;
    display: flex;
    flex-direction: column;
}

.stat-item strong {
    font-size: 1.2rem;
    color: var(--primary-color);
}

.stat-item small {
    font-size: 0.75rem;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.quick-actions {
    display: grid;
    gap: 10px;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    background: var(--light-gray);
    color: var(--dark-gray);
    text-decoration: none;
    border-radius: var(--border-radius);
    transition: var(--transition-fast);
    font-weight: 500;
}

.quick-action-btn:hover {
    background: var(--primary-color);
    color: var(--white);
    transform: translateY(-2px);
    box-shadow: var(--shadow-medium);
}

@media (max-width: 767px) {
    .page-title {
        font-size: 2rem;
    }
    
    .dashboard-stats {
        grid-template-columns: 1fr;
    }
    
    .empresa-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .empresa-stats {
        align-self: stretch;
        justify-content: space-around;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
