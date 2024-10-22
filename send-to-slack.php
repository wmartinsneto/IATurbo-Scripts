<?php
// Recebe os dados do POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Verifica se o campo "text" está presente
if (isset($data['text'])) {
    $webhookUrl = 'https://hooks.slack.com/services/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';

    // Configura o payload para enviar ao Slack
    $payload = json_encode([
        'text' => $data['text']
    ]);

    // Configura as opções de contexto para a requisição HTTP
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => $payload,
        ]
    ];

    // Cria o contexto de stream
    $context = stream_context_create($options);

    // Envia a requisição ao Slack
    $result = @file_get_contents($webhookUrl, false, $context);

    // Verifica o resultado e retorna a resposta apropriada
    if ($result === FALSE) {
        echo 'Falha ao enviar a mensagem ao Slack.';
    } else {
        echo 'Mensagem enviada com sucesso ao Slack.';
    }
} else {
    echo 'O campo "text" é obrigatório.';
}
