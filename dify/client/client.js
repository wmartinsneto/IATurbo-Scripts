const chatbot = document.getElementById('chatbot');
const chatInput = document.getElementById('chatInput');
const chatWindow = document.getElementById('chatWindow');
const sendButton = document.getElementById('sendButton');
const closeButton = document.getElementById('closeButton');
const refreshButton = document.getElementById('refreshButton');

// NEW: Variables and elements for recording
const micButton = document.getElementById('micButton');
let mediaRecorder;
let recordedChunks = [];
let recordingStartTime;
let recordingTimerInterval;

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
            // Converte o markdown em HTML utilizando Showdown ao finalizar o efeito typewriter
            const botMessageContainer = botMessage.querySelector('.botMessage');
            let rawText = botMessageContainer.textContent;
            
            // Opcional: substitua quebras de linha por <br> se não forem interpretadas
            rawText = rawText.replace(/\n/g, '<br>');
            
            const converter = new showdown.Converter();
            const formattedHTML = converter.makeHtml(rawText);
            botMessageContainer.innerHTML = formattedHTML;

            // Agora, obtém o áudio e adiciona o player abaixo da mensagem
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
                        // Anexa o player dentro da mesma container, abaixo do texto
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
        setTimeout(() => typeWriter(element, text, i + 1, callback), 5); // 5ms delay for 3x speed
    } else {
        if (callback) callback();
    }
}

// Container that will replace the default layout during recording.
function showRecordingLayout() {
    // Hide input and micButton. We keep sendButton temporarily for both stop and later cancel.
    chatInput.style.display = 'none';
    micButton.style.display = 'none';

    // Create new recording container inside #chatbot
    const recordingContainer = document.createElement('div');
    recordingContainer.id = 'recordingContainer';
    recordingContainer.style.display = 'flex';
    recordingContainer.style.alignItems = 'center';
    recordingContainer.style.justifyContent = 'space-around';
    // Layout: Trash button, Recording indicator, Timer, Stop button

    recordingContainer.innerHTML = `
        <button id="trashButton" title="Cancelar" style="background: transparent; border: none; color: #ccc; font-size: 18px; cursor: pointer;">🗑️</button>
        <span id="recordingIndicator" style="font-size: 20px; color: red; animation: blink 1s infinite;">●</span>
        <span id="recordingTimer" style="font-family: monospace;">00:00</span>
        <button id="stopButton" title="Parar e enviar" style="background: transparent; border: none; color: #ccc; font-size: 18px; cursor: pointer;">⏹</button>
    `;
    chatbot.appendChild(recordingContainer);
    
    // Start a timer to update recordingTimer every second.
    recordingStartTime = Date.now();
    recordingTimerInterval = setInterval(() => {
        const elapsed = Date.now() - recordingStartTime;
        const seconds = Math.floor(elapsed / 1000) % 60;
        const minutes = Math.floor(elapsed / 60000);
        document.getElementById('recordingTimer').textContent =
            (minutes < 10 ? '0' + minutes : minutes) + ':' +
            (seconds < 10 ? '0' + seconds : seconds);
    }, 1000);
}

function resetChatbotLayout() {
    // Remove recording container if exists
    const recContainer = document.getElementById('recordingContainer');
    if (recContainer) {
        recContainer.parentNode.removeChild(recContainer);
    }
    clearInterval(recordingTimerInterval);
    // Restore input and mic button visibility
    chatInput.style.display = 'block';
    micButton.style.display = 'inline-block';
    chatInput.focus();
}

// Function to send recording (audio blob) to speechToText.php
function sendRecording(blob) {
    let formData = new FormData();
    formData.append('audio', blob, 'recording.webm'); // Adjust extension/type as needed
    
    // Change layout to "Transcrevendo..." with ellipsis animation.
    chatInput.style.display = 'none';
    const transcribingContainer = document.createElement('div');
    transcribingContainer.id = 'transcribingContainer';
    transcribingContainer.style.display = 'flex';
    transcribingContainer.style.alignItems = 'center';
    transcribingContainer.innerHTML = `<span id="transcribingText">Transcrevendo<span id="ellipsis"></span></span>
    <button id="cancelTranscription" title="Cancelar" style="background: transparent; border: none; color: #ccc; font-size: 18px; cursor: pointer;">✖</button>`;
    chatbot.appendChild(transcribingContainer);
    
    // Animate ellipsis text
    let ellipsisInterval = setInterval(() => {
        const ellipsis = document.getElementById('ellipsis');
        ellipsis.textContent = ellipsis.textContent.length < 3 ? ellipsis.textContent + '.' : '';
    }, 500);
    
    // Listen to cancel transcription
    document.getElementById('cancelTranscription').addEventListener('click', () => {
        clearInterval(ellipsisInterval);
        transcribingContainer.parentNode.removeChild(transcribingContainer);
        resetChatbotLayout();
    });
    
    // Send the recording to backend
    fetch('https://iaturbo.com.br/wp-content/uploads/scripts/speech/speechToText.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        clearInterval(ellipsisInterval);
        transcribingContainer.parentNode.removeChild(transcribingContainer);
        if (data.error) {
            alert('Erro na transcrição: ' + data.error);
            resetChatbotLayout();
        } else if (data.text || data.transcription) {
            // Insere a transcrição no input e simula o envio
            chatInput.value = data.text || data.transcription;
            resetChatbotLayout();
            sendMessage(); // dispara o fluxo de envio
        }
    })
    .catch(err => {
        clearInterval(ellipsisInterval);
        alert('Erro na transcrição: ' + err);
        resetChatbotLayout();
    });
}

// Set up the microphone recording functionality
micButton.addEventListener('click', () => {
    // Prepare layout for recording
    showRecordingLayout();
    
    // Start recording using MediaRecorder
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
                // Stop all tracks of the stream.
                stream.getTracks().forEach(track => track.stop());
                // Create a blob from the recorded data.
                const audioBlob = new Blob(recordedChunks, { type: 'audio/webm' });
                // Reset layout and send the recording.
                sendRecording(audioBlob);
            };
            mediaRecorder.start();
        })
        .catch(err => {
            console.error('Erro ao capturar áudio: ', err);
            resetChatbotLayout();
        });
});

// Handling the trash and stop buttons inside the recording layout.
document.addEventListener('click', function(e) {
    if(e.target && e.target.id === 'trashButton') {
        // Cancel recording, stop MediaRecorder and reset layout.
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
        resetChatbotLayout();
    }
    if(e.target && e.target.id === 'stopButton') {
        // User stops the recording and sends it.
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
    }
});
