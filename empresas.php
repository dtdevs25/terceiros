<?php
// Corrigido os caminhos para incluir arquivos da pasta includes/
require_once __DIR__ . 
'/includes/config.php




';
require_once __DIR__ . 
'/includes/connection.php




'; // Corrigido de /db/ para /includes/
require_once __DIR__ . 
'/includes/functions.php




';

// Requer login
require_login();

$page_title = 'Cadastrar/Editar Terceiro';
// Usar require_once para header e footer
require_once __DIR__ . 
'/includes/header.php




';

// $pdo já deve estar disponível globalmente via connection.php
global $pdo;

$empresas = get_todas_empresas();
$filiais = get_todas_filiais();
$edit_terceiro = null;
$error_message = null;
$success_message = null;

// Garante que a sessão está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Buscar terceiro para edição
if (isset($_GET[
'edit_id




'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id) {
        // Adicionar verificação de permissão de filial para usuário comum
        $filiais_permitidas_sql = '';
        $allowed_ids = []; // Inicializa $allowed_ids
        if (!is_admin() && !empty($_SESSION[
'user_filiais




'])) {
            $allowed_ids = explode(',', $_SESSION[
'user_filiais




']);
            $allowed_ids = array_filter(array_map('intval', $allowed_ids)); // Limpa e converte para int
            if (!empty($allowed_ids)) {
                $placeholders = implode(',', array_fill(0, count($allowed_ids), '?'));
                $filiais_permitidas_sql = " AND filial_id IN ($placeholders)";
            }
        } elseif (!is_admin() && empty($_SESSION[
'user_filiais




'])) {
             // Usuário comum sem filiais não pode editar ninguém
             $filiais_permitidas_sql = " AND 1=0"; // Condição sempre falsa
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM terceiros WHERE id = ? $filiais_permitidas_sql");
            $params = array_merge([$edit_id], $allowed_ids);
            $stmt->execute($params);
            $edit_terceiro = $stmt->fetch();

            if (!$edit_terceiro) {
                $_SESSION[
'error_message




'] = 'Terceiro não encontrado ou acesso não permitido.';
                header('Location: monitoramento.php');
                exit;
            }
        } catch (PDOException $e) {
             $_SESSION[
'error_message




'] = 'Erro ao buscar terceiro: ' . $e->getMessage();
             error_log("Erro buscar terceiro edit: " . $e->getMessage());
             header('Location: monitoramento.php');
             exit;
        }
    }
}

// Processar formulário (Adicionar ou Editar)
if ($_SERVER[
'REQUEST_METHOD




'] === 'POST') {
    $action = $_POST[
'action




'] ?? null;
    $terceiro_id = filter_input(INPUT_POST, 'terceiro_id', FILTER_VALIDATE_INT);

    // Coleta e sanitização básica dos dados
    $nome_completo = trim($_POST[
'nome_completo




'] ?? '');
    $empresa_id = filter_input(INPUT_POST, 'empresa_id', FILTER_VALIDATE_INT);
    $filial_id = filter_input(INPUT_POST, 'filial_id', FILTER_VALIDATE_INT);
    $observacoes = trim($_POST[
'observacoes




'] ?? '');

    // Datas (validação básica, idealmente usar JS para formato)
    $datas = [
        'aso_data' => $_POST[
'aso_data




'] ?? null,
        'epi_data' => $_POST[
'epi_data




'] ?? null,
        'nr10_data' => $_POST[
'nr10_data




'] ?? null,
        'nr11_data' => $_POST[
'nr11_data




'] ?? null,
        'nr12_data' => $_POST[
'nr12_data




'] ?? null,
        'nr18_data' => $_POST[
'nr18_data




'] ?? null,
        'integracao_data' => $_POST[
'integracao_data




'] ?? null,
        'nr20_data' => $_POST[
'nr20_data




'] ?? null,
        'nr33_data' => $_POST[
'nr33_data




'] ?? null,
        'nr35_data' => $_POST[
'nr35_data




'] ?? null,
    ];

    // Checkboxes 'aplicavel'
    $aplicaveis = [
        'aso_aplicavel' => isset($_POST[
'aso_aplicavel




']) ? 1 : 0,
        'epi_aplicavel' => isset($_POST[
'epi_aplicavel




']) ? 1 : 0,
        'nr10_aplicavel' => isset($_POST[
'nr10_aplicavel




']) ? 1 : 0,
        'nr11_aplicavel' => isset($_POST[
'nr11_aplicavel




']) ? 1 : 0,
        'nr12_aplicavel' => isset($_POST[
'nr12_aplicavel




']) ? 1 : 0,
        'nr18_aplicavel' => isset($_POST[
'nr18_aplicavel




']) ? 1 : 0,
        'integracao_aplicavel' => isset($_POST[
'integracao_aplicavel




']) ? 1 : 0,
        'nr20_aplicavel' => isset($_POST[
'nr20_aplicavel




']) ? 1 : 0,
        'nr33_aplicavel' => isset($_POST[
'nr33_aplicavel




']) ? 1 : 0,
        'nr35_aplicavel' => isset($_POST[
'nr35_aplicavel




']) ? 1 : 0,
    ];

    // Validação básica
    if (empty($nome_completo) || empty($empresa_id) || empty($filial_id)) {
        $error_message = 'Nome completo, Empresa e Filial são obrigatórios.';
    } else {
        // Tratamento do Upload da Foto
        $foto_path = $edit_terceiro[
'foto_path




'] ?? null; // Mantém a foto existente por padrão
        if (isset($_FILES[
'foto




']) && $_FILES[
'foto




'][
'error




'] == UPLOAD_ERR_OK) {
            $foto_tmp_name = $_FILES[
'foto




'][
'tmp_name




'];
            $foto_name = $_FILES[
'foto




'][
'name




'];
            $foto_size = $_FILES[
'foto




'][
'size




'];
            $foto_type = $_FILES[
'foto




'][
'type




'];
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
        }

        // Continua apenas se não houve erro no upload
        if (!$error_message) {
            try {
                // Verifica permissão de filial para usuário comum ANTES de salvar
                if (!is_admin()) {
                    $allowed_ids = explode(',', $_SESSION[
'user_filiais




'] ?? '');
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
                    $_SESSION[
'success_message




'] = 'Terceiro cadastrado com sucesso!';
                    header('Location: monitoramento.php?highlight='.$new_id); // Redireciona após sucesso
                    exit;
                } elseif ($action === 'update' && $terceiro_id) {
                    $sql = "UPDATE terceiros SET $sql_fields WHERE id = ?";
                    $params[] = $terceiro_id;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $_SESSION[
'success_message




'] = 'Terceiro atualizado com sucesso!';
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
        $edit_terceiro[
'id




'] = $terceiro_id; // Mantém o ID se for edição
        // Adiciona os 'aplicavel' que não vêm no POST se desmarcados
        foreach ($aplicaveis as $key => $value) {
             if (!isset($edit_terceiro[$key])) {
                 $edit_terceiro[$key] = 0;
             }
        }
        // Mantém a foto existente se o upload falhou e era uma edição
        if ($action === 'update' && $terceiro_id && !isset($foto_path)) {
             try {
                 $stmt_foto = $pdo->prepare("SELECT foto_path FROM terceiros WHERE id = ?");
                 $stmt_foto->execute([$terceiro_id]);
                 $edit_terceiro[
'foto_path




'] = $stmt_foto->fetchColumn();
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

?>

<h3><?php echo $edit_terceiro ? 'Editar Terceiro' : 'Cadastrar Novo Terceiro'; ?></h3>

<?php if ($error_message): ?>
    <div class="alert alert-danger"><?php echo html_escape($error_message); ?></div>
<?php endif; ?>
<?php /* Mensagem de sucesso é geralmente mostrada na página de destino (monitoramento)
<?php if ($success_message): ?>
     <div class="alert alert-success"><?php echo html_escape($success_message); ?></div>
<?php endif; ?>
*/ ?>

<form method="POST" action="terceiros.php<?php echo $edit_terceiro ? '?edit_id='.$edit_terceiro[
'id




'] : ''; ?>" enctype="multipart/form-data">
    <input type="hidden" name="action" value="<?php echo $edit_terceiro ? 'update' : 'add'; ?>">
    <?php if ($edit_terceiro): ?>
        <input type="hidden" name="terceiro_id" value="<?php echo $edit_terceiro[
'id




']; ?>">
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="mb-3">
                <label for="nome_completo" class="form-label">Nome Completo <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nome_completo" name="nome_completo" value="<?php echo html_escape($edit_terceiro[
'nome_completo




'] ?? ''); ?>" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="empresa_id" class="form-label">Empresa Prestadora <span class="text-danger">*</span></label>
                    <select class="form-select" id="empresa_id" name="empresa_id" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($empresas as $empresa): ?>
                            <option value="<?php echo $empresa[
'id




']; ?>" <?php echo (isset($edit_terceiro[
'empresa_id




']) && $edit_terceiro[
'empresa_id




'] == $empresa[
'id




']) ? 'selected' : ''; ?>>
                                <?php echo html_escape($empresa[
'nome




']); ?>
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
                                if (!is_admin() && !empty($_SESSION[
'user_filiais




'])) {
                                    $allowed_ids = explode(',', $_SESSION[
'user_filiais




']);
                                    $allowed_ids = array_filter(array_map('intval', $allowed_ids));
                                    if (!in_array($filial[
'id




'], $allowed_ids)) {
                                        $is_allowed = false;
                                    }
                                }
                                if ($is_allowed):
                             ?>
                            <option value="<?php echo $filial[
'id




']; ?>" <?php echo (isset($edit_terceiro[
'filial_id




']) && $edit_terceiro[
'filial_id




'] == $filial[
'id




']) ? 'selected' : ''; ?>>
                                <?php echo html_escape($filial[
'nome




']); ?>
                            </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="observacoes" class="form-label">Observações Adicionais</label>
                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo html_escape($edit_terceiro[
'observacoes




'] ?? ''); ?></textarea>
            </div>

        </div>
        <div class="col-md-4">
            <div class="mb-
(Content truncated due to size limit. Use line ranges to read in chunks)