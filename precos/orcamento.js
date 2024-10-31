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
            <h2>👋 Olá, ${data.LeadInfo.nome}!</h2>
            <p>Aqui está orçamento detalhado do seu chatbot. 🔍</p>
            <p style="color:#ababab! important;"><small>ID: ${orcamentoId} <i>(Válido por 7 dias)</i></small></p>
            <p><strong>Dados Pessoais:</strong></p>
            <p style="color:#ababab! important;">Email: ${data.LeadInfo.email}<br>WhatsApp: ${data.LeadInfo.whatsapp}</p>
        </div>`;

    // Resumo Geral do Orçamento
    const resumoGeral = `
        <div class="section">
            <h3>📊 Resumo Geral do Orçamento</h3>
            <p><i>${data.ResumoGeral.TextoResumo}</i></p>
        </div>`;

    // Novo cabeçalho para detalhes dos itens configurados
    const detalhesItensConfigurados = `
        <div class="section">
            <h3>🛠️ Detalhes dos Itens Configurados</h3>
        </div>`;

    // Preparação das variáveis
    let conversaComIa = '';
    let redesSociais = '';
    let apiPublica = '';
    let apiSobMedida = '';
    let multimidia = '';
    let suporteMonitoramento = '';

    // Função auxiliar para organizar os detalhes de cada item configurado
    function formatItemSection(item, description, personalization) {
        return `
            <tr>
                <td>${item.Nome}</td>
                <td>${description}</td>
                <td>R$ ${item.Custo || 'N/A'}</td>
                <td>${item.Tempo || 'N/A'} horas úteis</td>
            </tr>`;
    }

    function formatSupportItemSection(item) {
        return `
            <tr>
                <td>${item.Modulo}</td>
                <td>${item.Nivel} - <span style="color:#ababab! important;">${item.DescricaoCustomizacao}</span></td>
                <td>R$ ${item.Custo || 'N/A'}</td>
            </tr>`;
    }
    // Preparar cada seção
    data.ItensConfigurados.forEach(item => {
        let descricao = item.DescricaoPrincipal || 'Descrição não disponível.';
        let personalizacao = item.DescricaoPersonalizada || "Nenhuma descrição personalizada.";

        if (item.Categoria == "Implementacao") {
            if (item.Modulo === "ConversaComIA") {
                conversaComIa += `
                <div class="section">
                    <h4 style="color:#43d9ea;">💬 Conversa Com IA</h4>
                    <p style="color:#ababab! important;">${descricao}</p>
                    <p><strong>Personalização:</strong> ${personalizacao}</p>
                    <table>
                        <tr><td><strong>Nível de Personalização:</strong></td><td>${item.Nivel} - ${item.DescricaoCustomizacao}</td></tr>
                        <tr><td><strong>Preço:</strong></td><td>R$ ${item.Custo || 'N/A'}</td></tr>
                        <tr><td><strong>Tempo de Implementação:</strong></td><td>${item.Tempo || 'N/A'} horas</td></tr>
                    </table>
                </div>`;
            }
            if (item.Modulo === "Conectado") {
                if (item.Item.includes("RedesSociais")) {
                    redesSociais += `
                        <tr>
                            <td>${item.Descricao}</td>
                            <td>R$ ${item.Custo || 'N/A'}</td>
                            <td>${item.Tempo || 'N/A'} horas</td>
                        </tr>`;
                } else if (item.Item.includes("APIPublica")) {
                    apiPublica += `
                        <tr>
                            <td>${item.DescricaoPersonalizada}</td>
                            <td>R$ ${item.Custo || 'N/A'}</td>
                            <td>${item.Tempo || 'N/A'} horas</td>
                        </tr>`;
                } else if (item.Item.includes("APISobMedida")) {
                    apiSobMedida += `
                        <div class="section">
                            <p><strong>Personalização:</strong> ${personalizacao}</p>
                            <p><strong>Alerta:</strong> Preço e prazo sob consulta.</p>
                        </div>`;
                }
            }
            if (item.Modulo === "Multimidia") {
                multimidia += formatItemSection(item, personalizacao, item.Nivel);
            }
        }
        if (item.Categoria === "Manutencao") {
            suporteMonitoramento += formatSupportItemSection(item);
        }


    });

    // Combine all sections into the container
    container.innerHTML = leadInfo + resumoGeral + detalhesItensConfigurados + conversaComIa;

    // Add Redes Sociais section if not empty
    if (redesSociais) {
        container.innerHTML += `
            <h4 style="color:#43d9ea;">🌐 Conectado</h4>
            <p style="color:#ababab! important;">${precosData.Conectado.Descricao}</p>
            <h5>⚙ Redes Sociais</h5>
            <p style="color:#ababab! important;">${precosData.Conectado.RedesSociais.Descricao}</p>
            <table>
                <thead>
                    <tr><th>Descrição</th><th>Preço</th><th>Tempo de Implementação</th></tr>
                </thead>
                <tbody>${redesSociais}</tbody>
            </table>`;
    }

    // Add API Pública section if not empty
    if (apiPublica) {
        container.innerHTML += `
            <h5>⚙ APIs Públicas</h5>
            <p style="color:#ababab! important;">${precosData.Conectado.APIPublica.Descricao}</p>
            <table>
                <thead>
                    <tr><th>Descrição</th><th>Preço</th><th>Tempo de Implementação</th></tr>
                </thead>
                <tbody>${apiPublica}</tbody>
            </table>`;
    }

    // Add API Sob Medida section if not empty
    if (apiSobMedida) {
        container.innerHTML += `
            <h5>⚙ API Sob Medida</h5>
            <p style="color:#ababab! important;">${precosData.Conectado.APISobMedida.Descricao}</p>
            ${apiSobMedida}`;
    }

    // Add Multimidia section if not empty
    if (multimidia) {
        container.innerHTML += `
            <h4 style="color:#43d9ea;">🎥 Multimídia</h4>
            <p style="color:#ababab! important;">${precosData.Multimidia.Descricao}</p>
            <table>
                <thead>
                    <tr><th>Componente</th><th>Descrição</th><th>Preço</th><th>Tempo de Implementação</th></tr>
                </thead>
                <tbody>${multimidia}</tbody>
            </table>`;
    }

    // Add Suporte e Monitoramento Contínuo section
    if (suporteMonitoramento) {
        container.innerHTML += `
            <h4 style="color:#43d9ea;">🛡️ Suporte e Monitoramento Contínuo</h4>
            <table>
                <thead>
                    <tr><th>Módulo</th><th>Nível</th><th>Preço</th></tr>
                </thead>
                <tbody>${suporteMonitoramento}</tbody>
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
