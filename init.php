<?php
/**
 * Script de inicialização para o chatbot IATurbo
 * 
 * Este script é executado quando o container é iniciado. Ele verifica os requisitos
 * do sistema, cria as pastas necessárias e prepara o ambiente.
 */

// Carregar configurações
require_once __DIR__ . '/config.php';

// Função para registrar logs durante a inicialização
function init_log($message, $level = 'info') {
    $log_dir = config('logs_dir', './logs/');
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    $log_file = $log_dir . 'init-' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "$timestamp [$level] - $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Cabeçalho do script
echo "IATurbo Chatbot - Script de Inicialização\n";
echo "=======================================\n\n";
init_log("Iniciando script de inicialização");

// 1. Verificar extensões do PHP necessárias
echo "Verificando extensões do PHP...\n";
$required_extensions = ['curl', 'json', 'mbstring'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}

if (count($missing_extensions) > 0) {
    $message = "Extensões requeridas não instaladas: " . implode(', ', $missing_extensions);
    echo "ERRO: $message\n";
    init_log($message, 'error');
    exit(1);
} else {
    echo "✓ Todas as extensões PHP requeridas estão disponíveis\n";
    init_log("Todas as extensões PHP requeridas estão disponíveis");
}

// 2. Criar diretórios necessários
echo "\nCriando diretórios necessários...\n";
$directories = [
    config('logs_dir', './logs/'),
    config('output_dir', './speech/output/'),
    './speech/input/',
    config('pending_dir', './dify/pending/'),
    config('completed_dir', './dify/completed/'),
    './public/',
    './public/images/'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0777, true)) {
            echo "✓ Diretório criado: $dir\n";
            init_log("Diretório criado: $dir");
        } else {
            echo "⚠ Não foi possível criar o diretório: $dir\n";
            init_log("Não foi possível criar o diretório: $dir", 'warning');
        }
    } else {
        echo "✓ Diretório já existe: $dir\n";
        init_log("Diretório já existe: $dir");
    }
}

// 3. Verificar permissões de escrita
echo "\nVerificando permissões de escrita...\n";
$writable = true;

foreach ($directories as $dir) {
    if (!is_writable($dir)) {
        echo "⚠ O diretório $dir não possui permissão de escrita\n";
        init_log("O diretório $dir não possui permissão de escrita", 'warning');
        $writable = false;
    }
}

if ($writable) {
    echo "✓ Todos os diretórios possuem permissão de escrita\n";
    init_log("Todos os diretórios possuem permissão de escrita");
} else {
    echo "⚠ Alguns diretórios não possuem permissão de escrita. Ajuste as permissões manualmente.\n";
    init_log("Alguns diretórios não possuem permissão de escrita", 'warning');
}

// 4. Verificar configurações de API
echo "\nVerificando configurações de API...\n";

// Verificar chave da OpenAI
if (empty(config('openai_api_key'))) {
    echo "⚠ Chave da API da OpenAI não configurada. Funcionalidades de áudio estarão limitadas.\n";
    init_log("Chave da API da OpenAI não configurada", 'warning');
} else {
    echo "✓ Chave da API da OpenAI configurada\n";
    init_log("Chave da API da OpenAI configurada");
}

// Verificar integrações
if (config('enable_trello', true)) {
    if (empty(config('trello_api_key')) || empty(config('trello_api_token'))) {
        echo "⚠ Integração com Trello está habilitada, mas as credenciais não estão configuradas\n";
        init_log("Credenciais do Trello não configuradas", 'warning');
    } else {
        echo "✓ Integração com Trello configurada\n";
        init_log("Integração com Trello configurada");
    }
} else {
    echo "ℹ Integração com Trello desabilitada\n";
    init_log("Integração com Trello desabilitada");
}

if (config('enable_slack', true)) {
    if (empty(config('slack_webhook_url'))) {
        echo "⚠ Integração com Slack está habilitada, mas a URL do webhook não está configurada\n";
        init_log("URL do webhook do Slack não configurada", 'warning');
    } else {
        echo "✓ Integração com Slack configurada\n";
        init_log("Integração com Slack configurada");
    }
} else {
    echo "ℹ Integração com Slack desabilitada\n";
    init_log("Integração com Slack desabilitada");
}

// 5. Criar arquivo de verificação de funcionamento
$health_file = './public/health.php';
$health_content = <<<'EOT'
<?php
// Arquivo de health check
$status = [
    'status' => 'ok',
    'timestamp' => time(),
    'version' => '1.0.0',
    'environment' => getenv('APP_ENV') ?: 'production'
];
header('Content-Type: application/json');
echo json_encode($status);
EOT;

file_put_contents($health_file, $health_content);
echo "\n✓ Criado arquivo de health check: $health_file\n";
init_log("Criado arquivo de health check: $health_file");

// 6. Verificar URL da API do Dify
if (empty(config('dify_api_url')) || empty(config('dify_api_key'))) {
    echo "\n⚠ Configurações da API do Dify não estão completas. O chatbot pode não funcionar corretamente.\n";
    init_log("Configurações da API do Dify não estão completas", 'warning');
} else {
    echo "\n✓ Configurações da API do Dify verificadas\n";
    init_log("Configurações da API do Dify verificadas");
}

// Mensagem de finalização
echo "\nInicialização concluída!\n";
echo "O chatbot está pronto para uso em: " . config('base_url', 'http://localhost:8080') . "\n";
init_log("Inicialização concluída com sucesso");
