<?php
// Iniciar buffer de saída para evitar tela em branco
ob_start();

// Corrigido os caminhos para incluir arquivos da pasta includes/
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/functions.php';

// Requer login
require_login();

$page_title = 'Cadastrar/Editar Terceiro';
// Usar require_once para header e footer
require_once __DIR__ . '/includes/header.php';

// $pdo já deve estar disponível globalmente via connection.php
global $pdo;

$empresas = get_todas_empresas();
$filiais = get_todas_filiais();
$edit_terceiro = null;
$error_message = null;

// Garante que a sessão está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// URL da imagem padrão
define('DEFAULT_IMAGE_URL', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcREumLfonBJmjgM0aRzzwaWzcicE0n9DW8t_w&s');

// Função para baixar e salvar a imagem padrão
function downloadDefaultImage($upload_dir) {
    $default_image_name = 'default_' . uniqid() . '.jpg';
    $destination = $upload_dir . $default_image_name;

    try {
        // Baixa a imagem da URL
        $image_content = file_get_contents(DEFAULT_IMAGE_URL);
        if ($image_content === false) {
            throw new Exception('Falha ao baixar a imagem padrão.');
        }

        // Garante que o diretório de upload existe
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }

        // Salva a imagem no diretório de upload
        if (file_put_contents($destination, $image_content) === false) {
            throw new Exception('Falha ao salvar a imagem padrão.');
        }

        return $default_image_name;
    } catch (Exception $e) {
        error_log("Erro ao baixar/salvar imagem padrão: " . $e->getMessage());
        return null; // Retorna null se houver erro
    }
}

