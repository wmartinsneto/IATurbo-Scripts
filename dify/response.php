<?php
/**
 * response.php
 * 
 * Este script recebe um `id` via GET, verifica se o arquivo correspondente na pasta `./completed` existe,
 * e retorna o conteúdo do arquivo JSON ou um status de pendente.
 */

$completed_dir = './completed/';
$log_file = './logs/response-' . date('Y-m-d') . '.log';

// Função para registrar logs
function log_message($message) {
    global $log_file;
    $log_entry = date('Y-m-d H:i:s') . " - " . $message . "\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Recebe o parâmetro `id` via GET
$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'id é obrigatório']);
    exit;
}

$completed_file_path = $completed_dir . $id . '.json';

if (!file_exists($completed_file_path)) {
    log_message("Resultado pendente para o id: $id");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'pending']);
    exit;
}

// Lê os dados do arquivo completado
$completed_data = json_decode(file_get_contents($completed_file_path), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    log_message("Erro ao decodificar JSON para o id: $id");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Erro ao decodificar o JSON de thought']);
    exit;
}

// Decodifica o campo "thought"
$parsed_thought = json_decode($completed_data['thought'], true);

if (json_last_error() !== JSON_ERROR_NONE) {
    log_message("Erro ao decodificar o campo thought para o id: $id");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Erro ao decodificar o campo thought']);
    exit;
}

// Combina os dados do arquivo completado com os dados decodificados do campo "thought"
$response_data = array_merge($completed_data, $parsed_thought);

// Remove o campo "thought" original
unset($response_data['thought']);

// Adiciona o status "completed"
$response_data['status'] = 'completed';

// Log the completion
log_message("Resposta completada para o id: $id");

// Retorna o conteúdo do arquivo JSON
header('Content-Type: application/json');
echo json_encode($response_data);
?>