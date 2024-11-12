<?php
/**
 * slack_integration.php
 *
 * Este script recebe um `id` via POST, carrega os dados correspondentes e envia uma notificação ao Slack.
 */

include 'helpers.php';

// Recebe os parâmetros do JSON de entrada
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    log_message('slack_integration', 'error', "Parâmetro 'id' não fornecido.");
    die(json_encode(['error' => 'Parâmetro "id" não fornecido.']));
}

log_message('slack_integration', 'info', "Iniciando integração com Slack para id: $id.");

// Caminhos dos arquivos
$completed_file_path = './completed/' . $id . '.json';

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
$slackMessage .= "https://iaturbo.com.br/wp-content/uploads/scripts/speech/output/audio_$id.mp3 \n\n";
$slackMessage .= "### Mensagem de Controle\n" . $mensagemDeControle . "\n\n";

log_message('slack_integration', 'info', "Mensagem para Slack: " . $slackMessage);

try {
    $webhookUrl = 'https://hooks.slack.com/services/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';

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