<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de IPTU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f5f5;
            font-family: Arial, sans-serif;
        }
        .header {
            background: linear-gradient(135deg, #1e7e34 0%, #218838 100%);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .logo {
            max-width: 80px;
            margin-right: 15px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #218838;
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px;
            font-weight: bold;
        }
        .btn-primary {
            background-color: #218838;
            border-color: #218838;
        }
        .btn-primary:hover {
            background-color: #1e7e34;
            border-color: #1e7e34;
        }
        .result-card {
            display: none;
            margin-top: 20px;
        }
        .codigo-barras {
            font-family: monospace;
            font-size: 1.2em;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .loading {
            display: none;
            text-align: center;
            margin: 20px 0;
        }
        .error-message {
            color: #dc3545;
            margin-top: 10px;
            display: none;
        }
        .imovel-item {
            cursor: pointer;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin-bottom: 10px;
            transition: background-color 0.3s;
        }
        .imovel-item:hover {
            background-color: #f8f9fa;
        }
        .imovel-item.selected {
            background-color: #e9ecef;
            border-color: #218838;
        }
        .imoveis-list {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <img src="https://cdn-icons-png.flaticon.com/512/2942/2942789.png" alt="Logo Prefeitura" class="logo">
                    <div>
                        <h1 class="mb-0">Serviços Públicos Online</h1>
                        <p class="mb-0">Município de Exemplo</p>
                    </div>
                </div>
                <div>
                    <a href="swagger-ui.html" class="btn btn-light" target="_blank">Documentação API</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <h2 class="text-center mb-4">CARNÊ DE IPTU</h2>
                
                <div class="card">
                    <div class="card-header">
                        Consulta de IPTU
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="tipoBusca" class="form-label">Tipo de Busca:</label>
                            <select class="form-select" id="tipoBusca">
                                <option value="imovel">Imóvel</option>
                                <option value="proprietario">Proprietário</option>
                            </select>
                        </div>
                        
                        <!-- Campos para busca por Imóvel -->
                        <div id="buscaImovel">
                            <div class="mb-3">
                                <label for="codigoImovel" class="form-label">Código do Imóvel:</label>
                                <input type="number" class="form-control" id="codigoImovel" placeholder="Digite o código do imóvel">
                            </div>
                        </div>
                        
                        <!-- Campos para busca por Proprietário -->
                        <div id="buscaProprietario" style="display: none;">
                            <div class="mb-3">
                                <label for="documentoProprietario" class="form-label">CPF / CNPJ:</label>
                                <input type="text" class="form-control" id="documentoProprietario" placeholder="Digite o CPF ou CNPJ do proprietário">
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <button type="button" class="btn btn-primary" id="btnConsultar">Consultar</button>
                        </div>
                        
                        <div class="loading">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                            <p>Carregando dados...</p>
                        </div>
                        
                        <div class="error-message" id="errorMessage"></div>
                    </div>
                </div>
                
                <!-- Lista de Imóveis (para busca por proprietário) -->
                <div class="card result-card" id="imoveisListCard">
                    <div class="card-header">
                        Imóveis Encontrados
                    </div>
                    <div class="card-body">
                        <p>Selecione um imóvel para visualizar os detalhes do IPTU:</p>
                        <div class="imoveis-list" id="imoveisList"></div>
                        <div class="text-center">
                            <button type="button" class="btn btn-primary" id="btnVerDetalhes" disabled>Ver Detalhes</button>
                        </div>
                    </div>
                </div>
                
                <!-- Resultado da Consulta -->
                <div class="card result-card" id="resultadoCard">
                    <div class="card-header">
                        Detalhes do IPTU
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Dados do Imóvel</h5>
                                <p><strong>Código:</strong> <span id="codigoResult"></span></p>
                                <p><strong>Endereço:</strong> <span id="enderecoResult"></span></p>
                                <p><strong>Bairro:</strong> <span id="bairroResult"></span></p>
                                <p><strong>Área:</strong> <span id="areaResult"></span> m²</p>
                                <p><strong>Tipo:</strong> <span id="tipoResult"></span></p>
                            </div>
                            <div class="col-md-6">
                                <h5>Dados do Proprietário</h5>
                                <p><strong>Nome:</strong> <span id="nomeProprietarioResult"></span></p>
                                <p><strong>CPF/CNPJ:</strong> <span id="documentoProprietarioResult"></span></p>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-12">
                                <h5>Dados do Boleto</h5>
                                <p><strong>Exercício:</strong> <span id="exercicioResult"></span></p>
                                <p><strong>Valor:</strong> R$ <span id="valorResult"></span></p>
                                <p><strong>Vencimento:</strong> <span id="vencimentoResult"></span></p>
                                <p><strong>Código de Barras:</strong></p>
                                <div class="codigo-barras" id="codigoBarrasResult"></div>
                                <div class="text-center mt-4">
                                    <a href="#" class="btn btn-primary" id="linkBoleto" target="_blank">Baixar Boleto</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elementos do DOM
            const tipoBusca = document.getElementById('tipoBusca');
            const buscaImovel = document.getElementById('buscaImovel');
            const buscaProprietario = document.getElementById('buscaProprietario');
            const codigoImovel = document.getElementById('codigoImovel');
            const documentoProprietario = document.getElementById('documentoProprietario');
            const btnConsultar = document.getElementById('btnConsultar');
            const loading = document.querySelector('.loading');
            const errorMessage = document.getElementById('errorMessage');
            const resultadoCard = document.getElementById('resultadoCard');
            const imoveisListCard = document.getElementById('imoveisListCard');
            const imoveisList = document.getElementById('imoveisList');
            const btnVerDetalhes = document.getElementById('btnVerDetalhes');
            
            // Dados temporários para armazenar resultados
            let imoveisEncontrados = [];
            let imovelSelecionado = null;
            
            // Alternar entre tipos de busca
            tipoBusca.addEventListener('change', function() {
                if (this.value === 'imovel') {
                    buscaImovel.style.display = 'block';
                    buscaProprietario.style.display = 'none';
                } else {
                    buscaImovel.style.display = 'none';
                    buscaProprietario.style.display = 'block';
                }
                
                // Resetar resultados
                resultadoCard.style.display = 'none';
                imoveisListCard.style.display = 'none';
                errorMessage.style.display = 'none';
            });
            
            // Formatar CPF/CNPJ
            documentoProprietario.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                
                if (value.length <= 11) {
                    // Formatar como CPF
                    if (value.length > 9) {
                        value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4');
                    } else if (value.length > 6) {
                        value = value.replace(/(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3');
                    } else if (value.length > 3) {
                        value = value.replace(/(\d{3})(\d{0,3})/, '$1.$2');
                    }
                } else {
                    // Formatar como CNPJ
                    if (value.length > 12) {
                        value = value.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{0,2})/, '$1.$2.$3/$4-$5');
                    } else if (value.length > 8) {
                        value = value.replace(/(\d{2})(\d{3})(\d{3})(\d{0,4})/, '$1.$2.$3/$4');
                    } else if (value.length > 5) {
                        value = value.replace(/(\d{2})(\d{3})(\d{0,3})/, '$1.$2.$3');
                    } else if (value.length > 2) {
                        value = value.replace(/(\d{2})(\d{0,3})/, '$1.$2');
                    }
                }
                
                this.value = value;
            });
            
            // Consultar IPTU
            btnConsultar.addEventListener('click', function() {
                // Resetar mensagens e resultados
                errorMessage.style.display = 'none';
                resultadoCard.style.display = 'none';
                imoveisListCard.style.display = 'none';
                
                // Validar campos
                if (tipoBusca.value === 'imovel' && !codigoImovel.value) {
                    mostrarErro('Por favor, informe o código do imóvel.');
                    return;
                }
                
                if (tipoBusca.value === 'proprietario' && !documentoProprietario.value) {
                    mostrarErro('Por favor, informe o CPF ou CNPJ do proprietário.');
                    return;
                }
                
                // Mostrar loading
                loading.style.display = 'block';
                
                // Fazer a consulta
                if (tipoBusca.value === 'imovel') {
                    consultarPorImovel(codigoImovel.value);
                } else {
                    consultarPorProprietario(documentoProprietario.value);
                }
            });
            
            // Consultar por código do imóvel
            function consultarPorImovel(codigo) {
                fetch(`api_imovel.php?codigo=${codigo}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        loading.style.display = 'none';
                        exibirResultadoImovel(data);
                    })
                    .catch(error => {
                        loading.style.display = 'none';
                        mostrarErro('Imóvel não encontrado ou erro na consulta. Por favor, verifique o código informado.');
                        console.error('Erro na consulta:', error);
                    });
            }
            
            // Consultar por CPF/CNPJ do proprietário
            function consultarPorProprietario(documento) {
                fetch(`api_proprietario.php?id=${documento}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        loading.style.display = 'none';
                        
                        if (data.total_imoveis === 0) {
                            mostrarErro('Nenhum imóvel encontrado para este proprietário.');
                            return;
                        }
                        
                        // Armazenar imóveis encontrados
                        imoveisEncontrados = data.imoveis;
                        
                        // Exibir lista de imóveis
                        exibirListaImoveis(data);
                    })
                    .catch(error => {
                        loading.style.display = 'none';
                        mostrarErro('Proprietário não encontrado ou erro na consulta. Por favor, verifique o CPF/CNPJ informado.');
                        console.error('Erro na consulta:', error);
                    });
            }
            
            // Exibir resultado da consulta por imóvel
            function exibirResultadoImovel(data) {
                // Preencher dados do imóvel
                document.getElementById('codigoResult').textContent = data.imovel.codigo;
                document.getElementById('enderecoResult').textContent = data.imovel.endereco;
                document.getElementById('bairroResult').textContent = data.imovel.bairro;
                document.getElementById('areaResult').textContent = data.imovel.area;
                document.getElementById('tipoResult').textContent = data.imovel.tipo;
                
                // Preencher dados do proprietário
                document.getElementById('nomeProprietarioResult').textContent = data.proprietario.nome;
                document.getElementById('documentoProprietarioResult').textContent = data.proprietario.id;
                
                // Preencher dados do boleto
                document.getElementById('exercicioResult').textContent = data.boleto.exercicio;
                document.getElementById('valorResult').textContent = data.boleto.valor.toFixed(2);
                document.getElementById('vencimentoResult').textContent = formatarData(data.boleto.vencimento);
                document.getElementById('codigoBarrasResult').textContent = data.boleto.codigo_barras;
                
                // Configurar link do boleto
                document.getElementById('linkBoleto').href = `boletos/gerar_boleto.php?codigo=${data.imovel.codigo}`;
                
                // Exibir card de resultado
                resultadoCard.style.display = 'block';
            }
            
            // Exibir lista de imóveis do proprietário
            function exibirListaImoveis(data) {
                // Limpar lista anterior
                imoveisList.innerHTML = '';
                
                // Adicionar informações do proprietário
                const proprietarioInfo = document.createElement('div');
                proprietarioInfo.className = 'mb-3';
                proprietarioInfo.innerHTML = `
                    <p><strong>Proprietário:</strong> ${data.proprietario.nome}</p>
                    <p><strong>${data.proprietario.tipo.toUpperCase()}:</strong> ${data.proprietario.id}</p>
                    <p><strong>Total de Imóveis:</strong> ${data.total_imoveis}</p>
                `;
                imoveisList.appendChild(proprietarioInfo);
                
                // Adicionar cada imóvel à lista
                data.imoveis.forEach((item, index) => {
                    const imovelItem = document.createElement('div');
                    imovelItem.className = 'imovel-item';
                    imovelItem.dataset.index = index;
                    imovelItem.innerHTML = `
                        <p><strong>Código:</strong> ${item.imovel.codigo}</p>
                        <p><strong>Endereço:</strong> ${item.imovel.endereco}</p>
                        <p><strong>Tipo:</strong> ${item.imovel.tipo}</p>
                        <p><strong>Valor IPTU:</strong> R$ ${item.boleto.valor.toFixed(2)}</p>
                    `;
                    
                    // Adicionar evento de clique
                    imovelItem.addEventListener('click', function() {
                        // Remover seleção anterior
                        const itensSelecionados = document.querySelectorAll('.imovel-item.selected');
                        itensSelecionados.forEach(item => item.classList.remove('selected'));
                        
                        // Adicionar seleção ao item clicado
                        this.classList.add('selected');
                        
                        // Armazenar índice do imóvel selecionado
                        imovelSelecionado = parseInt(this.dataset.index);
                        
                        // Habilitar botão de detalhes
                        btnVerDetalhes.disabled = false;
                    });
                    
                    imoveisList.appendChild(imovelItem);
                });
                
                // Exibir card de lista de imóveis
                imoveisListCard.style.display = 'block';
                
                // Resetar seleção
                imovelSelecionado = null;
                btnVerDetalhes.disabled = true;
            }
            
            // Evento para ver detalhes do imóvel selecionado
            btnVerDetalhes.addEventListener('click', function() {
                if (imovelSelecionado !== null && imoveisEncontrados.length > 0) {
                    const item = imoveisEncontrados[imovelSelecionado];
                    
                    // Criar objeto no formato esperado pela função exibirResultadoImovel
                    const data = {
                        imovel: item.imovel,
                        boleto: item.boleto,
                        proprietario: {
                            nome: document.querySelector('#imoveisList p:first-child strong').nextSibling.textContent.trim(),
                            id: document.querySelector('#imoveisList p:nth-child(2) strong').nextSibling.textContent.trim()
                        }
                    };
                    
                    // Exibir detalhes do imóvel
                    exibirResultadoImovel(data);
                }
            });
            
            // Função auxiliar para mostrar mensagem de erro
            function mostrarErro(mensagem) {
                errorMessage.textContent = mensagem;
                errorMessage.style.display = 'block';
            }
            
            // Função auxiliar para formatar data
            function formatarData(dataString) {
                const data = new Date(dataString);
                return data.toLocaleDateString('pt-BR');
            }
        });
    </script>
</body>
</html>
