// Constantes de configuração
const DEBUG_MODE = true;
const LOG_SOURCE = "LP_ChatbotsIATurbo";

// Recupera dados do localStorage (se existirem) ou gera novos
let sessionId = localStorage.getItem('sessionId') || generateSessionId();
localStorage.setItem('sessionId', sessionId);
let conversationId = localStorage.getItem('conversationId') || null;

// Controle de áudio - recupera a preferência do usuário ou define como ligado por padrão
let audioEnabled = localStorage.getItem('audioEnabled') !== 'false'; // true por padrão

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
const audioToggleButton = document.getElementById('audioToggleButton');
const sizeToggleButton = document.getElementById('sizeToggleButton');
const maximizeIcon = document.getElementById('maximizeIcon');
const minimizeIcon = document.getElementById('minimizeIcon');
const soundOnIcon = document.getElementById('soundOnIcon');
const soundOffIcon = document.getElementById('soundOffIcon');
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
    await fetch('http://172.16.20.237:8089/remote-log.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message, type, source })
    });
}

// Função para logs detalhados quando DEBUG_MODE está ativo
function debugLog(message) {
    if (DEBUG_MODE) {
        log("VERSION 1.0.1 - " + message, LOG_SOURCE, "DEBUG");
    }
}

// Atualiza localStorage
function updateLocalStorage() {
    localStorage.setItem('sessionId', sessionId);
    if (conversationId) {
        localStorage.setItem('conversationId', conversationId);
    }
    localStorage.setItem('messages', messagesContainer.innerHTML);
    localStorage.setItem('audioEnabled', audioEnabled.toString());
    debugLog("LocalStorage atualizado.");
}

// Função para atualizar a interface de acordo com o estado do áudio
function updateAudioToggleUI() {
    if (audioEnabled) {
        soundOnIcon.style.display = 'inline';
        soundOnIcon.style.fill = '#43d9ea'; // Cor ciano padrão
        soundOffIcon.style.display = 'none';
        audioToggleButton.setAttribute('data-tooltip', 'Desativar áudio');
    } else {
        soundOnIcon.style.display = 'none';
        soundOffIcon.style.display = 'inline';
        soundOffIcon.style.fill = '#ff4d4d'; // Cor vermelha para indicar desligado
        audioToggleButton.setAttribute('data-tooltip', 'Ativar áudio');
    }

    // Atualiza o conteúdo do tooltip se ele já foi inicializado
    if (tooltips[audioToggleButton.id]) {
        tooltips[audioToggleButton.id].setContent(audioToggleButton.getAttribute('data-tooltip'));
    }
}

// Função para controlar o áudio (mutar/desmutar existentes e configurar autoplay para novos)
function toggleAudio() {
    audioEnabled = !audioEnabled;

    // Atualiza todos os players de áudio existentes
    const audioPlayers = document.querySelectorAll('.audio-player audio');
    audioPlayers.forEach(audio => {
        if (!audioEnabled) {
            audio.pause(); // Pausa a reprodução
            audio.muted = true; // Muta o áudio
        } else {
            audio.pause(); // Pausa a reprodução
            audio.muted = false; // Desmuta o áudio
        }
    });

    // Atualiza a interface
    updateAudioToggleUI();

    // Salva a preferência
    updateLocalStorage();

    debugLog(`Áudio ${audioEnabled ? 'ativado' : 'desativado'}`);
}

// Objeto para manter referência aos tooltips
const tooltips = {};

/**
 * Inicializa os tooltips usando Tippy.js
 */
