<?php
// Corrigido os caminhos para incluir arquivos da pasta includes/
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/functions.php';

// Requer login
require_login();

$page_title = 'Dashboard';
// Usar require_once para header e footer
require_once __DIR__ . '/includes/header.php';

// $pdo já deve estar disponível globalmente via connection.php
global $pdo;

$stats = [
    'total_colaboradores' => 0,
    'total_empresas' => 0,
    'liberados' => 0,
    'bloqueados' => 0,
];
$alertas_vencimento = [];
$treinamentos_aplicaveis_count = [];

// Restrição de filial para usuário comum
$filial_condition = '';
$params_filial = [];
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Garante que a sessão está iniciada
}

if (!is_admin() && !empty($_SESSION['user_filiais'])) {
    $allowed_ids = explode(',', $_SESSION['user_filiais']);
    // Filtrar IDs vazios ou inválidos, se necessário
    $allowed_ids = array_filter(array_map('intval', $allowed_ids));  
    if (!empty($allowed_ids)) {
        $placeholders = implode(',', array_fill(0, count($allowed_ids), '?'));
        $filial_condition = " WHERE filial_id IN ($placeholders)";
        $params_filial = $allowed_ids;
    }
} elseif (!is_admin() && empty($_SESSION['user_filiais'])) {
    // Usuário comum sem filiais não vê dados
    $filial_condition = " WHERE 1=0"; // Condição sempre falsa
}

try {
    // Contagem total de colaboradores (respeitando filtro de filial)
    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM terceiros" . $filial_condition);
    $stmt_total->execute($params_filial);
    $stats['total_colaboradores'] = $stmt_total->fetchColumn();

    // Contagem total de empresas (geral, não filtrado por filial de usuário)
    $stats['total_empresas'] = $pdo->query("SELECT COUNT(*) FROM empresas")->fetchColumn();

    // Contagem de liberados/bloqueados e alertas (respeitando filtro de filial)
    $stmt_terceiros = $pdo->prepare("SELECT * FROM terceiros" . $filial_condition);
    $stmt_terceiros->execute($params_filial);
    $terceiros = $stmt_terceiros->fetchAll();

    $hoje = new DateTime();
    $data_limite_alerta = (new DateTime())->modify('+30 days');

    $documentos_validade = [
        'aso' => '+1 year',
        'nr10' => '+2 years',
        'nr11' => '+1 year',
        'nr12' => '+1 year',
        'nr18' => '+1 year',
        'integracao' => '+1 year',
        'nr20' => '+1 year',
        'nr33' => '+1 year',
        'nr35' => '+2 years',
    ];
    $documentos_labels = [
        'aso' => 'ASO',
        'epi' => 'EPI',
        'nr10' => 'NR 10',
        'nr11' => 'NR 11',
        'nr12' => 'NR 12',
        'nr18' => 'NR 18',
        'integracao' => 'Integração',
        'nr20' => 'NR 20',
        'nr33' => 'NR 33',
        'nr35' => 'NR 35',
    ];

    foreach ($documentos_labels as $key => $label) {
        $treinamentos_aplicaveis_count[$label] = 0;
    }

    foreach ($terceiros as $terceiro) {
        $status_info = calcular_status_terceiro($terceiro['id']);
        if ($status_info['status'] === 'LIBERADO') {
            $stats['liberados']++;
        } elseif ($status_info['status'] === 'BLOQUEADO') {
            $stats['bloqueados']++;
        }

        // Verificar vencimentos próximos e contar aplicáveis
        foreach ($documentos_validade as $doc_key => $validade) {
            $data_col = $doc_key . '_data';
            $aplicavel_col = $doc_key . '_aplicavel';
            $label = $documentos_labels[$doc_key];

            if ($terceiro[$aplicavel_col]) {
                $treinamentos_aplicaveis_count[$label]++;
                if (!empty($terceiro[$data_col])) {
                    try {
                        $data_doc = new DateTime($terceiro[$data_col]);
                        $data_vencimento = (clone $data_doc)->modify($validade);

                        if ($data_vencimento >= $hoje && $data_vencimento <= $data_limite_alerta) {
                            $alertas_vencimento[] = [
                                'terceiro_id' => $terceiro['id'],
                                'nome_terceiro' => $terceiro['nome_completo'],
                                'documento' => $label,
                                'data_vencimento' => $data_vencimento->format('d/m/Y')
                            ];
                        }
                    } catch (Exception $e) {
                        // Ignorar data inválida para alerta
                    }
                }
            }
        }
        // Contar EPI aplicável (sem data de vencimento)
        if ($terceiro['epi_aplicavel']) {
            $treinamentos_aplicaveis_count['EPI']++;
        }
    }

    // Ordenar alertas por data de vencimento
    usort($alertas_vencimento, function($a, $b) {
        // Converter data BR para formato comparável
        $dateA = DateTime::createFromFormat('d/m/Y', $a['data_vencimento']);
        $dateB = DateTime::createFromFormat('d/m/Y', $b['data_vencimento']);
        if (!$dateA || !$dateB) return 0; // Em caso de erro de formato
        return $dateA <=> $dateB;
    });

    // Ordenar treinamentos por contagem (decrescente)
    arsort($treinamentos_aplicaveis_count);

} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Erro ao buscar dados do dashboard: ' . $e->getMessage();
    error_log('Erro Dashboard PDO: ' . $e->getMessage()); // Logar erro
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Erro inesperado no dashboard: ' . $e->getMessage();
    error_log('Erro Dashboard Geral: ' . $e->getMessage()); // Logar erro
}
?>

