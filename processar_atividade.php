<?php
ob_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php'; // Usar o autoloader do Composer
require_once __DIR__ . '/includes/libs/fpdf186/fpdf.php'; // Incluir FPDF

require_login();

global $pdo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Método de requisição inválido.';
    header('Location: monitoramento.php');
    exit;
}

$terceiro_id = filter_input(INPUT_POST, 'terceiro_id', FILTER_VALIDATE_INT);
$nome_colaborador = trim($_POST['nome_colaborador'] ?? '');
$aceite = isset($_POST['aceite']) ? 1 : 0;
$assinatura_data = $_POST['assinatura_data'] ?? '';

if (!$terceiro_id || !$nome_colaborador || !$aceite || !$assinatura_data) {
    $_SESSION['error_message'] = 'Todos os campos são obrigatórios, incluindo a assinatura.';
    header('Location: liberar_atividade.php?id=' . $terceiro_id);
    exit;
}

// Processar a imagem da assinatura
$assinatura_path = null;
if (preg_match('/^data:image\/(\w+);base64,/', $assinatura_data, $type)) {
    $data = substr($assinatura_data, strpos($assinatura_data, ',') + 1);
    $type = strtolower($type[1]); // png

    if (!in_array($type, ['png'])) {
        $_SESSION['error_message'] = 'Formato de imagem inválido. Use PNG.';
        header('Location: liberar_atividade.php?id=' . $terceiro_id);
        exit;
    }

    $data = base64_decode($data);
    if ($data === false) {
        $_SESSION['error_message'] = 'Erro ao decodificar a assinatura.';
        header('Location: liberar_atividade.php?id=' . $terceiro_id);
        exit;
    }

    $upload_dir = __DIR__ . '/uploads/assinaturas/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $assinatura_filename = 'assinatura_' . $terceiro_id . '_' . time() . '.' . $type;
    $assinatura_path = 'assinaturas/' . $assinatura_filename;
    $full_path_assinatura = $upload_dir . $assinatura_filename;

    if (!file_put_contents($full_path_assinatura, $data)) {
        $_SESSION['error_message'] = 'Erro ao salvar a assinatura.';
        header('Location: liberar_atividade.php?id=' . $terceiro_id);
        exit;
    }
} else {
    $_SESSION['error_message'] = 'Dados da assinatura inválidos.';
    header('Location: liberar_atividade.php?id=' . $terceiro_id);
    exit;
}

// Obter a filial do colaborador
try {
    $stmt = $pdo->prepare("
        SELECT f.nome as filial_nome
        FROM terceiros t
        JOIN filiais f ON t.filial_id = f.id
        WHERE t.id = ?
    ");
    $stmt->execute([$terceiro_id]);
    $filial = $stmt->fetch();

    if (!$filial) {
        $_SESSION['error_message'] = 'Filial do colaborador não encontrada.';
        header('Location: liberar_atividade.php?id=' . $terceiro_id);
        exit;
    }

    // Mapear filial para cidade
    $filial_nome = $filial['filial_nome'];
    $cidade = 'São Paulo'; // Cidade padrão
    if ($filial_nome === 'CSP Manaus') {
        $cidade = 'Manaus';
    } elseif (in_array($filial_nome, ['CSP São Miguel', 'CSP Pinheiros'])) {
        $cidade = 'São Paulo';
    } elseif (in_array($filial_nome, ['GLP', 'Matriz'])) {
        $cidade = 'Campinas';
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Erro ao buscar filial do colaborador.';
    error_log('Erro ao buscar filial: ' . $e->getMessage());
    header('Location: liberar_atividade.php?id=' . $terceiro_id);
    exit;
}

// Obter a data atual no formato brasileiro
setlocale(LC_TIME, 'pt_BR.UTF-8', 'pt_BR', 'portuguese');
$data_atual = strftime("{$cidade}, %d de %B de %Y", time());

// Criar diretório para documentos se não existir
$doc_dir = __DIR__ . '/uploads/docs/';
if (!is_dir($doc_dir)) {
    mkdir($doc_dir, 0755, true);
}

// Gerar nome do arquivo PDF
$pdf_filename = 'termo_' . $terceiro_id . '_' . time() . '.pdf';
$pdf_path = 'docs/' . $pdf_filename;
$full_path_pdf = $doc_dir . $pdf_filename;

// Criar PDF com FPDF
try {
    // Inicializar PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetMargins(20, 20, 20);
    
    // Configurar fontes
    $pdf->SetFont('Arial', 'B', 14);
    
    // Título
    $pdf->Cell(0, 10, utf8_decode('TERMO DE CIÊNCIA E COMPROMETIMENTO COM AS NORMAS DE SEGURANÇA'), 0, 1, 'C');
    $pdf->Ln(10);
    
    // Conteúdo do termo
    $pdf->SetFont('Arial', '', 12);
    $pdf->Write(6, utf8_decode('Eu, '));
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Write(6, utf8_decode($nome_colaborador));
    $pdf->SetFont('Arial', '', 12);
    $pdf->Write(6, utf8_decode(', declaro que recebi e compreendi as diretrizes de segurança da '));
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Write(6, utf8_decode('CTDI do Brasil Ltda.'));
    $pdf->SetFont('Arial', '', 12);
    $pdf->Write(6, utf8_decode(' Comprometo-me a seguir as normas estabelecidas e a utilizar corretamente os Equipamentos de Proteção Individual (EPIs) durante toda a atividade.'));
    
    $pdf->Ln(10);
    $pdf->Write(6, utf8_decode('Estou ciente de que o descumprimento dessas regras pode resultar em medidas conforme as políticas da empresa.'));
    
    $pdf->Ln(20);
    
    // Data
    $pdf->Cell(0, 6, utf8_decode($data_atual), 0, 1, 'R');
    $pdf->Ln(20);
    
    // Assinatura
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 6, utf8_decode('Assinatura:'), 0, 1, 'L');
    $pdf->Ln(5);
    
    // Adicionar imagem da assinatura
    if (file_exists($full_path_assinatura)) {
        $pdf->Image($full_path_assinatura, 20, null, 60);
    }
    
    // Salvar o PDF
    $pdf->Output('F', $full_path_pdf);
    
    if (!file_exists($full_path_pdf)) {
        throw new Exception("Falha ao gerar o arquivo PDF");
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Erro ao gerar o PDF: ' . $e->getMessage();
    error_log('Erro ao gerar PDF: ' . $e->getMessage());
    header('Location: liberar_atividade.php?id=' . $terceiro_id);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO log_atividades (terceiro_id, nome_colaborador, data_liberacao, termo_aceito, assinatura, doc_path)
        VALUES (?, ?, NOW(), ?, ?, ?)
    ");
    $stmt->execute([$terceiro_id, $nome_colaborador, $aceite, $assinatura_path, $pdf_path]);

    $_SESSION['success_message'] = 'Atividade liberada com sucesso! O termo foi gerado como documento PDF.';
    header('Location: monitoramento.php?highlight=' . $terceiro_id);
    exit;
} catch (Exception $e) {
    if ($assinatura_path && file_exists($full_path_assinatura)) {
        unlink($full_path_assinatura);
    }
    if ($pdf_path && file_exists($full_path_pdf)) {
        unlink($full_path_pdf);
    }
    $_SESSION['error_message'] = 'Erro ao liberar atividade.';
    error_log('Erro Processamento Atividade: ' . $e->getMessage());
    header('Location: liberar_atividade.php?id=' . $terceiro_id);
    exit;
}
?>
