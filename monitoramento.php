<?php
ob_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$page_title = 'Monitoramento de Terceiros';
require_once __DIR__ . '/includes/header.php';

global $pdo;

$terceiros = [];
$empresas = get_todas_empresas();
$filiais = get_todas_filiais();

$filtro_nome = trim($_GET['nome'] ?? '');
$filtro_empresa_id = filter_input(INPUT_GET, 'empresa_id', FILTER_VALIDATE_INT);
$filtro_filial_id = filter_input(INPUT_GET, 'filial_id', FILTER_VALIDATE_INT);
$filtro_status = $_GET['status'] ?? '';

function montar_query_terceiros($pdo, $filtro_nome, $filtro_empresa_id, $filtro_filial_id) {
    $sql = "SELECT t.*, e.nome as empresa_nome, f.nome as filial_nome
            FROM terceiros t
            JOIN empresas e ON t.empresa_id = e.id
            JOIN filiais f ON t.filial_id = f.id
            WHERE 1=1";
    $params = [];

    if ($filtro_nome) {
        $sql .= " AND t.nome_completo LIKE ?";
        $params[] = "%$filtro_nome%";
    }

    if ($filtro_empresa_id) {
        $sql .= " AND t.empresa_id = ?";
        $params[] = $filtro_empresa_id;
    }

    if ($filtro_filial_id) {
        $sql .= " AND t.filial_id = ?";
        $params[] = $filtro_filial_id;
    }

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    if (!is_admin()) {
        $filiais_usuario = $_SESSION['user_filiais'] ?? '';
        $ids = array_filter(array_map('intval', explode(',', $filiais_usuario)));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " AND t.filial_id IN ($placeholders)";
            $params = array_merge($params, $ids);
        } else {
            $sql .= " AND 1=0";
        }
    }

    $sql .= " ORDER BY t.nome_completo ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

try {
    $terceiros_raw = montar_query_terceiros($pdo, $filtro_nome, $filtro_empresa_id, $filtro_filial_id);

    foreach ($terceiros_raw as $terceiro) {
        $status_info = calcular_status_terceiro($terceiro['id']);
        $terceiro['status'] = $status_info['status'];
        $terceiro['vencidos'] = $status_info['vencidos'];
        $terceiro['trabalhou_hoje'] = verificar_trabalho_hoje($pdo, $terceiro['id']);

        if (!$filtro_status || $filtro_status === $terceiro['status']) {
            $terceiros[] = $terceiro;
        }
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Erro ao buscar terceiros.';
    error_log('Erro Monitoramento: ' . $e->getMessage());
}

$highlight_id = filter_input(INPUT_GET, 'highlight', FILTER_VALIDATE_INT);

if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" id="successAlert" role="alert">' . html_escape($_SESSION['success_message']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" id="errorAlert" role="alert">' . html_escape($_SESSION['error_message']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>';
    unset($_SESSION['error_message']);
}
?>

<h3>Monitoramento de Terceiros</h3>

