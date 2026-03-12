<?php
ob_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$page_title = 'Liberação de Atividade';
require_once __DIR__ . '/includes/header.php';

global $pdo;

$terceiro_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$terceiro_id) {
    $_SESSION['error_message'] = 'ID do colaborador inválido.';
    header('Location: monitoramento.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT t.nome_completo, e.nome as empresa_nome, f.nome as filial_nome
        FROM terceiros t
        JOIN empresas e ON t.empresa_id = e.id
        JOIN filiais f ON t.filial_id = f.id
        WHERE t.id = ?
    ");
    $stmt->execute([$terceiro_id]);
    $terceiro = $stmt->fetch();

    if (!$terceiro) {
        $_SESSION['error_message'] = 'Colaborador não encontrado.';
        header('Location: monitoramento.php');
        exit;
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Erro ao buscar dados do colaborador.';
    error_log('Erro Liberação: ' . $e->getMessage());
    header('Location: monitoramento.php');
    exit;
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

<h3>Liberação de Atividade</h3>

<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-user-check text-success me-2"></i> Termo de Liberação - <?= html_escape($terceiro['nome_completo']); ?></h5>
    </div>
    <div class="card-body">
        <p><strong>Colaborador:</strong> <?= html_escape($terceiro['nome_completo']); ?></p>
        <p><strong>Empresa:</strong> <?= html_escape($terceiro['empresa_nome']); ?></p>
        <p><strong>Filial:</strong> <?= html_escape($terceiro['filial_nome']); ?></p>
        <hr>
        <h5>TERMO DE CIÊNCIA E COMPROMETIMENTO COM AS NORMAS DE SEGURANÇA</h5>
        <div class="border p-3 mb-3" style="max-height: 200px; overflow-y: auto;">
            <p>Eu, <strong><?= html_escape($terceiro['nome_completo']); ?></strong>, declaro que recebi e compreendi as diretrizes de segurança da <strong>CTDI do Brasil Ltda.</strong>. Comprometo-me a seguir as normas estabelecidas e a utilizar corretamente os Equipamentos de Proteção Individual (EPIs) durante toda a atividade.</p>
            <p>Estou ciente de que o descumprimento dessas regras pode resultar em medidas conforme as políticas da empresa.</p>
        </div>
        <form method="POST" action="processar_atividade.php" id="assinaturaForm">
            <input type="hidden" name="terceiro_id" value="<?= $terceiro_id; ?>">
            <input type="hidden" name="nome_colaborador" value="<?= html_escape($terceiro['nome_completo']); ?>">
            <input type="hidden" name="assinatura_data" id="assinatura_data">
            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="aceite" name="aceite" required>
                    <label class="form-check-label" for="aceite">Li e concordo com o termo de responsabilidade. O termo será gerado como documento Word após a confirmação.</label>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Assinatura:</label>
                <div class="border rounded" style="position: relative; width: 100%; max-width: 100%;">
                    <canvas id="signaturePad" class="signature-pad" style="width: 100%; height: 200px;"></canvas>
                </div>
                <div class="mt-2">
                    <button type="button" class="btn btn-secondary btn-sm" id="clearSignature"><i class="fas fa-eraser me-2"></i> Limpar</button>
                </div>
            </div>
            <button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-2"></i> Confirmar Liberação</button>
            <a href="monitoramento.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Voltar</a>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('signaturePad');
    const signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgb(255, 255, 255)',
        penColor: 'rgb(0, 0, 0)'
    });

    const clearButton = document.getElementById('clearSignature');
    const form = document.getElementById('assinaturaForm');
    const assinaturaData = document.getElementById('assinatura_data');

    // Função corrigida para responsividade
    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        
        // Define o tamanho de exibição (CSS)
        canvas.style.width = '100%';
        canvas.style.height = '200px';

        // Define o tamanho real do buffer de desenho
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        
        // Escala o contexto para corresponder ao ratio, para que a assinatura não fique borrada
        const ctx = canvas.getContext('2d');
        ctx.scale(ratio, ratio);
        
        // Limpa o canvas após o redimensionamento
        signaturePad.clear(); 
    }

    // Redimensiona o canvas quando a janela muda de tamanho e na carga inicial
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    clearButton.addEventListener('click', () => {
        signaturePad.clear();
    });

    form.addEventListener('submit', (e) => {
        if (signaturePad.isEmpty()) {
            e.preventDefault();
            alert('Por favor, forneça sua assinatura.');
            return;
        }
        // Converte a assinatura para uma imagem PNG em formato Base64
        assinaturaData.value = signaturePad.toDataURL('image/png');
    });
});
</script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha512-Fo3rlrZj/k7ujTnHg4CGR2D7kSs0v4LLanw2qksYuRlEzO+tcaEPQogQ0KaoGN26/zrn20ImR1DfuLWnOo7aBA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<?php require_once __DIR__ . '/includes/footer.php'; ?>