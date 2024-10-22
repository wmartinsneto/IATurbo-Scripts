<?php
/**
 * remote-log.php
 * 
 * Endpoint para registrar logs remotamente.
 * 
 * Este script recebe informações para logar, incluindo o tipo de log (INFO, ERROR, DEBUG) e a fonte.
 * Ele grava essas informações em um arquivo de log na pasta ./logs.
 * 
 * Como usar:
 * 
 * Envie uma requisição POST para este script com um JSON contendo:
 * - "message": o texto a ser logado.
 * - "type": o tipo de log ("INFO", "ERROR", "DEBUG").
 * - "source": a fonte que gerou o log.
 * 
 * Exemplo de body JSON:
 * {
 *   "message": "Este é um log de teste",
 *   "type": "INFO",
 *   "source": "ManyChat"
 * }
 */

// Defina quais tipos de log você deseja ver (INFO, ERROR, DEBUG, ALL, NONE)
define('LOG_LEVEL', 'ALL');

// Função para gravar logs
function logMessage($message, $type, $source) {
    // Verifica se o tipo de log deve ser registrado
    if (LOG_LEVEL === 'NONE' || (LOG_LEVEL !== 'ALL' && LOG_LEVEL !== $type)) {
        return;
    }

    // Certifica-se de que a pasta de logs existe
    if (!is_dir('./logs')) {
        mkdir('./logs', 0777, true);
    }

    // Gera o nome do arquivo de log com o source
    $today = date("Y-m-d");
    $filename = "./logs/remote-log-{$source}-{$today}.log";

    // Monta o conteúdo do log
    $logEntry = "[" . date("Y-m-d H:i:s") . "] [$type] $message" . PHP_EOL;

    // Escreve o log no arquivo
    file_put_contents($filename, $logEntry, FILE_APPEND);
}

// Recebe os dados do POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Verifica se os parâmetros necessários estão presentes
if (isset($data['message']) && isset($data['type']) && isset($data['source'])) {
    logMessage($data['message'], $data['type'], $data['source']);
    echo 'Log registrado com sucesso.';
} else {
    echo 'Os parâmetros "message", "type" e "source" são obrigatórios.';
}
