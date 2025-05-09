<?php
/**
 * trello_integration.php
 *
 * Este script recebe um `id` via POST, carrega os dados correspondentes e envia as informações ao Trello.
 */

require_once __DIR__ . '/../config.php';
include 'helpers.php';

// Verificar se a integração com Trello está habilitada
if (!config('enable_trello', true)) {
    log_message('trello_integration', 'info', "Integração com Trello desabilitada via configuração.");
    http_response_code(200);
    echo json_encode(['status' => 'disabled']);
    exit;
}

// Chaves de API do Trello
$apiKey = config('trello_api_key');
$apiToken = config('trello_api_token');
$boardId = config('trello_board_id'); // ID do board "Chatbots IATurbo"
$listId = config('trello_list_id'); // ID da lista "Novo Lead"

// Verificar se as credenciais do Trello foram configuradas
if (empty($apiKey) || empty($apiToken)) {
    log_message('trello_integration', 'error', "Credenciais do Trello não configuradas.");
    http_response_code(500);
    echo json_encode(['error' => 'Credenciais do Trello não configuradas.']);
    exit;
}

// Recebe os parâmetros do JSON de entrada
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    log_message('trello_integration', 'error', "Parâmetro 'id' não fornecido.");
    die(json_encode(['error' => 'Parâmetro "id" não fornecido.']));
}

log_message('trello_integration', 'info', "Iniciando integração com Trello para id: $id.");

// Caminhos dos arquivos
$completed_file_path = config('completed_dir') . $id . '.json';

// Verifica se os arquivos existem
if (!file_exists($completed_file_path)) {
    log_message('trello_integration', 'error', "Arquivo não encontrado: $completed_file_path");
    die(json_encode(['error' => "Arquivo não encontrado: $completed_file_path"]));
}

// Carrega os dados necessários
$response_data = json_decode(file_get_contents($completed_file_path), true);

// Processa todos os pensamentos do agente
$agent_thoughts = $response_data['agent_thoughts'] ?? [];
$mensagemDeTexto = getMensagemDeTexto($agent_thoughts);
$mensagemDeVoz = getMensagemDeVoz($agent_thoughts);
$mensagemDeControle = getMensagemDeControle($agent_thoughts);

// Prepara os dados
$formattedResponse = "### Mensagem de Texto\n" . $mensagemDeTexto . "\n\n";
$formattedResponse .= "### Mensagem de Voz\n" . $mensagemDeVoz . "\n\n";
$formattedResponse .= config('base_url') . "/speech/output/audio_$id.mp3 \n\n";
$formattedResponse .= "### Mensagem de Controle\n" . $mensagemDeControle . "\n\n";

