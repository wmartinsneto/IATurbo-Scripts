<?php
// Configurações
$api_key = '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';
$save_dir = './output/';
$log_dir = './logs/';

// Verifica se a pasta de destino e de logs existem, caso contrário, cria
if (!is_dir($save_dir)) {
    mkdir($save_dir, 0777, true);
    log_message('speech', 'info', '[generate-audio-player] Diretório ' . $save_dir . ' criado.');
}
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true);
    log_message('speech', 'info', '[generate-audio-player] Diretório ' . $log_dir . ' criado.');
}

include 'helpers.php';

// Recebe o payload JSON da requisição
$payload = json_decode(file_get_contents('php://input'), true);
log_message('speech', 'info', '[generate-audio-player] Payload recebido: ' . json_encode($payload));

// Valida se todos os campos necessários estão presentes
if (!isset($payload['input_text'], $payload['voice'], $payload['model'])) {
    log_message('speech', 'error', '[generate-audio-player] Payload inválido: ' . json_encode($payload));
    die(json_encode(['error' => 'Payload inválido']));
}

// Dados do payload
$input_text = $payload['input_text'];
$voice = $payload['voice'];
$model = $payload['model'];

// URL da API de Text-to-Speech da OpenAI
$url = 'https://api.openai.com/v1/audio/speech';

// Dados para a requisição
$data = [
    'model' => $model,
    'input' => $input_text,
    'voice' => $voice
];
log_message('speech', 'info', '[generate-audio-player] Dados para a requisição: ' . json_encode($data));

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
$response = file_get_contents($url, false, $context);

if ($response === FALSE) {
    log_message('speech', 'error', '[generate-audio-player] Falha na requisição para a API da OpenAI');
    die(json_encode(['error' => 'Falha na requisição para a API da OpenAI']));
}

log_message('speech', 'info', '[generate-audio-player] Resposta da API recebida');

// Gera um nome único para o arquivo MP3
$filename = uniqid() . '.mp3';
$file_path = $save_dir . $filename;

// Salva o conteúdo do áudio como um arquivo MP3
file_put_contents($file_path, $response);

// Verifica se o arquivo foi criado com sucesso
if (!file_exists($file_path) || filesize($file_path) == 0) {
    log_message('speech', 'error', '[generate-audio-player] Falha ao salvar o arquivo de áudio: ' . $file_path);
    die(json_encode(['error' => 'Falha ao salvar o arquivo de áudio']));
}

log_message('speech', 'info', '[generate-audio-player] Arquivo de áudio salvo com sucesso: ' . $file_path);

// URL de acesso ao arquivo MP3
$audio_url = 'https://iaturbo.com.br/wp-content/uploads/scripts/speech/output/' . $filename;

// Retorna o HTML da tag de áudio
$html_audio = '<audio controls autoplay><source src="' . $audio_url . '" type="audio/mp3"></audio>';
log_message('speech', 'success', '[generate-audio-player] Áudio gerado com sucesso: ' . $audio_url);
echo json_encode(['html_audio' => $html_audio]);
?>
