<?php
/**
 * response.php
 * 
 * Este script recebe um `id` via GET, verifica se o arquivo correspondente na pasta `./completed` existe,
 * e retorna o conteúdo do arquivo JSON ou um status de pendente.
 */

$start_time = microtime(true);

$completed_dir = './completed/';

include 'helpers.php';

// Recebe o parâmetro `id` via GET
$id = $_GET['id'] ?? null;

if (!$id) {
    log_message('response', 'error', 'id é obrigatório');
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'id é obrigatório']);
    exit;
}

$completed_file_path = $completed_dir . $id . '.json';

if (!file_exists($completed_file_path)) {
    log_message('response', 'info', "Resultado pendente para o id: $id");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'pending']);
    exit;
}

// Lê os dados do arquivo completado
$completed_data = json_decode(file_get_contents($completed_file_path), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    log_message('response', 'error', "Erro ao decodificar JSON para o id: $id");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Erro ao decodificar o JSON completado']);
    exit;
}

// Processa todos os pensamentos do agente
$agent_thoughts = $completed_data['agent_thoughts'] ?? [];
$mensagemDeTexto = getMensagemDeTexto($agent_thoughts);
$mensagemDeVoz = getMensagemDeVoz($agent_thoughts);
$mensagemDeControle = getMensagemDeControle($agent_thoughts);

// Adiciona as novas mensagens ao completed_data
$completed_data['mensagemDeTexto'] = $mensagemDeTexto;
$completed_data['mensagemDeVoz'] = $mensagemDeVoz;
$completed_data['mensagemDeControle'] = $mensagemDeControle;
$completed_data['status'] = 'completed';

// Log da conclusão
$end_time = microtime(true);
$execution_time = round(($end_time - $start_time) * 1000, 2);
log_message('response', 'info', "Resposta completada para o id: $id. Tempo total de execução: {$execution_time}ms.");

// Retorna a resposta JSON
header('Content-Type: application/json');
echo json_encode($completed_data);
?>