function initializeTooltips() {
    // Seleciona todos os elementos com data-tooltip
    const tooltipElements = document.querySelectorAll('[data-tooltip]');

    // Inicializa Tippy para cada elemento
    tooltipElements.forEach(element => {
        if (!tooltips[element.id]) {
            tooltips[element.id] = tippy(element, {
                content: element.getAttribute('data-tooltip'),
                arrow: true,
                placement: element.closest('#chatHeader') ? 'bottom' : 'top',
                theme: 'tron',
                duration: [300, 200], // [entrada, saída]
                trigger: 'mouseenter focus', // Mostra ao passar o mouse ou dar foco
                hideOnClick: false,
                appendTo: document.body,
                zIndex: 10000,
                animation: 'scale',
                delay: [300, 0], // Pequeno atraso antes de aparecer, sem atraso ao sair
                onHide(instance) {
                    // Reseta a flag de que este tooltip está com timer pra esconder
                    if (instance.reference._tippy_timer) {
                        clearTimeout(instance.reference._tippy_timer);
                        delete instance.reference._tippy_timer;
                    }
                }
            });
        }
    });
}

/**
 * Mostra o tooltip temporariamente e depois o esconde
 * @param {HTMLElement} element - O elemento que contém o tooltip
 * @param {number} duration - Duração em milissegundos que o tooltip ficará visível
 */
function showTemporaryTooltip(element, duration = 2000) {
    if (!element || !element.hasAttribute('data-tooltip')) return;

    // Garante que os tooltips foram inicializados
    if (!tooltips[element.id]) {
        initializeTooltips();
    }

    // Mostra o tooltip
    if (tooltips[element.id]) {
        tooltips[element.id].show();

        // Esconde o tooltip após o tempo especificado
        setTimeout(() => {
            if (tooltips[element.id]) {
                tooltips[element.id].hide();
            }
        }, duration);
    }
}

// Event listener para o botão de toggle de áudio
audioToggleButton.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleAudio();
    showTemporaryTooltip(audioToggleButton, 1500);
});

// Restaura mensagens armazenadas e exibe o chatWindow
window.addEventListener('load', () => {
    const storedMessages = localStorage.getItem('messages');
    if (storedMessages && storedMessages.trim() !== "") {
        messagesContainer.innerHTML = storedMessages;
        chatWindow.style.display = 'flex';
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        chatbot.classList.add('expanded');
        debugLog("Window load: mensagens restauradas do localStorage.");
    } else {
        debugLog("Window load: mensagens não restauradas do localStorage.");
    }

    // Inicializa a interface de áudio
    updateAudioToggleUI();

    // Inicializa os tooltips
    initializeTooltips();
    debugLog("Window load: tooltips inicializados.");
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
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
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
    showTemporaryTooltip(closeButton, 1500);
    debugLog("closeButton clicado: chatWindow fechado.");
});