<h3>Dashboard</h3>

<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <a href="monitoramento.php" style="text-decoration: none;">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Colaboradores Cadastrados <?php echo is_admin() ? '(Total)' : '(Suas Filiais)'; ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_colaboradores']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <?php 
        // NOTA: Substitua 'is_admin()' pela condição correta de permissão, se necessário
        // Exemplo: if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'):
        if (is_admin()):  
        ?>
            <a href="/admin/empresas.php" style="text-decoration: none;">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Empresas Cadastradas
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_empresas']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-building fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        <?php else: ?>
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Empresas Cadastradas
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_empresas']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-building fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <a href="monitoramento.php?status=LIBERADO" style="text-decoration: none;">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Colaboradores Liberados
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['liberados']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <a href="monitoramento.php?status=BLOQUEADO" style="text-decoration: none;">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Colaboradores Bloqueados
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['bloqueados']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-warning">Alertas: Vencendo nos Próximos 30 Dias</h6>
            </div>
            <div class="card-body" style="max-height: 345px; overflow-y: auto;">
                <?php if (empty($alertas_vencimento)): ?>
                    <p>Nenhum documento vencendo nos próximos 30 dias.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($alertas_vencimento as $alerta): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="terceiros.php?edit_id=<?php echo $alerta['terceiro_id']; ?>"><?php echo html_escape($alerta['nome_terceiro']); ?></a>
                                    <small class="d-block text-muted"><?php echo html_escape($alerta['documento']); ?></small>
                                </div>
                                <span class="badge bg-warning rounded-pill"><?php echo $alerta['data_vencimento']; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-info">Treinamentos/Documentos Mais Aplicáveis</h6>
            </div>
            <div class="card-body" style="max-height: 345px; overflow-y: auto;">
                <?php if (empty($treinamentos_aplicaveis_count) || max(array_values($treinamentos_aplicaveis_count)) == 0): ?>
                    <p>Nenhum dado de treinamento aplicável encontrado.</p>
                <?php else: ?>
                    <?php foreach ($treinamentos_aplicaveis_count as $label => $count): ?>
                        <?php if ($count > 0): // Mostra apenas os que têm contagem > 0 ?>
                            <?php
                            // Calcula a porcentagem (evita divisão por zero)
                            $percentage = ($stats['total_colaboradores'] > 0) ? round(($count / $stats['total_colaboradores']) * 100) : 0;
                            ?>
                            <h4 class="small font-weight-bold"><?php echo html_escape($label); ?> <span class="float-end"><?php echo $count; ?> (<?php echo $percentage; ?>%)</span></h4>
                            <div class="progress mb-2">
                                <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                <p class="small text-muted mt-2" style="font-size: 0.75rem;">Contagem de colaboradores visíveis (<?php echo $stats['total_colaboradores']; ?>) com treinamento marcado como aplicável.</p>
            </div>
        </div>
    </div>
</div>

<style>
.border-left-primary {
    border-left: .25rem solid #4e73df !important;
}
.border-left-success {
    border-left: .25rem solid #1cc88a !important;
}
.border-left-info {
    border-left: .25rem solid #36b9cc !important;
}
.border-left-warning {
    border-left: .25rem solid #f6c23e !important;
}
.border-left-danger {
    border-left: .25rem solid #e74a3b !important;
}
.text-gray-300 {
    color: #dddfeb !important;
}
.text-gray-800 {
    color: #5a5c69 !important;
}
.font-weight-bold {
    font-weight: 700 !important;
}
.text-xs {
    font-size: .7rem;
}
</style>

<?php
// Usar require_once para o footer
require_once __DIR__ . '/includes/footer.php';
?>
