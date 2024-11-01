// Função para capturar o ID do orçamento da URL
function getOrcamentoIdFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    const id = urlParams.get('id');
    console.log("ID do orçamento:", id);
    return id;
}

// Função para formatar e exibir os dados do orçamento com o novo layout
function displayOrcamentoData(data, precosData, orcamentoId) {
    console.log("Iniciando exibição de dados do orçamento.");
    const container = document.getElementById('orcamento-container');

    // Informações do lead
    const leadInfo = `
        <div class="section">
            <h2 style="text-align:center !important;">👋 Olá, ${data.LeadInfo.nome}!</h2><br>
            <p style="color:white;text-align:center !important;">Parabéns por este importante passo em direção a um atendimento ao cliente mais moderno e eficiente!<br><br>Você está prestes a conferir uma solução sob medida para você que transformará:<br>1️⃣ O relacionamento com seus clientes<br>2️⃣ A eficiência do seu negócio<br>3️⃣ A imagem da sua marca<br>4️⃣ Os seus resultados 🚀</p>
            <p style="text-align:center !important;"><strong>Dados Pessoais:</strong></p>
            <p style="text-align:center !important;">Email: ${data.LeadInfo.email}<br>WhatsApp: ${data.LeadInfo.whatsapp}</p>
            <p style="text-align:center !important;"><small>ID: ${orcamentoId}<br><i>(Válido por 7 dias)</i></small></p>
        </div>`;

    // Resumo Geral do Orçamento
    const resumoGeral = `
        <br>
        <div class="section">
            <h3>📊 Resumo Geral do Orçamento</h3>
            ${data.ResumoGeral.TextoResumo}
        </div>`;

    // Novo cabeçalho para detalhes dos itens configurados
    const detalhesItensConfigurados = `
        <br>
        <div class="section">
            <h3>🛠️ Detalhes dos Itens Configurados</h3>
        </div>`;

    // Preparação das variáveis
    let conversaComIa = '';
    let redesSociais = '';
    let apiPublica = '';
    let conexaoPersonalizada = '';
    let multimidia = '';
    let suporteMelhoriaContinua = '';

    // Função auxiliar para organizar os detalhes de cada item configurado
    function formatItemSection(item, description, personalization) {
        const tempoEmDias = (item.Tempo / 8).toFixed(0); // Assuming 8 hours per workday
        return `
            <tr>
                <td><strong>${item.Nome}</strong><br><span style="color:#ababab">${item.DescricaoPrincipal}</span><br><strong>Personalização:</strong> ${item.DescricaoPersonalizada}</td>
                <td>${item.Custo.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) || 'N/A'}</td>
                <td>${tempoEmDias} dia(s)</td>
            </tr>`;
    }

    function formatSupportItemSection(item) {
        return `
            <tr>
                <td><strong>${item.Modulo}</strong></td>
                <td><strong>${item.Nivel}</strong><br><span>${item.DescricaoCustomizacao}</span></td>
                <td>R$ ${item.Custo || 'N/A'}</td>
            </tr>`;
    }
    // Preparar cada seção
    data.ItensConfigurados.forEach(item => {
        let nome = item.Nome || 'Nome não disponível.';
        let descricao = item.DescricaoPrincipal || 'Descrição não disponível.';
        let personalizacao = item.DescricaoPersonalizada || "Nenhuma descrição personalizada.";

        if (item.Categoria == "Implementacao") {
            if (item.Modulo === "ConversaComIA") {
                const tempoEmDias = (item.Tempo / 8).toFixed(0); // Assuming 8 hours per workday
                conversaComIa += `
                <br>
                <div class="section">
                    <h4>${nome}</h4>
                    <p>${descricao}</p>
                    <p style="color:white"><strong>Personalização:</strong> <i>${personalizacao}</i></p>
                    <table>
                        <tr><td><strong>Nível de Personalização</strong></td><td><strong>${item.Nivel}</strong><br><span style="color:#ababab">${item.DescricaoCustomizacao}</span></td></tr>
                        <tr><td><strong>Preço</strong></td><td>${item.Custo.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) || 'N/A'}</td></tr>
                        <tr><td><strong>Prazo de Entrega</strong></td><td>${tempoEmDias} dia(s)</td></tr>
                    </table>
                </div>`;
            }
            if (item.Modulo === "Conectado") {
                if (item.Item.includes("RedesSociais")) {
                    const tempoEmDias = (item.Tempo / 8).toFixed(0); // Assuming 8 hours per workday
                    redesSociais += `
                        <tr>
                            <td><strong>${item.Nome}</strong><br><span style="color:$ababab">${item.Descricao}</span></td>
                            <td>${item.Custo.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) || 'N/A'}</td>
                            <td>${tempoEmDias || 'N/A'} dia(s)</td>
                        </tr>`;
                } else if (item.Item.includes("APIPublica")) {
                    const tempoEmDias = (item.Tempo / 8).toFixed(0); // Assuming 8 hours per workday
                    apiPublica += `
                        <tr>
                            <td><strong>${item.DescricaoPersonalizada}</strong><br><span style="color:#ababab"><strong>Nível de complexidade: ${item.Nivel}</strong><br>${item.DescricaoCustomizacao}</span></td>
                            <td>${item.Custo.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) || 'N/A'}</td>
                            <td>${tempoEmDias|| 'N/A'} dia(s)</td>
                        </tr>`;
                } else if (item.Item.includes("ConexaoPersonalizada")) {
                    conexaoPersonalizada += `
                        <div class="section">
                            <p style="color:white"><strong>Personalização:</strong> ${personalizacao}</p>
                            <p><strong>🚨Atenção:</strong> ATENÇÃO: Este orçamento inclui a criação de uma Conexão Personalizada, cujos preços e prazos não são tabelados. Entre em contato para obter estas informações e completar este orçamento agora mesmo.</p>
                        </div>`;
                }
            }
            if (item.Modulo === "Multimidia") {
                multimidia += formatItemSection(item, personalizacao, item.Nivel);
            }
        }
        if (item.Categoria === "Manutencao") {
            suporteMelhoriaContinua += formatSupportItemSection(item);
        }


    });

    // Combine all sections into the container
    container.innerHTML = leadInfo + resumoGeral + detalhesItensConfigurados + conversaComIa;

    // Add Redes Sociais section if not empty
    if (redesSociais) {
        container.innerHTML += `
            <br>
            <h4>${precosData.Conectado.Nome}</h4>
            <p>${precosData.Conectado.Descricao}</p>
            <h5>⚙ Redes Sociais</h5>
            <p>${precosData.Conectado.RedesSociais.Descricao}</p>
            <table>
                <thead>
                    <tr><th>Descrição</th><th>Preço</th><th>Prazo de Entrega</th></tr>
                </thead>
                <tbody>${redesSociais}</tbody>
            </table>`;
    }

    // Add API Pública section if not empty
    if (apiPublica) {
        container.innerHTML += `
            <br>
            <h5>${precosData.Conectado.APIPublica.Nome}</h5>
            <p>${precosData.Conectado.APIPublica.Descricao}</p>
            <table>
                <thead>
                    <tr><th>Descrição</th><th>Preço</th><th>Prazo de Entrega</th></tr>
                </thead>
                <tbody>${apiPublica}</tbody>
            </table>`;
    }

    // Add Conexão Personalizada section if not empty
    if (conexaoPersonalizada) {
        container.innerHTML += `
            <br>
            <h5>${precosData.Conectado.ConexaoPersonalizada.Nome}</h5>
            <p>${precosData.Conectado.ConexaoPersonalizada.Descricao}</p>
            ${conexaoPersonalizada}`;
    }

    // Add Multimidia section if not empty
    if (multimidia) {
        container.innerHTML += `
            <br>
            <h4>${precosData.Multimidia.Nome}</h4>
            <p>${precosData.Multimidia.Descricao}</p>
            <table>
                <thead>
                    <tr><th>Descrição</th><th>Preço</th><th>Prazo de Entrega</th></tr>
                </thead>
                <tbody>${multimidia}</tbody>
            </table>`;
    }

    // Add Suporte e Melhoria Contínua section
    if (suporteMelhoriaContinua) {
        container.innerHTML += `
            <br>
            <h4>${precosData.ConversaComIA.SuporteMelhoriaContinua.Nome}</h4>
            <table>
                <thead>
                    <tr><th>Módulo</th><th>Nível</th><th>Preço</th></tr>
                </thead>
                <tbody>${suporteMelhoriaContinua}</tbody>
            </table>`;
    }
}

