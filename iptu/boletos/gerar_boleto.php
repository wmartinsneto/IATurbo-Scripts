<?php
/**
 * Gerador de boletos de IPTU
 * 
 * Este script recebe o código do imóvel e gera um arquivo PDF para download
 * com os dados do boleto, imóvel e proprietário.
 */

// Incluir os arquivos necessários
require_once(__DIR__ . '/../lib/funcoes.php');
require_once(__DIR__ . '/../lib/BoletoPDF.php');

// Verificar se o método da requisição é GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

// Verificar se o código do imóvel foi fornecido
if (!isset($_GET['codigo'])) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['erro' => 'Código do imóvel não fornecido']);
    exit;
}

$codigo = intval($_GET['codigo']);

// Carregar os dados
$dados = carregarDados();
if (!$dados) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao carregar dados']);
    exit;
}

// Buscar o imóvel
$resultadoImovel = buscarImovelPorCodigo($codigo, $dados);
if (!$resultadoImovel['encontrado']) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['erro' => 'Imóvel não encontrado']);
    exit;
}

// Buscar o boleto
$resultadoBoleto = buscarBoletoPorCodigoImovel($codigo, $dados);
if (!$resultadoBoleto['encontrado']) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['erro' => 'Boleto não encontrado para este imóvel']);
    exit;
}

// Buscar o proprietário
$resultadoProprietario = buscarProprietarioPorCodigoImovel($codigo, $dados);
if (!$resultadoProprietario['encontrado']) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['erro' => 'Proprietário não encontrado para este imóvel']);
    exit;
}

// Extrair os dados para facilitar o acesso
$imovel = $resultadoImovel['imovel'];
$boleto = $resultadoBoleto['boleto'];
$proprietario = $resultadoProprietario['proprietario'];
$tipoProprietario = $resultadoProprietario['tipo'];

// Gerar o nome do arquivo PDF
$dataAtual = date('Y-m-d');
$nomeArquivo = "boleto_iptu_{$codigo}_{$dataAtual}.pdf";

// Criar o PDF - versão simplificada
$pdf = new BoletoPDF();
$pdf->AddPage();

// Título principal
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'BOLETO IPTU 2025 - VERSÃO SIMPLIFICADA', 0, 1, 'C');
$pdf->Ln(5);

// Dados do imóvel
$pdf->TituloSecao('Dados do Imóvel');
$pdf->InfoLinha('Código', $imovel['codigo']);
$pdf->InfoLinha('Endereço', $imovel['endereco']);
$pdf->InfoLinha('Bairro', $imovel['bairro']);
$pdf->InfoLinha('Área', $imovel['area'] . ' m²');
$pdf->InfoLinha('Tipo', $imovel['tipo']);
$pdf->Ln(5);

// Dados do proprietário
$pdf->TituloSecao('Dados do Proprietário');
$pdf->InfoLinha('Nome', $proprietario['nome']);
$pdf->InfoLinha('Documento', $proprietario['id'] . ' (' . strtoupper($tipoProprietario) . ')');
$pdf->Ln(5);

// Dados do boleto
$pdf->TituloSecao('Dados do Pagamento');
$pdf->InfoLinha('Exercício', $boleto['exercicio']);
$pdf->InfoLinha('Valor', 'R$ ' . formatarValor($boleto['valor']));
$pdf->InfoLinha('Vencimento', formatarData($boleto['vencimento']));
$pdf->InfoLinha('Código de Barras', $boleto['codigo_barras']);
$pdf->Ln(5);

// Gerar o PDF para download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
header('Cache-Control: max-age=0');
$pdf->Output('I', $nomeArquivo);
exit;
