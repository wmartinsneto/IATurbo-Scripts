<?php

/**
 * API para interação com a OpenAI para obter mensagens de threads
 * 
 * Esta API realiza as seguintes operações:
 * 1. Obtém uma mensagem específica de uma thread usando a API da OpenAI.
 * 
 * Parâmetros esperados na requisição GET:
 * - ThreadId: ID da thread para identificar a mensagem (obrigatório).
 * - MessageId: ID da mensagem a ser obtida (obrigatório).
 * 
 * Respostas da API:
 * - Sucesso ao obter a mensagem: Retorna a mensagem no formato JSON.
 * - Falha devido a parâmetros inválidos: "ThreadId e MessageId são necessários."
 * - Falha ao obter a mensagem: Retorna um erro no formato JSON.
 * 
 * Logs:
 * - Logs de sucesso são gravados em um arquivo único: 'splitted_messages_sucesso.txt'
 * - Logs de erro são gravados em arquivos separados por erro, com nomes contendo data, hora e threadId (se disponível)
 * 
 * Dependências:
 * - PHP cURL (para fazer requisições HTTP para a API da OpenAI)
 */

// Constantes
define("OPENAI_API_KEY", "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");

// Função para registrar logs de sucesso
function registrarLogSucesso($mensagem) {
    $caminhoLog = './logs/splitted_messages_sucesso.txt';
    file_put_contents($caminhoLog, date("Y-m-d H:i:s") . " - " . $mensagem . "\n", FILE_APPEND);
}

// Função para registrar logs de erro
function registrarLogErro($mensagem, $threadId = null) {
    $dataHora = date("Ymd_His");
    $milissegundos = substr((string)microtime(), 2, 3);
    $threadIdPart = $threadId ? "_$threadId" : "";
    $nomeArquivo = "splitted_messages_erro_{$dataHora}_{$milissegundos}{$threadIdPart}.txt";
    $caminhoLog = './logs/' . $nomeArquivo;
    file_put_contents($caminhoLog, $mensagem);
}


// Função para obter a mensagem da OpenAI
function obterMensagemOpenAI($threadId, $messageId, &$logPadrao, &$logErro) {
    $url = "https://api.openai.com/v1/threads/" . $threadId . "/messages/" . $messageId;
    $logPadrao .= "Request URL: " . $url . "\n";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . OPENAI_API_KEY,
        'OpenAI-Beta: assistants=v1'
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $resposta = curl_exec($ch);
    if (curl_errno($ch)) {
        $logErro .= "Erro cURL: " . curl_error($ch) . "\n";
        curl_close($ch);
        return json_encode(["error" => "Erro cURL: " . curl_error($ch)]);
    }

    curl_close($ch);
    $logPadrao .= "Raw Response: " . $resposta . "\n";

    $dados = json_decode($resposta, true);
    $logPadrao .= "Decoded Response: " . print_r($dados, true) . "\n";

    if (!$dados || !isset($dados['content'][0]['text']['value'])) {
        $logErro .= "Formato de resposta inválido.\n";
        return json_encode(['error' => 'Formato de resposta inválido.']);
    }

    $mensagem = $dados['content'][0]['text']['value'];
    $mensagemDecodificada = json_decode($mensagem, true);

    if (!$mensagemDecodificada) {
        $logErro .= "Erro ao decodificar JSON da mensagem obtida: " . json_last_error_msg() . "\n";
        return json_encode(['error' => 'Erro ao decodificar JSON da mensagem obtida: ' . json_last_error_msg()]);
    }

    // Limpeza de caracteres suspeitos na mensagem decodificada
    array_walk_recursive($mensagemDecodificada, function(&$item) {
        if (is_string($item)) {
            // Remove caracteres de controle (exceto quebras de linha)
            $item = trim(preg_replace('/[^\P{C}\n]+/u', '', $item)); 
        }
    });

    return $mensagemDecodificada;
}

// Processamento da requisição
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $threadId = $_GET['ThreadId'] ?? '';
    $messageId = $_GET['MessageId'] ?? '';

    $logPadrao = "";
    $logErro = "";

    $logPadrao .= "Recebida requisição GET com ThreadId: " . $threadId . " e MessageId: " . $messageId . "\n";

    if ($threadId && $messageId) {
        $mensagemObtida = obterMensagemOpenAI($threadId, $messageId, $logPadrao, $logErro);

        if (is_array($mensagemObtida) && !isset($mensagemObtida['error'])) {
            $logPadrao .= "Mensagem obtida com sucesso.\n";
            registrarLogSucesso($logPadrao);
            echo json_encode($mensagemObtida);
        } else {
            $logErro .= "Erro ao obter mensagem: " . ($mensagemObtida['error'] ?? 'Formato de resposta inválido.') . "\n";
            registrarLogErro($logErro, $threadId);
            echo json_encode(['error' => $mensagemObtida['error'] ?? 'Formato de resposta inválido.']);
        }
    } else {
        $logErro .= "ThreadId e MessageId são necessários.\n";
        registrarLogErro($logErro);
        echo json_encode(['error' => 'ThreadId e MessageId são necessários.']);
    }
}
?>
