<?php
/**
 * trello_integration.php
 *
 * Este script recebe um `id` via POST, carrega os dados correspondentes e envia as informações ao Trello.
 */

// Chaves de API do Trello
$apiKey = 'eede912d114a15aacb161506dd170cf5';
$apiToken = 'ATTA3176dbbbc3c0e5a22bdb3fcba706ac35c9b4b96e930098e562d153b0a5758d6718848C8E';
$boardId = '669eaa63f0fde49eefa7381a'; // ID do board "Chatbots IATurbo"
$listId = '669eaa743dce7b9bf018a635'; // ID da lista "Novo Lead"

// Função para registrar logs
function log_message($message) {
    $log_file = './logs/trello_integration-' . date('Y-m-d') . '.log';
    $log_entry = date('Y-m-d H:i:s') . " - " . $message . "\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Função para registrar logs de sucesso
function registrarLogSucesso($mensagem) {
    $caminhoLog = './logs/trello_log_sucesso.txt';
    file_put_contents($caminhoLog, date("Y-m-d H:i:s") . " - " . $mensagem . "\n", FILE_APPEND);
}

// Função para registrar logs de erro
function registrarLogErro($mensagem, $sessionId = null) {
    $dataHora = date("Ymd_His");
    $milissegundos = substr((string)microtime(), 2, 3);
    $sessionIdPart = $sessionId ? "_$sessionId" : "";
    $nomeArquivo = "trello_log_erro_{$dataHora}_{$milissegundos}{$sessionIdPart}.txt";
    $caminhoLog = './logs/' . $nomeArquivo;
    file_put_contents($caminhoLog, $mensagem);
}

// Função para logar todos os parâmetros de entrada recebidos do `run.php`
function logInputParams($data) {
    $caminhoLog = './logs/trello_input_log.txt';
    $logMensagem = "Parâmetros recebidos do `run.php`:\n" . print_r($data, true) . "\n";
    file_put_contents($caminhoLog, date("Y-m-d H:i:s") . " - " . $logMensagem . "\n", FILE_APPEND);
}

// Função para logar todos os parâmetros enviados para o Trello
function logOutputParams($params) {
    $caminhoLog = './logs/trello_output_log.txt';
    $logMensagem = "Parâmetros enviados para o Trello:\n" . print_r($params, true) . "\n";
    file_put_contents($caminhoLog, date("Y-m-d H:i:s") . " - " . $logMensagem . "\n", FILE_APPEND);
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

// Função para criar ou atualizar um cartão no Trello
function send_to_trello($data) {
    global $apiKey, $apiToken, $boardId, $listId;

    // Verifica se o sessionId foi passado corretamente
    if (isset($data['sessionId']) && !empty($data['sessionId'])) {
        $sessionId = $data['sessionId'];
    } else {
        $errorMsg = "Session ID não encontrado.";
        registrarLogErro($errorMsg, null);
        log_message($errorMsg);  // Log detalhado
        return;
    }

    // Loga todos os parâmetros de entrada
    logInputParams($data);

    $source = $data['source'];
    $userData = $data['userData'];
    $question = $data['leadQuestion']; // Pergunta do lead
    $formattedResponse = $data['formattedResponse']; // Resposta formatada para o Trello

    // Monta o conteúdo do comentário
    $comment = "### Pergunta do Lead:\n$question\n\n";
    $comment .= "**Resposta do Chatbot**:\n$formattedResponse\n\n";

    // Loga os parâmetros de saída antes de enviá-los ao Trello
    logOutputParams([
        'sessionId' => $sessionId,
        'source' => $source,
        'userData' => $userData,
        'question' => $question,
        'formattedResponse' => $formattedResponse
    ]);

    // Gera o nome do cartão baseado na lógica de flexibilidade
    $cardName = generateCardName($source, $userData, $sessionId);

    // Inicia os logs para criar ou atualizar o cartão
    $logPadrao = "";
    $logErro = "";
    log_message("Buscando ou criando cartão no Trello para sessionId: $sessionId");  // Log detalhado

    // Busca o cartão no Trello pelo sessionId do payload
    $searchResult = searchCard($sessionId, $apiKey, $apiToken, $boardId, $logPadrao, $logErro);

    if ($searchResult) {
        // Se o card existe, adiciona um comentário
        $cardId = $searchResult['id'];
        log_message("Cartão encontrado para sessionId: $sessionId. ID do cartão: $cardId");  // Log detalhado

        $resultComment = addCommentToCard($cardId, $comment, $apiKey, $apiToken, $logPadrao, $logErro);
        if ($resultComment) {
            registrarLogSucesso($logPadrao);
            log_message("Comentário adicionado ao cartão $cardId com sucesso.");  // Log detalhado
        } else {
            $logErro .= "Erro ao adicionar comentário ao card existente.\n";
            registrarLogErro($logErro, $sessionId);
            log_message("Falha ao adicionar comentário ao cartão $cardId. Erro: $logErro");  // Log detalhado
        }
        echo 'Comentário adicionado ao card existente.';
    } else {
        // Se o card não existe, cria um novo card com o nome gerado
        log_message("Cartão não encontrado para sessionId: $sessionId. Criando novo cartão.");  // Log detalhado

        $resultCreate = createCard($cardName, $sessionId, $source, $userData, $comment, $apiKey, $apiToken, $listId, $logPadrao, $logErro);
        if ($resultCreate) {
            registrarLogSucesso($logPadrao);
            log_message("Novo cartão criado com sucesso para sessionId: $sessionId.");  // Log detalhado
        } else {
            $logErro .= "Erro ao criar novo card.\n";
            registrarLogErro($logErro, $sessionId);
            log_message("Falha ao criar novo cartão para sessionId: $sessionId. Erro: $logErro");  // Log detalhado
        }
        echo 'Novo card criado com sucesso.';
    }
}

// Função para formatar o conteúdo com base no JSON_SCHEMA
function formatTrelloContent($id, $response_data) {
    log_message("Iniciando formatTrelloContent. Id = $id");
    $formatted_content = "";

    // Mensagem de Texto
    if (!empty($response_data['mensagemDeTexto'])) {
        $formatted_content .= "### Mensagem de Texto\n";
        $formatted_content .= $response_data['mensagemDeTexto'] . "\n\n";
        log_message("Mensagem de Texto formatada.");
    }

    // Mensagem de Voz
    if (!empty($response_data['mensagemDeVoz'])) {
        $formatted_content .= "### Mensagem de Voz\n";
        $formatted_content .= $response_data['mensagemDeVoz'] . "\n\n";
        $formatted_content .= "https://iaturbo.com.br/wp-content/uploads/scripts/speech/output/audio_$id.mp3 \n\n";
        log_message("Mensagem de Voz formatada.");
    }

    // Informações de Controle
    if (!empty($response_data['mensagemDeControle'])) {
        $formatted_content .= "### Mensagem de Controle\n";
        $formatted_content .= $response_data['mensagemDeControle'] . "\n\n";
        log_message("Mensagem de Controle formatada.");
    }
    log_message("formatted_content = $formatted_content");

    return $formatted_content;
}

// Recebe os parâmetros do JSON de entrada
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    log_message("Parâmetro 'id' não fornecido.");
    die(json_encode(['error' => 'Parâmetro "id" não fornecido.']));
}

log_message("Iniciando integração com Trello para id: $id.");

// Caminhos dos arquivos
$completed_file_path = './completed/' . $id . '.json';

// Verifica se os arquivos existem
if (!file_exists($completed_file_path)) {
    log_message("Arquivo não encontrado: $completed_file_path");
    die(json_encode(['error' => "Arquivo não encontrado: $completed_file_path"]));
}

// Carrega os dados necessários
$response_data = json_decode(file_get_contents($completed_file_path), true);

// Prepara os dados
$decodedTextResponse = json_decode($response_data['thought'], true);
$formattedResponse = formatTrelloContent($id, $decodedTextResponse);

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
    log_message("Envio ao Trello concluído para id: $id.");
} catch (Exception $e) {
    log_message("Falha ao enviar para o Trello para id: $id. Erro: " . $e->getMessage());
}

// Retorna 200 OK
http_response_code(200);