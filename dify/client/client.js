// Constantes de configuração
const DEBUG_MODE = true;
const LOG_SOURCE = "IATurboJSClient_Chatbots_LP";

// Recupera dados do localStorage (se existirem) ou gera novos
let sessionId = localStorage.getItem('sessionId') || generateSessionId();
localStorage.setItem('sessionId', sessionId);
let conversationId = localStorage.getItem('conversationId') || null;

const chatbot = document.getElementById('chatbot');
const chatbotInput = document.getElementById('chatbotInput');
const transcribingMessage = document.getElementById('transcribingMessage');
const buttonsContainer = document.getElementById('buttonsContainer');
const recordingContainer = document.getElementById('recordingContainer');
const trashButton = document.getElementById('trashButton');
const recordingTimer = document.getElementById('recordingTimer');
const micOnButton = document.getElementById('micOnButton');
const micOffButton = document.getElementById('micOffButton');
const sendButton = document.getElementById('sendButton');
const chatWindow = document.getElementById('chatWindow');
const refreshButton = document.getElementById('refreshButton');
const closeButton = document.getElementById('closeButton');
const messagesContainer = document.getElementById('messagesContainer'); // Declaração única

let mediaRecorder;
let recordedChunks = [];
let recordingStartTime;
let recordingTimerInterval;
let chatMode = "initial"; // "initial", "recording", "transcribing"
let cancelRecording = false;
let placeholders = ["Precisa de ajuda?", "Pergunte para a IARA"];
let currentPlaceholder = 0;

// Função única para log (usando a nova versão do remote-log.php)
async function log(message, source, type) {
    await fetch('https://iaturbo.com.br/wp-content/uploads/scripts/remote-log.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message, type, source })
    });
}

// Função para logs detalhados quando DEBUG_MODE está ativo
function debugLog(message) {
    if (DEBUG_MODE) {
        log(message, LOG_SOURCE, "DEBUG");
    }
}

// Atualiza localStorage
function updateLocalStorage() {
    localStorage.setItem('sessionId', sessionId);
    if (conversationId) {
        localStorage.setItem('conversationId', conversationId);
    }
    localStorage.setItem('messages', messagesContainer.innerHTML);
}

// Restaura mensagens armazenadas e exibe o chatWindow
window.addEventListener('load', () => {
    const storedMessages = localStorage.getItem('messages');
    if (storedMessages && storedMessages.trim() !== "") {
        messagesContainer.innerHTML = storedMessages;
        chatWindow.style.display = 'flex';
        chatWindow.scrollTop = chatWindow.scrollHeight;
        chatbot.classList.add('expanded');
        debugLog("Window load: mensagens restauradas do localStorage.");
    }
    debugLog("Window load: mensagens não restauradas do localStorage.");
});

// Alterna placeholders do input
setInterval(() => {
    chatbotInput.classList.add('fade');
    setTimeout(() => {
        currentPlaceholder = (currentPlaceholder + 1) % placeholders.length;
        chatbotInput.placeholder = placeholders[currentPlaceholder];
        chatbotInput.classList.remove('fade');
    }, 500);
}, 3000);

// Eventos para abrir o chatWindow e ajustar a expansão do chatbot
chatbot.addEventListener('mouseenter', () => {
    chatbot.classList.add('expanded');
    chatbotInput.focus();
    debugLog("chatbot mouseenter - chatbot expandido e chatbotInput focado.");
    if (messagesContainer.innerHTML.trim() !== "") {
        chatWindow.style.display = 'flex';
        chatWindow.scrollTop = chatWindow.scrollHeight;
        debugLog("chatbot mouseenter - chatWindow exibido com conteúdo.");
    }
});

chatbot.addEventListener('mouseleave', () => {
    if (chatMode !== 'recording') {
        chatbot.classList.remove('expanded');
        debugLog("chatbot mouseleave: remoção de 'expanded' (mode não é recording).");
    }
});

// Botão X: fecha o chatWindow (mantém o conteúdo)
closeButton.addEventListener('click', () => {
    chatWindow.style.display = 'none';
    chatbot.classList.remove('expanded');
    debugLog("closeButton clicado: chatWindow fechado.");
});

// Botão Refresh: limpa a conversa e apaga os dados do localStorage
refreshButton.addEventListener('click', (e) => {
    e.stopPropagation();
    if (messagesContainer) {
        messagesContainer.innerHTML = "";
    }
    conversationId = null;
    localStorage.removeItem('conversationId');
    localStorage.removeItem('messages');
    localStorage.removeItem('sessionId');
    chatWindow.style.display = 'none';
    chatbot.classList.remove('expanded');
    sessionId = generateSessionId();
    localStorage.setItem('sessionId', sessionId);
    debugLog("refreshButton clicado: conversa limpa, novo sessionId gerado.");
});

