<?php
/**
 * Listagem de Funcionários
 * Sistema de Gerenciamento de Funcionários
 */

// Simulação de constantes e funções para o exemplo funcionar de forma isolada
if (!defined('SITE_NAME')) define('SITE_NAME', 'Meu Sistema');
if (!defined('SITE_URL')) define('SITE_URL', ''); // Deixe em branco para caminhos relativos no exemplo

function verificarPermissao($acao) {
    // Simulação: permite tudo para o exemplo
    return true;
}

function formatarCPF($cpf) {
    // Simulação de formatação
    return preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "\$1.\$2.\$3-\$4", $cpf);
}

function gerarTokenCSRF() {
    // Simulação de token
    return 'exemplo_de_token_csrf';
}

class Utils {
    public static function formatDate($date) {
        if (empty($date)) return 'N/A';
        return date('d/m/Y', strtotime($date));
    }
}

// Simulação de dados do banco de dados
$funcionarios = [
    [
        'id' => 1,
        'nome' => "João D'Silva",
        'cpf' => '11122233344',
        'matricula' => 'MAT001',
        'foto' => null,
        'aso_data' => '2025-12-31',
        'aso_status' => 'valido',
        'treinamentos_vencidos' => 0,
        'treinamentos_a_vencer' => 0,
        'postos_trabalho' => 'Portaria Principal',
        'aptidao_trabalho' => 'apto',
    ],
    [
        'id' => 2,
        'nome' => 'Maria Oliveira',
        'cpf' => '44455566677',
        'matricula' => 'MAT002',
        'foto' => 'caminho/para/foto.jpg', // Simulação de foto
        'aso_data' => '2024-08-20',
        'aso_status' => 'vencido',
        'treinamentos_vencidos' => 1,
        'treinamentos_a_vencer' => 0,
        'postos_trabalho' => 'Recepção',
        'aptidao_trabalho' => 'inapto',
    ],
];

$empresas = [
    ['id' => 1, 'razao_social' => 'Empresa A'],
    ['id' => 2, 'razao_social' => 'Empresa B'],
];

$postos = [
     ['id' => 10, 'nome' => 'Portaria Principal'],
     ['id' => 11, 'nome' => 'Recepção'],
];

$filtros = [
    'nome' => $_GET['nome'] ?? '',
    'cpf' => $_GET['cpf'] ?? '',
    'matricula' => $_GET['matricula'] ?? '',
    'empresa_id' => $_GET['empresa_id'] ?? '',
    'posto_id' => $_GET['posto_id'] ?? '',
    'status_aso' => $_GET['status_aso'] ?? ''
];

$page_title = 'Funcionários - ' . SITE_NAME;
// require_once $_SERVER["DOCUMENT_ROOT"] . "/includes/header.php"; // Comentado para o exemplo
?>

