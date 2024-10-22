<?php
// Verifica o token de autenticação
$headers = getallheaders();
if (!isset($headers['Authorization']) || $headers['Authorization'] !== 'Bearer 4d3f2f5e6c7b8a9d4b5f6e7a8c9d2b1a') {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

// Define os parâmetros fixos
$eventTypeId = 1189231;
$timeZone = 'America/Sao_Paulo';
$startDate = date('Y-m-d');
$endDate = date('Y-m-d', strtotime('+1 week'));

// URL da API do Cal.com com parâmetros de consulta, incluindo a chave de API
$calApiUrl = "https://api.cal.com/v1/slots?eventTypeId={$eventTypeId}&timeZone=" . urlencode($timeZone) . "&startTime={$startDate}&endTime={$endDate}&apiKey=cal_live_983351587843497cd8aadf0693ec3859";

// Faz a requisição para a API do Cal.com
$response = file_get_contents($calApiUrl);
if ($response === FALSE) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Failed to fetch slots from Cal.com"]);
    exit();
}

$slots = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Failed to decode JSON response from Cal.com"]);
    exit();
}

// Função para converter a data para o formato desejado sem strftime
function formatDateToFriendly($dateString) {
    $date = new DateTime($dateString);
    
    // Dias da semana em português
    $daysOfWeek = [
        'Monday'    => 'Segunda-feira',
        'Tuesday'   => 'Terça-feira',
        'Wednesday' => 'Quarta-feira',
        'Thursday'  => 'Quinta-feira',
        'Friday'    => 'Sexta-feira',
        'Saturday'  => 'Sábado',
        'Sunday'    => 'Domingo',
    ];
    
    // Meses em português com inicial maiúscula
    $months = [
        'January'   => 'Janeiro',
        'February'  => 'Fevereiro',
        'March'     => 'Março',
        'April'     => 'Abril',
        'May'       => 'Maio',
        'June'      => 'Junho',
        'July'      => 'Julho',
        'August'    => 'Agosto',
        'September' => 'Setembro',
        'October'   => 'Outubro',
        'November'  => 'Novembro',
        'December'  => 'Dezembro',
    ];
    
    // Obtém o nome do dia da semana em inglês e converte para português
    $dayOfWeek = $daysOfWeek[$date->format('l')]; 

    // Obtém o nome do mês em inglês e converte para português
    $month = $months[$date->format('F')];

    // Formata a data no estilo "22 de Outubro de 2024"
    $formattedDate = $dayOfWeek . ', ' . $date->format('d') . ' de ' . $month . ' de ' . $date->format('Y');
    return $formattedDate;
}

// Processa os slots para converter as datas no formato amigável
$formattedSlots = [];
foreach ($slots['slots'] as $date => $times) {
    $friendlyDate = formatDateToFriendly($date);
    $formattedSlots[$friendlyDate] = $times;
}

// Retorna os slots formatados
header('Content-Type: application/json');
echo json_encode(['slots' => $formattedSlots], JSON_UNESCAPED_UNICODE);
?>
