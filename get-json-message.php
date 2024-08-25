<?php

// Definindo a chave da API como uma constante
define('OPENAI_API_KEY', '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');

// Função para logar mensagens em um arquivo
function logMessage($message) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/log_' . date('Ymd') . '.txt';
    $logEntry = date('Y-m-d H:i:s') . " - " . $message . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Função para listar os passos do run
function listRunSteps($threadId, $runId) {
    $url = "https://api.openai.com/v1/threads/{$threadId}/runs/{$runId}/steps";
    $apiKey = OPENAI_API_KEY;

    $headers = [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json",
        "OpenAI-Beta: assistants=v2"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        logMessage("cURL error: " . curl_error($ch));
    }
    curl_close($ch);

    $decodedResponse = json_decode($response, true);
    logMessage("listRunSteps response: " . print_r($decodedResponse, true));
    return $decodedResponse;
}

// Função para recuperar a mensagem usando o messageId
function retrieveMessage($threadId, $messageId) {
    $url = "https://api.openai.com/v1/threads/{$threadId}/messages/{$messageId}";
    $apiKey = OPENAI_API_KEY;

    $headers = [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json",
        "OpenAI-Beta: assistants=v2"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        logMessage("cURL error: " . curl_error($ch));
    }
    curl_close($ch);

    $decodedResponse = json_decode($response, true);
    logMessage("retrieveMessage response: " . print_r($decodedResponse, true));
    return $decodedResponse;
}

// Função principal para retornar o JSON da mensagem gerada
function getMessageDetails($threadId, $runId) {
    $runSteps = listRunSteps($threadId, $runId);
    
    if (!isset($runSteps['data'][0]['step_details']['message_creation']['message_id'])) {
        logMessage("No message_id found in runSteps: " . print_r($runSteps, true));
        return null;
    }

    $messageId = $runSteps['data'][0]['step_details']['message_creation']['message_id']; // Obter o messageId corretamente
    
    $messageDetails = retrieveMessage($threadId, $messageId);

    // Verificando se a mensagem foi obtida corretamente
    if (!isset($messageDetails['content'][0]['text']['value'])) {
        logMessage("No content found in messageDetails: " . print_r($messageDetails, true));
        return null;
    }

    // Decodificar o JSON dentro do campo "value"
    $decodedValue = json_decode($messageDetails['content'][0]['text']['value'], true);

    // Retornar o JSON decodificado
    return $decodedValue;
}

// Capturando parâmetros passados via URL (query string)
$threadId = $_GET['threadId'];
$runId = $_GET['runId'];

// Logando os parâmetros recebidos
logMessage("Received threadId: $threadId, runId: $runId");

// Verificando se os parâmetros necessários foram fornecidos
if (!$threadId || !$runId) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameters: threadId and runId"]);
    logMessage("Missing required parameters: threadId or runId not provided.");
    exit();
}

// Executando a função principal e retornando o resultado
$response = getMessageDetails($threadId, $runId);
header('Content-Type: application/json');
echo json_encode($response);

if ($response === null) {
    logMessage("No valid response returned from getMessageDetails.");
}

?>
