<?php
/**
 * Funções auxiliares para o sistema de IPTU
 * 
 * Este arquivo contém funções para carregar dados e buscar informações
 * sobre imóveis, boletos e proprietários.
 */

// Função para carregar os dados do JSON
function carregarDados() {
    $jsonFile = __DIR__ . '/../dados.json';
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

// Função para formatar valor monetário
function formatarValor($valor) {
    return number_format($valor, 2, ',', '.');
}

// Função para formatar data
function formatarData($data) {
    $timestamp = strtotime($data);
    return date('d/m/Y', $timestamp);
}
