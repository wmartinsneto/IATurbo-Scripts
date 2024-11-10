<?php

/**
 * API para Verificação de Arquivo de Áudio
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
 * GET /speech/get-audio.php?id=12345
 * 
 * Respostas:
 * 
 * - Se o arquivo for encontrado:
 *   {
 *     "status": "ok",
 *     "audio_url": "https://iaturbo.com.br/wp-content/uploads/scripts/speech/output/audio_12345.mp3"
 *   }
 * 
 * - Se o arquivo não for encontrado após 5 tentativas:
 *   {
 *     "status": "pending"
 *   }
 */

// Função para verificar se o arquivo de áudio existe
function check_audio_file($id) {
    $filename = 'audio_' . $id . '.mp3';
    $audio_file_path = __DIR__ . '/output/' . $filename;
    return file_exists($audio_file_path);
}

// Função para retornar a URL do arquivo de áudio
function get_audio_url($id) {
    $filename = 'audio_' . $id . '.mp3';
    return 'https://iaturbo.com.br/wp-content/uploads/scripts/speech/output/' . $filename;
}

// Verifica se o parâmetro "id" foi fornecido
if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Parâmetro "id" não fornecido.']);
    exit;
}

$id = $_GET['id'];
$attempts = 0;
$max_attempts = 5;
$interval = 1; // Intervalo de 1 segundo entre as tentativas

while ($attempts < $max_attempts) {
    if (check_audio_file($id)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'audio_url' => get_audio_url($id)]);
        exit;
    }
    $attempts++;
    sleep($interval);
}

// Se o arquivo não for encontrado após 5 tentativas, retorna status "pending"
header('Content-Type: application/json');
echo json_encode(['status' => 'pending']);