// Botão Refresh: limpa a conversa e apaga os dados do localStorage
refreshButton.addEventListener('click', (e) => {
    e.stopPropagation();
    showTemporaryTooltip(refreshButton, 1500);
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

    chatWindow.style.display = 'flex';
    chatbot.classList.add('expanded');

    debugLog("sendMessage: chatWindow aberto devido a nova mensagem.");

    const userMessage = document.createElement('div');
    userMessage.className = 'message';
    userMessage.innerHTML = `<div class="userMessage">${question}</div><div class="userIcon">${getUserIcon()}</div>`;
    messagesContainer.appendChild(userMessage);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
    updateLocalStorage();

    const botMessage = document.createElement('div');
    botMessage.className = 'message';
    botMessage.innerHTML = `<div class="botIcon"></div>
                            <div class="botMessage">
                                <h4 id="searching-ellipsis" style="color: #a0a0a0;">Pensando<span>.</span><span>.</span><span>.</span></h4>
                            </div>`;
    messagesContainer.appendChild(botMessage);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
    updateLocalStorage();

    chatbotInput.focus();
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
            const converter = new showdown.Converter({
                simpleLineBreaks: true,
                simplifiedAutoLink: true,
                openLinksInNewWindow: true,
                emoji: true,
                tables: true,
                tasklists: true,
                underline: true,
                metadata: true
              });
            const formattedHTML = converter.makeHtml(rawText);
            botMessageContainer.innerHTML = formattedHTML;
            updateLocalStorage();

            fetch(`http://172.16.20.237:8089/speech/get-audio.php?id=${requestId}`)
                .then(res => res.json())
                .then(async data => {
                    if (data.status === 'ok') {
                        await log("Resposta da API de áudio recebida: " + JSON.stringify(data), LOG_SOURCE, "INFO");
                        const audioWrapper = document.createElement('div');
                        audioWrapper.className = 'audio-player';
                        audioWrapper.innerHTML = `
                            <audio controls ${audioEnabled ? 'autoplay' : ''} ${audioEnabled ? '' : 'muted'}>
                              <source src="${data.audio_url}" type="audio/mpeg">
                              Seu navegador não suporta o áudio HTML5.
                            </audio>`;
                        // Adiciona o audio-player diretamente ao message (container pai), não ao botMessage
                        botMessage.appendChild(audioWrapper);
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                        updateLocalStorage();
                    }
                })
                .catch(err => {
                    console.error('Erro ao obter áudio:', err);
                    log("Erro ao obter áudio: " + err, LOG_SOURCE, "ERROR");
                })
                .finally(() => {
                    chatbotInput.disabled = false;
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
        const response = await fetch('http://172.16.20.237:8089/dify/request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                question,
                source: 'Chatbots IATurbo - www.iaturbo.com.br',
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
        await fetch('http://172.16.20.237:8089/dify/run.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: requestId,
                chatflow_url: 'http://host.docker.internal:8082/v1',
                chatflow_key: 'app-jHgWk5y5rooraLNm3N4bD7FP' //IARA CB LandingPage
            })
        });
        let responseText = 'Aguarde...';
        for (let i = 0; i < 10; i++) {
            const response = await fetch(`http://172.16.20.237:8089/dify/response.php?id=${requestId}`);
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
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        setTimeout(() => typeWriter(element, text, i + 1, callback), 5);
    } else {
        if (callback) callback();
    }
}

// Modo INITIAL: Exibe o input com placeholder cinza escuro.
function setChatModeInitial() {
    chatMode = "initial";
    chatbotInput.style.display = 'block';
    chatbotInput.disabled = false; // Garante que o input esteja habilitado no modo inicial
    placeholders = ["Precisa de ajuda?", "Pergunte para a IARA"];
    transcribingMessage.style.display = 'none';
    recordingContainer.style.display = 'none';
    micOnButton.style.display = 'none';
    micOffButton.style.display = 'inline-flex';
    sendButton.style.display = 'inline-flex';

    // Remove as classes visuais do modo de gravação
    chatbot.classList.remove('recording-mode');
    chatbotInput.classList.remove('recording');

    sendButton.classList.remove('pulse');

    micOffButton.disabled = false;
    sendButton.disabled = false;
    updatePlaceholderStyle('initial');  // Atualiza o placeholder para o modo inicial
    debugLog("Modo INITIAL ativado");
}

// Modo RECORDING: Exibe o placeholder com efeito de piscar vermelho.
function setChatModeRecording() {
    chatMode = "recording";
    chatbotInput.style.display = 'block';
    chatbotInput.disabled = true; // Desabilita o input durante a gravação
    transcribingMessage.style.display = 'none';
    placeholders = ["Gravando...", "Estou te ouvindo..."];
    updatePlaceholderStyle('recording');  // Atualiza o placeholder para o modo recording

    // Adiciona as classes para os novos estilos visuais de gravação
    chatbot.classList.add('recording-mode');
    chatbotInput.classList.add('recording');

    micOffButton.style.display = 'none';
    recordingContainer.style.display = 'flex';
    micOnButton.style.display = 'inline-flex';
    sendButton.style.display = 'inline-flex';

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
    chatbotInput.disabled = true; // Desabilita o input durante a transcrição
    transcribingMessage.style.display = 'block';
    transcribingMessage.innerHTML = `Transcrevendo<span id="ellipsis"></span>`;
    recordingContainer.style.display = 'none';
    micOnButton.style.display = 'inline-flex';
    micOffButton.style.display = 'none';
    micOnButton.disabled = true;
    sendButton.disabled = true;

    // Remove as classes visuais do modo de gravação
    chatbot.classList.remove('recording-mode');
    chatbotInput.classList.remove('recording');

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

    fetch('http://172.16.20.237:8089/speech/speechToText.php', {
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
    showTemporaryTooltip(micOffButton, 1500);
    debugLog("micOffButton clicado: setChatModeRecording");
});
sendButton.addEventListener('click', () => {
    showTemporaryTooltip(sendButton, 1500);
    if (chatMode === "recording" && mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    } else {
        sendMessage();
    }
});
trashButton.addEventListener('click', () => {
    cancelRecording = true;
    showTemporaryTooltip(trashButton, 1500);
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

// Função para alternar entre os modos normal e maximizado
function toggleSize() {
    const isMaximized = chatWindow.classList.contains('maximized');

    if (isMaximized) {
        // Mudar para o modo normal
        chatWindow.classList.remove('maximized');
        chatbot.classList.remove('maximized');

        // Atualizar ícones
        maximizeIcon.style.display = 'inline';
        minimizeIcon.style.display = 'none';

        // Atualizar tooltip
        sizeToggleButton.setAttribute('data-tooltip', 'Maximizar');
        if (tooltips[sizeToggleButton.id]) {
            tooltips[sizeToggleButton.id].setContent('Maximizar');
        }

        debugLog("Janela restaurada para o tamanho normal");
    } else {
        // Mudar para o modo maximizado
        chatWindow.classList.add('maximized');
        chatbot.classList.add('maximized');

        // Atualizar ícones
        maximizeIcon.style.display = 'none';
        minimizeIcon.style.display = 'inline';

        // Atualizar tooltip
        sizeToggleButton.setAttribute('data-tooltip', 'Restaurar');
        if (tooltips[sizeToggleButton.id]) {
            tooltips[sizeToggleButton.id].setContent('Restaurar');
        }

        debugLog("Janela maximizada");
    }

    // Garantir que a rolagem mostre o conteúdo mais recente
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

// Event listener para o botão de alternar tamanho
sizeToggleButton.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleSize();
    showTemporaryTooltip(sizeToggleButton, 1500);
});

// Inicia no modo INITIAL
setChatModeInitial();

/**
 * updatePlaceholderStyle(mode)
 * Injeta ou atualiza uma regra de estilo para o placeholder do chatbotInput e
 * altera imediatamente seu valor.
 * @param {string} mode - 'initial' para placeholder cinza escuro ou 'recording' para placeholder vermelho piscando.
 */
function updatePlaceholderStyle(mode) {
    let styleEl = document.getElementById('chatbotInputPlaceholderStyle');
    if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = 'chatbotInputPlaceholderStyle';
        document.head.appendChild(styleEl);
    }
    if (mode === 'initial') {
        styleEl.innerHTML = `
            #chatbotInput::placeholder { color: #999; }
            #chatbotInput::-webkit-input-placeholder { color: #999; }
            #chatbotInput:-ms-input-placeholder { color: #999; }
        `;
        // Atualiza imediatamente o placeholder para o modo inicial.
        chatbotInput.placeholder = "Precisa de ajuda?";
    } else if (mode === 'recording') {
        styleEl.innerHTML = `
            @keyframes blink {
                0% { opacity: 1; }
                50% { opacity: 0; }
                100% { opacity: 1; }
            }
            #chatbotInput::placeholder {
                color: red;
                animation: blink 1s infinite;
            }
            #chatbotInput::-webkit-input-placeholder {
                color: red;
                animation: blink 1s infinite;
            }
            #chatbotInput:-ms-input-placeholder {
                color: red;
                animation: blink 1s infinite;
            }
        `;
        // Atualiza imediatamente o placeholder para o modo recording.
        chatbotInput.placeholder = "Gravando...";
    }
}
