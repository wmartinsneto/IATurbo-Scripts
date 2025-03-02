<?php
/**
 * remote-log.php
 * 
 * Endpoint para registrar logs remotamente.
 * 
 * Este script recebe informações para logar, incluindo o tipo de log (INFO, ERROR, DEBUG) e a fonte.
 * Ele grava essas informações em um arquivo de log na pasta ./logs.
 * Em caso de ERROR ou WARNING o log também é gravado em um arquivo separado, conforme a data.
 * 
 * Exemplo de uso:
 * Envie uma requisição POST para este script com um JSON contendo:
 * {
 *   "message": "Este é um log de teste",
 *   "type": "INFO",
 *   "source": "ManyChat"
 * }
 */

// Defina quais tipos de log você deseja ver (INFO, ERROR, DEBUG, ALL, NONE)
define('LOG_LEVEL', 'ALL');

/**
 * Função para gravar logs.
 *
 * @param string $message O texto a ser logado
 * @param string $type O tipo de log ("INFO", "ERROR", "DEBUG")
 * @param string $source A fonte que gerou o log
 */
function logMessage($message, $type, $source) {
    // Verifica se o tipo de log deve ser registrado
    if (LOG_LEVEL === 'NONE' || (LOG_LEVEL !== 'ALL' && LOG_LEVEL !== $type)) {
        return;
    }
    
    // Certifica-se de que a pasta de logs existe
    if (!is_dir('./logs')) {
        mkdir('./logs', 0777, true);
    }
    
    // Gera o nome do arquivo de log principal com o source e data
    $today = date("Y-m-d");
    $filename = "./logs/remote-log_{$today}_{$source}.log";
    $timestamp = date("Y-m-d H:i:s");
    $logEntry = "[$timestamp] [$type] $message" . PHP_EOL;
    
    // Escreve o log principal no arquivo
    file_put_contents($filename, $logEntry, FILE_APPEND);
    
    // Se o tipo for error ou warning, grava também em um arquivo separado
    $lowerType = strtolower($type);
    if ($lowerType === 'error' || $lowerType === 'warning') {
        $error_log_filename = "./logs/remote-log_{$today}_{$source}." . $lowerType . ".log";
        file_put_contents($error_log_filename, $logEntry, FILE_APPEND);
    }
}

// Recebe os dados do POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Verifica se os parâmetros necessários estão presentes
if (isset($data['message']) && isset($data['type']) && isset($data['source'])) {
    logMessage($data['message'], strtoupper($data['type']), $data['source']);
    echo 'Log registrado com sucesso.';
} else {
    echo 'Os parâmetros "message", "type" e "source" são obrigatórios.';
}
?>
