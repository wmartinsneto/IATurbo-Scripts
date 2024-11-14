<?php
/**
 * run.php
 * 
 * Este script recebe um `id` (gerado pelo `request.php`), uma URL do Dify e um token de autorização via POST,
 * carrega o arquivo correspondente na pasta `./pending`, envia a pergunta para o Dify,
 * aguarda a resposta consultando o histórico de conversas,
 * salva a resposta na pasta `./completed` e retorna um status 200 OK.
 */

$pending_dir = './pending/';
$completed_dir = './completed/';

include 'helpers.php';

if (!is_dir($completed_dir)) {
    mkdir($completed_dir, 0777, true);
}

// Recebe os parâmetros do JSON de entrada
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$dify_url = $data['chatflow_url'] ?? null;
$dify_key = $data['chatflow_key'] ?? null;

log_message('run', 'info', "Requisição recebida. ID: $id, Dify URL: $dify_url");

// Verifica se todos os parâmetros obrigatórios estão presentes
if (!$id || !$dify_url || !$dify_key) {
    log_message('run', 'error', "Parâmetros faltando. id, chatflow_url e chatflow_key são obrigatórios.");
    die(json_encode(['error' => 'Parâmetros faltando. id, chatflow_url e chatflow_key são obrigatórios.']));
}

$file_path = $pending_dir . $id . '.json';

if (!file_exists($file_path)) {
    log_message('run', 'error', "Arquivo não encontrado para id: $id");
    die(json_encode(['error' => 'Arquivo não encontrado.']));
}

// Lê os dados do arquivo pendente
$pending_data = json_decode(file_get_contents($file_path), true);
log_message('run', 'info', "Arquivo carregado com sucesso para id: $id. Dados: " . print_r($pending_data, true));

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

log_message('run', 'info', "Payload preparado para Dify: " . json_encode($payload));

// Envia a pergunta para o Dify
$ch = curl_init($dify_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $dify_key
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

// Executa a requisição
$result = curl_exec($ch);

if ($result === FALSE) {
    log_message('run', 'error', "Error sending question to Dify for id: $id. Error: " . curl_error($ch));
    die(json_encode(['error' => 'Error sending question to Dify.']));
}

curl_close($ch);

// Função para obter o conversation_id caso esteja faltando
function get_conversation_id($user, $dify_key) {
    $url = "https://api.dify.ai/v1/conversations?user=$user&limit=1";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $dify_key
    ]);

    $result = curl_exec($ch);

    if ($result === FALSE) {
        log_message('run', 'error', "Erro ao obter conversation_id para o user: $user. Erro: " . curl_error($ch));
        return null;
    }

    curl_close($ch);

    $response_data = json_decode($result, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message('run', 'error', "Erro ao decodificar JSON ao obter conversation_id para o user: $user");
        return null;
    }

    if (empty($response_data['data'])) {
        log_message('run', 'info', "Nenhuma conversa encontrada para o user: $user");
        return null;
    }

    return $response_data['data'][0]['id'] ?? null;
}

// Recupera o user e conversation_id
$user = urlencode($pending_data['overrideConfig']['sessionId']);
if (empty($conversation_id)) {
    log_message('run', 'info', "conversation_id está faltando. Tentando obter o conversation_id para o user: $user");
    $conversation_id = get_conversation_id($user, $dify_key);
    if (empty($conversation_id)) {
        log_message('run', 'error', "Falha ao obter o conversation_id para o user: $user");
        die(json_encode(['error' => 'Falha ao obter o conversation_id.']));
    }
    log_message('run', 'info', "conversation_id obtido com sucesso: $conversation_id");
}

// URL para consultar o histórico de conversas
$limit = 1;
$retry_url = "https://api.dify.ai/v1/messages?user=$user&conversation_id=$conversation_id&limit=$limit";

