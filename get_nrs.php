<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $nrs = get_nrs_liberados($id);
    echo json_encode(['success' => true, 'nrs' => $nrs]);
} catch (Exception $e) {
    error_log('Erro ao buscar NRs: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar NRs']);
    exit;
}

// Função interna
function get_nrs_liberados($terceiro_id) {
    global $pdo;

    $query = "SELECT 
        nr10_data, nr10_aplicavel,
        nr11_data, nr11_aplicavel,
        nr12_data, nr12_aplicavel,
        nr18_data, nr18_aplicavel,
        nr20_data, nr20_aplicavel,
        nr33_data, nr33_aplicavel,
        nr35_data, nr35_aplicavel
        FROM terceiros
        WHERE id = ?";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$terceiro_id]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dados) {
        return [];
    }

    $nrs = [
        'NR 10' => ['data' => $dados['nr10_data'], 'aplicavel' => $dados['nr10_aplicavel'], 'validade' => 24],
        'NR 11' => ['data' => $dados['nr11_data'], 'aplicavel' => $dados['nr11_aplicavel'], 'validade' => 12],
        'NR 12' => ['data' => $dados['nr12_data'], 'aplicavel' => $dados['nr12_aplicavel'], 'validade' => 12],
        'NR 18' => ['data' => $dados['nr18_data'], 'aplicavel' => $dados['nr18_aplicavel'], 'validade' => 12],
        'NR 20' => ['data' => $dados['nr20_data'], 'aplicavel' => $dados['nr20_aplicavel'], 'validade' => 12],
        'NR 33' => ['data' => $dados['nr33_data'], 'aplicavel' => $dados['nr33_aplicavel'], 'validade' => 12],
        'NR 35' => ['data' => $dados['nr35_data'], 'aplicavel' => $dados['nr35_aplicavel'], 'validade' => 24],
    ];

    $liberadas = [];

    foreach ($nrs as $nome => $info) {
        if ($info['aplicavel']) {
            $data_treinamento = $info['data'];
            if ($data_treinamento) {
                $data = DateTime::createFromFormat('Y-m-d', $data_treinamento);
                if ($data) {
                    $data_validade = clone $data;
                    $data_validade->modify("+{$info['validade']} months");

                    if (new DateTime() <= $data_validade) {
                        $liberadas[] = $nome;
                    }
                }
            }
        }
    }

    return $liberadas;
}
