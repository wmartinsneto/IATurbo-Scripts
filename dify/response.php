<?php
/**
 * response.php
 * 
 * Este script recebe um `id` via GET, verifica se o arquivo correspondente na pasta `./completed` existe,
 * e retorna o conteúdo do arquivo JSON ou um status de pendente.
 */

$completed_dir = './completed/';
$log_file = './logs/response-' . date('Y-m-d') . '.log';

// Função para registrar logs
function log_message($message) {
    global $log_file;
    $log_entry = date('Y-m-d H:i:s') . " - " . $message . "\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Recebe o parâmetro `id` via GET
$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'id é obrigatório']);
    exit;
}

$completed_file_path = $completed_dir . $id . '.json';

if (!file_exists($completed_file_path)) {
    log_message("Resultado pendente para o id: $id");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'pending']);
    exit;
}

// Lê os dados do arquivo completado
$completed_data = json_decode(file_get_contents($completed_file_path), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    log_message("Erro ao decodificar JSON para o id: $id");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Erro ao decodificar o JSON completado']);
    exit;
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
            log_message("Erro ao decodificar o pensamento: " . json_last_error_msg());
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
            log_message("Erro ao decodificar o pensamento: " . json_last_error_msg());
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
            log_message("Erro ao decodificar o pensamento: " . json_last_error_msg());
            continue;
        }
        $mensagemDeControle .= ($parsed_thought['mensagemDeControle'] ?? '') . "\n\n";
    }
    return trim($mensagemDeControle);
}

// Processa todos os pensamentos do agente
$agent_thoughts = $completed_data['agent_thoughts'] ?? [];
$mensagemDeTexto = getMensagemDeTexto($agent_thoughts);
$mensagemDeVoz = getMensagemDeVoz($agent_thoughts);
$mensagemDeControle = getMensagemDeControle($agent_thoughts);

// Adiciona as novas mensagens ao completed_data
$completed_data['mensagemDeTexto'] = $mensagemDeTexto;
$completed_data['mensagemDeVoz'] = $mensagemDeVoz;
$completed_data['mensagemDeControle'] = $mensagemDeControle;
$completed_data['status'] = 'completed';

// Log da conclusão
log_message("Resposta completada para o id: $id");

// Retorna a resposta JSON
header('Content-Type: application/json');
echo json_encode($completed_data);
?>