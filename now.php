<?php
// Verifica o token de autenticação
$headers = getallheaders();
if (!isset($headers['Authorization']) || $headers['Authorization'] !== 'Bearer 4d3f2f5e6c7b8a9d4b5f6e7a8c9d2b1a') {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

// Define o local para o português do Brasil
setlocale(LC_TIME, 'pt_BR.utf8');

// Obtém a data e hora atual
$timestamp = time();
$formattedDate = strftime('%A, %d de %B de %Y, %H:%M:%S', $timestamp);

// Imprime a data formatada
header('Content-Type: application/json');
echo json_encode(array(
    "formattedDate" => $formattedDate,
    "timezone" => "America/Sao_Paulo"
));

?>
