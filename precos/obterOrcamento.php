<?php
header('Content-Type: application/json');

// Caminhos dos arquivos
$jsonFile = __DIR__ . '/chatbots_iaturbo_precos.json';
$logDir = __DIR__ . '/logs';
$orcamentosDir = __DIR__ . '/orcamentos'; // Pasta para salvar os orçamentos

// Verifica se o arquivo JSON existe
if (!file_exists($jsonFile)) {
    $error = ['error' => 'Arquivo de preços não encontrado.'];
    echo json_encode($error);
    exit;
}

// Lê o conteúdo do arquivo JSON
$jsonData = json_decode(file_get_contents($jsonFile), true);

// Verifica se a pasta de logs existe, caso contrário, cria
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Verifica se a pasta de orçamentos existe, caso contrário, cria
if (!is_dir($orcamentosDir)) {
    mkdir($orcamentosDir, 0755, true);
}

// Função para registrar logs
function writeLog($message) {
    global $logDir;
    $date = date('Y-m-d');
    $logFile = "$logDir/log_$date.txt";
    $time = date('H:i:s');
    $logMessage = "[$time] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Obtém os parâmetros enviados via POST e ignora parâmetros vazios, nulos ou com valor "None"
$params = array_filter($_POST, function($value) {
    return !is_null($value) && $value !== '' && $value !== 'None';
});

// Verifica e separa os dados do lead dos parâmetros de configuração
$leadInfo = [
    'nome' => $params['nome'] ?? null,
    'email' => $params['email'] ?? null,
    'whatsapp' => $params['whatsapp'] ?? null
];
unset($params['nome'], $params['email'], $params['whatsapp']); // Remove do array principal

// Verifica se os parâmetros obrigatórios (nome, email, WhatsApp) estão presentes e válidos
if (empty($leadInfo['nome']) || empty($leadInfo['email']) || empty($leadInfo['whatsapp'])) {
    $error = ['error' => 'Nome, email e WhatsApp são obrigatórios.'];
    echo json_encode($error);
    writeLog('Erro: Nome, email e WhatsApp são obrigatórios.');
    exit;
}

// Validação do formato de email
if (!filter_var($leadInfo['email'], FILTER_VALIDATE_EMAIL)) {
    $error = ['error' => 'Email inválido.'];
    echo json_encode($error);
    writeLog('Erro: Email inválido.');
    exit;
}

// Limpeza do número para remover espaços e traços
$leadInfo['whatsapp'] = preg_replace('/[\s-]/', '', $leadInfo['whatsapp']);

// Validação do formato internacional E.164 (mínimo de 10 e máximo de 15 dígitos, iniciando com '+')
if (!preg_match('/^\+\d{10,15}$/', $leadInfo['whatsapp'])) {
    $error = ['error' => 'Número de WhatsApp inválido. Por favor, use o formato internacional: +[código do país][número]. Exemplo para um número brasileiro: +5511999999999.'];
    echo json_encode($error);
    writeLog('Erro: WhatsApp inválido.');
    exit;
}


// Inicializa arrays para itens válidos e inválidos
$validItems = [];
$invalidParams = [];

// Função para converter parâmetros planos em hierarquia
function buildParamHierarchy($params) {
    $hierarchy = [];
    foreach ($params as $key => $value) {
        $keys = explode('_', $key);
        $ref = &$hierarchy;
        foreach ($keys as $k) {
            if (!isset($ref[$k]) || !is_array($ref[$k])) {
                $ref[$k] = [];
            }
            $ref = &$ref[$k];
        }
        $ref = $value;
    }
    return $hierarchy;
}

// Converte os parâmetros planos em hierarquia
$paramHierarchy = buildParamHierarchy($params);

// Função para validar parâmetros e montar o orçamento
function validateAndBuild($params, $jsonData, &$validItems, &$invalidParams, &$hasCustomAPI, &$inputParams, $path = '') {
    foreach ($params as $key => $value) {
        $currentPath = $path ? "$path > $key" : $key;
        $inputParams[$currentPath] = $value;

        if ($key === 'ConversaComIA') {
            if (is_array($value)) {
                // Descrição padrão do ConversaComIA
                $item = [
                    'Item' => 'ConversaComIA',
                    'DescricaoPrincipal' => $jsonData['ConversaComIA']['Descricao'],
                    'Categoria' => 'Implementacao',
                    'Modulo' => 'ConversaComIA'
                ];
                // Descrição personalizada fornecida pelo lead
                if (isset($value['DescricaoLead'])) {
                    $item['DescricaoPersonalizada'] = $value['DescricaoLead'];
                } else {
                    $invalidParams[] = 'ConversaComIA_DescricaoLead (Obrigatório)';
                }
                // Nível de customização
                if (isset($value['NivelPersonalizacaoConversa'])) {
                    $nivel = $value['NivelPersonalizacaoConversa'];
                    if (isset($jsonData['ConversaComIA']['NivelPersonalizacaoConversa'][$nivel])) {
                        $customizacao = $jsonData['ConversaComIA']['NivelPersonalizacaoConversa'][$nivel];
                        $item['Nivel'] = $customizacao['Nome'];
                        $item['DescricaoCustomizacao'] = $customizacao['Descricao'];
                        $item['Custo'] = $customizacao['Custo'];
                        $item['Tempo'] = $customizacao['Tempo'];
                    } else {
                        $invalidParams[] = 'ConversaComIA_NivelPersonalizacaoConversa (Valor inválido: ' . $nivel . ')';
                    }
                } else {
                    $invalidParams[] = 'ConversaComIA_NivelPersonalizacaoConversa (Obrigatório)';
                }
                $validItems[] = $item;

                // Suporte e Monitoramento
                if (isset($value['SuporteMelhoriaContinua'])) {
                    $nivel = $value['SuporteMelhoriaContinua'];
                    if (isset($jsonData['ConversaComIA']['SuporteMelhoriaContinua'][$nivel])) {
                        $suporte = $jsonData['ConversaComIA']['SuporteMelhoriaContinua'][$nivel];
                        $item = [
                            'Item' => 'ConversaComIA > SuporteMelhoriaContinua',
                            'Nivel' => $suporte['Nome'],
                            'DescricaoCustomizacao' => $suporte['Descricao'],
                            'Custo' => $suporte['Custo'],
                            'Categoria' => 'Manutencao',
                            'Modulo' => 'Conversa Com IA'
                        ];
                        $validItems[] = $item;
                    } else {
                        $invalidParams[] = 'ConversaComIA_SuporteMelhoriaContinua (Valor inválido: ' . $nivel . ')';
                    }
                }
            } else {
                $invalidParams[] = 'ConversaComIA (Esperado um objeto com parâmetros)';
            }
        } elseif ($key === 'Conectado') {
            if (is_array($value)) {
                // Processa Redes Sociais, API Pública e API Sob Medida
                if (isset($value['RedesSociais'])) {
                    foreach ($value['RedesSociais'] as $rede => $selecionado) {
                        if ($selecionado === 'true') {
                            if (isset($jsonData['Conectado']['RedesSociais'][$rede])) {
                                $item = $jsonData['Conectado']['RedesSociais'][$rede];
                                $item['Item'] = "Conectado > RedesSociais > $rede";
                                $item['Categoria'] = 'Implementacao';
                                $item['Modulo'] = 'Conectado';
                                $validItems[] = $item;
                            } else {
                                $invalidParams[] = "Conectado_RedesSociais_$rede (Rede inválida)";
                            }
                        }
                    }
                }
                // Processa APIs Públicas
                if (isset($value['APIPublica']) && is_array($value['APIPublica'])) {
                    $i = 0;
                    foreach ($value['APIPublica'] as $api) {
                        if (isset($api['Descricao']) && isset($api['Nivel'])) {
                            $nivel = $api['Nivel'];
                            if (isset($jsonData['Conectado']['APIPublica'][$nivel])) {
                                $apiData = $jsonData['Conectado']['APIPublica'][$nivel];
                                $item = [
                                    'Item' => "Conectado > APIPublica > " . ($i + 1),
                                    'DescricaoPersonalizada' => $api['Descricao'],
                                    'Nivel' => $nivel,
                                    'DescricaoCustomizacao' => $apiData['Descricao'],
                                    'Custo' => $apiData['Custo'],
                                    'Tempo' => $apiData['Tempo'],
                                    'Categoria' => 'Implementacao',
                                    'Modulo' => 'Conectado'
                                ];
                                $validItems[] = $item;
                            } else {
                                $invalidParams[] = "Conectado_APIPublica_{$i}_Nivel (Valor inválido: $nivel)";
                            }
                        } else {
                            $invalidParams[] = "Conectado_APIPublica_{$i} (Descrição e Nível são obrigatórios)";
                        }
                        $i++;
                    }
                }
                // Processa API Sob Medida
                if (isset($value['APISobMedida']['Descricao'])) {
                    $item = [
                        'Item' => 'Conectado > APISobMedida',
                        'DescricaoPersonalizada' => $value['APISobMedida']['Descricao'],
                        'Aviso' => 'Preços e prazos para esta solução só podem ser oferecidos sob consulta.',
                        'Categoria' => 'Implementacao',
                        'Modulo' => 'Conectado'
                    ];
                    $validItems[] = $item;
                    $hasCustomAPI = true;
                }
                // Suporte e Monitoramento
                if (isset($value['SuporteMelhoriaContinua'])) {
                    $nivel = $value['SuporteMelhoriaContinua'];
                    if (isset($jsonData['Conectado']['SuporteMelhoriaContinua'][$nivel])) {
                        $suporte = $jsonData['Conectado']['SuporteMelhoriaContinua'][$nivel];
                        $item = [
                            'Item' => 'Conectado > SuporteMelhoriaContinua',
                            'Nivel' => $nivel,
                            'DescricaoCustomizacao' => $suporte['Descricao'],
                            'Custo' => $suporte['Custo'],
                            'Categoria' => 'Manutencao',
                            'Modulo' => 'Conectado'
                        ];
                        $validItems[] = $item;
                    } else {
                        $invalidParams[] = 'Conectado_SuporteMelhoriaContinua (Valor inválido: ' . $nivel . ')';
                    }
                }
            } else {
                $invalidParams[] = 'Conectado (Esperado um objeto com parâmetros)';
            }
        } elseif ($key === 'Multimidia') {
            if (is_array($value)) {
                // Processa funcionalidades de Áudio e Imagem
                foreach (['Audio', 'Imagem'] as $tipo) {
                    if (isset($value[$tipo])) {
                        foreach ($value[$tipo] as $funcionalidade => $selecionado) {
                            if (is_array($selecionado)) {
                                // Verifica se a funcionalidade existe no JSON de preços
                                if (isset($jsonData['Multimidia'][$tipo][$funcionalidade])) {
                                    $itemData = $jsonData['Multimidia'][$tipo][$funcionalidade];
                                    $item = [
                                        'Item' => "Multimidia > $tipo > $funcionalidade",
                                        'Nome' => $itemData['Nome'],
                                        'DescricaoPrincipal' => $itemData['Descricao'],
                                        'Custo' => $itemData['Custo'],
                                        'Tempo' => $itemData['Tempo'],
                                        'Categoria' => 'Implementacao',
                                        'Modulo' => 'Multimidia'
                                    ];
                                    // Descrição personalizada
                                    if (isset($selecionado['DescricaoLead'])) {
                                        $item['DescricaoPersonalizada'] = $selecionado['DescricaoLead'];
                                    } else {
                                        $invalidParams[] = "Multimidia_{$tipo}_{$funcionalidade}_DescricaoLead (Obrigatório)";
                                    }
                                    $validItems[] = $item;
                                } else {
                                    $invalidParams[] = "Multimidia_{$tipo}_{$funcionalidade} (Funcionalidade inválida)";
                                }
                            }
                        }
                    }
                }
                // Suporte e Monitoramento
                if (isset($value['SuporteMelhoriaContinua'])) {
                    $nivel = $value['SuporteMelhoriaContinua'];
                    if (isset($jsonData['Multimidia']['SuporteMelhoriaContinua'][$nivel])) {
                        $suporte = $jsonData['Multimidia']['SuporteMelhoriaContinua'][$nivel];
                        $item = [
                            'Item' => 'Multimidia > SuporteMelhoriaContinua',
                            'Nivel' => $nivel,
                            'DescricaoCustomizacao' => $suporte['Descricao'],
                            'Custo' => $suporte['Custo'],
                            'Categoria' => 'Manutencao',
                            'Modulo' => 'Multimidia'
                        ];
                        $validItems[] = $item;
                    } else {
                        $invalidParams[] = 'Multimidia_SuporteMelhoriaContinua (Valor inválido: ' . $nivel . ')';
                    }
                }
            } else {
                $invalidParams[] = 'Multimidia (Esperado um objeto com parâmetros)';
            }
        } else {
            $invalidParams[] = "$currentPath (Parâmetro inválido)";
        }
    }
}

// Variável para verificar se há solicitação de API sob medida
$hasCustomAPI = false;

// Variável para armazenar os parâmetros de entrada processados
$inputParams = [];

// Inicia a validação e construção do orçamento
validateAndBuild($paramHierarchy, $jsonData, $validItems, $invalidParams, $hasCustomAPI, $inputParams);

// Se houver parâmetros inválidos, retorna erro
if (!empty($invalidParams)) {
    $errorMessage = 'Parâmetros inválidos ou faltando: ' . implode(', ', $invalidParams) . '.';
    $error = ['error' => $errorMessage];
    echo json_encode($error);
    writeLog('Erro: ' . $errorMessage . ' Parâmetros recebidos: ' . json_encode($params));
    exit;
}

// Separa itens em Implementação e Manutenção e calcula subtotais por módulo
$implementationItems = [];
$maintenanceItems = [];
$totalImplementationCost = 0;
$totalImplementationTime = 0;
$totalMaintenanceCost = 0;

// Inicializa subtotais por módulo
$moduleSubtotals = [
    'ConversaComIA' => ['CustoImplementacao' => 0, 'TempoImplementacao' => 0],
    'Conectado' => ['CustoImplementacao' => 0, 'TempoImplementacao' => 0],
    'Multimidia' => ['CustoImplementacao' => 0, 'TempoImplementacao' => 0],
];

foreach ($validItems as $item) {
    if (isset($item['Categoria'])) {
        if ($item['Categoria'] === 'Implementacao') {
            $implementationItems[] = $item;
            $totalImplementationCost += isset($item['Custo']) ? $item['Custo'] : 0;
            $totalImplementationTime += isset($item['Tempo']) ? $item['Tempo'] : 0;

            // Atualiza subtotais por módulo
            if (isset($item['Modulo'])) {
                $modulo = $item['Modulo'];
                $moduleSubtotals[$modulo]['CustoImplementacao'] += isset($item['Custo']) ? $item['Custo'] : 0;
                $moduleSubtotals[$modulo]['TempoImplementacao'] += isset($item['Tempo']) ? $item['Tempo'] : 0;
            }
        } elseif ($item['Categoria'] === 'Manutencao') {
            $maintenanceItems[] = $item;
            $totalMaintenanceCost += isset($item['Custo']) ? $item['Custo'] : 0;
        }
    }
}

// Monta a resposta incluindo as informações do lead
$id = "orcamento_" . date('Y_m_d') . uniqid();
$response = [
    'LeadInfo' => $leadInfo,
    'ParametrosEntrada' => $inputParams,
    'ItensConfigurados' => $validItems,
    'ResumoGeral' => [
        'CustoImplementacao' => $totalImplementationCost,
        'TempoImplementacao' => $totalImplementationTime,
        'CustoManutencao' => $totalMaintenanceCost,
        'Subtotais' => $moduleSubtotals,
        'UrlOrcamentoDetalhado' => "https://iaturbo.com.br/orcamentos/?id=".$id
    ]
];

// Adiciona resumo textual
$tempoEmDias = ceil($totalImplementationTime / 8); // Considerando 8 horas por dia
$textoResumo = "O custo de implementação é de R$ " . number_format($totalImplementationCost, 2, ',', '.') . ", com um prazo estimado de {$totalImplementationTime} horas ({$tempoEmDias} dias úteis) para entrega.";
if ($totalMaintenanceCost > 0) {
    $textoResumo .= " O custo mensal de manutenção é de R$ " . number_format($totalMaintenanceCost, 2, ',', '.') . ".";
}
if ($hasCustomAPI) {
    $textoResumo .= " ATENÇÃO: O orçamento solicitado inclui o a criação de uma API Sob Medida. Os preços e prazos desta solução não são tabelados. Entre em contato agora mesmo para obter estas informações.";
}
$response['ResumoGeral']['TextoResumo'] = $textoResumo;

// Salva o payload de saída na pasta /orcamentos com nome no formato especificado
$filename = $orcamentosDir . "/" . $id . ".json";

file_put_contents($filename, json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Retorna a resposta em JSON
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Registra o log da requisição bem-sucedida
writeLog("Orçamento gerado com sucesso. Arquivo salvo em: $filename. Parâmetros: " . json_encode($params) . ". Resposta: " . json_encode($response));
?>