// Buscar terceiro para edição
if (isset($_GET['edit_id'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id) {
        // Adicionar verificação de permissão de filial para usuário comum
        $filiais_permitidas_sql = '';
        $allowed_ids = []; // Inicializa $allowed_ids
        if (!is_admin() && !empty($_SESSION['user_filiais'])) {
            $allowed_ids = explode(',', $_SESSION['user_filiais']);
            $allowed_ids = array_filter(array_map('intval', $allowed_ids)); // Limpa e converte para int
            if (!empty($allowed_ids)) {
                $placeholders = implode(',', array_fill(0, count($allowed_ids), '?'));
                $filiais_permitidas_sql = " AND filial_id IN ($placeholders)";
            }
        } elseif (!is_admin() && empty($_SESSION['user_filiais'])) {
            // Usuário comum sem filiais não pode editar ninguém
            $filiais_permitidas_sql = " AND 1=0"; // Condição sempre falsa
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM terceiros WHERE id = ? $filiais_permitidas_sql");
            $params = array_merge([$edit_id], $allowed_ids);
            $stmt->execute($params);
            $edit_terceiro = $stmt->fetch();

            if (!$edit_terceiro) {
                $_SESSION['error_message'] = 'Terceiro não encontrado ou acesso não permitido.';
                header('Location: monitoramento.php');
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'Erro ao buscar terceiro: ' . $e->getMessage();
            error_log("Erro buscar terceiro edit: " . $e->getMessage());
            header('Location: monitoramento.php');
            exit;
        }
    }
}

// Processar formulário (Adicionar ou Editar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $terceiro_id = filter_input(INPUT_POST, 'terceiro_id', FILTER_VALIDATE_INT);

    // Coleta e sanitização básica dos dados
    $nome_completo = trim($_POST['nome_completo'] ?? '');
    $empresa_id = filter_input(INPUT_POST, 'empresa_id', FILTER_VALIDATE_INT);
    $filial_id = filter_input(INPUT_POST, 'filial_id', FILTER_VALIDATE_INT);
    $observacoes = trim($_POST['observacoes'] ?? '');

    // Datas (validação básica, idealmente usar JS para formato)
    $datas = [
        'aso_data' => $_POST['aso_data'] ?? null,
        'epi_data' => $_POST['epi_data'] ?? null,
        'nr10_data' => $_POST['nr10_data'] ?? null,
        'nr11_data' => $_POST['nr11_data'] ?? null,
        'nr12_data' => $_POST['nr12_data'] ?? null,
        'nr18_data' => $_POST['nr18_data'] ?? null,
        'integracao_data' => $_POST['integracao_data'] ?? null,
        'nr20_data' => $_POST['nr20_data'] ?? null,
        'nr33_data' => $_POST['nr33_data'] ?? null,
        'nr35_data' => $_POST['nr35_data'] ?? null,
    ];

    // Processar aplicabilidade (garantir que 0 ou 1 seja salvo)
    $aplicaveis = [
        'aso_aplicavel' => 1, // Fixado em Sim
        'epi_aplicavel' => filter_input(INPUT_POST, 'epi_aplicavel', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]),
        'nr10_aplicavel' => filter_input(INPUT_POST, 'nr10_aplicavel', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]),
        'nr11_aplicavel' => filter_input(INPUT_POST, 'nr11_aplicavel', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]),
        'nr12_aplicavel' => filter_input(INPUT_POST, 'nr12_aplicavel', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]),
        'nr18_aplicavel' => filter_input(INPUT_POST, 'nr18_aplicavel', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]),
        'integracao_aplicavel' => filter_input(INPUT_POST, 'integracao_aplicavel', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]),
        'nr20_aplicavel' => filter_input(INPUT_POST, 'nr20_aplicavel', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]),
        'nr33_aplicavel' => filter_input(INPUT_POST, 'nr33_aplicavel', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]),
        'nr35_aplicavel' => filter_input(INPUT_POST, 'nr35_aplicavel', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]),
    ];

    // Validação básica
    $error_message = null;
    if (empty($nome_completo) || empty($empresa_id) || empty($filial_id)) {
        $error_message = 'Nome completo, Empresa e Filial são obrigatórios.';
    } elseif (empty(trim($datas['aso_data']))) {
        $error_message = 'A data do ASO é obrigatória.';
    } else {
        // Validar formato da data do ASO
        if (!empty($datas['aso_data'])) {
            $date_obj = DateTime::createFromFormat('d/m/Y', $datas['aso_data']);
            if (!$date_obj || $date_obj->format('d/m/Y') !== $datas['aso_data']) {
                $error_message = 'A data do ASO é inválida. Use o formato DD/MM/AAAA (ex.: 22/05/2023).';
            }
        }

        // Tratamento do Upload da Foto
        $foto_path = $edit_terceiro['foto_path'] ?? null; // Mantém a foto existente por padrão
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
            $foto_tmp_name = $_FILES['foto']['tmp_name'];
            $foto_name = $_FILES['foto']['name'];
            $foto_size = $_FILES['foto']['size'];
            $foto_type = $_FILES['foto']['type'];
            $foto_ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));

            $allowed_types = ['jpg', 'jpeg', 'png'];
            $max_size = 2 * 1024 * 1024; // 2MB

            if (in_array($foto_ext, $allowed_types) && $foto_size <= $max_size) {
                // Gera um nome único para o arquivo
                $new_foto_name = uniqid('foto_', true) . '.' . $foto_ext;
                $destination = UPLOAD_DIR . $new_foto_name;

                // Garante que o diretório de upload existe
                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0775, true);
                }

                if (move_uploaded_file($foto_tmp_name, $destination)) {
                    // Remove a foto antiga se existir e for diferente da nova
                    if ($foto_path && file_exists(UPLOAD_DIR . $foto_path) && basename($foto_path) !== $new_foto_name) {
                        @unlink(UPLOAD_DIR . $foto_path);
                    }
                    $foto_path = $new_foto_name; // Atualiza o caminho da foto
                } else {
                    $error_message = 'Erro ao mover o arquivo da foto.';
                }
            } else {
                $error_message = 'Erro no upload: Arquivo inválido (permitido: JPG, PNG até 2MB).';
            }
        } elseif ($_FILES['foto']['error'] == UPLOAD_ERR_NO_FILE && !$foto_path) {
            // Nenhuma foto enviada e nenhuma foto existente: usar imagem padrão
            $foto_path = downloadDefaultImage(UPLOAD_DIR);
            if (!$foto_path) {
                $error_message = 'Erro ao carregar a imagem padrão.';
            }
        }

        // Continua apenas se não houve erro
        if (!$error_message) {
            try {
                // Verifica permissão de filial para usuário comum ANTES de salvar
                if (!is_admin()) {
                    $allowed_ids = explode(',', $_SESSION['user_filiais'] ?? '');
                    $allowed_ids = array_filter(array_map('intval', $allowed_ids));
                    if (empty($allowed_ids) || !in_array($filial_id, $allowed_ids)) {
                        throw new Exception('Usuário não tem permissão para esta filial.');
                    }
                    // Se for update, verifica se o ID original pertence às filiais permitidas
                    if ($action === 'update' && $terceiro_id) {
                        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM terceiros WHERE id = ? AND filial_id IN (".implode(',', array_fill(0, count($allowed_ids), '?')).")");
                        $check_params = array_merge([$terceiro_id], $allowed_ids);
                        $stmt_check->execute($check_params);
                        if ($stmt_check->fetchColumn() == 0) {
                            throw new Exception('Usuário não tem permissão para editar este terceiro.');
                        }
                    }
                }

                $sql_fields = 'nome_completo = ?, empresa_id = ?, filial_id = ?, observacoes = ?, foto_path = ?';
                $params = [$nome_completo, $empresa_id, $filial_id, $observacoes, $foto_path];

                foreach ($datas as $key => $value) {
                    $sql_fields .= ', ' . $key . ' = ?';
                    // Converte data BR para formato SQL (YYYY-MM-DD) ou NULL
                    $date_obj = DateTime::createFromFormat('d/m/Y', $value);
                    $params[] = ($date_obj) ? $date_obj->format('Y-m-d') : null;
                }
                foreach ($aplicaveis as $key => $value) {
                    $sql_fields .= ', ' . $key . ' = ?';
                    $params[] = $value;
                }

                if ($action === 'add') {
                    $sql = "INSERT INTO terceiros SET $sql_fields";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $new_id = $pdo->lastInsertId();
                    $_SESSION['just_added'] = $new_id; // Marca que foi adicionado
                    header('Location: terceiros.php'); // Redireciona para terceiros.php
                    exit;
                } elseif ($action === 'update' && $terceiro_id) {
                    $sql = "UPDATE terceiros SET $sql_fields WHERE id = ?";
                    $params[] = $terceiro_id;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $_SESSION['success_message'] = 'Terceiro atualizado com sucesso!';
                    header('Location: monitoramento.php?highlight='.$terceiro_id); // Redireciona após sucesso
                    exit;
                }
            } catch (PDOException $e) {
                $error_message = 'Erro ao salvar no banco de dados: ' . $e->getMessage();
                error_log("Erro Salvar Terceiro PDO: " . $e->getMessage()); // Logar erro
            } catch (Exception $e) {
                $error_message = 'Erro: ' . $e->getMessage();
                error_log("Erro Salvar Terceiro Geral: " . $e->getMessage()); // Logar erro
            }
        }
    }
    // Se houve erro, recarrega os dados do formulário com os valores POST
    if ($error_message) {
        $edit_terceiro = $_POST; // Preenche com dados submetidos
        $edit_terceiro['id'] = $terceiro_id; // Mantém o ID se for edição
        // Adiciona os 'aplicavel' que não vêm no POST se desmarcados
        foreach ($aplicaveis as $key => $value) {
            if ($key !== 'aso_aplicavel' && !isset($edit_terceiro[$key])) {
                $edit_terceiro[$key] = 0;
            }
        }
        // Mantém a foto existente se o upload falhou e era uma edição
        if ($action === 'update' && $terceiro_id && !isset($foto_path)) {
            try {
                $stmt_foto = $pdo->prepare("SELECT foto_path FROM terceiros WHERE id = ?");
                $stmt_foto->execute([$terceiro_id]);
                $edit_terceiro['foto_path'] = $stmt_foto->fetchColumn();
            } catch (PDOException $e) {
                // Ignora erro ao buscar foto antiga
            }
        }
    }
}