// Função para obter o histórico de conversas
function get_conversation_history($retry_url, $dify_key, $id, $pending_data) {
    global $completed_dir;

    $ch = curl_init($retry_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $dify_key
    ]);

    $result = curl_exec($ch);

    if ($result === FALSE) {
        log_message('run', 'error', "Erro ao obter o histórico de conversa para id: $id. Erro: " . curl_error($ch));
        return false;
    }

    curl_close($ch);

    // Loga a resposta bruta para depuração
    log_message('run', 'info', "Raw response for id: $id: " . $result);

    // Processa a resposta do endpoint
    $response_data = json_decode($result, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message('run', 'error', "Erro ao decodificar JSON da resposta para o id: $id");
        return false;
    }

    if (empty($response_data['data'])) {
        log_message('run', 'info', "Nenhuma mensagem encontrada para o id: $id");
        return false;
    }

    $latest_message = $response_data['data'][0];
    $query = $latest_message['query'] ?? '';

    if ($query !== $pending_data['question']) {
        log_message('run', 'info', "A pergunta na resposta não corresponde à pergunta no arquivo pendente para o id: $id");
        return false;
    }

    // Adiciona o campo "thought" à resposta
    $latest_message['thought'] = $latest_message['answer'];

    // Combina os dados do arquivo pendente com os dados da resposta
    $combined_data = array_merge($pending_data, $latest_message);

    // Salva os dados combinados na pasta `./completed`
    $completed_file_path = $completed_dir . $id . '.json';
    file_put_contents($completed_file_path, json_encode($combined_data, JSON_PRETTY_PRINT));
    log_message('run', 'info', "Dados combinados salvos em: $completed_file_path");

    return $completed_file_path;
}

// Loop de tentativas para obter a resposta
$max_retries = 18;
$retry_interval = 5; // segundos

$completed_file_path = null;
for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
    log_message('run', 'info', "Tentativa $attempt de $max_retries para obter a resposta.");
    $completed_file_path = get_conversation_history($retry_url, $dify_key, $id, $pending_data);
    if ($completed_file_path) {
        break;
    }
    sleep($retry_interval);
}

if ($attempt > $max_retries) {
    log_message('run', 'error', "Falha ao obter resposta de Dify após $max_retries tentativas para id: $id");
    die(json_encode(['error' => "Falha ao obter resposta de Dify após $max_retries tentativas."]));
}

// Remove o arquivo da pasta pending
if (!unlink($file_path)) {
    log_message('run', 'error', "Falha ao remover o arquivo pendente para id: $id");
}

log_message('run', 'info', "Arquivo pendente removido com sucesso para id: $id");

// Lê os dados do arquivo completado
$response_data = json_decode(file_get_contents($completed_file_path), true);

// Verifica o source e decide se deve chamar o generate-audio.php
$source = $response_data['source'] ?? 'Desconhecido';
if ($source === 'Telegram') {
    log_message('run', 'info', "Source é Telegram. Chamada ao generate-audio.php não será feita para id: $id.");
} else {
    // Integração com generate-audio.php
    log_message('run', 'info', "Iniciando integração com generate-audio.php para mensagem $id.");

    $agent_thoughts = $response_data['agent_thoughts'] ?? [];
    $mensagemDeVoz = getMensagemDeVoz($agent_thoughts);
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

    log_message('run', 'info', "Chamada para generate-audio.php enviada para id: $id.");
}

// Integração com trello_integration.php
log_message('run', 'info', "Iniciando integração com trello_integration.php para mensagem $id.");

// Prepara o payload
$trello_payload = json_encode(['id' => $id]);

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

log_message('run', 'info', "Chamada para trello_integration.php enviada para id: $id.");

// Integração com slack_integration.php
log_message('run', 'info', "Iniciando integração com slack_integration.php para mensagem $id.");

// Prepara o payload
$slack_payload = json_encode(['id' => $id]);

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

log_message('run', 'info', "Chamada para slack_integration.php enviada para id: $id.");

// Retorna 200 OK
http_response_code(200);
log_message('run', 'info', "Processamento concluído para id: $id.");
?>