<form method="GET" action="monitoramento.php" class="mb-4 p-3 border rounded bg-light shadow-sm">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label for="nome" class="form-label">Nome:</label>
            <input type="text" class="form-control form-control-sm" id="nome" name="nome" value="<?= html_escape($filtro_nome); ?>" style="font-size: 0.75rem; padding: 4px;">
        </div>
        <div class="col-md-2">
            <label for="empresa_id" class="form-label">Empresa:</label>
            <select class="form-select form-select-sm" id="empresa_id" name="empresa_id" style="font-size: 0.85rem; padding: 4px;">
                <option value="">Todas</option>
                <?php foreach ($empresas as $empresa): ?>
                
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

                    <option value="<?= $empresa['id']; ?>" <?= $filtro_empresa_id == $empresa['id'] ? 'selected' : ''; ?>><?= html_escape($empresa['nome']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="filial_id" class="form-label">Unidade:</label>
            <select class="form-select form-select-sm" id="filial_id" name="filial_id" style="font-size: 0.8rem; padding: 4px;">
                <option value="">Todas</option>
                <?php foreach ($filiais as $filial): ?>
                    <option value="<?= $filial['id']; ?>" <?= $filtro_filial_id == $filial['id'] ? 'selected' : ''; ?>><?= html_escape($filial['nome']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="status" class="form-label">Status:</label>
            <select class="form-select form-select-sm" id="status" name="status" style="font-size: 0.8rem; padding: 4px;">
                <option value="">Todos</option>
                <option value="LIBERADO" <?= $filtro_status == 'LIBERADO' ? 'selected' : ''; ?>>Liberado</option>
                <option value="BLOQUEADO" <?= $filtro_status == 'BLOQUEADO' ? 'selected' : ''; ?>>Bloqueado</option>
            </select>
        </div>
        <div class="col-md-3 d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-primary" style="padding: 6px 12px; border-radius: 6px; font-weight: 500; font-size: 0.8rem; background: linear-gradient(135deg, #007bff, #0056b3); box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: all 0.3s ease; border: none;" onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 4px 10px rgba(0,0,0,0.2)';" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 2px 5px rgba(0,0,0,0.1)';">
                <i class="fas fa-filter me-1" style="font-size: 0.75rem;"></i> Filtrar
            </button>
            <a href="monitoramento.php" class="btn btn-secondary" style="padding: 6px 12px; border-radius: 6px; font-weight: 500; font-size: 0.8rem; background: linear-gradient(135deg, #6c757d, #495057); color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: all 0.3s ease; border: none;" onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 4px 10px rgba(0,0,0,0.2)';" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 2px 5px rgba(0,0,0,0.1)';">
                <i class="fas fa-times me-1" style="font-size: 0.75rem;"></i> Limpar
            </a>
        </div>
    </div>
</form>

<?php if (empty($terceiros)): ?>
    <p>Nenhum terceiro encontrado com os filtros aplicados.</p>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-striped table-hover table-bordered" style="font-size: 0.9rem;">
        <thead class="table-dark">
            <tr>
                <th style="display:none;">ID</th>
                <th>Foto</th>
                <th>Nome</th>
                <th>Empresa</th>
                <th>Filial</th>
                <th>Status</th>
                <th>Pendências/Vencidos</th>
                <th style="width: 60px;" class="text-center">Presente</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($terceiros as $terceiro): ?>
                <tr class="<?= $highlight_id == $terceiro['id'] ? 'table-info' : ''; ?>" style="cursor:pointer;">
                    <td style="display:none;"><?= $terceiro['id']; ?></td>
                    <td>
                        <?php
                        $foto_url = APP_URL . '/img/placeholder.png';
                        $caminho_foto = UPLOAD_DIR . $terceiro['foto_path'];
                        if (!empty($terceiro['foto_path']) && file_exists($caminho_foto)) {
                            $foto_url = APP_URL . '/uploads/' . html_escape($terceiro['foto_path']);
                        }
                        ?>
                        <img src="<?= $foto_url; ?>" class="rounded-circle" style="width:30px;height:30px;object-fit:cover;">
                    </td>
                    <td><?= html_escape($terceiro['nome_completo']); ?></td>
                    <td><?= html_escape($terceiro['empresa_nome']); ?></td>
                    <td><?= html_escape($terceiro['filial_nome']); ?></td>
                    <td class="text-center">
                        <?php if ($terceiro['status'] === 'LIBERADO'): ?>
                            <i class="fas fa-unlock text-success fs-6" title="Colaborador Liberado"></i>
                        <?php elseif ($terceiro['status'] === 'BLOQUEADO'): ?>
                            <i class="fas fa-ban text-danger fs-6" title="Colaborador Bloqueado"></i>
                        <?php else: ?>
                            <span class="badge text-bg-warning" style="font-size: 0.7rem;">
                                <?= html_escape($terceiro['status']); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($terceiro['status'] === 'BLOQUEADO' && !empty($terceiro['vencidos'])): ?>
                            <ul class="list-unstyled mb-0 small">
                                <?php foreach ($terceiro['vencidos'] as $v): ?>
                                    <li><small class="text-danger"><?= html_escape($v); ?></small></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php elseif ($terceiro['status'] === 'ERRO'): ?>
                            <small class="text-danger">Erro ao calcular status.</small>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?= $terceiro['trabalhou_hoje'] ? '<i class="fas fa-check-double text-success fs-6" title="Colaborador trabalhando na unidade"></i>' : '<i class="fas fa-times-circle text-danger fs-6" title="Colaborador não está na unidade"></i>'; ?>
                    </td>
                    <td>
                        <?php if (is_admin()): ?>
                        
                            <div class="d-flex gap-1">
                                <a href="terceiros.php?edit_id=<?= $terceiro['id']; ?>" class="btn btn-sm btn-warning btn-modern btn-edit" title="Editar">
                                    <i class="fas fa-edit"></i>
                                 </a>
                                 <button type="button" class="btn btn-sm btn-danger btn-modern btn-delete" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-terceiro-id="<?= $terceiro['id']; ?>" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                    </button>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="modal fade" id="modalTerceiroInfo" tabindex="-1" aria-labelledby="modalTerceiroLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="modalTerceiroLabel"><i class="fas fa-info-circle text-primary me-2"></i> Informações do Colaborador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body" id="modalTerceiroContent">
            </div>
            <div class="modal-footer bg-light" id="modalTerceiroFooter">
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title" id="confirmDeleteLabel"><i class="fas fa-exclamation-triangle text-danger me-2"></i> Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="text-center mb-4"><strong>Atenção!</strong> Esta ação não pode ser desfeita.</p>
                <p class="text-center">Deseja realmente excluir este terceiro?</p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="padding: 6px 12px; border-radius: 6px; font-weight: 500; font-size: 0.8rem; background: linear-gradient(135deg, #6c757d, #495057); color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: all 0.3s ease; border: none;" onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 4px 10px rgba(0,0,0,0.2)';" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 2px 5px rgba(0,0,0,0.1)';">
                    <i class="fas fa-times me-1"></i> Cancelar
                </button>
                <form method="POST" action="terceiros_actions.php" id="deleteForm" style="display:inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="terceiro_id" id="terceiroIdToDelete">
                    <button type="submit" class="btn btn-danger" style="padding: 6px 12px; border-radius: 6px; font-weight: 500; font-size: 0.8rem; background: linear-gradient(135deg, #dc3545, #a71d2a); color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: all 0.3s ease; border: none;" onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 4px 10px rgba(0,0,0,0.2)';" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 2px 5px rgba(0,0,0,0.1)';">
                        <i class="fas fa-trash me-1"></i> Excluir
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    console.log('Script carregado: Iniciando verificação de alertas e modais.');

    // Função para fechar alertas
    function closeAlert(alert) {
        console.log('Fechando alerta:', alert.id || alert.className);
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => {
            alert.remove();
            console.log('Alerta removido:', alert.id || alert.className);
        }, 500);
    }

    // Verifica alertas existentes no carregamento
    function checkAlerts() {
        const alerts = document.querySelectorAll('#successAlert, #errorAlert, .alert-success, .alert-danger');
        console.log('Alertas encontrados:', alerts.length);
        alerts.forEach(alert => {
            console.log('Processando alerta:', alert.id || alert.className);
            setTimeout(() => {
                try {
                    closeAlert(alert);
                } catch (e) {
                    console.error('Erro ao fechar alerta:', e);
                }
            }, 10000);
        });
    }

    // Executa verificação inicial de alertas
    checkAlerts();

    // Verifica continuamente a cada 1 segundo
    const interval = setInterval(checkAlerts, 1000);

    // Para o intervalo após 30 segundos
    setTimeout(() => {
        clearInterval(interval);
        console.log('Intervalo de verificação de alertas encerrado.');
    }, 30000);

    // Modal para informações do terceiro
    const modalElement = document.getElementById('modalTerceiroInfo');
    let modalInfo = null;
    if (modalElement && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        modalInfo = new bootstrap.Modal(modalElement);
        console.log('Bootstrap Modal inicializado com sucesso.');
    } else {
        console.error('Bootstrap Modal não disponível. Verifique se o Bootstrap JavaScript está carregado.');
    }

    const modalContent = document.getElementById('modalTerceiroContent');
    const modalFooter = document.getElementById('modalTerceiroFooter');

    // Adiciona evento de clique nas linhas da tabela
    const rows = document.querySelectorAll('table tbody tr');
    console.log('Linhas da tabela encontradas:', rows.length);
    rows.forEach(row => {
        row.addEventListener('click', async (event) => {
            console.log('Clique detectado na linha da tabela.');
            const isActionButton = event.target.closest('td:last-child button') !== null || event.target.closest('td:last-child a') !== null;

            if (isActionButton) {
                console.log('Clique em botão de ação, ignorando modal.');
                return;
            }

            if (!modalInfo) {
                console.error('Modal não inicializado. Não é possível exibir.');
                return;
            }

            const id = row.querySelector('td')?.innerText?.trim();
            const nomeColaborador = row.querySelector('td:nth-child(3)')?.innerText?.trim() || 'Colaborador';
            const status = row.querySelector('.badge')?.innerText?.trim() || 
                          (row.querySelector('.fa-unlock') ? 'LIBERADO' : 
                           row.querySelector('.fa-ban') ? 'BLOQUEADO' : '');
            console.log('Dados da linha:', { id, nomeColaborador, status });

            modalContent.innerHTML = '';
            modalFooter.innerHTML = '';

            if (status === 'LIBERADO') {
                try {
                    console.log('Buscando NRs para o terceiro ID:', id);
                    const res = await fetch(`get_nrs.php?id=${id}`);
                    const data = await res.json();
                    console.log('Resposta de get_nrs.php:', data);

                    const nrDescricoes = {
                        'NR 10 (Eletricidade)': 'Essencial para trabalhos elétricos (Instalações/Manutenções em Quadros Elétricos por exemplo).',
                        'NR 11 (Transporte e Movimentação)': 'Obrigatória para operar equipamentos (Empilhadeiras, PMTA).',
                        'NR 12 (Máquinas e Equipamentos)': 'Necessária para operar máquinas e equipamentos industriais.',
                        'NR 18 (Construção Civil)': 'Imprescindível para obras complexas (Construir, Demolir).',
                        'NR 20 (Inflamáveis e Combustíveis)': 'Obrigatória para qualquer trabalho com esses materiais.',
                        'NR 33 (Espaços Confinados)': 'Essencial para entrar nesses locais (Caixas d\'água, Forros)',
                        'NR 35 (Trabalho em Altura)': 'Necessária para trabalhos com risco de queda, acima de 2 metros (Troca de Telhas, Limpeza de Caixa d\'água por exemplo).'
                    };

                    const nrsObrigatorias = [
                        'NR 10 (Eletricidade)',
                        'NR 11 (Transporte e Movimentação)',
                        'NR 12 (Máquinas e Equipamentos)',
                        'NR 18 (Construção Civil)',
                        'NR 20 (Inflamáveis e Combustíveis)',
                        'NR 33 (Espaços Confinados)',
                        'NR 35 (Trabalho em Altura)'
                    ];

                    const nrMap = {
                        'NR 10': 'NR 10 (Eletricidade)',
                        'NR 11': 'NR 11 (Transporte e Movimentação)',
                        'NR 12': 'NR 12 (Máquinas e Equipamentos)',
                        'NR 18': 'NR 18 (Construção Civil)',
                        'NR 20': 'NR 20 (Inflamáveis e Combustíveis)',
                        'NR 33': 'NR 33 (Espaços Confinados)',
                        'NR 35': 'NR 35 (Trabalho em Altura)'
                    };

                    const nrsColaborador = data.nrs.map(nr => nrMap[nr] || nr);
                    const nrsFaltantes = nrsObrigatorias.filter(nr => !nrsColaborador.includes(nr));

                    modalContent.innerHTML = `
                        <div class="mb-3 text-center">
                            <h4><i class="fas fa-user-check text-success me-2"></i> Colaborador Liberado</h4>
                            <hr class="my-2">
                        </div>
                        <p class="text-center"><strong>${nomeColaborador}</strong> está apto para as atividades:</p>
                        <ul class="list-group list-group-flush mb-3">
                            ${nrsColaborador.map(nr => `<li class="list-group-item"><strong>${nr}:</strong> <span class="text-muted">${nrDescricoes[nr] || 'Descrição não disponível'}</span></li>`).join('')}
                        </ul>
                        ${nrsFaltantes.length > 0 ? `
                            <div class="alert alert-warning mt-3" role="alert">
                                <i class="fas fa-exclamation-triangle text-warning me-2"></i><strong>ATENÇÃO:</strong><br><br>
                                Colaborador NÃO POSSUI liberação para as atividades:
                                <ul class="list-unstyled" style="font-size: 0.8rem;"><br>
                                    ${nrsFaltantes.map(nr => `<li style="margin-bottom: 0.5rem;">- <strong>${nr}</strong> - ${nrDescricoes[nr] || 'Descrição não disponível'}</li>`).join('')}
                                </ul>
                            </div>
                        ` : ''}
                        <div class="d-flex justify-content-center">
                            <a href="liberar_atividade.php?id=${id}" class="btn btn-success btn-lg shadow-sm" id="liberarInicioAtividadeBtn"><i class="fas fa-play-circle me-2"></i> Liberar Início da Atividade</a>
                        </div>
                    `;
                } catch (e) {
                    console.error('Erro ao buscar NRs:', e);
                    modalContent.innerHTML = '<p class="text-danger text-center"><i class="fas fa-exclamation-triangle me-2"></i> Erro ao buscar dados.</p>';
                    modalFooter.innerHTML = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i> Fechar</button>';
                }
            } else if (status === 'BLOQUEADO') {
                modalContent.innerHTML = `
                    <div class="mb-3 text-center">
                        <h4><i class="fas fa-ban text-danger me-2"></i> Acesso Bloqueado</h4>
                        <hr class="my-2">
                        <p>O colaborador <strong>${nomeColaborador}</strong> está impedido de iniciar atividades na unidade.</p>
                        <p class="mt-3"><i class="fas fa-shield-alt text-info me-2"></i> Em caso de dúvidas, entre em contato com o Departamento de Segurança do Trabalho.</p>
                    </div>
                `;
                modalFooter.innerHTML = '';
            }

            console.log('Exibindo modal para o terceiro:', nomeColaborador);
            modalInfo.show();
        });
    });

    // Handle delete modal
    const deleteButtons = document.querySelectorAll('[data-bs-target="#confirmDeleteModal"]');
    console.log('Botões de exclusão encontrados:', deleteButtons.length);
    deleteButtons.forEach(button => {
        button.addEventListener('click', () => {
            const terceiroId = button.getAttribute('data-terceiro-id');
            console.log('Configurando ID para exclusão:', terceiroId);
            document.getElementById('terceiroIdToDelete').value = terceiroId;
        });
    });
});
</script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha512-Fo3rlrZj/k7ujTnHg4CGR2D7kSs0v4LLanw2qksYuRlEzO+tcaEPQogQ0KaoGN26/zrn20ImR1DfuLWnOo7aBA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<?php require_once __DIR__ . '/includes/footer.php'; ?>