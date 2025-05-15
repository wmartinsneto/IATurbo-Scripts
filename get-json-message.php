<?php

/**
 * API para Recuperação de Mensagem Gerada por um Assistente da OpenAI
 *
 * Esta API recebe um `threadId` e um `runId` como parâmetros via query string
 * e retorna o conteúdo JSON da mensagem gerada pelo assistente OpenAI.
 * 
 * Funcionamento:
 * 1. A API faz uma chamada para listar os passos (steps) de um "run" específico, 
 *    usando o `threadId` e o `runId` fornecidos.
 * 2. Com base nos passos listados, a API obtém o `messageId` da mensagem gerada.
 * 3. A API então faz uma nova chamada para recuperar a mensagem usando o `messageId`.
 * 4. O conteúdo JSON da mensagem (armazenado no campo "value") é decodificado e retornado ao cliente.
 * 
 * Parâmetros:
 * - `threadId` (string): O ID do thread associado ao "run".
 * - `runId` (string): O ID do run que contém os passos executados.
 * 
 * Resposta:
 * - Retorna um objeto JSON com o conteúdo da mensagem gerada.
 * - Se faltar algum parâmetro ou ocorrer um erro, a API retorna um código HTTP apropriado
 *   e uma mensagem de erro.
 * 
 * Logs:
 * - A API grava logs detalhados de todas as operações no diretório `logs`, incluindo erros de cURL,
 *   respostas da API e os parâmetros recebidos.
 * 
 * Uso:
 * - Faça uma requisição GET para esta API com os parâmetros `threadId` e `runId` na URL.
 * 
 * Exemplo de Uso:
 * URL: https://iaturbo.com.br/wp-content/uploads/scripts/get-json-message.php?threadId=thread_3wRqkK76J5RdsNpNCFZ83q1L&runId=run_HuCNLlPzqILqQ6e70O4OqQ6Z
 * 
 */

 // Definindo a chave da API como uma constante
define('OPENAI_API_KEY', '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');

/**
 * Função para logar mensagens em um arquivo
 * 
 * @param string $message A mensagem a ser logada
 */
function logMessage($message) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);  // Cria o diretório de logs se não existir
    }
    $logFile = $logDir . '/log_' . date('Ymd') . '.txt';  // Define o nome do arquivo de log com a data atual
    $logEntry = date('Y-m-d H:i:s') . " - " . $message . PHP_EOL;  // Formata a mensagem com data e hora
    file_put_contents($logFile, $logEntry, FILE_APPEND);  // Escreve a mensagem no arquivo de log
}

/**
 * Função para listar os passos de um "run"
 * 
 * @param string $threadId O ID do thread
 * @param string $runId O ID do run
 * @return array A resposta decodificada da API contendo os detalhes dos passos
 */
function listRunSteps($threadId, $runId) {
    $url = "https://api.openai.com/v1/threads/{$threadId}/runs/{$runId}/steps";  // URL da API
    $apiKey = OPENAI_API_KEY;

    $headers = [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json",
        "OpenAI-Beta: assistants=v2"
    ];

    // Inicia a requisição cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);  // Executa a requisição
    if (curl_errno($ch)) {
        logMessage("cURL error: " . curl_error($ch));  // Loga erros de cURL, se existirem
    }
    curl_close($ch);

    $decodedResponse = json_decode($response, true);  // Decodifica a resposta JSON
    logMessage("listRunSteps response: " . print_r($decodedResponse, true));  // Loga a resposta
    return $decodedResponse;
}

/**
 * Função para recuperar a mensagem gerada usando o messageId
 * 
 * @param string $threadId O ID do thread
 * @param string $messageId O ID da mensagem
 * @return array A resposta decodificada da API contendo os detalhes da mensagem
 */
function retrieveMessage($threadId, $messageId) {
    $url = "https://api.openai.com/v1/threads/{$threadId}/messages/{$messageId}";  // URL da API
    $apiKey = OPENAI_API_KEY;

    $headers = [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json",
        "OpenAI-Beta: assistants=v2"
    ];

    // Inicia a requisição cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);  // Executa a requisição
    if (curl_errno($ch)) {
        logMessage("cURL error: " . curl_error($ch));  // Loga erros de cURL, se existirem
    }
    curl_close($ch);

    $decodedResponse = json_decode($response, true);  // Decodifica a resposta JSON
    logMessage("retrieveMessage response: " . print_r($decodedResponse, true));  // Loga a resposta
    return $decodedResponse;
}

/**
 * Função principal para obter o JSON da mensagem gerada
 * 
 * @param string $threadId O ID do thread
 * @param string $runId O ID do run
 * @return array|null O JSON decodificado da mensagem ou null em caso de erro
 */
function getMessageDetails($threadId, $runId) {
    $runSteps = listRunSteps($threadId, $runId);
    
    // Verifica se o messageId foi encontrado no runSteps
    if (!isset($runSteps['data'][0]['step_details']['message_creation']['message_id'])) {
        logMessage("No message_id found in runSteps: " . print_r($runSteps, true));
        return null;
    }

    $messageId = $runSteps['data'][0]['step_details']['message_creation']['message_id'];  // Extrai o messageId corretamente
    
    $messageDetails = retrieveMessage($threadId, $messageId);

    // Verifica se o conteúdo da mensagem foi obtido corretamente
    if (!isset($messageDetails['content'][0]['text']['value'])) {
        logMessage("No content found in messageDetails: " . print_r($messageDetails, true));
        return null;
    }

    // Decodifica o JSON dentro do campo "value"
    $decodedValue = json_decode($messageDetails['content'][0]['text']['value'], true);

    // Retorna o JSON decodificado
    return $decodedValue;
}

// Captura os parâmetros passados via URL (query string)
$threadId = $_GET['threadId'];
$runId = $_GET['runId'];

// Loga os parâmetros recebidos
logMessage("Received threadId: $threadId, runId: $runId");

// Verifica se os parâmetros necessários foram fornecidos
if (!$threadId || !$runId) {
    http_response_code(400);  // Retorna código HTTP 400 (Bad Request) se os parâmetros estiverem ausentes
    echo json_encode(["error" => "Missing required parameters: threadId and runId"]);
    logMessage("Missing required parameters: threadId or runId not provided.");
    exit();
}

// Executa a função principal e retorna o resultado
$response = getMessageDetails($threadId, $runId);
header('Content-Type: application/json');
echo json_encode($response);

if ($response === null) {
    logMessage("No valid response returned from getMessageDetails.");
}

?>
