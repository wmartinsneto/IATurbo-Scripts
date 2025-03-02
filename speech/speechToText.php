<?php
/**
 * speechToText.php
 *
 * Recebe via POST um arquivo de áudio, salva o arquivo recebido na pasta /input,
 * chama a API da OpenAI (Whisper) para transcrição e retorna a transcrição em JSON.
 * Logs completos são gerados para todas as etapas de entrada e saída.
 */

header('Content-Type: application/json');
include 'helpers.php';

$start_time = microtime(true);
log_message('speechToText', 'info', 'Início de execução do speechToText.php');

// Verifica se um arquivo de áudio foi enviado
if (!isset($_FILES['audio'])) {
    log_message('speechToText', 'error', 'Arquivo de áudio não enviado');
    echo json_encode(['error' => 'Arquivo de áudio não enviado']);
    exit;
}

$audioFilePath = $_FILES['audio']['tmp_name'];
$audioMime = $_FILES['audio']['type'];
$audioName = $_FILES['audio']['name'];
log_message('speechToText', 'info', "Arquivo recebido: {$audioName}, MIME: {$audioMime}");

// Cria a pasta /input se não existir e move o arquivo para lá com timestamp
$input_dir = './input/';
if (!is_dir($input_dir)) {
    mkdir($input_dir, 0777, true);
    log_message('speechToText', 'info', "Diretório {$input_dir} criado.");
}
$timestamp = date('Ymd_His');
$savedFileName = $input_dir . "audio_{$timestamp}_" . basename($audioName);

if (!move_uploaded_file($audioFilePath, $savedFileName)) {
    log_message('speechToText', 'error', "Falha ao salvar áudio de entrada em: {$savedFileName}");
    echo json_encode(['error' => 'Falha ao salvar áudio de entrada']);
    exit;
}
log_message('speechToText', 'info', "Áudio de entrada salvo em: {$savedFileName}");

// Configura a chamada à API da OpenAI (Whisper)
$url = 'https://api.openai.com/v1/audio/transcriptions';
$apiKey = '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';

$postFields = [
    'file'  => new CURLFile(realpath($savedFileName), $audioMime, basename($savedFileName)),
    'model' => 'whisper-1',
    'response_format' => 'json',
    'language' => 'pt',
    'prompt' => 'Português Brasileiro, IATurbo, Chatbots IATurbo, IARA, Chatbot'
];

log_message('speechToText', 'info', "Preparando requisição para a API de transcrição: " . json_encode([
    'file'  => basename($savedFileName),
    'model' => 'whisper-1',
    'response_format' => 'json',
    'language' => 'pt',
    'prompt' => 'Português Brasileiro, IATurbo, Chatbots IATurbo, IARA, Chatbot'
]));

// Configurações da requisição cURL
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $apiKey",
        "Content-Type: multipart/form-data"
    ],
    CURLOPT_POSTFIELDS => $postFields,
]);

// Executa a requisição e captura a resposta
$response = curl_exec($curl);
if (curl_errno($curl)) {
    $error_msg = curl_error($curl);
    log_message('speechToText', 'error', "Erro na chamada à API: " . $error_msg);
    echo json_encode(['error' => $error_msg]);
    curl_close($curl);
    exit;
}
curl_close($curl);
log_message('speechToText', 'info', "Resposta da API: " . $response);

// Calcula e loga o tempo total de execução
$execution_time = round((microtime(true) - $start_time) * 1000);
log_message('speechToText', 'info', "Tempo total de execução: {$execution_time}ms");

// Retorna a resposta da API
echo $response;
?>