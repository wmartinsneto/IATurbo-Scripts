<?php
/**
 * API para consulta de IPTU por CPF/CNPJ do proprietário
 * 
 * Este endpoint recebe o CPF ou CNPJ do proprietário e retorna a lista de imóveis
 * associados a este proprietário, com os respectivos dados de IPTU.
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

// Função para buscar proprietário por CPF/CNPJ
function buscarProprietarioPorId($id, $dados) {
    // Remover caracteres especiais para comparação
    $idLimpo = preg_replace('/[^0-9]/', '', $id);
    
    // Verificar se parece com CPF (11 dígitos)
    if (strlen($idLimpo) == 11) {
        foreach ($dados['proprietarios']['cpf'] as $proprietario) {
            $propIdLimpo = preg_replace('/[^0-9]/', '', $proprietario['id']);
            if ($propIdLimpo == $idLimpo) {
                return [
                    'encontrado' => true,
                    'proprietario' => $proprietario,
                    'tipo' => 'cpf'
                ];
            }
        }
    }
    // Verificar se parece com CNPJ (14 dígitos)
    else if (strlen($idLimpo) == 14) {
        foreach ($dados['proprietarios']['cnpj'] as $proprietario) {
            $propIdLimpo = preg_replace('/[^0-9]/', '', $proprietario['id']);
            if ($propIdLimpo == $idLimpo) {
                return [
                    'encontrado' => true,
                    'proprietario' => $proprietario,
                    'tipo' => 'cnpj'
                ];
            }
        }
    }
    
    // Se chegou aqui, não encontrou
    return ['encontrado' => false];
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

// Verificar se o método da requisição é GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

// Verificar se o CPF/CNPJ foi fornecido
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['erro' => 'CPF/CNPJ não fornecido']);
    exit;
}

$id = $_GET['id'];

// Carregar os dados
$dados = carregarDados();
if (!$dados) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao carregar dados']);
    exit;
}

// Buscar o proprietário
$resultadoProprietario = buscarProprietarioPorId($id, $dados);
if (!$resultadoProprietario['encontrado']) {
    http_response_code(404);
    echo json_encode(['erro' => 'Proprietário não encontrado']);
    exit;
}

$proprietario = $resultadoProprietario['proprietario'];
$imoveis = [];

// Buscar os imóveis e boletos associados ao proprietário
foreach ($proprietario['imoveis'] as $codigoImovel) {
    $resultadoImovel = buscarImovelPorCodigo($codigoImovel, $dados);
    $resultadoBoleto = buscarBoletoPorCodigoImovel($codigoImovel, $dados);
    
    if ($resultadoImovel['encontrado'] && $resultadoBoleto['encontrado']) {
        $imoveis[] = [
            'imovel' => $resultadoImovel['imovel'],
            'boleto' => $resultadoBoleto['boleto'],
            'tipo_imovel' => $resultadoImovel['tipo']
        ];
    }
}

// Montar a resposta
$resposta = [
    'proprietario' => [
        'nome' => $proprietario['nome'],
        'id' => $proprietario['id'],
        'tipo' => $resultadoProprietario['tipo']
    ],
    'imoveis' => $imoveis,
    'total_imoveis' => count($imoveis)
];

// Retornar a resposta
echo json_encode($resposta, JSON_PRETTY_PRINT);
