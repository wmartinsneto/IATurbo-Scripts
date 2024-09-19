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

// Validação da resposta do FlowiseAI
$response_data = json_decode($response, true);
if (!$response_data) {
    log_message("Falha ao decodificar a resposta do FlowiseAI para id: $id");
    die(json_encode(['error' => 'Falha ao decodificar a resposta do FlowiseAI.']));
}

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

// Integração com o Trello - Chama a função `send_to_trello` para enviar a resposta ao Trello
log_message("Iniciando envio ao Trello para id: $id.");

// Substitua os parâmetros abaixo conforme sua necessidade
send_to_trello([
    'sessionId' => $data['overrideConfig']['sessionId'] ?? $id, // Certifique-se de que o sessionId correto está sendo usado
    'name' => $data['userData']['firstName'] . ' ' . $data['userData']['lastName'],  // Nome real do lead
    'message' => $data['question'] ?? 'Sem pergunta disponível', // Pergunta do lead
    'source' => $data['source'] ?? 'Desconhecido', // Fonte da mensagem (whats, insta, etc.)
    'userData' => $data['userData'] ?? [],  // Dados do usuário
    'content' => "Aqui vai o conteúdo gerado para o Trello",  // Substitua pelo conteúdo real
    'textResponse' => $response_data['text'] ?? 'Sem resposta de texto disponível', // Resposta em texto do FlowiseAI
    'audioUrl' => $response_data['audioUrl'] ?? null, // Se houver resposta de áudio, passe a URL aqui
    'controlData' => $response_data['controlData'] ?? [], // Informações de controle da conversa, se existirem
]);

log_message("Envio ao Trello completo para id: $id.");

// Retorna 200 OK
http_response_code(200);
log_message("Resposta retornada com sucesso para id: $id.");
?>