// Função para exibir mensagens e enviar a pergunta
const sendMessage = async () => {
    if (chatbotInput.disabled) return;
    const question = chatbotInput.value;
    if (question.trim() === '') return;
    
    // Log da entrada do lead
    await log("Entrada do lead: " + question, LOG_SOURCE, "INFO");

    if (chatWindow.style.display === 'none') {
        chatWindow.style.display = 'flex';
        chatbot.classList.add('expanded');
        debugLog("sendMessage: chatWindow aberto devido a nova mensagem.");
    }
    
    const userMessage = document.createElement('div');
    userMessage.className = 'message';
    userMessage.innerHTML = `<div class="userMessage">${question}</div><div class="userIcon">${getUserIcon()}</div>`;
    messagesContainer.appendChild(userMessage);
    chatWindow.scrollTop = chatWindow.scrollHeight;
    updateLocalStorage();
    
    const botMessage = document.createElement('div');
    botMessage.className = 'message';
    botMessage.innerHTML = `<div class="botIcon"></div>
                            <div class="botMessage">
                                <h4 id="searching-ellipsis">Pensando<span>.</span><span>.</span><span>.</span></h4>
                            </div>`;
    messagesContainer.appendChild(botMessage);
    chatWindow.scrollTop = chatWindow.scrollHeight;
    updateLocalStorage();
    
    chatbotInput.value = '';
    chatbotInput.disabled = true;
    
    try {
        const requestId = await sendQuestion(question, sessionId);
        await log("Pergunta enviada. requestId: " + requestId, LOG_SOURCE, "INFO");
        
        const response = await getResponse(requestId);
        await log("Resposta da API de texto: " + response, LOG_SOURCE, "INFO");
        
        botMessage.innerHTML = `<div class="botIcon"></div><div class="botMessage"></div>`;
        typeWriter(botMessage.querySelector('.botMessage'), response, 0, () => {
            const botMessageContainer = botMessage.querySelector('.botMessage');
            let rawText = botMessageContainer.textContent;
            rawText = rawText.replace(/\n/g, '<br>');
            const converter = new showdown.Converter();
            const formattedHTML = converter.makeHtml(rawText);
            botMessageContainer.innerHTML = formattedHTML;
            updateLocalStorage();
            
            fetch(`https://iaturbo.com.br/wp-content/uploads/scripts/speech/get-audio.php?id=${requestId}`)
                .then(res => res.json())
                .then(async data => {
                    if (data.status === 'ok') {
                        await log("Resposta da API de áudio recebida: " + JSON.stringify(data), LOG_SOURCE, "INFO");
                        const audioWrapper = document.createElement('div');
                        audioWrapper.className = 'audio-player';
                        audioWrapper.innerHTML = `
                            <audio controls autoplay>
                              <source src="${data.audio_url}" type="audio/mpeg">
                              Seu navegador não suporta o áudio HTML5.
                            </audio>`;
                        botMessageContainer.appendChild(audioWrapper);
                        chatWindow.scrollTop = chatWindow.scrollHeight;
                        updateLocalStorage();
                    }
                })
                .catch(err => {
                    console.error('Erro ao obter áudio:', err);
                    log("Erro ao obter áudio: " + err, LOG_SOURCE, "ERROR");
                })
                .finally(() => {
                    chatbotInput.disabled = false;
                    chatbotInput.focus();
                });
        });
    } catch (error) {
        log("Erro ao processar a pergunta: " + error.message, LOG_SOURCE, "ERROR");
    } finally {
        chatbotInput.disabled = false;
    }
};

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
        await log("Pergunta enviada com sucesso. Dados: " + JSON.stringify(data), LOG_SOURCE, "INFO");
        conversationId = data.conversation_id || conversationId;
        updateLocalStorage();
        return data.id;
    } catch (error) {
        await log("Erro ao enviar pergunta: " + error.message, LOG_SOURCE, "ERROR");
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
                await log("Resposta recebida com sucesso: " + responseText, LOG_SOURCE, "INFO");
                updateLocalStorage();
                break;
            }
            await new Promise(resolve => setTimeout(resolve, 5000));
        }
        return responseText;
    } catch (error) {
        await log("Erro ao obter resposta: " + error.message, LOG_SOURCE, "ERROR");
        throw error;
    }
}

function typeWriter(element, text, i = 0, callback) {
    if (i < text.length) {
        element.innerHTML += text.charAt(i);
        chatWindow.scrollTop = chatWindow.scrollHeight;
        setTimeout(() => typeWriter(element, text, i + 1, callback), 5);
    } else {
        if (callback) callback();
    }
}

