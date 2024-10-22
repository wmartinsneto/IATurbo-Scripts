<?php
// Verifica o token de autenticação
$headers = getallheaders();
if (!isset($headers['Authorization']) || $headers['Authorization'] !== 'Bearer 4d3f2f5e6c7b8a9d4b5f6e7a8c9d2b1a') {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

// Obtém o corpo da solicitação
$data = json_decode(file_get_contents("php://input"), true);

// Lista de campos necessários
$requiredFields = ['leadName', 'leadEmail', 'leadWhatsAppNumber', 'startDateTime', 'eventNotes'];
$missingFields = [];
$validationErrors = [];

// Verifica se todos os campos obrigatórios estão presentes
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        $missingFields[] = $field;
    }
}

// Validação do email
if (!empty($data['leadEmail']) && !filter_var($data['leadEmail'], FILTER_VALIDATE_EMAIL)) {
    $validationErrors[] = "O email inserido não parece estar correto. Por favor, verifique e insira um email válido, como exemplo@dominio.com.";
}

// Validação do número de WhatsApp (formato internacional)
if (!empty($data['leadWhatsAppNumber'])) {
    $phoneNumber = $data['leadWhatsAppNumber'];
    $phonePattern = '/^\+\d{1,3}\s?\d{1,4}?\s?\d{4,14}$/'; // Regex para validar o formato internacional

    if (!preg_match($phonePattern, $phoneNumber)) {
        $validationErrors[] = "Ops! O número '$phoneNumber' parece estar no formato incorreto. Por favor, insira o número de telefone no formato internacional. Comece com '+', seguido pelo código do país, código de área e o número. Exemplo: +55 11 99999-9999 (para São Paulo, Brasil). 😊";
    }
}

// Se houver campos ausentes, adiciona ao array de erros
if (!empty($missingFields)) {
    $validationErrors[] = "Preciso dos seguintes dados para prosseguir com o agendamento: " . implode(", ", $missingFields) . ".";
}

// Se houver erros de validação, retorna um erro detalhado
if (!empty($validationErrors)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["error" => implode(" ", $validationErrors)]);
    exit();
}

// Validação da frase de confirmação
if (empty($data['leadConfirmationPhrase'])) {
    $validData = [
        "Nome" => $data['leadName'],
        "Email" => $data['leadEmail'],
        "WhatsApp" => $data['leadWhatsAppNumber'],
        "Data/Hora do Evento" => $data['startDateTime'],
        "Notas do Evento" => $data['eventNotes']
    ];
    $validDataText = "Nome: " . $validData['Nome'] . "\n" .
                     "Email: " . $validData['Email'] . "\n" .
                     "WhatsApp: " . $validData['WhatsApp'] . "\n" .
                     "Data/Hora do Evento: " . $validData['Data/Hora do Evento'] . "\n" .
                     "Notas do Evento: " . $validData['Notas do Evento'];
    $validationErrors[] = "Já tenho todas as informações para nossa reunião:\n$validDataText\nAgora só preciso da sua confirmação.\nSe estiver tudo ok, por favor, confirme o agendamento respondendo com 'SIM'.";
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["error" => implode(" ", $validationErrors)]);
    exit();
}

// Define os parâmetros da API de agendamento do Cal.com
$eventTypeId = 1189231;
$timeZone = "America/Sao_Paulo";
$url = "https://api.cal.com/v1/bookings?apiKey=cal_live_983351587843497cd8aadf0693ec3859";

// Corpo da solicitação
$requestBody = [
    "responses" => [
        "email" => $data['leadEmail'],
        "name" => $data['leadName'],
        "notes" => $data['eventNotes'],
        "guests" => [],
        "phone" => $data['leadWhatsAppNumber']
    ],
    "start" => $data['startDateTime'],
    "eventTypeId" => $eventTypeId,
    "timeZone" => $timeZone,
    "language" => "pt",
    "location" => "",
    "metadata" => [
        "acknowledgment" => "Lead confirmed booking with phrase: " . $data['leadConfirmationPhrase']
    ],
    "hasHashedBookingLink" => false,
    "hashedLink" => null
];

// Create /log directory if it doesn't exist
$logDir = __DIR__ . '/log';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// Create log file with current date in the filename
$logFile = $logDir . '/log_' . date('Y_m_d') . '.log';

// Function to write to log file
function writeLog($message) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $message . PHP_EOL, FILE_APPEND);
}

// Log the request body
writeLog("Request Body: " . json_encode($requestBody));

// Use cURL to send the request
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
$result = curl_exec($ch);

// Log the response
writeLog("Response: " . $result);

if ($result === FALSE) {
    http_response_code(500);
    $error = curl_error($ch);
    writeLog("Error: " . $error);
    echo json_encode(["error" => "Ops ... ocorreu uma falha ao realizar o agendamento", "details" => $error]);
    curl_close($ch);
    exit();
}

curl_close($ch);
$bookingData = json_decode($result, true);

// Check for errors in the response from Cal.com
if (isset($bookingData['message'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["error" => $bookingData['message']]);
    exit();
}

// Return the same payload received from Cal.com
http_response_code(200);
header('Content-Type: application/json');
echo json_encode($bookingData);

// Notificação para o Slack
$slackWebhookUrl = 'https://hooks.slack.com/services/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';
$slackMessage = [
    'text' => "Novo agendamento de consultoria:\n" .
              "- Nome: " . $data['leadName'] . "\n" .
              "- Email: " . $data['leadEmail'] . "\n" .
              "- WhatsApp: " . $data['leadWhatsAppNumber'] . "\n" .
              "- Data/Hora do Evento: " . $data['startDateTime'] . "\n" .
              "- Notas do Evento: " . $data['eventNotes'] . "\n" .
              "- Frase de Confirmação: " . $data['leadConfirmationPhrase'] . "\n"
];

// Envia a notificação para o Slack
$optionsSlack = [
    'http' => [
        'header' => "Content-Type: application/json\r\n",
        'method' => 'POST',
        'content' => json_encode($slackMessage)
    ]
];

$contextSlack = stream_context_create($optionsSlack);
$responseSlack = file_get_contents($slackWebhookUrl, false, $contextSlack);

if ($responseSlack === FALSE) {
    echo json_encode(["error" => "Falha ao enviar a notificação para o Slack"]);
    exit();
}

// Retorna uma resposta de sucesso
echo json_encode([
    "message" => "Agendamento realizado com sucesso!",
    "bookingDetails" => $bookingData
]);
?>
