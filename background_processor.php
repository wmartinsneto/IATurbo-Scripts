<?php

// Inclua as funções e constantes necessárias aqui
require_once 'send-to-trello.php';
require_once 'splitted-messages.php';

// Função para processar os dados
function processBackground($data) {
    global $apiKey, $apiToken, $boardId, $listId;

    $logPadrao = "";
    $logErro = "";

    $threadId = $data['threadId'];
    $leadMessageId = $data['leadMessageId'];
    $botMessageId = $data['botMessageId'];
    $sessionId = $data['sessionId'];
    $source = $data['source'];
    $userData = $data['userData'];

    // Obtém as mensagens do OpenAI
    $leadMessage = obterMensagemOpenAI($threadId, $leadMessageId, $logPadrao, $logErro);
    $botMessage = obterMensagemOpenAI($threadId, $botMessageId, $logPadrao, $logErro);

    if ($leadMessage === null || $botMessage === null) {
        $logErro .= "Erro ao obter mensagens do OpenAI.\n";
        registrarLogErro($logErro, $sessionId);
        return;
    }

    // Decodifica o JSON da mensagem do bot se necessário
    $botMessageJson = json_decode($botMessage, true);
    if ($botMessageJson !== null) {
        $botMessageFinal = '';
        for ($i = 1; $i <= 10; $i++) {
            if (isset($botMessageJson["splittedText$i"]) && $botMessageJson["splittedText$i"] !== "N/A") {
                $botMessageFinal .= $botMessageJson["splittedText$i"] . "\n";
            }
        }
    } else {
        $botMessageFinal = $botMessage;
    }

    // Primeiro, procura o card
    $searchResult = searchCard($sessionId, $apiKey, $apiToken, $boardId, $logPadrao, $logErro);

    if ($searchResult) {
        // Se o card existe, adiciona um comentário
        $cardId = $searchResult['id'];
        $resultComment = addCommentToCard($cardId, "Mensagem do Usuário:\n$leadMessage\n\nResposta do Chatbot:\n$botMessageFinal\n", $apiKey, $apiToken, $logPadrao, $logErro);
        if ($resultComment) {
            registrarLogSucesso($logPadrao);
        } else {
            $logErro .= "Erro ao adicionar comentário ao card existente.\n";
            registrarLogErro($logErro, $sessionId);
        }
    } else {
        // Se o card não existe, cria um novo card
        $resultCreate = createCard($sessionId, $source, $userData, $leadMessage, $botMessageFinal, $apiKey, $apiToken, $listId, $logPadrao, $logErro);
        if ($resultCreate) {
            registrarLogSucesso($logPadrao);
        } else {
            $logErro .= "Erro ao criar novo card.\n";
            registrarLogErro($logErro, $sessionId);
        }
    }
}

// Recebe o nome do arquivo do processo em background
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Adicionando logs para diagnosticar o problema
registrarLogErro("Dados recebidos: " . print_r($data, true));

if (isset($data['fileName'])) {
    $fileName = $data['fileName'];
    $filePath = './messages/' . $fileName;
    $fileData = json_decode(file_get_contents($filePath), true);

    if ($fileData) {
        processBackground($fileData);
    } else {
        registrarLogErro("Erro ao ler o arquivo de dados: $filePath");
    }
} else {
    registrarLogErro("Parâmetros fileName são obrigatórios.");
}
?>
