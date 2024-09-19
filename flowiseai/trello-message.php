<?php

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
        registrarLogErro('Session ID não encontrado.', null);
        return;
    }

    $source = $data['source'];
    $userData = $data['userData'];
    $question = $data['message']; // Pergunta do lead
    $textResponse = $data['textResponse']; // Resposta em texto
    $audioUrl = isset($data['audioUrl']) ? $data['audioUrl'] : null; // URL de áudio, se existir
    $controlData = isset($data['controlData']) ? $data['controlData'] : []; // Dados de controle (temperatura, etc.)

    // Monta o conteúdo do comentário
    $comment = "### Pergunta:\n$question\n\n";
    $comment .= "**Resposta Gerada (Texto)**:\n$textResponse\n\n";
    if ($audioUrl) {
        $comment .= "**Resposta Gerada (Voz)**:\n[Acessar áudio]($audioUrl)\n\n";
    }
    if (!empty($controlData)) {
        $comment .= "**Detalhes de Controle**:\n";
        foreach ($controlData as $key => $value) {
            $comment .= "- **" . ucfirst($key) . "**: $value\n";
        }
    }

    // Gera o nome do cartão baseado na lógica de flexibilidade
    $cardName = generateCardName($source, $userData, $sessionId);

    // Busca o cartão no Trello pelo sessionId do payload
    $logPadrao = "";
    $logErro = "";
    $searchResult = searchCard($sessionId, $apiKey, $apiToken, $boardId, $logPadrao, $logErro);

    if ($searchResult) {
        // Se o card existe, adiciona um comentário
        $cardId = $searchResult['id'];
        $resultComment = addCommentToCard($cardId, $comment, $apiKey, $apiToken, $logPadrao, $logErro);
        if ($resultComment) {
            registrarLogSucesso($logPadrao);
        } else {
            $logErro .= "Erro ao adicionar comentário ao card existente.\n";
            registrarLogErro($logErro, $sessionId);
        }
        echo 'Comentário adicionado ao card existente.';
    } else {
        // Se o card não existe, cria um novo card com o nome gerado
        $resultCreate = createCard($cardName, $sessionId, $source, $userData, $comment, $apiKey, $apiToken, $listId, $logPadrao, $logErro);
        if ($resultCreate) {
            registrarLogSucesso($logPadrao);
        } else {
            $logErro .= "Erro ao criar novo card.\n";
            registrarLogErro($logErro, $sessionId);
        }
        echo 'Novo card criado com sucesso.';
    }
}

?>
