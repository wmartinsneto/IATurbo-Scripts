<![CDATA[<?php
/**
 * view-messages.php
 *
 * Esta página apresenta uma interface moderna inspirada no visual Tron para visualizar
 * os JSONs armazenados na pasta ./completed. As mensagens são agrupadas por conversation_id
 * e ordenadas por created_at.
 *
 * A página exibe ao lado esquerdo uma lista de conversas (com conversation_id, created_at e source)
 * e, ao selecionar uma conversa, exibe no painel direito a sequência de mensagens, onde cada mensagem
 * mostra a pergunta, a mensagem de texto (mensagemDeTexto), um player de áudio para a mensagem de voz
 * – obtido via get-audio.php usando o file_id, e um painel com a mensagem de controle (mensagemDeControle).
 *
 * Recursos de usabilidade e acessibilidade, como tooltips e ícones SVG animados, são utilizados para
 * reforçar o design e a interatividade.
 */

// Define o diretório de arquivos completados
$completed_dir = __DIR__ . '/completed/';

// Lista os arquivos JSON na pasta completed
$files = array_values(array_filter(scandir($completed_dir), function($file) {
    return preg_match('/\.json$/', $file);
}));

$conversations = [];

// Carrega cada arquivo JSON e agrupa por conversation_id
foreach ($files as $file) {
    $json = file_get_contents($completed_dir . $file);
    $data = json_decode($json, true);
    // Adiciona o identificador do arquivo sem extensão para uso do get-audio.php
    $data['file_id'] = basename($file, '.json');
    if (!$data || !isset($data['conversation_id'])) {
        continue;
    }
    $conv_id = $data['conversation_id'];
    // Certifica-se que o campo created_at existe (timestamp) – caso contrário, usar 0
    $created_at = isset($data['created_at']) ? $data['created_at'] : 0;
    if (!isset($conversations[$conv_id])) {
        $conversations[$conv_id] = [
            'conversation_id' => $conv_id,
            'source' => isset($data['source']) ? $data['source'] : 'Desconhecido',
            'messages' => []
        ];
    }
    $data['created_at'] = $created_at;
    $conversations[$conv_id]['messages'][] = $data;
}

 // Ordena as mensagens de cada conversa por created_at (mais novas no topo)
foreach ($conversations as &$conv) {
    usort($conv['messages'], function($a, $b) {
        return $b['created_at'] <=> $a['created_at'];
    });
}
unset($conv);

// Ordena a lista de conversas pela data da primeira mensagem (mais novas no topo)
usort($conversations, function($a, $b) {
    $timeA = isset($a['messages'][0]['created_at']) ? $a['messages'][0]['created_at'] : 0;
    $timeB = isset($b['messages'][0]['created_at']) ? $b['messages'][0]['created_at'] : 0;
    return $timeB <=> $timeA;
});

// Transforma os dados das conversas em JSON para exportar ao JavaScript
$conversations_json = json_encode($conversations);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Visualizador de Mensagens - Chatbots IATurbo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Estilos inspirados no Tron e na identidade do chatbot -->
  <style>
    body {
      margin: 0;
      font-family: 'Roboto', Arial, sans-serif;
      background: #0d0d0d;
      color: #f1f1f1;
      overflow: hidden;
    }
    .container {
      display: flex;
      height: 100vh;
    }
    .sidebar {
      width: 300px;
      background: rgba(18, 18, 18, 0.9);
      border-right: 1px solid #43d9ea;
      overflow-y: auto;
      padding: 1rem;
    }
    .sidebar h2 {
      font-size: 1.5rem;
      text-align: center;
      margin-bottom: 1rem;
      color: #43d9ea;
    }
    .conv-item {
      padding: 0.75rem;
      margin-bottom: 0.5rem;
      background: rgba(67, 217, 234, 0.1);
      border: 1px solid #43d9ea;
      border-radius: 8px;
      cursor: pointer;
      transition: background 0.3s;
    }
    .conv-item:hover {
      background: rgba(67, 217, 234, 0.2);
    }
    .conv-item.active {
      background: rgba(67, 217, 234, 0.3);
    }
    .conv-meta {
      font-size: 0.85rem;
      color: #a0a0a0;
    }
    .content {
      flex: 1;
      background: #0d0d0d;
      overflow-y: auto;
      padding: 1rem;
    }
    .content h2 {
      text-align: center;
      font-size: 1.75rem;
      margin-bottom: 1rem;
      color: #43d9ea;
    }
    .message {
      display: flex;
      background: rgba(18, 18, 18, 0.95);
      border: 1px solid #43d9ea;
      border-radius: 8px;
      padding: 1rem;
      margin-bottom: 1rem;
      align-items: flex-start;
    }
    .message .icon {
      margin-right: 0.75rem;
    }
    .message .icon svg {
      width: 40px;
      height: 40px;
      fill: #43d9ea;
      animation: pulse 2s infinite;
    }
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }
    .message .msg-details {
      flex: 1;
    }
    .message .msg-details .question {
      font-weight: bold;
      margin-bottom: 0.5rem;
    }
    .message .msg-details .mensagemDeTexto {
      margin-bottom: 0.5rem;
    }
    .message .msg-details .mensagemDeControle {
      background: rgba(67, 217, 234, 0.1);
      border: 1px solid #43d9ea;
      border-radius: 4px;
      padding: 0.5rem;
      font-size: 0.85rem;
      margin-top: 0.5rem;
      text-align: right;
    }
    .message .audio-player {
      margin-top: 0.5rem;
    }
    .message .audio-player audio {
      width: 100%;
      outline: none;
    }
    /* Tooltips simples */
    .tooltip {
      position: relative;
      cursor: help;
    }
    .tooltip:hover::after {
      content: attr(data-tooltip);
      position: absolute;
      bottom: 100%;
      left: 50%;
      transform: translate(-50%, -0.5rem);
      background: rgba(18, 18, 18, 0.9);
      color: #43d9ea;
      padding: 0.5rem;
      white-space: nowrap;
      border: 1px solid #43d9ea;
      border-radius: 4px;
      font-size: 0.75rem;
      z-index: 10000;
    }
    /* Scrollbar customizada */
    ::-webkit-scrollbar {
      width: 8px;
    }
    ::-webkit-scrollbar-thumb {
      background: #43d9ea;
      border-radius: 4px;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="sidebar">
      <h2>Conversas</h2>
      <ul id="conversationList" style="list-style: none; padding: 0;"></ul>
    </div>
    <div class="content">
      <h2>Detalhes da Conversa</h2>
      <div id="conversationDetails"></div>
    </div>
  </div>
  <script>
    // Dados das conversas vindos do PHP
    const conversations = <?php echo $conversations_json; ?>;
    
    // Função para formatar timestamp
    function formatTimestamp(ts) {
      const date = new Date(ts * 1000);
      return date.toLocaleString();
    }

    // Popula a lista de conversas
    const conversationList = document.getElementById('conversationList');
    const conversationDetails = document.getElementById('conversationDetails');

    conversations.forEach((conv, index) => {
      // Usa a primeira mensagem do grupo para exibir created_at, source e conversation id
      const firstMsg = conv.messages[0];
      const li = document.createElement('li');
      li.className = 'conv-item tooltip';
      li.setAttribute('data-tooltip', `Source: ${conv.source}\nCriado em: ${formatTimestamp(firstMsg.created_at)}`);
      li.dataset.index = index;
      li.innerHTML = `<div><strong>${conv.conversation_id}</strong></div>
                       <div class="conv-meta">${formatTimestamp(firstMsg.created_at)}</div>`;
      li.addEventListener('click', function() {
        document.querySelectorAll('.conv-item').forEach(item => item.classList.remove('active'));
        this.classList.add('active');
        displayConversation(conversations[this.dataset.index]);
      });
      conversationList.appendChild(li);
    });

    // Exibe as mensagens da conversa selecionada
    function displayConversation(conv) {
      conversationDetails.innerHTML = '';
      conv.messages.forEach(msg => {
        // Inicializa variáveis de resposta
        let mensagemDeTexto = msg.mensagemDeTexto ? msg.mensagemDeTexto : '';
        let mensagemDeVoz = msg.mensagemDeVoz ? msg.mensagemDeVoz : '';
        let mensagemDeControle = msg.mensagemDeControle ? msg.mensagemDeControle : '';
        // Se alguma informação estiver faltando, tente extrair do campo "answer"
        if ((!mensagemDeTexto || !mensagemDeVoz || !mensagemDeControle) && msg.answer) {
          try {
            const parsedAnswer = JSON.parse(msg.answer);
            if (!mensagemDeTexto && parsedAnswer.mensagemDeTexto)
              mensagemDeTexto = parsedAnswer.mensagemDeTexto;
            if (!mensagemDeVoz && parsedAnswer.mensagemDeVoz)
              mensagemDeVoz = parsedAnswer.mensagemDeVoz;
            if (!mensagemDeControle && parsedAnswer.mensagemDeControle)
              mensagemDeControle = parsedAnswer.mensagemDeControle;
          } catch(e) {
            // Se ocorrer erro de parse, ignora e mantém valores atuais
          }
        }
        
        // Cria o player de áudio: usa o file_id do JSON para chamar get-audio.php
        let audioHtml = '';
        if (msg.file_id) {
          audioHtml = '<em>Carregando áudio...</em>';
          fetch('https://iaturbo.com.br/wp-content/uploads/scripts/speech/get-audio.php?id=' + msg.file_id)
            .then(response => response.json())
            .then(data => {
              if(data.status === 'ok' && data.audio_url){
                audioHtml = `<audio controls>
                              <source src="${data.audio_url}" type="audio/mpeg">
                              Seu navegador não suporta áudio.
                            </audio>`;
              } else {
                audioHtml = '<em>Áudio pendente</em>';
              }
              // Atualiza o player no DOM
              const playerElem = document.getElementById('audio-' + msg.file_id);
              if(playerElem){
                playerElem.innerHTML = audioHtml;
              }
            })
            .catch(error => {
              audioHtml = '<em>Erro ao carregar áudio</em>';
              const playerElem = document.getElementById('audio-' + msg.file_id);
              if(playerElem){
                playerElem.innerHTML = audioHtml;
              }
            });
          // Inicialmente, cria um placeholder com id para atualização
          audioHtml = `<div id="audio-${msg.file_id}">${audioHtml}</div>`;
        } else {
          audioHtml = '<em>Sem áudio</em>';
        }
        
        const container = document.createElement('div');
        container.className = 'message';
        
        // Ícone SVG (exemplo simples de um ícone de mensagem)
        const iconDiv = document.createElement('div');
        iconDiv.className = 'icon';
        iconDiv.innerHTML = `<svg viewBox="0 0 24 24">
          <path d="M4,4H20V16H5.17L4,17.17V4Z"></path>
        </svg>`;
        
        const detailsDiv = document.createElement('div');
        detailsDiv.className = 'msg-details';
        
        // Pergunta
        const questionEl = document.createElement('div');
        questionEl.className = 'question';
        questionEl.textContent = msg.question ? msg.question : 'Sem pergunta';
        
        // Mensagem de Texto
        const textEl = document.createElement('div');
        textEl.className = 'mensagemDeTexto';
        textEl.innerHTML = mensagemDeTexto ? mensagemDeTexto.replace(/\n/g, '<br>') : '<em>Sem texto</em>';
        
        // Audio player para mensagem de voz
        const audioDivContainer = document.createElement('div');
        audioDivContainer.className = 'audio-player';
        audioDivContainer.innerHTML = audioHtml;
        
        // Mensagem de Controle
        const controlDiv = document.createElement('div');
        controlDiv.className = 'mensagemDeControle';
        controlDiv.innerHTML = mensagemDeControle ? mensagemDeControle.replace(/\n/g, '<br>') : '<em>Sem controle</em>';
        
        detailsDiv.appendChild(questionEl);
        detailsDiv.appendChild(textEl);
        detailsDiv.appendChild(audioDivContainer);
        detailsDiv.appendChild(controlDiv);
        
        container.appendChild(iconDiv);
        container.appendChild(detailsDiv);
        conversationDetails.appendChild(container);
      });
    }
    // Se houver ao menos uma conversa, exibe a primeira por padrão
    if(conversations.length > 0) {
      document.querySelector('.conv-item').classList.add('active');
      displayConversation(conversations[0]);
    }
  </script>
</body>
</html>
]]>
