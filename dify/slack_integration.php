<?php
/**
 * slack_integration.php
 *
 * Este script recebe um `id` via POST, carrega os dados correspondentes e envia uma notificação ao Slack.
 */

require_once __DIR__ . '/../config.php';
include 'helpers.php';

// Verificar se a integração com Slack está habilitada
if (!config('enable_slack', true)) {
    log_message('slack_integration', 'info', "Integração com Slack desabilitada via configuração.");
    http_response_code(200);
    echo json_encode(['status' => 'disabled']);
    exit;
}

// Obter a URL do webhook do Slack
$webhookUrl = config('slack_webhook_url');

// Verificar se a URL do webhook foi configurada
if (empty($webhookUrl)) {
    log_message('slack_integration', 'error', "URL do webhook do Slack não configurada.");
    http_response_code(500);
    echo json_encode(['error' => 'URL do webhook do Slack não configurada.']);
    exit;
}

// Recebe os parâmetros do JSON de entrada
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    log_message('slack_integration', 'error', "Parâmetro 'id' não fornecido.");
    die(json_encode(['error' => 'Parâmetro "id" não fornecido.']));
}

log_message('slack_integration', 'info', "Iniciando integração com Slack para id: $id.");

// Caminhos dos arquivos
$completed_file_path = config('completed_dir') . $id . '.json';

// Verifica se os arquivos existem
if (!file_exists($completed_file_path)) {
    log_message('slack_integration', 'error', "Arquivo não encontrado: $completed_file_path");
    die(json_encode(['error' => "Arquivo não encontrado: $completed_file_path"]));
}

$response_data = json_decode(file_get_contents($completed_file_path), true);

// Monta a mensagem
$slackMessage = "Lead no " . ($response_data['source'] ?? 'Desconhecido') . "\n";
foreach ($response_data['userData'] as $key => $value) {
    $slackMessage .= ucfirst($key) . ": $value\n";
}
$slackMessage .= "PERGUNTA:\n" . ($response_data['question'] ?? 'Sem pergunta') . "\n\n";

// Processa todos os pensamentos do agente
$agent_thoughts = $response_data['agent_thoughts'] ?? [];
$mensagemDeTexto = getMensagemDeTexto($agent_thoughts);
$mensagemDeVoz = getMensagemDeVoz($agent_thoughts);
$mensagemDeControle = getMensagemDeControle($agent_thoughts);

$slackMessage .= "### Mensagem de Texto\n" . $mensagemDeTexto . "\n\n";
$slackMessage .= "### Mensagem de Voz\n" . $mensagemDeVoz . "\n\n";
$slackMessage .= config('base_url') . "/speech/output/audio_$id.mp3 \n\n";
$slackMessage .= "### Mensagem de Controle\n" . $mensagemDeControle . "\n\n";

log_message('slack_integration', 'info', "Mensagem para Slack: " . $slackMessage);

try {
    $payload = json_encode(['text' => $slackMessage]);

    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => $payload,
        ]
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents($webhookUrl, false, $context);

    if ($result === FALSE) {
        $error = error_get_last();
        log_message('slack_integration', 'error', "Falha ao enviar a mensagem ao Slack para id: $id. Erro: " . $error['message']);
    } else {
        log_message('slack_integration', 'info', "Mensagem enviada com sucesso ao Slack para id: $id.");
    }
} catch (Exception $e) {
    log_message('slack_integration', 'error', "Erro ao enviar para o Slack para id: $id. Erro: " . $e->getMessage());
}

// Retorna 200 OK
http_response_code(200);
log_message('slack_integration', 'info', "Processamento concluído para id: $id.");
?>