// Função para criar ou atualizar um cartão no Trello
function send_to_trello($data) {
    global $apiKey, $apiToken, $boardId, $listId;

    // Verifica se o sessionId foi passado corretamente
    if (isset($data['sessionId']) && !empty($data['sessionId'])) {
        $sessionId = $data['sessionId'];
    } else {
        $errorMsg = "Session ID não encontrado.";
        log_message('trello_integration', 'error', $errorMsg);
        return;
    }

    // Loga todos os parâmetros de entrada
    log_message('trello_integration', 'info', "Parâmetros recebidos: " . print_r($data, true));

    $source = $data['source'];
    $userData = $data['userData'];
    $question = $data['leadQuestion']; // Pergunta do lead
    $formattedResponse = $data['formattedResponse']; // Resposta formatada para o Trello

    // Monta o conteúdo do comentário
    $comment = "### Pergunta do Lead:\n$question\n\n";
    $comment .= "**Resposta do Chatbot**:\n$formattedResponse\n\n";

    // Loga os parâmetros de saída antes de enviá-los ao Trello
    log_message('trello_integration', 'info', "Parâmetros enviados para o Trello: " . print_r([
        'sessionId' => $sessionId,
        'source' => $source,
        'userData' => $userData,
        'question' => $question,
        'formattedResponse' => $formattedResponse
    ], true));

    // Gera o nome do cartão baseado na lógica de flexibilidade
    $cardName = generateCardName($source, $userData, $sessionId);

    // Inicia os logs para criar ou atualizar o cartão
    $logPadrao = "";
    $logErro = "";
    log_message('trello_integration', 'info', "Buscando ou criando cartão no Trello para sessionId: $sessionId");

    // Busca o cartão no Trello pelo sessionId do payload
    $searchResult = searchCard($sessionId, $apiKey, $apiToken, $boardId, $logPadrao, $logErro);

    if ($searchResult) {
        // Se o card existe, adiciona um comentário
        $cardId = $searchResult['id'];
        log_message('trello_integration', 'info', "Cartão encontrado para sessionId: $sessionId. ID do cartão: $cardId");

        $resultComment = addCommentToCard($cardId, $comment, $apiKey, $apiToken, $logPadrao, $logErro);
        if ($resultComment) {
            log_message('trello_integration', 'info', "Comentário adicionado ao cartão $cardId com sucesso.");
        } else {
            $logErro .= "Erro ao adicionar comentário ao card existente.\n";
            log_message('trello_integration', 'error', $logErro);
        }
    } else {
        // Se o card não existe, cria um novo card com o nome gerado
        log_message('trello_integration', 'info', "Cartão não encontrado para sessionId: $sessionId. Criando novo cartão.");

        $resultCreate = createCard($cardName, $sessionId, $source, $userData, $comment, $apiKey, $apiToken, $listId, $logPadrao, $logErro);
        if ($resultCreate) {
            log_message('trello_integration', 'info', "Novo cartão criado com sucesso para sessionId: $sessionId.");
        } else {
            $logErro .= "Erro ao criar novo card.\n";
            log_message('trello_integration', 'error', $logErro);
        }
    }
}

// Função para buscar um cartão no Trello com base no sessionId
function searchCard($sessionId, $apiKey, $apiToken, $boardId, &$logPadrao, &$logErro) {
    $url = "https://api.trello.com/1/boards/$boardId/cards?key=$apiKey&token=$apiToken";

    // Verifica se o sessionId está correto e presente nos logs
    $logPadrao .= "Buscando por Session ID: $sessionId\n";
    $logPadrao .= "URL para obter cartões no quadro: " . $url . "\n";

    // Inicializa o cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // Executa a requisição e captura a resposta
    $response = curl_exec($ch);

    if ($response === FALSE) {
        $logErro .= "Erro cURL: " . curl_error($ch) . "\n";
        curl_close($ch);
        return null;
    }

    // Fecha a sessão cURL
    curl_close($ch);

    // Decodifica a resposta JSON
    $cards = json_decode($response, true);

    // Verificar se há cartões e procurar pelo sessionId na descrição
    foreach ($cards as $card) {
        if (strpos($card['desc'], $sessionId) !== false) {
            $logPadrao .= "Cartão encontrado para o Session ID: $sessionId\n";
            return $card;
        }
    }

    $logPadrao .= "Nenhum cartão encontrado para o Session ID: $sessionId\n";
    return null;
}

// Função para criar um novo cartão no Trello
function createCard($name, $sessionId, $source, $userData, $comment, $apiKey, $apiToken, $listId, &$logPadrao, &$logErro) {
    $url = "https://api.trello.com/1/cards?key=$apiKey&token=$apiToken";
    $timestamp = date("Y-m-d H:i:s");

    // Adiciona o log para criação de cartão com sessionId correto
    $logPadrao .= "Criando novo cartão com Session ID: $sessionId\n";

    $desc = "Conversa iniciada através do $source às $timestamp\nSessionId: $sessionId\n";
    foreach ($userData as $key => $value) {
        $desc .= ucfirst($key) . ": $value\n";
    }
    $data = array(
        'name' => $name,
        'desc' => $desc,
        'idList' => $listId
    );
    $logErro .= "Dados para criar cartão: " . print_r($data, true) . "\n";
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ),
    );
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === FALSE) {
        $logErro .= "Falha na requisição para criar cartão: " . error_get_last()['message'] . "\n";
        return null;
    }

    $logErro .= "Resposta ao criar cartão: " . print_r($result, true) . "\n";

    $card = json_decode($result, true);
    addCommentToCard($card['id'], $comment, $apiKey, $apiToken, $logPadrao, $logErro);

    return $card;
}