// Modo INITIAL: Remove a classe 'pulse' do sendButton para quitar o efeito
function setChatModeInitial() {
    chatMode = "initial";
    chatbotInput.style.display = 'block';
    transcribingMessage.style.display = 'none';
    recordingContainer.style.display = 'none';
    micOnButton.style.display = 'none';
    micOffButton.style.display = 'inline-block';
    sendButton.style.display = 'inline-block';
    
    sendButton.classList.remove('pulse');
    
    micOffButton.disabled = false;
    sendButton.disabled = false;
    chatbotInput.focus();
    debugLog("Modo INITIAL ativado");
}

// Modo RECORDING: Adiciona a classe 'pulse' ao sendButton para o efeito de pulso
function setChatModeRecording() {
    chatMode = "recording";
    chatbotInput.style.display = 'block';
    transcribingMessage.style.display = 'none';
    
    micOffButton.style.display = 'none';
    recordingContainer.style.display = 'flex';
    micOnButton.style.display = 'inline-block';
    sendButton.style.display = 'inline-block';
    
    sendButton.classList.add('pulse');
    
    cancelRecording = false;
    recordingStartTime = Date.now();
    recordingTimerInterval = setInterval(() => {
        const elapsed = Date.now() - recordingStartTime;
        const seconds = Math.floor(elapsed / 1000) % 60;
        const minutes = Math.floor(elapsed / 60000);
        recordingTimer.textContent = (minutes < 10 ? '0' + minutes : minutes) + ':' + (seconds < 10 ? '0' + seconds : seconds);
    }, 1000);
    
    navigator.mediaDevices.getUserMedia({ audio: true })
        .then(stream => {
            recordedChunks = [];
            mediaRecorder = new MediaRecorder(stream);
            mediaRecorder.ondataavailable = event => {
                if (event.data.size > 0) {
                    recordedChunks.push(event.data);
                }
            };
            mediaRecorder.onstop = () => {
                clearInterval(recordingTimerInterval);
                stream.getTracks().forEach(track => track.stop());
                if (cancelRecording) {
                    setChatModeInitial();
                    return;
                }
                const audioBlob = new Blob(recordedChunks, { type: 'audio/webm' });
                setChatModeTranscribing();
                sendRecording(audioBlob);
            };
            mediaRecorder.start();
            debugLog("Modo RECORDING ativado: gravação iniciada");
        })
        .catch(err => {
            console.error('Erro ao capturar áudio: ', err);
            setChatModeInitial();
        });
}

// Modo TRANSCRIBING
function setChatModeTranscribing() {
    chatMode = "transcribing";
    chatbotInput.style.display = 'none';
    transcribingMessage.style.display = 'block';
    transcribingMessage.innerHTML = `Transcrevendo<span id="ellipsis"></span>`;
    recordingContainer.style.display = 'none';
    micOnButton.style.display = 'inline-block';
    micOffButton.style.display = 'none';
    micOnButton.disabled = true;
    sendButton.disabled = true;
    
    setInterval(() => {
        const ellipsis = document.getElementById('ellipsis');
        if (ellipsis) {
            if (ellipsis.textContent.length < 3) {
                ellipsis.textContent += '.';
            } else {
                ellipsis.textContent = '';
            }
        }
    }, 500);
    debugLog("Modo TRANSCRIBING ativado");
}

function sendRecording(blob) {
    const formData = new FormData();
    formData.append('audio', blob, 'recording.webm');
    
    fetch('https://iaturbo.com.br/wp-content/uploads/scripts/speech/speechToText.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            alert('Erro na transcrição: ' + data.error);
            setChatModeInitial();
        } else if (data.text || data.transcription) {
            chatbotInput.value = data.text || data.transcription;
            setChatModeInitial();
            sendMessage();
        }
    })
    .catch(err => {
        alert('Erro na transcrição: ' + err);
        setChatModeInitial();
    });
}

// Eventos dos botões
micOffButton.addEventListener('click', () => {
    setChatModeRecording();
    debugLog("micOffButton clicado: setChatModeRecording");
});
sendButton.addEventListener('click', () => {
    if (chatMode === "recording" && mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    } else {
        sendMessage();
    }
});
trashButton.addEventListener('click', () => {
    cancelRecording = true;
    debugLog("trashButton clicado: cancelRecording = true");
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
        debugLog("trashButton clicado: gravação cancelada");

    } else {
        setChatModeInitial();
        debugLog("trashButton clicado: setChatModeInitial");
    }
});

// Envia a mensagem ao pressionar Enter
chatbotInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        sendMessage();
        debugLog("chatbotInput Enter pressionado: sendMessage");
    }
});

// Inicia no modo INITIAL
setChatModeInitial();
