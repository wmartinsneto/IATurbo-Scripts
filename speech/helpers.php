<?php
// helpers.php

// Função para registrar logs
function log_message($origin, $level, $message) {
    $log_dir = './logs/';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    $log_file = $log_dir . $origin . '-' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "$timestamp [$level] - $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);

    // Se o nível for error ou warning, também salva em arquivos separados
    if ($level === 'error' || $level === 'warning') {
        $error_log_file = $log_dir . $origin . '.' . $level . '.log';
        file_put_contents($error_log_file, $log_entry, FILE_APPEND);
    }
}
?>