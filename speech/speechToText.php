<?php
/**
 * speechToText.php
 *
 * Recebe via POST um arquivo de áudio enviado pelo frontend e o envia para a API da OpenAI (Whisper)
 * para transcrição. Retorna a transcrição em JSON.
 */

header('Content-Type: application/json');

$apiKey = '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'; 

// Verifica se um arquivo foi enviado
if (!isset($_FILES['audio'])) {
    echo json_encode(['error' => 'Arquivo de áudio não enviado']);
    exit;
}

$audioFilePath = $_FILES['audio']['tmp_name'];
$audioMime = $_FILES['audio']['type'];
$audioName = $_FILES['audio']['name'];

// API endpoint para transcrição (exemplo: Whisper)
$url = 'https://api.openai.com/v1/audio/transcriptions';

// Prepara a requisição cURL com o áudio
$curl = curl_init();
$postFields = [
    'file'  => new CURLFile($audioFilePath, $audioMime, $audioName),
    'model' => 'whisper-1'  // Modelo sugerido; ajuste se necessário
];

curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $apiKey"
    ],
    CURLOPT_POSTFIELDS => $postFields,
]);

$response = curl_exec($curl);
if (curl_errno($curl)) {
    echo json_encode(['error' => curl_error($curl)]);
    exit;
}
curl_close($curl);
echo $response;