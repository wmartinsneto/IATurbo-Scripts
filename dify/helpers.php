<?php
// helpers.php

// Incluir configuração centralizada, se ainda não incluída
if (!function_exists('config')) {
    require_once __DIR__ . '/../config.php';
}

// Função para registrar logs
function log_message($origin, $level, $message) {
    $log_dir = config('logs_dir', './logs/');
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    $log_file = $log_dir . $origin . '-' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "$timestamp [$level] - $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);

    // Se o nível for error ou warning, também salva em arquivos separados com data
    if ($level === 'error' || $level === 'warning') {
        $error_log_file = $log_dir . $origin . '-' . date('Y-m-d') . '.' . $level . '.log';
        file_put_contents($error_log_file, $log_entry, FILE_APPEND);
    }
}

// Função para obter todas as mensagens de texto
function getMensagemDeTexto($agent_thoughts) {
    $mensagemDeTexto = '';
    foreach ($agent_thoughts as $thought) {
        $thought_text = $thought['thought'] ?? '';
        if (!$thought_text) {
            continue;
        }
        $parsed_thought = json_decode($thought_text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message("helpers", "error", "Erro ao decodificar o pensamento: " . json_last_error_msg());
            continue;
        }
        $mensagemDeTexto .= ($parsed_thought['mensagemDeTexto'] ?? '') . "\n\n";
    }
    return trim($mensagemDeTexto);
}

// Função para obter a última mensagem de voz
function getMensagemDeVoz($agent_thoughts) {
    $mensagemDeVoz = '';
    $max_position = -1;
    foreach ($agent_thoughts as $thought) {
        $thought_text = $thought['thought'] ?? '';
        $position = $thought['position'] ?? -1;
        if (!$thought_text || $position <= $max_position) {
            continue;
        }
        $parsed_thought = json_decode($thought_text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message("helpers", "error", "Erro ao decodificar o pensamento: " . json_last_error_msg());
            continue;
        }
        $mensagemDeVoz = $parsed_thought['mensagemDeVoz'] ?? '';
        $max_position = $position;
    }
    return $mensagemDeVoz;
}

// Função para obter a última mensagem de controle
function getMensagemDeControle($agent_thoughts) {
    $mensagemDeControle = '';
    $max_position = -1;
    foreach ($agent_thoughts as $thought) {
        $thought_text = $thought['thought'] ?? '';
        $position = $thought['position'] ?? -1;
        if (!$thought_text || $position <= $max_position) {
            continue;
        }
        $parsed_thought = json_decode($thought_text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message("helpers", "error", "Erro ao decodificar o pensamento: " . json_last_error_msg());
            continue;
        }
        $mensagemDeControle = $parsed_thought['mensagemDeControle'] ?? '';
        $max_position = $position;
    }
    return $mensagemDeControle;
}
?>
