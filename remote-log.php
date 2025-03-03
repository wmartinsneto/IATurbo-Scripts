<?php
/**
 * remote-log.php
 * 
 * Endpoint para registrar logs remotamente.
 * 
 * Este script recebe informações para logar, incluindo o tipo de log (INFO, ERROR ou DEBUG) e a fonte.
 * Ele grava o log principal em um arquivo na pasta ./logs e, se o tipo for ERROR, WARNING ou DEBUG,
 * grava também o log em um arquivo adicional dentro de uma pasta separada (./log/error, ./log/warning ou ./log/debug).
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
 * @param string $message O texto a ser logado.
 * @param string $type O tipo de log ("INFO", "ERROR" ou "DEBUG").
 * @param string $source A fonte que gerou o log.
 */
function logMessage($message, $type, $source) {
    // Verifica se o tipo de log deve ser registrado
    if (LOG_LEVEL === 'NONE' || (LOG_LEVEL !== 'ALL' && LOG_LEVEL !== $type)) {
        return;
    }

    // Pasta para o log principal
    $mainDir = './logs';
    if (!is_dir($mainDir)) {
        mkdir($mainDir, 0777, true);
    }
    
    $today = date("Y-m-d");
    $timestamp = date("Y-m-d H:i:s");
    $logEntry = "[$timestamp] [$type] $message" . PHP_EOL;
    
    // Grava o log principal em ./logs/remote-log_{data}_{source}.log
    $filename = "$mainDir/remote-log_{$today}_{$source}.log";
    file_put_contents($filename, $logEntry, FILE_APPEND);

    // Se o tipo for ERROR, WARNING ou DEBUG, grava o log adicional em pasta separada
    $lowerType = strtolower($type);
    if (in_array($lowerType, ['error', 'warning', 'debug'])) {
        $subDir = "./logs/$lowerType";
        if (!is_dir($subDir)) {
            mkdir($subDir, 0777, true);
        }
        $subFilename = "$subDir/remote-log_{$today}_{$source}.$lowerType.log";
        file_put_contents($subFilename, $logEntry, FILE_APPEND);
    }
}

// Recebe os dados do POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Verifica se os parâmetros necessários estão presentes
if (isset($data['message']) && isset($data['type']) && isset($data['source'])) {
    // Converte o type para maiúsculas para consistência
    logMessage($data['message'], strtoupper($data['type']), $data['source']);
    echo 'Log registrado com sucesso.';
} else {
    echo 'Os parâmetros "message", "type" e "source" são obrigatórios.';
}
?>
