const chatbot = document.getElementById('chatbot');
const chatInput = document.getElementById('chatInput');
const chatWindow = document.getElementById('chatWindow');
const sendButton = document.getElementById('sendButton');
const closeButton = document.getElementById('closeButton');
const refreshButton = document.getElementById('refreshButton');
const micButton = document.getElementById('micButton');

let mediaRecorder;
let recordedChunks = [];
let recordingStartTime;
let recordingTimerInterval;
let chatMode = "initial"; // "initial", "recording", "transcribing"
let cancelRecording = false; // Flag para cancelamento da gravação

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

// Reabre a janela do chat quando o input recebe foco, se estava fechado.
chatInput.addEventListener('focus', () => {
    if (chatWindow.style.display === 'none') {
        chatWindow.style.display = 'flex';
    }
});

chatbot.addEventListener('mouseleave', () => {
    chatbot.classList.remove('expanded');
});

const sendMessage = async () => {
    if (chatInput.disabled) return;

    const question = chatInput.value;
    if (question.trim() === '') return;

    // Exibe a mensagem do usuário
    const userMessage = document.createElement('div');
    userMessage.className = 'message';
    userMessage.innerHTML = `<div class="userMessage">${question}</div><div class="userIcon">${getUserIcon()}</div>`;
    chatWindow.appendChild(userMessage);
    chatWindow.style.display = 'flex';
    chatWindow.scrollTop = chatWindow.scrollHeight;

    // Exibe animação de "Pensando..."
    const botMessage = document.createElement('div');
    botMessage.className = 'message';
    botMessage.innerHTML = `<div class="botIcon"></div><div class="botMessage"><h4 id="searching-ellipsis">Pensando<span>.</span><span>.</span><span>.</span></h4></div>`;
    chatWindow.appendChild(botMessage);
    chatWindow.scrollTop = chatWindow.scrollHeight;

    chatInput.value = '';
    chatInput.disabled = true;

    const sessionId = localStorage.getItem('sessionId') || generateSessionId();
    localStorage.setItem('sessionId', sessionId);

    try {
        const requestId = await sendQuestion(question, sessionId);
        const response = await getResponse(requestId);

        botMessage.innerHTML = `<div class="botIcon"></div><div class="botMessage"></div>`;
        typeWriter(botMessage.querySelector('.botMessage'), response, 0, () => {
            const botMessageContainer = botMessage.querySelector('.botMessage');
            let rawText = botMessageContainer.textContent;
            rawText = rawText.replace(/\n/g, '<br>');
            const converter = new showdown.Converter();
            const formattedHTML = converter.makeHtml(rawText);
            botMessageContainer.innerHTML = formattedHTML;

            fetch(`https://iaturbo.com.br/wp-content/uploads/scripts/speech/get-audio.php?id=${requestId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'ok') {
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

sendButton.addEventListener('click', () => {
    if (chatMode === "recording") {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
    } else {
        sendMessage();
    }
});

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

function typeWriter(element, text, i = 0, callback) {
    if (i < text.length) {
        element.innerHTML += text.charAt(i);
        chatWindow.scrollTop = chatWindow.scrollHeight;
        setTimeout(() => typeWriter(element, text, i + 1, callback), 5);
    } else {
        if (callback) callback();
    }
}

function setChatModeInitial() {
    chatMode = "initial";
    // Remove any temporary containers (recording or transcribing)
    const recContainer = document.getElementById('recordingContainer');
    if (recContainer) recContainer.remove();
    const transcribingContainer = document.getElementById('transcribingContainer');
    if (transcribingContainer) transcribingContainer.remove();
    clearInterval(recordingTimerInterval);
    
    // Ensure chatbot contains the initial elements in proper order: chatInput, micButton, sendButton.
    while(chatbot.firstChild) {
        chatbot.removeChild(chatbot.firstChild);
    }
    chatbot.appendChild(chatInput);
    chatbot.appendChild(micButton);
    chatbot.appendChild(sendButton);
    
    // Restore styles for initial mode.
    chatInput.style.display = 'block';
    micButton.style.display = 'inline-block';
    sendButton.style.display = 'inline-block';
    
    // Restore micButton to its normal (blue) state.
    micButton.style.width = 'auto';
    micButton.style.height = '30px';
    micButton.style.backgroundColor = "";
    micButton.style.border = "none";
    micButton.innerHTML = "";
    const img = document.createElement("img");
    img.src = "https://iaturbo.com.br/wp-content/uploads/2025/02/Mic-Azul.png";
    img.alt = "Microfone Azul";
    img.style.height = "30px";
    img.style.width = "21px";
    img.style.display = "block";
    micButton.appendChild(img);
    
    micButton.disabled = false;
    sendButton.disabled = false;
    micButton.style.opacity = 1;
    sendButton.style.opacity = 1;
    
    chatInput.focus();
}

// Modo Gravando: Atualizado para exibir a ordem: trashButton, timer, micButton (vermelho) e sendButton.
function setChatModeRecording() {
    chatMode = "recording";
    //chatInput.style.display = 'none';
    // Esconde o micButton do modo inicial
    micButton.style.display = 'none';
    
    cancelRecording = false;
    
    const recordingContainer = document.createElement('div');
    recordingContainer.id = 'recordingContainer';
    recordingContainer.style.display = 'flex';
    recordingContainer.style.alignItems = 'center';
    recordingContainer.style.justifyContent = 'space-around';
    // Cria container com trashButton e timer
    recordingContainer.innerHTML = `
        <button id="trashButton" title="Cancelar Gravação">
            <img src="https://iaturbo.com.br/wp-content/uploads/2025/02/Lixo2.png" style="width:auto; height:30px; animation: pulse-animation 2s infinite;">
        </button>
        <span id="recordingTimer" style="font-family: monospace;">00:00</span>
    `;
    // Atualiza o micButton para o estado de gravação (vermelho) e o exibe
    micButton.style.display = 'inline-block';
    micButton.innerHTML = '<img src="https://iaturbo.com.br/wp-content/uploads/2025/02/Mic-Vermelho.png" style="width:auto; height:30px; animation: pulse-animation 2s infinite;">';
    // Remove sendButton de seu container atual (se houver) e depois anexa micButton e sendButton, nesta ordem.
    if(sendButton.parentElement) sendButton.parentElement.removeChild(sendButton);
    recordingContainer.appendChild(micButton);
    recordingContainer.appendChild(sendButton);
    
    chatbot.appendChild(recordingContainer);
    
    const trashButton = recordingContainer.querySelector('#trashButton');
    if (trashButton) {
        trashButton.addEventListener('click', () => {
            cancelRecording = true;
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
            } else {
                setChatModeInitial();
            }
        });
    }
    
    recordingStartTime = Date.now();
    recordingTimerInterval = setInterval(() => {
        const elapsed = Date.now() - recordingStartTime;
        const seconds = Math.floor(elapsed / 1000) % 60;
        const minutes = Math.floor(elapsed / 60000);
        document.getElementById('recordingTimer').textContent =
            (minutes < 10 ? '0' + minutes : minutes) + ':' + (seconds < 10 ? '0' + seconds : seconds);
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
        })
        .catch(err => {
            console.error('Erro ao capturar áudio: ', err);
            setChatModeInitial();
        });
}

// Modo Transcrevendo: Atualizado para exibir "Transcrevendo..." fixo à esquerda, seguido por micButton e sendButton à direita.
function setChatModeTranscribing() {
    chatMode = "transcribing";
    const recContainer = document.getElementById('recordingContainer');
    if (recContainer) {
        recContainer.remove();
        clearInterval(recordingTimerInterval);
    }

    chatInput.style.display = 'none';
    
    const transcribingContainer = document.createElement('div');
    transcribingContainer.id = 'transcribingContainer';
    transcribingContainer.style.display = 'flex';
    transcribingContainer.style.alignItems = 'center';
    transcribingContainer.style.marginRight = 'auto !important';
    // Cria área fixa à esquerda para o texto "Transcrevendo..."
    transcribingContainer.innerHTML = `<div id="transcribingLeft" style="text-align: left; width=100%;">Transcrevendo<span id="ellipsis"></span></div>`;
    // Reanexa micButton e sendButton à direita
    transcribingContainer.appendChild(micButton);
    transcribingContainer.appendChild(sendButton);
    chatbot.appendChild(transcribingContainer);
    micButton.disabled = true;
    sendButton.disabled = true;
    micButton.style.opacity = 0.5;
    sendButton.style.opacity = 0.5;
    
    setInterval(() => {
        const ellipsis = document.getElementById('ellipsis');
        if(ellipsis) {
            ellipsis.textContent = ellipsis.textContent.length < 3 ? ellipsis.textContent + '.' : '';
        }
    }, 500);
}

function sendRecording(blob) {
    let formData = new FormData();
    formData.append('audio', blob, 'recording.webm');
    
    fetch('https://iaturbo.com.br/wp-content/uploads/scripts/speech/speechToText.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        const transcribingContainer = document.getElementById('transcribingContainer');
        if(transcribingContainer) transcribingContainer.remove();
        if (data.error) {
            alert('Erro na transcrição: ' + data.error);
            setChatModeInitial();
        } else if (data.text || data.transcription) {
            chatInput.value = data.text || data.transcription;
            setChatModeInitial();
            sendMessage();
        }
    })
    .catch(err => {
        const transcribingContainer = document.getElementById('transcribingContainer');
        if(transcribingContainer) transcribingContainer.remove();
        alert('Erro na transcrição: ' + err);
        setChatModeInitial();
    });
}

// Evento para iniciar gravação ao clicar no micButton (modo inicial)
micButton.addEventListener('click', () => {
    setChatModeRecording();
});
setChatModeInitial();
