<?php
/**
 * retry.php
 * 
 * Este script recebe um `id`, uma URL do Dify e um token de autorização via POST,
 * carrega o arquivo correspondente na pasta `./pending`, chama o endpoint "Get Conversation History Messages",
 * verifica se a resposta contém a pergunta correta e salva os dados na pasta `./completed`.
 */

$pending_dir = './pending/';
$completed_dir = './completed/';
$log_file = './logs/retry-' . date('Y-m-d') . '.log';

// Função para registrar logs
function log_message($message) {
    global $log_file;
    $log_entry = date('Y-m-d H:i:s') . " - " . $message . "\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Função para enviar notificação ao Slack
function send_slack_notification($pending_data, $error_message) {
    $webhookUrl = 'https://hooks.slack.com/services/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';

    $slackMessage = "Falha ao obter resposta de Dify após todas as tentativas.\n";
    $slackMessage .= "Erro: $error_message\n";
    $slackMessage .= "Lead no " . $pending_data['source'] . "\n";
    foreach ($pending_data['userData'] as $key => $value) {
        $slackMessage .= ucfirst($key) . ": $value\n";
    }
    $slackMessage .= "PERGUNTA:\n" . $pending_data['question'] . "\n";

    // Configura o payload para enviar ao Slack
    $payload = json_encode([
        'text' => $slackMessage
    ]);

    // Configura as opções de contexto para a requisição HTTP
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => $payload,
        ]
    ];

    // Cria o contexto de stream
    $context = stream_context_create($options);

    // Envia a requisição ao Slack
    $result = @file_get_contents($webhookUrl, false, $context);

    // Verifica o resultado e retorna a resposta apropriada
    if ($result === FALSE) {
        log_message('Falha ao enviar a mensagem ao Slack.');
    } else {
        log_message('Mensagem enviada com sucesso ao Slack.');
    }
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

// Função para obter o conversation_id se estiver faltando
function get_conversation_id($user, $dify_key) {
    $url = "https://api.dify.ai/v1/conversations?user=$user&limit=1";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $dify_key
    ]);

    $result = curl_exec($ch);

    if ($result === FALSE) {
        log_message("Error retrieving conversation ID for user: $user. Error: " . curl_error($ch));
        return null;
    }

    curl_close($ch);

    // Log the raw response for debugging
    log_message("Raw response for conversation ID: " . $result);

    // Processa a resposta do endpoint
    $response_data = json_decode($result, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("Erro ao decodificar JSON da resposta para o user: $user");
        return null;
    }

    if (empty($response_data['data'])) {
        log_message("Nenhuma conversa encontrada para o user: $user");
        return null;
    }

    return $response_data['data'][0]['id'] ?? null;
}

// Prepara os parâmetros para a chamada do endpoint "Get Conversation History Messages"
$user = urlencode($pending_data['overrideConfig']['sessionId']);
$conversation_id = $pending_data['conversation_id'] ?? '';

// Se o conversation_id estiver faltando, tenta obtê-lo
if (empty($conversation_id)) {
    log_message("conversation_id está faltando. Tentando obter o conversation_id para o user: $user");
    $conversation_id = get_conversation_id($user, $dify_key);
    if (empty($conversation_id)) {
        log_message("Falha ao obter o conversation_id para o user: $user");
        die(json_encode(['error' => 'Falha ao obter o conversation_id.']));
    }
    log_message("conversation_id obtido com sucesso: $conversation_id");
}

$limit = 1;
$retry_url = "$dify_url?user=$user&conversation_id=$conversation_id&limit=$limit";

log_message("Retry URL: $retry_url");

// Função para chamar o endpoint "Get Conversation History Messages"
function get_conversation_history($retry_url, $dify_key, $id, $pending_data) {
    global $completed_dir;

    $ch = curl_init($retry_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $dify_key
    ]);

    $result = curl_exec($ch);

    if ($result === FALSE) {
        log_message("Error retrieving conversation history for id: $id. Error: " . curl_error($ch));
        return false;
    }

    curl_close($ch);

    // Log the raw response for debugging
    log_message("Raw response for id: $id: " . $result);

    // Processa a resposta do endpoint
    $response_data = json_decode($result, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("Erro ao decodificar JSON da resposta para o id: $id");
        return false;
    }

    if (empty($response_data['data'])) {
        log_message("Nenhuma mensagem encontrada para o id: $id");
        return false;
    }

    $latest_message = $response_data['data'][0];
    $query = $latest_message['query'] ?? '';

    if ($query !== $pending_data['question']) {
        log_message("A pergunta na resposta não corresponde à pergunta no arquivo pendente para o id: $id");
        return false;
    }

    // Adiciona o campo "thought" à resposta
    $latest_message['thought'] = $latest_message['answer'];

    // Combina os dados do arquivo pendente com os dados da resposta
    $combined_data = array_merge($pending_data, $latest_message);

    // Salva os dados combinados na pasta `./completed`
    $completed_file_path = $completed_dir . $id . '.json';
    file_put_contents($completed_file_path, json_encode($combined_data, JSON_PRETTY_PRINT));
    log_message("Dados combinados salvos em: $completed_file_path");

    // Retorna 200 OK
    http_response_code(200);
    log_message("Resposta retornada com sucesso para id: $id.");
    echo json_encode(['status' => 'completed']);
    return true;
}

// Tenta obter a resposta até 12 vezes com intervalo de 5 segundos
$max_retries = 18;
$retry_interval = 5; // seconds

for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
    log_message("Tentativa $attempt de $max_retries para obter a resposta.");
    if (get_conversation_history($retry_url, $dify_key, $id, $pending_data)) {
        exit;
    }
    sleep($retry_interval);
}

log_message("Falha ao obter resposta de Dify após $max_retries tentativas para id: $id");
send_slack_notification($pending_data, "Falha ao obter resposta de Dify após $max_retries tentativas.");
die(json_encode(['error' => "Falha ao obter resposta de Dify após $max_retries tentativas."]));
?>