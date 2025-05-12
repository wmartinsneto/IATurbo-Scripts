<?php
/**
 * config.php
 * 
 * Arquivo central de configuração para o chatbot IATurbo.
 * Define todas as configurações do sistema, com suporte a variáveis de ambiente
 * para facilitar a configuração em diferentes ambientes.
 */

// Armazena as configurações carregadas
$_CONFIG = [];

/**
 * Função para recuperar valores de configuração
 * 
 * @param string $key Chave da configuração
 * @param mixed $default Valor padrão se a configuração não existir
 * @return mixed Valor da configuração ou o valor padrão
 */
function config($key, $default = null) {
    global $_CONFIG;
    
    // Se a configuração já foi carregada, retorna o valor
    if (isset($_CONFIG[$key])) {
        return $_CONFIG[$key];
    }
    
    // Converte a chave para formato de variável de ambiente (maiúsculas com underscores)
    $env_key = strtoupper(str_replace('.', '_', $key));
    
    // Tenta obter da variável de ambiente
    $value = getenv($env_key);
    
    // Se não encontrou na variável de ambiente, usa o valor padrão
    if ($value === false) {
        return $default;
    }
    
    // Converte valores booleanos e numéricos
    if ($value === 'true') $value = true;
    if ($value === 'false') $value = false;
    if (is_numeric($value) && !preg_match('/^0\d+$/', $value)) {
        $value = strpos($value, '.') !== false ? (float) $value : (int) $value;
    }
    
    // Armazena a configuração para uso futuro
    $_CONFIG[$key] = $value;
    
    return $value;
}

/**
 * Carrega configurações predefinidas
 */
function loadDefaultConfigurations() {
    global $_CONFIG;
    
    // URLs e caminhos base
    $_CONFIG['base_url'] = getenv('BASE_URL') ?: 'https://iaturbo.com.br/wp-content/uploads/scripts';
    
    // Configurações do Dify
    $_CONFIG['dify_api_url'] = getenv('DIFY_API_URL') ?: 'http://api:5001/v1';
    $_CONFIG['dify_api_key'] = getenv('DIFY_API_KEY') ?: 'app-jHgWk5y5rooraLNm3N4bD7FP';
    
    // Chaves de API e credenciais
    $_CONFIG['openai_api_key'] = getenv('OPENAI_API_KEY') ?: '';
    $_CONFIG['trello_api_key'] = getenv('TRELLO_API_KEY') ?: '';
    $_CONFIG['trello_api_token'] = getenv('TRELLO_API_TOKEN') ?: '';
    $_CONFIG['trello_board_id'] = getenv('TRELLO_BOARD_ID') ?: '';
    $_CONFIG['trello_list_id'] = getenv('TRELLO_LIST_ID') ?: '';
    $_CONFIG['slack_webhook_url'] = getenv('SLACK_WEBHOOK_URL') ?: '';
    
    // Configurações do serviço de fala
    $_CONFIG['speech_model'] = getenv('SPEECH_MODEL') ?: 'tts-1';
    $_CONFIG['speech_voice'] = getenv('SPEECH_VOICE') ?: 'shimmer';
    
    // Configurações de diretórios
    $_CONFIG['output_dir'] = getenv('OUTPUT_DIR') ?: './speech/output/';
    $_CONFIG['logs_dir'] = getenv('LOGS_DIR') ?: './logs/';
    $_CONFIG['pending_dir'] = getenv('PENDING_DIR') ?: './dify/pending/';
    $_CONFIG['completed_dir'] = getenv('COMPLETED_DIR') ?: './dify/completed/';
    
    // Flags para habilitar/desabilitar funcionalidades
    $_CONFIG['enable_trello'] = getenv('ENABLE_TRELLO') !== false ? (getenv('ENABLE_TRELLO') === 'false' ? false : true) : true;
    $_CONFIG['enable_slack'] = getenv('ENABLE_SLACK') !== false ? (getenv('ENABLE_SLACK') === 'false' ? false : true) : true;
    $_CONFIG['enable_audio'] = getenv('ENABLE_AUDIO') !== false ? (getenv('ENABLE_AUDIO') === 'false' ? false : true) : true;
    
    // Configurações de estilo
    $_CONFIG['css_theme'] = getenv('CSS_THEME') ?: 'default.css';
}

// Carregar configurações padrão
loadDefaultConfigurations();

// Carregar configurações de um arquivo .env, se existir (opcional)
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!empty($name)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