<!-- Adicionei Tailwind CSS para estilização do exemplo e da notificação -->
<script src="https://cdn.tailwindcss.com"></script>
<style>
    /* Estilos básicos para se assemelhar ao seu layout */
    body { font-family: sans-serif; background-color: #f9fafb; padding: 2rem; }
    .card { background-color: white; border-radius: 0.5rem; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1); margin-top: 1.5rem; }
    .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
    .card-title { font-weight: 600; font-size: 1.125rem; }
    .table-container { overflow-x: auto; }
    .table { width: 100%; text-align: left; }
    .table th, .table td { padding: 0.75rem 1.5rem; border-bottom: 1px solid #e5e7eb; }
    .table th { font-size: 0.75rem; text-transform: uppercase; color: #6b7280; }
    .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.375rem; border: 1px solid transparent; font-weight: 500; cursor: pointer; }
    .btn-primary { background-color: #2563eb; color: white; border-color: #2563eb; }
    .btn-danger { background-color: #dc2626; color: white; border-color: #dc2626; }
    .btn-secondary { background-color: #e5e7eb; color: #1f2937; }
    .btn-outline { border: 1px solid #d1d5db; background-color: white; }
    .badge { padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
    .badge-success { background-color: #dcfce7; color: #166534; }
    .badge-danger { background-color: #fee2e2; color: #991b1b; }
    .badge-warning { background-color: #fef3c7; color: #92400e; }
    .modal { display: none; position: fixed; z-index: 50; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
    .modal.show { display: flex; }
    .modal-content { background-color: white; padding: 1.5rem; border-radius: 0.5rem; width: 100%; max-width: 400px; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
    .modal-title { font-size: 1.25rem; font-weight: 600; }
    .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; }
    .modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem; }
</style>

<div class="funcionarios-page">
    <!-- ... (seu cabeçalho e filtros aqui) ... -->

    <!-- Resultados -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                Funcionários Cadastrados (<?php echo count($funcionarios); ?>)
            </h3>
        </div>
        
        <?php if (empty($funcionarios)): ?>
            <div class="p-8 text-center">
                <h3>Nenhum funcionário encontrado</h3>
                <p>Não há funcionários cadastrados com os filtros selecionados.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Funcionário</th>
                            <th>CPF</th>
                            <th>Matrícula</th>
                            <th>ASO</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($funcionarios as $funcionario): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($funcionario['nome']); ?></td>
                            <td><?php echo formatarCPF($funcionario['cpf']); ?></td>
                            <td><?php echo htmlspecialchars($funcionario['matricula']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $funcionario['aso_status'] === 'vencido' ? 'danger' : 'success'; ?>">
                                    <?php echo Utils::formatDate($funcionario['aso_data']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <?php if (verificarPermissao('delete')): ?>
                                    <!-- BOTÃO CORRIGIDO: Usando json_encode para passar o nome de forma segura -->
                                    <button type="button" class="btn btn-outline text-red-600" title="Excluir"
                                        onclick="confirmarExclusao(<?php echo $funcionario['id']; ?>, <?php echo htmlspecialchars(json_encode($funcionario['nome']), ENT_QUOTES, 'UTF-8'); ?>)">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3,6 5,6 21,6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
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
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Confirmar Exclusão</h5>
            <button type="button" class="modal-close" onclick="Modal.hide('confirmDeleteModal')">×</button>
        </div>
        <div class="modal-body">
            <p>Tem certeza que deseja excluir o funcionário "<strong id="funcionarioNomeExclusao"></strong>"?</p>
            <p class="text-red-600">Esta ação não pode ser desfeita.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="Modal.hide('confirmDeleteModal')">Cancelar</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Excluir</button>
        </div>
    </div>
</div>

<!-- Elemento para Notificações -->
<div id="notification" class="fixed bottom-5 right-5 bg-gray-800 text-white py-3 px-5 rounded-lg shadow-lg transition-all duration-300 opacity-0 transform translate-y-4 z-50">
    <p id="notificationMessage"></p>
</div>


<?php // require_once $_SERVER["DOCUMENT_ROOT"] . "/includes/footer.php"; // Comentado para o exemplo ?>

<script>
// Objeto simples para controlar o Modal
const Modal = {
    show(id) {
        const modal = document.getElementById(id);
        if(modal) modal.classList.add('show');
    },
    hide(id) {
        const modal = document.getElementById(id);
        if(modal) modal.classList.remove('show');
    }
};

// Função para mostrar notificações (substitui o alert)
function showNotification(message, isError = false) {
    const notification = document.getElementById('notification');
    const messageElement = document.getElementById('notificationMessage');

    if (!notification || !messageElement) return;

    messageElement.textContent = message;
    
    // Remove classes de cor anteriores e adiciona a correta
    notification.classList.remove('bg-red-600', 'bg-green-600');
    if (isError) {
        notification.classList.add('bg-red-600');
    } else {
        notification.classList.add('bg-green-600');
    }

    // Animação de entrada
    notification.classList.remove('opacity-0', 'translate-y-4');

    // Esconde a notificação após 3 segundos
    setTimeout(() => {
        notification.classList.add('opacity-0', 'translate-y-4');
    }, 3000);
}


// Função para confirmar exclusão
function confirmarExclusao(id, nome) {
    document.getElementById('funcionarioNomeExclusao').textContent = nome;
    Modal.show('confirmDeleteModal');
    
    // É importante redefinir o onclick para evitar que eventos antigos se acumulem
    document.getElementById('confirmDeleteBtn').onclick = function() {
        Modal.hide('confirmDeleteModal');
        
        // <-- LINHA CORRIGIDA: Usa concatenação simples, que é mais segura aqui.
        const url = '<?php echo SITE_URL; ?>/api/funcionarios/' + id;
        
        // Enviar requisição DELETE
        fetch(url, {
            method: 'DELETE',
            headers: {
                'X-CSRF-Token': '<?php echo gerarTokenCSRF(); ?>',
                'Content-Type': 'application/json'
            }
        })
        .then(response => {
             // Se a resposta for OK (status 200-299), tentamos ler o JSON.
             // Se não, rejeitamos a promessa para cair no .catch()
            if (response.ok) {
                return response.json();
            }
            return Promise.reject(response);
        })
        .then(data => {
            if (data.success) {
                showNotification('Funcionário excluído com sucesso!');
                // Recarrega a página para atualizar a lista após um pequeno delay
                setTimeout(() => location.reload(), 1500);
            } else {
                // Se a API retornar success: false
                showNotification(data.message || 'Ocorreu um erro na API.', true);
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
            // Se a requisição falhar (rede, 404, 500, etc.)
            showNotification('Erro na requisição. Verifique o console.', true);
        });
    };
}
</script>
