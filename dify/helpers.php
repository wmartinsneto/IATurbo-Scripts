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

// Função para obter todas as mensagens de voz
function getMensagemDeVoz($agent_thoughts) {
    $mensagemDeVoz = '';
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
        $mensagemDeVoz .= ($parsed_thought['mensagemDeVoz'] ?? '') . "\n\n";
    }
    return trim($mensagemDeVoz);
}

// Função para obter todas as mensagens de controle
function getMensagemDeControle($agent_thoughts) {
    $mensagemDeControle = '';
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
        $mensagemDeControle .= ($parsed_thought['mensagemDeControle'] ?? '') . "\n\n";
    }
    return trim($mensagemDeControle);
}
?>