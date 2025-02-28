const chatbot = document.getElementById('chatbot');
const chatInput = document.getElementById('chatInput');
const chatWindow = document.getElementById('chatWindow');
const sendButton = document.getElementById('sendButton');
const closeButton = document.getElementById('closeButton');
const refreshButton = document.getElementById('refreshButton');

let placeholders = ["Precisa de ajuda?", "Pergunte para a IARA"];
let currentPlaceholder = 0;
let conversationId = null;

setInterval(() => {
    chatInput.classList.add('fade');
    setTimeout(() => {
        currentPlaceholder = (currentPlaceholder + 1) % placeholders.length;
        chatInput.placeholder = placeholders[currentPlaceholder];
        chatInput.classList.remove('fade');
    }, 500);
}, 3000);

chatbot.addEventListener('mouseenter', () => {
    chatbot.classList.add('expanded');
    chatInput.focus();
});

// Reopen chatWindow when input gains focus if it was closed.
chatInput.addEventListener('focus', () => {
    if (chatWindow.style.display === 'none') {
        chatWindow.style.display = 'flex';
    }
});

chatbot.addEventListener('mouseleave', () => {
    chatbot.classList.remove('expanded');
});

const sendMessage = async () => {
    // Prevent duplicate sends if input is already disabled
    if (chatInput.disabled) return;

    const question = chatInput.value;
    if (question.trim() === '') return;

    // Exibe a pergunta no chat
    const userMessage = document.createElement('div');
    userMessage.className = 'message';
    userMessage.innerHTML = `<div class="userMessage">${question}</div><div class="userIcon">${getUserIcon()}</div>`;
    chatWindow.appendChild(userMessage);
    chatWindow.style.display = 'flex';
    chatWindow.scrollTop = chatWindow.scrollHeight;

    // Adiciona a animação de espera
    const botMessage = document.createElement('div');
    botMessage.className = 'message';
    botMessage.innerHTML = `<div class="botIcon"></div><div class="botMessage"><h4 id="searching-ellipsis">Pensando<span>.</span><span>.</span><span>.</span></h4></div>`;
    chatWindow.appendChild(botMessage);
    chatWindow.scrollTop = chatWindow.scrollHeight;

    // Limpa o campo de entrada e desabilita
    chatInput.value = '';
    chatInput.disabled = true;

    // Envia a pergunta para a API
    const sessionId = localStorage.getItem('sessionId') || generateSessionId();
    localStorage.setItem('sessionId', sessionId);

    try {
        // Envia a questão e aguarda o ID da requisição
        const requestId = await sendQuestion(question, sessionId);
        // Aguarda a resposta final
        const response = await getResponse(requestId);

        // Remove a animação e prepara a área para o texto com efeito typewriter
        botMessage.innerHTML = `<div class="botIcon"></div><div class="botMessage"></div>`;
        typeWriter(botMessage.querySelector('.botMessage'), response, 0, () => {
            // Quando o typing terminar, chama o get-audio.php para obter a URL do áudio
            fetch(`https://iaturbo.com.br/wp-content/uploads/scripts/speech/get-audio.php?id=${requestId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'ok') {
                        const botMessageContainer = botMessage.querySelector('.botMessage');
                        const audioWrapper = document.createElement('div');
                        audioWrapper.className = 'audio-player';
                        audioWrapper.innerHTML = `
                            <audio controls autoplay>
                              <source src="${data.audio_url}" type="audio/mpeg">
                              Seu navegador não suporta o áudio HTML5.
                            </audio>`;
                        botMessageContainer.appendChild(audioWrapper);
                        chatWindow.scrollTop = chatWindow.scrollHeight;
                    }
                })
                .catch(err => console.error('Erro ao obter áudio:', err))
                .finally(() => {
                    chatInput.focus();
                });
        });
    } catch (error) {
        logError('Erro ao processar a pergunta: ' + error.message);
    } finally {
        chatInput.disabled = false;
    }
};

sendButton.addEventListener('click', sendMessage);
chatInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        sendMessage();
    }
});

closeButton.addEventListener('click', () => {
    chatWindow.style.display = 'none';
});

refreshButton.addEventListener('click', () => {
    chatWindow.innerHTML = '';
    chatWindow.appendChild(document.getElementById('chatHeader'));
    conversationId = null;
});

function getUserIcon() {
    return `<svg width="30" height="30" viewBox="0 0 512 512" fill="none" xmlns="http://www.w3.org/2000/svg">
        <g clip-path="url(#clip0)">
            <rect width="512" height="512" rx="256" fill="#B2DDFF"></rect>
            <circle opacity="0.68" cx="256" cy="196" r="84" fill="white"></circle>
            <ellipse opacity="0.68" cx="256" cy="583.5" rx="266" ry="274.5" fill="white"></ellipse>
        </g>
        <defs>
            <clipPath id="clip0">
                <rect width="512" height="512" rx="256" fill="white"></rect>
            </clipPath>
        </defs>
    </svg>`;
}

function generateSessionId() {
    return 'sess_' + Math.random().toString(36).substr(2, 9);
}

async function sendQuestion(question, sessionId) {
    try {
        const response = await fetch('https://iaturbo.com.br/wp-content/uploads/scripts/dify/request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                question,
                source: 'JS-Client',
                conversation_id: conversationId,
                overrideConfig: { sessionId },
                userData: {}
            })
        });
        const data = await response.json();
        logInfo('Pergunta enviada com sucesso', 'request.php');
        return data.id;
    } catch (error) {
        logError('Erro ao enviar pergunta: ' + error.message, 'request.php');
        throw error;
    }
}

async function getResponse(requestId) {
    try {
        await fetch('https://iaturbo.com.br/wp-content/uploads/scripts/dify/run.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: requestId,
                chatflow_url: 'https://srv673787.hstgr.cloud/v1',
                chatflow_key: 'app-0VZo5qBBfhWNLa3bJFw7kr3m'
            })
        });
        let responseText = 'Aguarde...';
        for (let i = 0; i < 5; i++) {
            const response = await fetch(`https://iaturbo.com.br/wp-content/uploads/scripts/dify/response.php?id=${requestId}`);
            const data = await response.json();
            if (data.status === 'completed') {
                responseText = data.mensagemDeTexto;
                conversationId = data.conversation_id || conversationId;
                logInfo('Resposta recebida com sucesso', 'response.php');
                break;
            }
            await new Promise(resolve => setTimeout(resolve, 5000));
        }
        return responseText;
    } catch (error) {
        logError('Erro ao obter resposta: ' + error.message, 'response.php');
        throw error;
    }
}

async function logInfo(message, source) {
    await fetch('https://iaturbo.com.br/wp-content/uploads/scripts/dify/remote-log.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            message,
            type: 'INFO',
            source
        })
    });
}

async function logError(message, source) {
    await fetch('https://iaturbo.com.br/wp-content/uploads/scripts/dify/remote-log.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            message,
            type: 'ERROR',
            source
        })
    });
}

/* Updated typeWriter function that accepts a callback when finished */
function typeWriter(element, text, i = 0, callback) {
    if (i < text.length) {
        element.innerHTML += text.charAt(i);
        chatWindow.scrollTop = chatWindow.scrollHeight; // Scroll to bottom
        setTimeout(() => typeWriter(element, text, i + 1, callback), 17);
    } else {
        if (callback) callback();
    }
}