// Define os campos de documento/NR para o formulário
$documentos_campos = [
    ['id' => 'aso', 'label' => 'ASO (Validade: 1 ano)'],
    ['id' => 'epi', 'label' => 'Treinamento EPI (Registro)'],
    ['id' => 'nr10', 'label' => 'NR 10 (Validade: 2 anos)'],
    ['id' => 'nr11', 'label' => 'NR 11 (Validade: 1 ano)'],
    ['id' => 'nr12', 'label' => 'NR 12 (Validade: 1 ano)'],
    ['id' => 'nr18', 'label' => 'NR 18 (Validade: 1 ano)'],
    ['id' => 'integracao', 'label' => 'Integração (Validade: 1 ano)'],
    ['id' => 'nr20', 'label' => 'NR 20 (Validade: 1 ano)'],
    ['id' => 'nr33', 'label' => 'NR 33 (Validade: 1 ano)'],
    ['id' => 'nr35', 'label' => 'NR 35 (Validade: 2 anos)'],
];

// Verifica se acabou de adicionar um terceiro
$just_added = isset($_SESSION['just_added']) ? $_SESSION['just_added'] : null;
if ($just_added) {
    unset($_SESSION['just_added']); // Limpa a variável após uso
    unset($_SESSION['success_message']); // Limpa mensagem de sucesso para evitar exibição
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

/* Estilos específicos para botões primários e secundários para manter consistência com outras páginas */
.btn-primary.btn-modern {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
}

.btn-secondary.btn-modern {
    background: linear-gradient(135deg, #6c757d, #495057);
    color: white;
}

.btn-success.btn-modern {
    background: linear-gradient(135deg, #28a745, #218838);
    color: white;
}

.btn-danger.btn-modern {
    background: linear-gradient(135deg, #dc3545, #a71d2a);
    color: white;
}

.btn-warning.btn-modern {
    background: linear-gradient(135deg, #ffc107, #d39e00);
    color: #212529;
}

.btn-info.btn-modern {
    background: linear-gradient(135deg, #17a2b8, #117a8b);
    color: white;
}
</style>


<h3><?php echo $edit_terceiro ? 'Editar Terceiro' : 'Cadastrar Novo Terceiro'; ?></h3>

<?php if ($error_message): ?>
    <div class="alert alert-danger"><?php echo html_escape($error_message); ?></div>
<?php endif; ?>
<div id="form-messages" class="mt-3"></div>
<form id="terceiro-form" method="POST" action="terceiros.php<?php echo $edit_terceiro ? '?edit_id=' . $edit_terceiro['id'] : ''; ?>" enctype="multipart/form-data">
    <input type="hidden" name="action" value="<?php echo $edit_terceiro ? 'update' : 'add'; ?>">
    <?php if ($edit_terceiro): ?>
        <input type="hidden" name="terceiro_id" value="<?php echo $edit_terceiro['id']; ?>">
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="mb-3">
                <label for="nome_completo" class="form-label">Nome Completo <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nome_completo" name="nome_completo" value="<?php echo html_escape($edit_terceiro['nome_completo'] ?? ''); ?>" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="empresa_id" class="form-label">Empresa Prestadora <span class="text-danger">*</span></label>
                    <select class="form-select" id="empresa_id" name="empresa_id" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($empresas as $empresa): ?>
                            <option value="<?php echo $empresa['id']; ?>" <?php echo (isset($edit_terceiro['empresa_id']) && $edit_terceiro['empresa_id'] == $empresa['id']) ? 'selected' : ''; ?>>
                                <?php echo html_escape($empresa['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="filial_id" class="form-label">Filial de Atuação <span class="text-danger">*</span></label>
                    <select class="form-select" id="filial_id" name="filial_id" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($filiais as $filial): ?>
                            <?php
                                // Usuário comum só vê/seleciona filiais permitidas
                                $is_allowed = true;
                                if (!is_admin() && !empty($_SESSION['user_filiais'])) {
                                    $allowed_ids = explode(',', $_SESSION['user_filiais']);
                                    $allowed_ids = array_filter(array_map('intval', $allowed_ids));
                                    if (!in_array($filial['id'], $allowed_ids)) {
                                        $is_allowed = false;
                                    }
                                }
                                if ($is_allowed):
                            ?>
                            <option value="<?php echo $filial['id']; ?>" <?php echo (isset($edit_terceiro['filial_id']) && $edit_terceiro['filial_id'] == $filial['id']) ? 'selected' : ''; ?>>
                                <?php echo html_escape($filial['nome']); ?>
                            </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="observacoes" class="form-label">Observações Adicionais</label>
                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo html_escape($edit_terceiro['observacoes'] ?? ''); ?></textarea>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3 text-center">
                <label for="foto" class="form-label">Foto (JPG/PNG, max 2MB)</label>
                <div class="mb-2">
                    <?php
                        // Define o caminho da foto ou placeholder
                        $foto_url = DEFAULT_IMAGE_URL; // Default para imagem padrão
                        if (isset($edit_terceiro['foto_path']) && !empty($edit_terceiro['foto_path'])) {
                            $foto_local_path = UPLOAD_DIR . $edit_terceiro['foto_path'];
                            if (file_exists($foto_local_path)) {
                                $foto_url = APP_URL . '/Uploads/' . html_escape($edit_terceiro['foto_path']);
                            }
                        }
                    ?>
                    <img id="foto-preview" src="<?php echo $foto_url; ?>" alt="Foto do Terceiro" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                </div>
                <input class="form-control" type="file" id="foto" name="foto" accept=".jpg, .jpeg, .png" onchange="previewImage(event)">
            </div>
        </div>
    </div>

    <hr>
    <h4>Documentos e Treinamentos</h4>
    <div class="row">
        <?php foreach ($documentos_campos as $doc): ?>
            <?php
                $data_id = $doc['id'] . '_data';
                $aplicavel_id = $doc['id'] . '_aplicavel';
                $data_value = $edit_terceiro[$data_id] ?? '';
                // Converte data SQL (YYYY-MM-DD) para BR (DD/MM/YYYY) para exibição
                $data_display = '';
                if (!empty($data_value)) {
                    $date_obj = DateTime::createFromFormat('Y-m-d', $data_value);
                    if ($date_obj) {
                        $data_display = $date_obj->format('d/m/Y');
                    }
                }

                // Garante que o valor 'aplicavel' seja 1 ou 0, default 0 (false) para novos registros (exceto ASO)
                $aplicavel_checked = isset($edit_terceiro[$aplicavel_id]) ? (bool)$edit_terceiro[$aplicavel_id] : false;
                // Se foi um POST com erro, pega o valor do POST
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error_message && $doc['id'] !== 'aso') {
                    $aplicavel_checked = isset($_POST[$aplicavel_id]) && $_POST[$aplicavel_id] == '1';
                    // Pega a data do POST se houver erro
                    $data_display = $_POST[$data_id] ?? '';
                }
            ?>
            <div class="col-md-4 mb-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <?php if ($doc['id'] === 'aso'): ?>
                            <!-- ASO fixado em Sim -->
                            <div class="mb-2">
                                <label class="form-label d-block fw-bold"><?php echo html_escape($doc['label']); ?>:</label>
                                <span class="form-check-label">Sim</span>
                                <input type="hidden" name="<?php echo $aplicavel_id; ?>" value="1">
                            </div>
                            <div class="mt-2">
                                <label for="<?php echo $data_id; ?>" class="form-label small">Data Realização/Emissão: <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm date-input" id="<?php echo $data_id; ?>" name="<?php echo $data_id; ?>" value="<?php echo html_escape($data_display); ?>" placeholder="DD/MM/AAAA" required>
                                <div class="invalid-feedback"></div>
                            </div>
                        <?php else: ?>
                            <!-- Outros documentos com radios -->
                            <div class="mb-2 aplicabilidade-group aplicavel-control" data-target="#<?php echo $data_id; ?>">
                                <label class="form-label d-block fw-bold"><?php echo html_escape($doc['label']); ?>:</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input aplicavel-radio" type="radio" name="<?php echo $aplicavel_id; ?>" id="<?php echo $aplicavel_id; ?>_sim" value="1" <?php echo $aplicavel_checked ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="<?php echo $aplicavel_id; ?>_sim">Sim</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input aplicavel-radio" type="radio" name="<?php echo $aplicavel_id; ?>" id="<?php echo $aplicavel_id; ?>_nao" value="0" <?php echo !$aplicavel_checked ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="<?php echo $aplicavel_id; ?>_nao">Não</label>
                                </div>
                            </div>
                            <?php if ($doc['id'] !== 'epi'): // EPI não tem data ?>
                            <div class="mt-2">
                                <label for="<?php echo $data_id; ?>" class="form-label small">Data Realização/Emissão:</label>
                                <input type="text" class="form-control form-control-sm date-input" id="<?php echo $data_id; ?>" name="<?php echo $data_id; ?>" value="<?php echo html_escape($data_display); ?>" placeholder="DD/MM/AAAA" <?php echo !$aplicavel_checked ? 'disabled' : ''; ?>>
                                <div class="invalid-feedback"></div>
                            </div>
                            <?php else: ?>
                            <div class="mt-2">
                                <small class="text-muted d-block">(Apenas registro de aplicabilidade)</small>
                                <input type="hidden" name="<?php echo $data_id; ?>" value="">
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary btn-modern"><i class="fa-solid fa-save me-1"></i><?php echo $edit_terceiro ? 'Atualizar Terceiro' : 'Cadastrar Terceiro'; ?></button>
    <a href="monitoramento.php" class="btn btn-secondary btn-modern"><i class="fa-solid fa-cancel me-1"></i>Cancelar</a>
</div>

</form>

<!-- Modal de Confirmação -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="successModalLabel">Cadastro Concluído</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Terceiro cadastrado com sucesso! Deseja cadastrar outra pessoa?
            </div>
<div class="modal-footer">
    <button type="button" class="btn btn-primary btn-modern" id="cadastrarOutro">Sim</button>
    <button type="button" class="btn btn-secondary btn-modern" id="finalizar">Não</button>
</div>

        </div>
    </div>
</div>

<!-- Script para validação de data, habilitação/desabilitação, máscara, preview de imagem e modal -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Função para validar data no formato DD/MM/AAAA
    function isValidDate(dateString) {
        // Verifica o formato DD/MM/AAAA
        const regex = /^\d{2}\/\d{2}\/\d{4}$/;
        if (!regex.test(dateString)) {
            return false;
        }

        // Extrai dia, mês e ano
        const parts = dateString.split('/');
        const day = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10);
        const year = parseInt(parts[2], 10);

        // Validações básicas
        if (isNaN(day) || isNaN(month) || isNaN(year)) {
            return false;
        }
        if (month < 1 || month > 12) {
            return false;
        }
        if (day < 1 || day > 31) {
            return false;
        }
        if (year < 1900 || year > 2100) {
            return false;
        }

        // Verifica dias válidos por mês
        const daysInMonth = [31, ((year % 4 === 0 && year % 100 !== 0) || (year % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        if (day > daysInMonth[month - 1]) {
            return false;
        }

        // Verifica se a data é válida usando Date
        const date = new Date(year, month - 1, day);
        return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day;
    }

    // Habilita/desabilita campos de data baseado nos radios 'aplicavel' (exceto ASO)
    const radioContainers = document.querySelectorAll('.aplicavel-control');
    radioContainers.forEach(function(container) {
        const targetId = container.getAttribute('data-target');
        const targetInput = document.querySelector(targetId);
        const radios = container.querySelectorAll('.aplicavel-radio');

        if (targetInput && radios.length > 0) {
            // Função para atualizar o estado do input de data
            const updateDateInputState = () => {
                const selectedRadio = container.querySelector('.aplicavel-radio:checked');
                if (selectedRadio) {
                    const isApplicable = selectedRadio.value === '1';
                    targetInput.disabled = !isApplicable;
                    if (!isApplicable) {
                        targetInput.value = ''; // Limpa a data se 'Não' for selecionado
                        targetInput.classList.remove('is-invalid');
                        const feedback = targetInput.nextElementSibling;
                        if (feedback && feedback.classList.contains('invalid-feedback')) {
                            feedback.textContent = '';
                        }
                    }
                } else {
                    targetInput.disabled = true;
                    targetInput.value = '';
                    targetInput.classList.remove('is-invalid');
                }
            };

            // Define o estado inicial ao carregar a página
            updateDateInputState();

            // Adiciona listener para mudanças nos radios
            radios.forEach(radio => {
                radio.addEventListener('change', updateDateInputState);
            });
        }
    });

    // Adiciona máscara de data (simples)
    const dateInputs = document.querySelectorAll('.date-input');
    dateInputs.forEach(function(input) {
        input.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, ''); // Remove não dígitos
            if (v.length > 8) v = v.slice(0, 8);
            if (v.length >= 5) {
                e.target.value = v.slice(0, 2) + '/' + v.slice(2, 4) + '/' + v.slice(4);
            } else if (v.length >= 3) {
                e.target.value = v.slice(0, 2) + '/' + v.slice(2);
            } else {
                e.target.value = v;
            }
        });

        // Validação em tempo real no evento blur
        input.addEventListener('blur', function() {
            const value = input.value.trim();
            const feedback = input.nextElementSibling;
            if (input.id === 'aso_data' && value === '') {
                input.classList.add('is-invalid');
                if (feedback && feedback.classList.contains('invalid-feedback')) {
                    feedback.textContent = 'A data do ASO é obrigatória.';
                }
            } else if (value !== '' && !input.disabled) {
                if (!isValidDate(value)) {
                    input.classList.add('is-invalid');
                    if (feedback && feedback.classList.contains('invalid-feedback')) {
                        feedback.textContent = 'Data inválida. Use uma data válida no formato DD/MM/AAAA (ex.: 22/05/2023).';
                    }
                } else {
                    input.classList.remove('is-invalid');
                    if (feedback && feedback.classList.contains('invalid-feedback')) {
                        feedback.textContent = '';
                    }
                }
            } else {
                input.classList.remove('is-invalid');
                if (feedback && feedback.classList.contains('invalid-feedback')) {
                    feedback.textContent = '';
                }
            }
        });
    });

    // Validação do formulário no lado do cliente
    document.getElementById('terceiro-form').addEventListener('submit', function(event) {
        let isValid = true;
        let errorMessages = [];

        // Limpar mensagens de erro anteriores
        const errorDiv = document.getElementById('form-errors');
        if (errorDiv) {
            errorDiv.innerHTML = '';
            errorDiv.style.display = 'none';
        }
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        document.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');

        // Validar Nome Completo
        const nomeCompleto = document.getElementById('nome_completo');
        if (nomeCompleto.value.trim() === '') {
            isValid = false;
            nomeCompleto.classList.add('is-invalid');
            errorMessages.push('O Nome Completo é obrigatório.');
        }

        // Validar Empresa Prestadora
        const empresaId = document.getElementById('empresa_id');
        if (empresaId.value === '') {
            isValid = false;
            empresaId.classList.add('is-invalid');
            errorMessages.push('A Empresa Prestadora é obrigatória.');
        }

        // Validar Filial de Atuação
        const filialId = document.getElementById('filial_id');
        if (filialId.value === '') {
            isValid = false;
            filialId.classList.add('is-invalid');
            errorMessages.push('A Filial de Atuação é obrigatória.');
        }

        // Validar Foto (Tipo e Tamanho)
        const fotoInput = document.getElementById('foto');
        if (fotoInput.files.length > 0) {
            const file = fotoInput.files[0];
            const allowedTypes = ['image/jpeg', 'image/png'];
            const maxSize = 2 * 1024 * 1024; // 2MB

            if (!allowedTypes.includes(file.type)) {
                isValid = false;
                fotoInput.classList.add('is-invalid');
                errorMessages.push('Tipo de arquivo da foto inválido. Use JPG ou PNG.');
            }
            if (file.size > maxSize) {
                isValid = false;
                fotoInput.classList.add('is-invalid');
                errorMessages.push('O arquivo da foto excede o tamanho máximo de 2MB.');
            }
        }

        // Validar ASO (sempre obrigatório)
        const asoData = document.getElementById('aso_data');
        if (asoData.value.trim() === '') {
            isValid = false;
            asoData.classList.add('is-invalid');
            errorMessages.push('A data do ASO é obrigatória.');
            const feedback = asoData.nextElementSibling;
            if (feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.textContent = 'A data do ASO é obrigatória.';
            }
        } else if (!isValidDate(asoData.value)) {
            isValid = false;
            asoData.classList.add('is-invalid');
            errorMessages.push('A data do ASO é inválida. Use uma data válida no formato DD/MM/AAAA (ex.: 22/05/2023).');
            const feedback = asoData.nextElementSibling;
            if (feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.textContent = 'Data inválida. Use uma data válida no formato DD/MM/AAAA (ex.: 22/05/2023).';
            }
        }

        // Validar outras datas (formato DD/MM/AAAA e validade)
        const dateInputs = document.querySelectorAll('.date-input:not([disabled]):not(#aso_data)');
        dateInputs.forEach(input => {
            const value = input.value.trim();
            const feedback = input.nextElementSibling;
            if (value !== '') {
                if (!isValidDate(value)) {
                    isValid = false;
                    input.classList.add('is-invalid');
                    const label = document.querySelector(`label[for="${input.id}"]`);
                    const fieldName = label ? label.textContent.replace(':', '') : 'Data';
                    errorMessages.push(`A data em ${fieldName} é inválida. Use uma data válida no formato DD/MM/AAAA (ex.: 22/05/2023).`);
                    if (feedback && feedback.classList.contains('invalid-feedback')) {
                        feedback.textContent = 'Data inválida. Use uma data válida no formato DD/MM/AAAA (ex.: 22/05/2023).';
                    }
                }
            }
        });

        if (!isValid) {
            event.preventDefault(); // Impede o envio do formulário

            // Exibir mensagens de erro
            let errorHtml = '<strong>Por favor, corrija os seguintes erros:</strong><ul>';
            errorMessages.forEach(msg => {
                errorHtml += `<li>${msg}</li>`;
            });
            errorHtml += '</ul>';

            let existingErrorDiv = document.getElementById('form-errors');
            if (!existingErrorDiv) {
                existingErrorDiv = document.createElement('div');
                existingErrorDiv.id = 'form-errors';
                existingErrorDiv.className = 'alert alert-danger mt-3';
                this.insertBefore(existingErrorDiv, this.firstChild);
            }
            existingErrorDiv.innerHTML = errorHtml;
            existingErrorDiv.style.display = 'block';
            window.scrollTo(0, 0); // Rola para o topo para ver os erros
        }
    });

    // Confirmação após cadastro com modal
    const justAdded = <?php echo json_encode($just_added); ?>;
    if (justAdded) {
        const modal = new bootstrap.Modal(document.getElementById('successModal'));
        const form = document.getElementById('terceiro-form');
        const messagesDiv = document.getElementById('form-messages');

        // Mostrar o modal
        modal.show();

        // Botão "Sim" (Cadastrar Outro)
        document.getElementById('cadastrarOutro').addEventListener('click', function() {
            form.reset();
            document.getElementById('foto-preview').src = '<?php echo DEFAULT_IMAGE_URL; ?>';
            // Reabilitar campos de data e selecionar "Não" (exceto ASO)
            document.querySelectorAll('.date-input:not(#aso_data)').forEach(input => {
                input.disabled = true;
                input.classList.remove('is-invalid');
                const feedback = input.nextElementSibling;
                if (feedback && feedback.classList.contains('invalid-feedback')) {
                    feedback.textContent = '';
                }
            });
            document.querySelectorAll('.aplicavel-radio[value="0"]').forEach(radio => {
                radio.checked = true;
            });
            // Garantir que aso_data permaneça habilitado e required
            document.getElementById('aso_data').disabled = false;
            document.getElementById('aso_data').required = true;
            window.scrollTo(0, 0);
            modal.hide();
        });

        // Botão "Não" (Finalizar)
        document.getElementById('finalizar').addEventListener('click', function() {
            window.location.href = 'monitoramento.php?highlight=' + justAdded;
            modal.hide();
        });
    }
});

// Preview da imagem
function previewImage(event) {
    const reader = new FileReader();
    reader.onload = function() {
        const output = document.getElementById('foto-preview');
        output.src = reader.result;
    };
    if (event.target.files[0]) {
        reader.readAsDataURL(event.target.files[0]);
    } else {
        document.getElementById('foto-preview').src = '<?php echo DEFAULT_IMAGE_URL; ?>';
    }
}
</script>

<?php
// Usar require_once para o footer
require_once __DIR__ . '/includes/footer.php';
ob_end_flush();
?>