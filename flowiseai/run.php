<?php
/**
 * run.php
 * 
 * Descrição:
 * Este script recebe um `id` (gerado pelo `request.php`), uma URL do Flowise e um token de autorização via POST,
 * carrega o arquivo correspondente na pasta `./pending`, envia a pergunta para o FlowiseAI,
 * salva a resposta na pasta `./completed` e retorna um status 200 OK.
 * 
 * Entrada:
 * Espera-se que o script receba um payload JSON no seguinte formato:
 * {
 *   "id": "ID gerado pelo request.php",
 *   "chatflow_url": "URL do Flowise",
 *   "chatflow_key": "Token de autorização"
 * }
 * 
 * Saída:
 * O script salva a resposta do FlowiseAI na pasta `./completed` com o nome do arquivo como o `id` recebido e retorna um status 200 OK.
 * 
 * Logs:
 * - As solicitações são registradas no arquivo de log `./logs/run-YYYY-MM-DD.log`.
 * - A pasta de log será criada automaticamente, se não existir.
 * 
 * Exemplos de Uso:
 * Para testar este script, você pode usar o seguinte comando cURL:
 * 
 * curl -X POST https://iaturbo.com.br/wp-content/uploads/scripts/flowiseai/run.php \
 *      -H "Content-Type: application/json" \
 *      -d '{
 *            "id": "id_2024-08-28-13-45-30_5f2f5e1b3f8a1",
 *            "chatflow_url": "https://flowiseai-railway-production-16fe.up.railway.app/api/v1/prediction/b69ccbf6-8014-4005-a024-69cf600f65ae",
 *            "chatflow_key": "P2PrI1fntoWhCWQHrE93c3nJx0ZU0P-e11KZxpyfEr8"
 *          }'
 * 
 * Retorno esperado:
 * O script salva o arquivo no diretório `./completed` e retorna 200 OK.
 */

$pending_dir = './pending/';
$completed_dir = './completed/';
$log_file = './logs/run-' . date('Y-m-d') . '.log';

if (!is_dir($completed_dir)) {
    mkdir($completed_dir, 0777, true);
}

if (!is_dir(dirname($log_file))) {
    mkdir(dirname($log_file), 0777, true);
}

// Função para registrar logs
function log_message($message) {
    global $log_file;
    $log_entry = date('Y-m-d H:i:s') . " - " . $message . "\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Função para capturar e logar o erro de stream
function log_stream_error($url, $context) {
    $error_message = '';
    $error_message .= "Erro ao acessar a URL: $url\n";
    $error_message .= "Opções de Contexto: " . print_r($context, true) . "\n";
    
    $error = error_get_last();
    if ($error) {
        $error_message .= "Detalhes do erro: " . $error['message'] . "\n";
    }

    log_message($error_message);
}

// Recebe os parâmetros do JSON de entrada
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$chatflow_url = $data['chatflow_url'] ?? null;
$chatflow_key = $data['chatflow_key'] ?? null;

if (!$id || !$chatflow_url || !$chatflow_key) {
    log_message("Parâmetros faltando. id, chatflow_url e chatflow_key são obrigatórios.");
    die(json_encode(['error' => 'Parâmetros faltando. id, chatflow_url e chatflow_key são obrigatórios.']));
}

$file_path = $pending_dir . $id . '.json';

if (!file_exists($file_path)) {
    log_message("Arquivo não encontrado para id: $id");
    die(json_encode(['error' => 'Arquivo não encontrado.']));
}

// Lê os dados do arquivo pendente
$data = json_decode(file_get_contents($file_path), true);

// Envia a pergunta para o FlowiseAI
$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\nAuthorization: Bearer $chatflow_key\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
    ],
];

$context = stream_context_create($options);

// Captura a resposta ou o erro detalhado
$response = @file_get_contents($chatflow_url, false, $context);

if ($response === FALSE) {
    log_stream_error($chatflow_url, $options);
    die(json_encode(['error' => 'Falha na requisição ao FlowiseAI. Verifique os logs para mais detalhes.']));
}

// Salva a resposta na pasta ./completed
file_put_contents($completed_dir . $id . '.json', $response);
log_message("Processamento completo para id: $id. Resposta salva em ./completed/$id.json");

// Remove o arquivo da pasta pending
unlink($file_path);

// Retorna 200 OK
http_response_code(200);
log_message("Resposta retornada com sucesso para id: $id.");
?>
