<?php
/**
 * request.php
 * 
 * Descrição:
 * Este script recebe uma pergunta via POST, gera um `id` único,
 * salva os dados recebidos na pasta `./pending` e retorna o `id` gerado.
 * 
 * Entrada:
 * Espera-se que o script receba um payload JSON no seguinte formato:
 * {
 *   "question": "Sua pergunta aqui",
 *   "source": "whats, face, insta, etc",
 *   "overrideConfig": {
 *     "sessionId": "Seu sessionId aqui"
 *   },
 *   "userData": {
 *     "firstName": "Nome",
 *     "lastName": "Sobrenome",
 *     "phoneNumber": "Telefone",
 *     "email": "Email"
 *   }
 * }
 * 
 * Saída:
 * O script retornará um JSON contendo o `id` gerado para a pergunta:
 * {
 *   "id": "ID gerado para a pergunta"
 * }
 * 
 * Logs:
 * - As solicitações são registradas no arquivo de log `./logs/request-YYYY-MM-DD.log`.
 * - A pasta de log será criada automaticamente, se não existir.
 */

$start_time = microtime(true);

$pending_dir = './pending/';

include 'helpers.php';

if (!is_dir($pending_dir)) {
    mkdir($pending_dir, 0777, true);
}

// Recebe o JSON de entrada
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    log_message('request', 'error', "JSON de entrada inválido.");
    die(json_encode(['error' => 'JSON de entrada inválido.']));
}

// Gera um id único
$request_id = 'id_' . date('Ymd_His') . '_' . uniqid();

// Salva os dados na pasta ./pending
file_put_contents($pending_dir . $request_id . '.json', json_encode($data));
log_message('request', 'info', "ID gerado: $request_id - Dados salvos em ./pending/$request_id.json");

// Calcula o tempo total de execução
$execution_time = round((microtime(true) - $start_time) * 1000);

// Retorna o id para que o processo possa ser monitorado
log_message('request', 'info', "Processamento concluído para id: $request_id. Tempo total de execução: {$execution_time}ms");
echo json_encode(['id' => $request_id]);
?>