// Função para adicionar um comentário a um cartão no Trello
function addCommentToCard($cardId, $comment, $apiKey, $apiToken, &$logPadrao, &$logErro) {
    $url = "https://api.trello.com/1/cards/$cardId/actions/comments?key=$apiKey&token=$apiToken";
    $data = array(
        'text' => $comment
    );
    $logErro .= "Dados para adicionar comentário: " . print_r($data, true) . "\n";
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ),
    );
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === FALSE) {
        $logErro .= "Falha na requisição para adicionar comentário: " . error_get_last()['message'] . "\n";
        return null;
    }

    $logPadrao .= "Comentário adicionado ao cartão $cardId com sucesso.\n";
    return $result;
}

// Função para gerar o nome do cartão de forma flexível
function generateCardName($source, $userData, $sessionId) {
    // Checa se existe firstName e lastName
    if (!empty($userData['firstName']) && !empty($userData['lastName'])) {
        return "[$source]: {$userData['firstName']} {$userData['lastName']}";
    }
    // Se não houver nome completo, tenta usar o email
    elseif (!empty($userData['email'])) {
        return "[$source]: {$userData['email']}";
    }
    // Se não houver email, tenta usar o telefone
    elseif (!empty($userData['phoneNumber'])) {
        return "[$source]: {$userData['phoneNumber']}";
    }
    // Se nenhuma informação estiver disponível, usa o sessionId correto do payload
    else {
        return "[$source]: $sessionId";
    }
}

// Recebe os parâmetros do JSON de entrada
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    log_message('trello_integration', 'error', "Parâmetro 'id' não fornecido.");
    die(json_encode(['error' => 'Parâmetro "id" não fornecido.']));
}

log_message('trello_integration', 'info', "Iniciando integração com Trello para id: $id.");

// Caminhos dos arquivos
$completed_file_path = './completed/' . $id . '.json';

// Verifica se os arquivos existem
if (!file_exists($completed_file_path)) {
    log_message('trello_integration', 'error', "Arquivo não encontrado: $completed_file_path");
    die(json_encode(['error' => "Arquivo não encontrado: $completed_file_path"]));
}

// Carrega os dados necessários
$response_data = json_decode(file_get_contents($completed_file_path), true);

// Processa todos os pensamentos do agente
$agent_thoughts = $response_data['agent_thoughts'] ?? [];
$mensagemDeTexto = getMensagemDeTexto($agent_thoughts);
$mensagemDeVoz = getMensagemDeVoz($agent_thoughts);
$mensagemDeControle = getMensagemDeControle($agent_thoughts);

// Prepara os dados
$formattedResponse = "### Mensagem de Texto\n" . $mensagemDeTexto . "\n\n";
$formattedResponse .= "### Mensagem de Voz\n" . $mensagemDeVoz . "\n\n";
$formattedResponse .= "https://iaturbo.com.br/wp-content/uploads/scripts/speech/output/audio_$id.mp3 \n\n";
$formattedResponse .= "### Mensagem de Controle\n" . $mensagemDeControle . "\n\n";

// Envia para o Trello
try {
    send_to_trello([
        'leadRequestId' => $id,
        'leadQuestion' => $response_data['question'] ?? 'Sem pergunta disponível',
        'leadName' => $response_data['userData']['firstName'] . ' ' . $response_data['userData']['lastName'],
        'source' => $response_data['source'] ?? 'Desconhecido',
        'sessionId' => $response_data['overrideConfig']['sessionId'],
        'userData' => $response_data['userData'] ?? [],
        'formattedResponse' => $formattedResponse
    ]);
    log_message('trello_integration', 'info', "Envio ao Trello concluído para id: $id.");
} catch (Exception $e) {
    log_message('trello_integration', 'error', "Falha ao enviar para o Trello para id: $id. Erro: " . $e->getMessage());
}

// Retorna 200 OK
http_response_code(200);
?>
