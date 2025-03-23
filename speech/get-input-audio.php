<?php

/**
 * API para Obtenção de Arquivo de Áudio
 *
 * Este script verifica se um arquivo de áudio MP3 correspondente a um ID fornecido existe.
 * Se o arquivo não for encontrado, o script tenta novamente a cada 1 segundo, por até 5 vezes.
 * Se após 5 tentativas o arquivo ainda não for encontrado, retorna um status "pending".
 * Caso o arquivo seja encontrado, retorna status "ok" e a URL do áudio.
 *
 * Como usar:
 * 
 * Enviar uma requisição GET para este script com o parâmetro "id".
 * Exemplo:
 * 
 * GET /speech/get-input-audio.php?id=12345
 * 
 * Respostas:
 * 
 * - Se o arquivo for encontrado:
 *   {
 *     "status": "ok",
 *     "audio_url": "https://iaturbo.com.br/wp-content/uploads/scripts/speech/input/audio_12345.mp3"
 *   }
 * 
 * - Se o arquivo não for encontrado após 5 tentativas:
 *   {
 *     "status": "pending"
 *   }
 */

$start_time = microtime(true);

include 'helpers.php';

// Função para verificar se o arquivo de áudio existe
function check_audio_file($id) {
    $filename = 'audio_' . $id . '.mp3';
    $audio_file_path = __DIR__ . '/input/' . $filename;
    return file_exists($audio_file_path);
}

// Função para retornar a URL do arquivo de áudio
function get_audio_url($id) {
    $filename = 'audio_' . $id . '.mp3';
    return 'https://iaturbo.com.br/wp-content/uploads/scripts/speech/input/' . $filename;
}

// Verifica se o parâmetro "id" foi fornecido
if (!isset($_GET['id'])) {
    log_message('speech', 'error', '[get-audio] Parâmetro "id" não fornecido.');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Parâmetro "id" não fornecido.']);
    exit;
}

$id = $_GET['id'];
log_message('speech', 'info', '[get-audio] Obtenção do áudio iniciada para o ID: ' . $id);

$attempts = 0;
$max_attempts = 5;
$interval = 1; // Intervalo de 1 segundo entre as tentativas

while ($attempts < $max_attempts) {
    if (check_audio_file($id)) {
        $audio_url = get_audio_url($id);
        log_message('speech', 'info', '[get-audio] Áudio encontrado para o ID: ' . $id . '. URL: ' . $audio_url);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'audio_url' => $audio_url]);

        // Calcula o tempo total de execução
        $execution_time = round((microtime(true) - $start_time) * 1000);
        log_message('speech', 'info', "[get-audio] Tempo total de execução: {$execution_time}ms");

        exit;
    }
    $attempts++;
    log_message('speech', 'info', '[get-audio] Tentativa ' . $attempts . ' de ' . $max_attempts . ' para o ID: ' . $id);
    sleep($interval);
}

// Se o arquivo não for encontrado após 5 tentativas, retorna status "pending"
log_message('speech', 'error', '[get-audio] Áudio não encontrado após ' . $max_attempts . ' tentativas para o ID: ' . $id);
header('Content-Type: application/json');
echo json_encode(['status' => 'pending']);

// Calcula o tempo total de execução
$execution_time = round((microtime(true) - $start_time) * 1000);
log_message('speech', 'info', "[get-audio] Tempo total de execução: {$execution_time}ms");

?>