<?php
/**
 * run.php
 * 
 * Este script recebe um `id` (gerado pelo `request.php`), uma URL do Flowise e um token de autorização via POST,
 * carrega o arquivo correspondente na pasta `./pending`, envia a pergunta para o FlowiseAI,
 * salva a resposta na pasta `./completed`, envia as informações para o Trello e retorna um status 200 OK.
 */

$pending_dir = './pending/';
$completed_dir = './completed/';
$log_file = './logs/run-' . date('Y-m-d') . '.log';

// Inclui o script trello-message.php para enviar ao Trello
require_once './trello-message.php';

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
$chatflow_url = $data['chatflow_url'] ?? null;
$chatflow_key = $data['chatflow_key'] ?? null;

log_message("Requisição recebida. ID: $id, Chatflow URL: $chatflow_url");

// Verifica se todos os parâmetros obrigatórios estão presentes
if (!$id || !$chatflow_url || !$chatflow_key) {
    log_message("Parâmetros faltando. id, chatflow_url e chatflow_key são obrigatórios.");
    die(json_encode(['error' => 'Parâmetros faltando. id, chatflow_url e chatflow_key são obrigatórios.']));
}

$file_path = $pending_dir . $id . '.json';

if (!file_exists($file_path)) {
    log_message("Arquivo não encontrado para id: $id");
    die(json_encode(['error' => 'Arquivo não encontrado.']));
}

// Lê os dados do arquivo pendente
$data = json_decode(file_get_contents($file_path), true);
log_message("Arquivo carregado com sucesso para id: $id. Dados: " . print_r($data, true));

// Envia a pergunta para o FlowiseAI
$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\nAuthorization: Bearer $chatflow_key\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
    ],
];

$context = stream_context_create($options);

// Captura a resposta ou o erro detalhado
$response = @file_get_contents($chatflow_url, false, $context);

if ($response === FALSE) {
    log_stream_error($chatflow_url, $options);
    die(json_encode(['error' => 'Falha na requisição ao FlowiseAI. Verifique os logs para mais detalhes.']));
}

log_message("Resposta recebida de IATurbo para id: $id.");

// Validação da resposta do FlowiseAI
$response_data = json_decode($response, true);
if (!$response_data) {
    log_message("Falha ao decodificar a resposta de IATurbo para id: $id");
    die(json_encode(['error' => 'Falha ao decodificar a resposta do FlowiseAI.']));
}

log_message("Resposta decodificada com sucesso para id: $id. Resposta: " . print_r($response_data, true));

// Salva a resposta na pasta ./completed
if (file_put_contents($completed_dir . $id . '.json', json_encode($response_data, JSON_PRETTY_PRINT)) === false) {
    log_message("Falha ao salvar o arquivo em ./completed/$id.json.");
    die(json_encode(['error' => 'Falha ao salvar o arquivo processado.']));
}

log_message("Processamento completo para id: $id. Resposta salva em ./completed/$id.json");

// Remove o arquivo da pasta pending
if (!unlink($file_path)) {
    log_message("Falha ao remover o arquivo pendente para id: $id");
    die(json_encode(['error' => 'Falha ao remover o arquivo pendente.']));
}

log_message("Arquivo pendente removido com sucesso para id: $id");

// Integração com o Trello - Chama a função `send_to_trello` para enviar a resposta ao Trello
log_message("Iniciando envio ao Trello para mensagem $id.");

$formattedResponse = null;
try {

    log_message('Decoding $response_data["text"]');
    $decodedTextResponse = json_decode($response_data['text'], true);

    log_message('Formatting decodedTextResponse');
    $formattedResponse = formatTrelloContent($id, $decodedTextResponse);

    log_message('Sending to Trello');
    send_to_trello([
        'leadRequestId' => $id, 
        'leadQuestion' => $data['question'] ?? 'Sem pergunta disponível', 
        'leadName' => $data['userData']['firstName'] . ' ' . $data['userData']['lastName'],  
        'source' => $data['source'] ?? 'Desconhecido', 
        'sessionId' => $data['overrideConfig']['sessionId'], 
        'userData' => $data['userData'] ?? [],  
        'formattedResponse' => $formattedResponse
    ]);

    log_message("Envio ao Trello completo para id: $id.");
} catch (Exception $e) {
    log_message("Falha ao enviar ao Trello para id: $id. Erro: " . $e->getMessage(), 'error');
}

// Integração com Slack
log_message("Iniciando envio de notificação p/ Slack");
try {
    $webhookUrl = 'https://hooks.slack.com/services/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';

    $slackMessage = "Lead no " . $data['source'] . "\n";
    foreach ($data['userData'] as $key => $value) {
        $slackMessage .= ucfirst($key) . ": $value\n";
    }
    $slackMessage .= "PERGUNTA:\n" . $data['question'] . "\n\n";
    $slackMessage .= "RESPOSTA:\n" . $formattedResponse;

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
        echo 'Falha ao enviar a mensagem ao Slack.';
    } else {
        echo 'Mensagem enviada com sucesso ao Slack.';
    }
} catch (Exception $e) {
    log_message("Falha ao enviar notificação no Slack. Erro: " . $e->getMessage(), 'error');
}

// Retorna 200 OK mesmo em caso de falha no Trello
http_response_code(200);
log_message("Resposta retornada com sucesso para id: $id.");


?>