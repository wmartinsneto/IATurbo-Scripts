<?php
/**
 * run.php
 * 
 * Este script recebe um `id` (gerado pelo `request.php`), uma URL do Dify e um token de autorização via POST,
 * carrega o arquivo correspondente na pasta `./pending`, envia a pergunta para o Dify,
 * salva a resposta na pasta `./completed`, envia as informações para o Trello e retorna um status 200 OK.
 */

$pending_dir = './pending/';
$completed_dir = './completed/';
$log_file = './logs/run-' . date('Y-m-d') . '.log';

if (!is_dir($completed_dir)) {
    mkdir($completed_dir, 0777, true);
}

if (!is_dir(dirname($log_file))) {
    mkdir(dirname($log_file), 0777, true);
}

// Função para registrar logs
function log_message($message) {
    global $log_file;
    $log_entry = date('Y-m-d H:i:s') . " - " . $message . "\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Função para capturar e logar erros de stream
function log_stream_error($url, $context) {
    $error_message = '';
    $error_message .= "Erro ao acessar a URL: $url\n";
    $error_message .= "Opções de Contexto: " . print_r($context, true) . "\n";
    
    $error = error_get_last();
    if ($error) {
        $error_message .= "Detalhes do erro: " . $error['message'] . "\n";
    }

    log_message($error_message);
}

// Recebe os parâmetros do JSON de entrada
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$dify_url = $data['chatflow_url'] ?? null;
$dify_key = $data['chatflow_key'] ?? null;

log_message("Requisição recebida. ID: $id, Dify URL: $dify_url");

// Verifica se todos os parâmetros obrigatórios estão presentes
if (!$id || !$dify_url || !$dify_key) {
    log_message("Parâmetros faltando. id, chatflow_url e chatflow_key são obrigatórios.");
    die(json_encode(['error' => 'Parâmetros faltando. id, chatflow_url e chatflow_key são obrigatórios.']));
}

$file_path = $pending_dir . $id . '.json';

if (!file_exists($file_path)) {
    log_message("Arquivo não encontrado para id: $id");
    die(json_encode(['error' => 'Arquivo não encontrado.']));
}

// Lê os dados do arquivo pendente
$pending_data = json_decode(file_get_contents($file_path), true);
log_message("Arquivo carregado com sucesso para id: $id. Dados: " . print_r($pending_data, true));

// Prepara o payload para o Dify
$conversation_id = $pending_data['conversation_id'] ?? '';
$payload = [
    'inputs' => [],
    'query' => $pending_data['question'],
    'response_mode' => 'streaming',
    'conversation_id' => $conversation_id,
    'user' => $pending_data['overrideConfig']['sessionId'],
    'files' => []
];

log_message("Payload preparado para Dify: " . json_encode($payload));

// Envia a pergunta para o Dify
$ch = curl_init($dify_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $dify_key
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$thought_found = false;

curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($id, $completed_dir, $pending_data, &$thought_found) {

    // Store the length of the original data
    $original_length = strlen($data);

    // Remove the "data: " prefix if it exists
    $data = preg_replace('/^data: /', '', $data);

    $response = json_decode($data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        //log_message("Erro ao decodificar JSON: " . json_last_error_msg());
        return $original_length; // Return the length of the original data to avoid cURL error
    }

    // Process only if event is "agent_thought" and thought is not empty
    if (isset($response['event']) && $response['event'] === 'agent_thought' && !empty(trim($response['thought']))) {
        log_message("Dados recebidos do Dify: " . $data);
        $completed_file_path = $completed_dir . $id . '.json';
        $combined_data = array_merge($pending_data, $response);
        file_put_contents($completed_file_path, json_encode($combined_data, JSON_PRETTY_PRINT));
        log_message("Dados de 'agent_thought' salvos em: $completed_file_path");
        $thought_found = true;
    }

    return $original_length; // Return the length of the original data to avoid cURL error
});

$result = curl_exec($ch);

if ($result === FALSE) {
    log_message("Error sending question to Dify for id: $id. Error: " . curl_error($ch));
    die(json_encode(['error' => 'Error sending question to Dify.']));
}

curl_close($ch);

// Verifica se o arquivo de resposta foi criado
$completed_file_path = $completed_dir . $id . '.json';
if (!$thought_found || !file_exists($completed_file_path)) {
    log_message("Falha ao obter resposta de Dify para id: $id");

    // Call retry.php internally
    $retry_payload = json_encode([
        'id' => $id,
        'chatflow_url' => 'https://api.dify.ai/v1/messages',
        'chatflow_key' => $dify_key
    ]);
    $retry_ch = curl_init('https://iaturbo.com.br/wp-content/uploads/scripts/dify/retry.php');
    curl_setopt($retry_ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($retry_ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($retry_ch, CURLOPT_POST, true);
    curl_setopt($retry_ch, CURLOPT_POSTFIELDS, $retry_payload);
    $retry_result = curl_exec($retry_ch);
    curl_close($retry_ch);
    log_message("Retry result: " . $retry_result);
}

// Log the completion
log_message("Response saved for id: $id");

// Remove o da pasta pending
if (!unlink($file_path)) {
    log_message("Falha ao remover o arquivo pendente para id: $id");
}

log_message("Arquivo pendente removido com sucesso para id: $id");

// Integração com generate-audio.php
log_message("Iniciando integração com generate-audio.php para mensagem $id.");

// Prepara o payload
$response_data = json_decode(file_get_contents($completed_file_path), true);
$mensagemDeVoz = json_decode($response_data['thought'], true)['mensagemDeVoz'] ?? 'Sem resposta disponível';
$audio_payload = json_encode([
    'input_text' => $mensagemDeVoz,
    'id' => $id
]);

// Configura a requisição cURL sem bloquear
$audio_ch = curl_init('https://iaturbo.com.br/wp-content/uploads/scripts/speech/generate-audio.php');
curl_setopt($audio_ch, CURLOPT_POSTFIELDS, $audio_payload);
curl_setopt($audio_ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($audio_ch, CURLOPT_POST, true);
curl_setopt($audio_ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($audio_ch, CURLOPT_TIMEOUT_MS, 50);
curl_setopt($audio_ch, CURLOPT_FORBID_REUSE, true);
curl_setopt($audio_ch, CURLOPT_CONNECTTIMEOUT_MS, 200);

curl_exec($audio_ch);
curl_close($audio_ch);

log_message("Chamada para generate-audio.php enviada para id: $id.");

// Integração com trello_integration.php
log_message("Iniciando integração com trello_integration.php para mensagem $id.");

// Prepara o payload
$trello_payload = json_encode([
    'id' => $id
]);

// Configura a requisição cURL sem bloquear
$trello_ch = curl_init('https://iaturbo.com.br/wp-content/uploads/scripts/dify/trello_integration.php');
curl_setopt($trello_ch, CURLOPT_POSTFIELDS, $trello_payload);
curl_setopt($trello_ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($trello_ch, CURLOPT_POST, true);
curl_setopt($trello_ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($trello_ch, CURLOPT_TIMEOUT_MS, 50);
curl_setopt($trello_ch, CURLOPT_FORBID_REUSE, true);
curl_setopt($trello_ch, CURLOPT_CONNECTTIMEOUT_MS, 200);

curl_exec($trello_ch);
curl_close($trello_ch);

log_message("Chamada para trello_integration.php enviada para id: $id.");

// Integração com slack_integration.php
log_message("Iniciando integração com slack_integration.php para mensagem $id.");

// Prepara o payload
$slack_payload = json_encode([
    'id' => $id
]);

// Configura a requisição cURL sem bloquear
$slack_ch = curl_init('https://iaturbo.com.br/wp-content/uploads/scripts/dify/slack_integration.php');
curl_setopt($slack_ch, CURLOPT_POSTFIELDS, $slack_payload);
curl_setopt($slack_ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($slack_ch, CURLOPT_POST, true);
curl_setopt($slack_ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($slack_ch, CURLOPT_TIMEOUT_MS, 50);
curl_setopt($slack_ch, CURLOPT_FORBID_REUSE, true);
curl_setopt($slack_ch, CURLOPT_CONNECTTIMEOUT_MS, 200);

curl_exec($slack_ch);
curl_close($slack_ch);

log_message("Chamada para slack_integration.php enviada para id: $id.");

// Retorna 200 OK
http_response_code(200);
log_message("Processamento concluído para id: $id.");
?>