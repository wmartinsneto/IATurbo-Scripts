<?php

/**
 * API para integração com o Trello para gerenciar cartões e adicionar comentários
 * 
 * Esta API realiza as seguintes operações:
 * 1. Busca um cartão no Trello baseado em um sessionId.
 * 2. Cria um novo cartão no Trello se o cartão não existir.
 * 3. Adiciona um comentário a um cartão existente no Trello.
 * 
 * Parâmetros esperados no corpo da requisição (JSON):
 * - sessionId: ID da sessão para identificar o cartão no Trello (obrigatório).
 * - message: Mensagem a ser adicionada como comentário no cartão (obrigatório).
 * - source: Fonte da mensagem, utilizada na descrição do cartão (obrigatório).
 * - userData: Dados do usuário a serem incluídos na descrição do cartão (obrigatório).
 * 
 * Respostas da API:
 * - Sucesso ao adicionar comentário ao cartão existente: "Comentário adicionado ao card existente."
 * - Sucesso ao criar novo cartão: "Novo card criado com sucesso."
 * - Falha devido a parâmetros inválidos: "Parâmetros sessionId, message, source e userData são obrigatórios."
 * 
 * Logs:
 * - Logs de sucesso são gravados em um arquivo único: 'trello_log_sucesso.txt'
 * - Logs de erro são gravados em arquivos separados por erro, com nomes contendo data, hora e sessionId (se disponível)
 * 
 * Dependências:
 * - PHP cURL (para fazer requisições HTTP para a API do Trello)
 */

// Chaves de API do Trello
$apiKey = 'eede912d114a15aacb161506dd170cf5';
$apiToken = 'ATTA3176dbbbc3c0e5a22bdb3fcba706ac35c9b4b96e930098e562d153b0a5758d6718848C8E';
$boardId = '669eaa63f0fde49eefa7381a'; // ID do board "Chatbots IATurbo"
$listId = '669eaa743dce7b9bf018a635'; // ID da lista "Novo Lead"

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


// Função para buscar um cartão no Trello com base no sessionId
function searchCard($sessionId, $apiKey, $apiToken, $boardId, &$logPadrao, &$logErro) {
    $url = "https://api.trello.com/1/boards/$boardId/cards?key=$apiKey&token=$apiToken";
    
    // Log da URL para comparação
    $logPadrao .= "URL para obter cartões no quadro: " . $url . "\n";

    // Inicializa o cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Executa a requisição e captura a resposta
    $response = curl_exec($ch);

    // Verifica erros na execução
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
function createCard($message, $sessionId, $source, $userData, $apiKey, $apiToken, $listId, &$logPadrao, &$logErro) {
    $url = "https://api.trello.com/1/cards?key=$apiKey&token=$apiToken";
    $timestamp = date("Y-m-d H:i:s");
    $desc = "Conversa iniciada através do $source às $timestamp\nSessionId: $sessionId\n";
    foreach ($userData as $key => $value) {
        $desc .= ucfirst($key) . ": $value\n";
    }
    $data = array(
        'name' => "Novo Lead - $sessionId",
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
    addCommentToCard($card['id'], $message, $apiKey, $apiToken, $logPadrao, $logErro);

    return $card;
}

// Função para adicionar um comentário a um cartão no Trello
function addCommentToCard($cardId, $message, $apiKey, $apiToken, &$logPadrao, &$logErro) {
    // Remove linhas que terminam com "N/A"
    $messageLines = explode("\n", $message);
    $messageLines = array_filter($messageLines, function($line) {
        return trim($line) !== "N/A";
    });
    $message = implode("\n", $messageLines);

    // Limpeza de caracteres suspeitos na mensagem
    $message = trim(preg_replace('/[^\P{C}\n]+/u', '', $message)); // Remove caracteres de controle

    $url = "https://api.trello.com/1/cards/$cardId/actions/comments?key=$apiKey&token=$apiToken";
    $data = array(
        'text' => $message
    );
    $logErro .= "Dados para adicionar comentário: " . print_r($data, true) . "\n";
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
        $logErro .= "Falha na requisição para adicionar comentário: " . error_get_last()['message'] . "\n";
        return null;
    }

    $logPadrao .= "Comentário adicionado ao cartão $cardId com sucesso.\n";
    return $result;
}

// Recebe os dados do POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$logPadrao = "";
$logErro = "";

if (isset($data['sessionId']) && isset($data['message']) && isset($data['source']) && isset($data['userData'])) {
    $sessionId = $data['sessionId'];
    $message = $data['message'];
    $source = $data['source'];
    $userData = $data['userData'];

    // Primeiro, procura o card
    $searchResult = searchCard($sessionId, $apiKey, $apiToken, $boardId, $logPadrao, $logErro);

    if ($searchResult) {
        // Se o card existe, adiciona um comentário
        $cardId = $searchResult['id'];
        $resultComment = addCommentToCard($cardId, $message, $apiKey, $apiToken, $logPadrao, $logErro);
        if ($resultComment) {
            registrarLogSucesso($logPadrao);
        } else {
            $logErro .= "Erro ao adicionar comentário ao card existente.\n";
            registrarLogErro($logErro, $sessionId);
        }
        echo 'Comentário adicionado ao card existente.';
    } else {
        // Se o card não existe, cria um novo card
        $resultCreate = createCard($message, $sessionId, $source, $userData, $apiKey, $apiToken, $listId, $logPadrao, $logErro);
        if ($resultCreate) {
            registrarLogSucesso($logPadrao);
        } else {
            $logErro .= "Erro ao criar novo card.\n";
            registrarLogErro($logErro, $sessionId);
        }
        echo 'Novo card criado com sucesso.';
    }
} else {
    $logErro = "Parâmetros sessionId, message, source e userData são obrigatórios.\n";
    $logErro .= "Dados recebidos: " . print_r($data, true) . "\n";
    registrarLogErro($logErro);
    echo 'Parâmetros sessionId, message, source e userData são obrigatórios.';
}
?>
