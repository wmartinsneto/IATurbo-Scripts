<?php
/**
 * flowiseai-chatflow.php
 *
 * Este script recebe um payload JSON contendo uma pergunta e um sessionId, e faz uma requisição para um endpoint de previsão da API Flowise AI.
 * O script envia a pergunta e o sessionId para o endpoint especificado, e retorna a resposta no campo "text" do JSON retornado pela API.
 *
 * Como utilizar:
 * 1. Envie um POST request para este script com um payload JSON, como o exemplo abaixo:
 * 
 * {
 *     "question": "Quem eu sou e qual a primeira pergunta que eu te fiz?",
 *     "overrideConfig": {
 *         "sessionId": "123"
 *     }     
 * }
 * 
 * 2. O script espera receber o token de autorização no cabeçalho "Authorization".
 * 
 * Retorno:
 * O script retorna um JSON com o conteúdo do campo "text", devidamente tratado.
 *
 * Exemplo de retorno:
 * 
 * {
 *     "mensagemDeTexto": "Você é o *Daniel* e a primeira pergunta que você me fez foi sobre a cotação do euro hoje. 💶",
 *     "mensagemDeVoz": "Você é o Daniel e a primeira coisa que me perguntou foi sobre a cotação do euro hoje. Precisa de mais alguma coisa?"
 * }
 */

// Habilitar ou desabilitar logs
define('ENABLE_LOGGING', true);

// Diretório de logs
$log_dir = './logs/';

// Função para registrar logs
function log_message($message) {
    global $log_dir;
    if (ENABLE_LOGGING) {
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0777, true);
        }
        $log_file = $log_dir . 'flowise-log-' . date('Ymd') . '.log';
        $log_entry = date('Y-m-d H:i:s') . " - {$message}\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}

// Recebe o token de autorização do cabeçalho da requisição
$authorization_token = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

if (!$authorization_token) {
    log_message("Erro: Token de autorização não fornecido.");
    die(json_encode(['error' => 'Token de autorização não fornecido.']));
}

// Configurações da API
$api_url = 'https://flowiseai-railway-production-16fe.up.railway.app/api/v1/prediction/b69ccbf6-8014-4005-a024-69cf600f65ae';

// Recebe o payload JSON da requisição
$payload = json_decode(file_get_contents('php://input'), true);
log_message("Parâmetros de entrada: " . json_encode($payload));

if (!isset($payload['question'], $payload['overrideConfig']['sessionId'])) {
    log_message("Erro: Payload inválido.");
    die(json_encode(['error' => 'Payload inválido. É necessário fornecer "question" e "sessionId".']));
}

$question = $payload['question'];
$sessionId = $payload['overrideConfig']['sessionId'];

// Prepara o payload para a requisição à API Flowise AI
$api_payload = json_encode([
    'question' => $question,
    'overrideConfig' => [
        'sessionId' => $sessionId
    ]
]);
log_message("Payload para Flowise AI: " . $api_payload);

// Configurações da requisição
$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n" .
                     "Authorization: $authorization_token\r\n",
        'method'  => 'POST',
        'content' => $api_payload,
    ],
];

$context  = stream_context_create($options);
$response = file_get_contents($api_url, false, $context);

if ($response === FALSE) {
    log_message("Erro: Falha na requisição para a API Flowise AI.");
    die(json_encode(['error' => 'Falha na requisição para a API Flowise AI']));
}

log_message("Resposta da API Flowise AI: " . $response);

// Decodifica a resposta da API
$response_data = json_decode($response, true);

if (isset($response_data['text'])) {
    // Extrai e decodifica o valor do campo "text"
    $text_response = json_decode($response_data['text'], true);
    log_message("Resposta retornada pelo script: " . json_encode($text_response));
    echo json_encode($text_response);
} else {
    log_message("Erro: A resposta da API não contém o campo 'text'.");
    die(json_encode(['error' => 'A resposta da API não contém o campo "text".']));
}
?>
