<?php
/**
 * response.php
 *
 * Descrição:
 * Este script verifica o status do processamento de uma requisição enviada ao FlowiseAI. 
 * Se o processamento estiver concluído, retorna o conteúdo completo do JSON dentro da chave "text" 
 * presente na resposta do FlowiseAI.
 * 
 * Entrada:
 * - `id`: ID gerado pelo `request.php` que identifica a requisição.
 * 
 * Saída (JSON):
 * - status: "pending" ou "completed"
 * - (se completed) todo o conteúdo JSON dentro de "text" retornado pelo FlowiseAI.
 * 
 * Logs:
 * - As solicitações são registradas no arquivo de log `./logs/response-YYYY-MM-DD.log`.
 * 
 * Exemplos de Uso:
 * Para testar este script, você pode usar o seguinte comando cURL:
 * 
 * curl -X GET "https://iaturbo.com.br/wp-content/uploads/scripts/flowiseai/response.php?id=id_2024-08-28-13-45-30_5f2f5e1b3f8a1"
 * 
 * Retorno esperado:
 * Se o processamento estiver concluído, o JSON completo contido em "text" será retornado.
 */

$results_dir = './completed/';
$log_file = './logs/response-' . date('Y-m-d') . '.log';

// Função para registrar logs
function log_message($message) {
    global $log_file;
    $log_entry = date('Y-m-d H:i:s') . " [info] " . $message . "\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Obtém o id da URL
$id = $_GET['id'] ?? '';

if (empty($id)) {
    http_response_code(400);
    echo json_encode(['error' => 'id é obrigatório']);
    exit;
}

// Verifica se o arquivo de resultado existe
$result_file = $results_dir . $id . '.json';

if (!file_exists($result_file)) {
    log_message("Resultado pendente para o id: $id");
    echo json_encode(['status' => 'pending']);
    exit;
}

// Lê o conteúdo do arquivo de resultado
$result_content = file_get_contents($result_file);
$result_data = json_decode($result_content, true);

// Extrai o JSON de "text" e trata-o corretamente
$text = $result_data['text'] ?? '';
$parsed_text = json_decode(trim($text), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    log_message("Erro ao decodificar JSON para o id: $id");
    echo json_encode(['status' => 'error', 'message' => 'Erro ao decodificar o JSON de texto']);
    exit;
}

// Retorna o status e o JSON completo extraído de "text"
$response = [
    'status' => 'completed'
] + $parsed_text;

log_message("Resposta completada para o id: $id");

echo json_encode($response);
?>
