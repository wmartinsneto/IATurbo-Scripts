<?php
header('Content-Type: application/json');

// Caminhos dos arquivos
$jsonFile = __DIR__ . '/chatbots_iaturbo_precos.json';
$logDir = __DIR__ . '/logs';

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

// Função para registrar logs
function writeLog($message) {
    global $logDir;
    $date = date('Y-m-d');
    $logFile = "$logDir/log_$date.txt";
    $time = date('H:i:s');
    $logMessage = "[$time] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Obtém os parâmetros enviados via POST
$params = $_POST;

// Verifica se parâmetros foram enviados
if (empty($params)) {
    $error = ['error' => 'Nenhum parâmetro foi enviado.'];
    echo json_encode($error);
    writeLog('Erro: Nenhum parâmetro foi enviado.');
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
                if (isset($value['CustomizacaoPrompt'])) {
                    $nivel = $value['CustomizacaoPrompt'];
                    if (isset($jsonData['ConversaComIA']['CustomizacaoPrompt'][$nivel])) {
                        $customizacao = $jsonData['ConversaComIA']['CustomizacaoPrompt'][$nivel];
                        $item['Nivel'] = $nivel;
                        $item['DescricaoCustomizacao'] = $customizacao['Descricao'];
                        $item['Custo'] = $customizacao['Custo'];
                        $item['Tempo'] = $customizacao['Tempo'];
                    } else {
                        $invalidParams[] = 'ConversaComIA_CustomizacaoPrompt (Valor inválido: ' . $nivel . ')';
                    }
                } else {
                    $invalidParams[] = 'ConversaComIA_CustomizacaoPrompt (Obrigatório)';
                }
                $validItems[] = $item;

                // Suporte e Monitoramento
                if (isset($value['SuporteMonitoramentoContinuo'])) {
                    $nivel = $value['SuporteMonitoramentoContinuo'];
                    if (isset($jsonData['ConversaComIA']['SuporteMonitoramentoContinuo'][$nivel])) {
                        $suporte = $jsonData['ConversaComIA']['SuporteMonitoramentoContinuo'][$nivel];
                        $item = [
                            'Item' => 'ConversaComIA > SuporteMonitoramentoContinuo',
                            'Nivel' => $nivel,
                            'DescricaoCustomizacao' => $suporte['Descricao'],
                            'Custo' => $suporte['Custo'],
                            'Categoria' => 'Manutencao',
                            'Modulo' => 'ConversaComIA'
                        ];
                        $validItems[] = $item;
                    } else {
                        $invalidParams[] = 'ConversaComIA_SuporteMonitoramentoContinuo (Valor inválido: ' . $nivel . ')';
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
                if (isset($value['SuporteMonitoramentoContinuo'])) {
                    $nivel = $value['SuporteMonitoramentoContinuo'];
                    if (isset($jsonData['Conectado']['SuporteMonitoramentoContinuo'][$nivel])) {
                        $suporte = $jsonData['Conectado']['SuporteMonitoramentoContinuo'][$nivel];
                        $item = [
                            'Item' => 'Conectado > SuporteMonitoramentoContinuo',
                            'Nivel' => $nivel,
                            'DescricaoCustomizacao' => $suporte['Descricao'],
                            'Custo' => $suporte['Custo'],
                            'Categoria' => 'Manutencao',
                            'Modulo' => 'Conectado'
                        ];
                        $validItems[] = $item;
                    } else {
                        $invalidParams[] = 'Conectado_SuporteMonitoramentoContinuo (Valor inválido: ' . $nivel . ')';
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
                if (isset($value['SuporteMonitoramentoContinuo'])) {
                    $nivel = $value['SuporteMonitoramentoContinuo'];
                    if (isset($jsonData['Multimidia']['SuporteMonitoramentoContinuo'][$nivel])) {
                        $suporte = $jsonData['Multimidia']['SuporteMonitoramentoContinuo'][$nivel];
                        $item = [
                            'Item' => 'Multimidia > SuporteMonitoramentoContinuo',
                            'Nivel' => $nivel,
                            'DescricaoCustomizacao' => $suporte['Descricao'],
                            'Custo' => $suporte['Custo'],
                            'Categoria' => 'Manutencao',
                            'Modulo' => 'Multimidia'
                        ];
                        $validItems[] = $item;
                    } else {
                        $invalidParams[] = 'Multimidia_SuporteMonitoramentoContinuo (Valor inválido: ' . $nivel . ')';
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

// Monta a resposta
$response = [
    'ParametrosEntrada' => $inputParams,
    'ItensConfigurados' => $validItems,
    'ResumoGeral' => [
        'CustoImplementacao' => $totalImplementationCost,
        'TempoImplementacao' => $totalImplementationTime,
        'CustoManutencao' => $totalMaintenanceCost,
        'Subtotais' => $moduleSubtotals
    ]
];

// Adiciona resumo textual
$tempoEmDias = ceil($totalImplementationTime / 8); // Considerando 8 horas por dia
$textoResumo = "O custo de implementação é de R$ {$totalImplementationCost}, com um prazo estimado de {$totalImplementationTime} horas ({$tempoEmDias} dias úteis).";
if ($totalMaintenanceCost > 0) {
    $textoResumo .= " O custo mensal de manutenção é de R$ {$totalMaintenanceCost}.";
}
if ($hasCustomAPI) {
    $textoResumo .= " Há itens que requerem consulta para determinação de preços e prazos.";
}
$response['ResumoGeral']['TextoResumo'] = $textoResumo;

// Retorna a resposta em JSON
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Registra o log da requisição bem-sucedida
writeLog('Orçamento gerado com sucesso. Parâmetros: ' . json_encode($params) . '. Resposta: ' . json_encode($response));
?>
