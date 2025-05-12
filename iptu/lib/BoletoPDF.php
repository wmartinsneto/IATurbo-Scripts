<?php
/**
 * Classe para geração de PDF de boletos de IPTU - Versão Simplificada
 * 
 * Esta classe estende a biblioteca FPDF para criar um PDF básico
 * com os dados do boleto de IPTU.
 */

require_once(__DIR__ . '/fpdf/fpdf.php');

class BoletoPDF extends FPDF {
    /**
     * Sobrescreve o método Header para adicionar o cabeçalho do boleto
     */
    function Header() {
        // Cabeçalho simples
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'IPTU 2025 - Boleto', 0, 1, 'C');
        $this->Ln(5);
    }
    
    /**
     * Sobrescreve o método Footer para adicionar o rodapé do boleto
     */
    function Footer() {
        // Rodapé simples
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->PageNo(), 0, 0, 'C');
    }
    
    /**
     * Adiciona um título de seção ao PDF
     * 
     * @param string $titulo Título da seção
     */
    function TituloSecao($titulo) {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, $titulo, 0, 1, 'L');
        $this->SetFont('Arial', '', 10);
    }
    
    /**
     * Adiciona uma linha de informação ao PDF
     * 
     * @param string $label Rótulo da informação
     * @param string $valor Valor da informação
     */
    function InfoLinha($label, $valor) {
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(60, 6, $label . ':', 0, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, $valor, 0, 1, 'L');
    }
    
    /**
     * Adiciona uma linha de corte ao PDF (versão simplificada)
     */
    function LinhaCorte() {
        $this->Ln(5);
        $this->SetDrawColor(0, 0, 0);
        $this->SetDash(1, 1); // Linha tracejada
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->SetDash(); // Restaura linha sólida
        $this->Ln(5);
    }
}