// Função para buscar o JSON do orçamento e exibir
function fetchOrcamentoData(orcamentoId) {
    const url = `https://iaturbo.com.br/wp-content/uploads/scripts/precos/orcamentos/${orcamentoId}.json`;
    const precosUrl = 'https://iaturbo.com.br/wp-content/uploads/scripts/precos/chatbots_iaturbo_precos.json';

    console.log("Buscando dados do orçamento e dos preços...");
    Promise.all([fetch(url), fetch(precosUrl)])
        .then(responses => {
            if (!responses[0].ok || !responses[1].ok) throw new Error('Dados não encontrados');
            return Promise.all(responses.map(res => res.json()));
        })
        .then(([data, precosData]) => {
            console.log("Dados do orçamento e preços recebidos com sucesso.");
            displayOrcamentoData(data, precosData, orcamentoId);
        })
        .catch(error => {
            document.getElementById('orcamento-container').innerHTML = `<p>Erro: ${error.message}</p>`;
            console.error("Erro ao buscar dados:", error);
        });
}

// Executa as funções ao carregar a página
document.addEventListener('DOMContentLoaded', () => {
    const orcamentoId = getOrcamentoIdFromUrl();
    if (orcamentoId) {
        fetchOrcamentoData(orcamentoId);
    } else {
        document.getElementById('orcamento-container').innerHTML = '<p>ID do orçamento não encontrado na URL.</p>';
        console.warn("ID do orçamento não encontrado na URL.");
    }
});
