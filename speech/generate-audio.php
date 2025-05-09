<?php

/**
 * API de Conversão de Texto para Fala (TTS) usando OpenAI
 */

$start_time = microtime(true);

// Incluir configuração centralizada
require_once __DIR__ . '/../config.php';
include 'helpers.php';

// Obter configurações das variáveis de ambiente
$api_key = config('openai_api_key');
$save_dir = config('output_dir');
$log_dir = config('logs_dir');

// Configurações de modelo e voz
$model = config('speech_model');
$voice = config('speech_voice');

// Verifica se a pasta de destino e de logs existem, caso contrário, cria
if (!is_dir($save_dir)) {
    mkdir($save_dir, 0777, true);
    log_message('speech', 'info', '[generate-audio] Diretório ' . $save_dir . ' criado.');
}
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true);
    log_message('speech', 'info', '[generate-audio] Diretório ' . $log_dir . ' criado.');
}

// Recebe o payload JSON da requisição
$payload = json_decode(file_get_contents('php://input'), true);
log_message('speech', 'info', '[generate-audio] Payload recebido: ' . json_encode($payload));

// Valida se todos os campos necessários estão presentes
if (!isset($payload['input_text'], $payload['id'])) {
    log_message('speech', 'error', '[generate-audio] Payload inválido: ' . json_encode($payload));
    die(json_encode(['error' => 'Payload inválido']));
}

// Dados do payload
$input_text = $payload['input_text'];
$id = $payload['id'];  // Parâmetro que será usado para nomear o arquivo
log_message('speech', 'info', '[generate-audio] Processando áudio para o ID: ' . $id);

// URL da API de Text-to-Speech da OpenAI
$url = 'https://api.openai.com/v1/audio/speech';

// Dados para a requisição
$data = [
    'model' => $model,
    'input' => $input_text,
    'voice' => $voice
];
log_message('speech', 'info', '[generate-audio] Dados para a requisição: ' . json_encode($data));

// Configurações da requisição
$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n" .
                     "Authorization: Bearer $api_key\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
    ],
];

$context  = stream_context_create($options);

// Faz a requisição para a API da OpenAI
log_message('speech', 'info', '[generate-audio] Enviando requisição para a API da OpenAI.');
$response = file_get_contents($url, false, $context);

if ($response === FALSE) {
    log_message('speech', 'error', '[generate-audio] Falha na requisição para a API da OpenAI.');
    die(json_encode(['error' => 'Falha na requisição para a API da OpenAI']));
}

log_message('speech', 'info', '[generate-audio] Resposta da API recebida com sucesso.');

// Gera um nome para o arquivo MP3 com base no ID recebido
$filename = 'audio_' . $id . '.mp3';
$file_path = $save_dir . $filename;

// Salva o conteúdo do áudio como um arquivo MP3
$result = file_put_contents($file_path, $response);

if ($result === FALSE) {
    log_message('speech', 'error', '[generate-audio] Falha ao salvar o arquivo de áudio: ' . $file_path);
    die(json_encode(['error' => 'Falha ao salvar o arquivo de áudio']));
}

log_message('speech', 'info', '[generate-audio] Arquivo de áudio salvo com sucesso: ' . $file_path);

// Verifica se o arquivo foi criado com sucesso
if (!file_exists($file_path) || filesize($file_path) == 0) {
    log_message('speech', 'error', '[generate-audio] O arquivo de áudio não foi criado corretamente: ' . $file_path);
    die(json_encode(['error' => 'Falha ao salvar o arquivo de áudio']));
}

// URL de acesso ao arquivo MP3
$audio_url = config('base_url') . '/speech/output/' . $filename;

// Retorna o JSON com a URL do áudio
$response_data = ['audio_url' => $audio_url];
log_message('speech', 'info', '[generate-audio] Processamento concluído com sucesso. Dados de resposta: ' . json_encode($response_data));

// Calcula o tempo total de execução
$execution_time = round((microtime(true) - $start_time) * 1000);

log_message('speech', 'info', "[generate-audio] Tempo total de execução: {$execution_time}ms");
echo json_encode($response_data);

?>
