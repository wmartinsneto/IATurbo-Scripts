<?php
/**
 * API para consulta de IPTU por código do imóvel
 * 
 * Este endpoint recebe o código do imóvel e retorna os dados do boleto de IPTU
 * correspondente, incluindo o link para download.
 */

// Configurações de cabeçalho para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Função para carregar os dados do JSON
function carregarDados() {
    $jsonFile = __DIR__ . '/dados.json';
    if (!file_exists($jsonFile)) {
        return false;
    }
    
    $jsonContent = file_get_contents($jsonFile);
    return json_decode($jsonContent, true);
}

// Função para buscar imóvel por código
function buscarImovelPorCodigo($codigo, $dados) {
    // Verificar nos imóveis residenciais
    foreach ($dados['imoveis']['residenciais'] as $imovel) {
        if ($imovel['codigo'] == $codigo) {
            return [
                'encontrado' => true,
                'imovel' => $imovel,
                'tipo' => 'residencial'
            ];
        }
    }
    
    // Verificar nos imóveis comerciais
    foreach ($dados['imoveis']['comerciais'] as $imovel) {
        if ($imovel['codigo'] == $codigo) {
            return [
                'encontrado' => true,
                'imovel' => $imovel,
                'tipo' => 'comercial'
            ];
        }
    }
    
    return ['encontrado' => false];
}

// Função para buscar boleto por código do imóvel
function buscarBoletoPorCodigoImovel($codigo, $dados) {
    // Verificar nos boletos residenciais
    foreach ($dados['boletos']['residenciais'] as $boleto) {
        if ($boleto['codigo_imovel'] == $codigo) {
            return [
                'encontrado' => true,
                'boleto' => $boleto
            ];
        }
    }
    
    // Verificar nos boletos comerciais
    foreach ($dados['boletos']['comerciais'] as $boleto) {
        if ($boleto['codigo_imovel'] == $codigo) {
            return [
                'encontrado' => true,
                'boleto' => $boleto
            ];
        }
    }
    
    return ['encontrado' => false];
}

// Função para buscar proprietário por código do imóvel
function buscarProprietarioPorCodigoImovel($codigo, $dados) {
    // Verificar nos proprietários CPF
    foreach ($dados['proprietarios']['cpf'] as $proprietario) {
        if (in_array($codigo, $proprietario['imoveis'])) {
            return [
                'encontrado' => true,
                'proprietario' => $proprietario,
                'tipo' => 'cpf'
            ];
        }
    }
    
    // Verificar nos proprietários CNPJ
    foreach ($dados['proprietarios']['cnpj'] as $proprietario) {
        if (in_array($codigo, $proprietario['imoveis'])) {
            return [
                'encontrado' => true,
                'proprietario' => $proprietario,
                'tipo' => 'cnpj'
            ];
        }
    }
    
    return ['encontrado' => false];
}

// Verificar se o método da requisição é GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

// Verificar se o código do imóvel foi fornecido
if (!isset($_GET['codigo'])) {
    http_response_code(400);
    echo json_encode(['erro' => 'Código do imóvel não fornecido']);
    exit;
}

$codigo = intval($_GET['codigo']);

// Carregar os dados
$dados = carregarDados();
if (!$dados) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao carregar dados']);
    exit;
}

// Buscar o imóvel
$resultadoImovel = buscarImovelPorCodigo($codigo, $dados);
if (!$resultadoImovel['encontrado']) {
    http_response_code(404);
    echo json_encode(['erro' => 'Imóvel não encontrado']);
    exit;
}

// Buscar o boleto
$resultadoBoleto = buscarBoletoPorCodigoImovel($codigo, $dados);
if (!$resultadoBoleto['encontrado']) {
    http_response_code(404);
    echo json_encode(['erro' => 'Boleto não encontrado para este imóvel']);
    exit;
}

// Buscar o proprietário
$resultadoProprietario = buscarProprietarioPorCodigoImovel($codigo, $dados);
if (!$resultadoProprietario['encontrado']) {
    http_response_code(404);
    echo json_encode(['erro' => 'Proprietário não encontrado para este imóvel']);
    exit;
}

// Montar a resposta
$resposta = [
    'imovel' => $resultadoImovel['imovel'],
    'boleto' => $resultadoBoleto['boleto'],
    'proprietario' => [
        'nome' => $resultadoProprietario['proprietario']['nome'],
        'id' => $resultadoProprietario['proprietario']['id'],
        'tipo' => $resultadoProprietario['tipo']
    ]
];

// Retornar a resposta
echo json_encode($resposta, JSON_PRETTY_PRINT);
