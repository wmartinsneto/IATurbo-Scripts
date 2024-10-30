// Função para capturar o ID do orçamento da URL
function getOrcamentoIdFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    const id = urlParams.get('id');
    console.log("ID do orçamento:", id);
    return id;
}

// Função para formatar e exibir os dados do orçamento com o novo layout
function displayOrcamentoData(data, precosData) {
    console.log("Iniciando exibição de dados do orçamento.");
    const container = document.getElementById('orcamento-container');

    // Informações do lead
    const leadInfo = `
        <div class="section">
            <h2>👋 Olá, ${data.LeadInfo.nome}!</h2>
            <p>Aqui está o seu orçamento detalhado. 🔍</p>
            <p><strong>Email:</strong> ${data.LeadInfo.email}<br><strong>WhatsApp:</strong> ${data.LeadInfo.whatsapp}</p>
        </div>`;

    // Resumo Geral do Orçamento
    const resumoGeral = `
        <div class="section">
            <h3>📊 Resumo Geral do Orçamento</h3>
            <p>${data.ResumoGeral.TextoResumo}</p>
        </div>`;

    // Preparação das variáveis
    let conversaComIa = '';
    let conectado = '';
    let multimidia = '';
    let suporteMonitoramento = '';

    // Função auxiliar para organizar os detalhes de cada item configurado
    function formatItemSection(item, description, personalization) {
        return `
            <tr>
                <td>${item.Item.split(" > ").pop()}</td>
                <td>${description}</td>
                <td>R$ ${item.Custo || 'N/A'}</td>
                <td>${item.Tempo || 'N/A'} horas</td>
            </tr>`;
    }

    // Preparar cada seção
    data.ItensConfigurados.forEach(item => {
        let descricao = item.DescricaoPrincipal || 'Descrição não disponível.';
        let personalizacao = item.DescricaoPersonalizada || "Nenhuma descrição personalizada.";
        
        if (item.Modulo === "ConversaComIA") {
            conversaComIa += `
                <div class="section">
                    <h4>💬 Conversa Com IA</h4>
                    <p><strong>Descrição:</strong> ${descricao}</p>
                    <p><strong>Personalização:</strong> ${personalizacao}</p>
                    <table>
                        <tr><td><strong>Nível:</strong></td><td>${item.Nivel || 'Padrão'}</td></tr>
                        <tr><td><strong>Custo:</strong></td><td>R$ ${item.Custo || 'N/A'}</td></tr>
                        <tr><td><strong>Tempo de Implementação:</strong></td><td>${item.Tempo || 'N/A'} horas</td></tr>
                    </table>
                </div>`;
        } else if (item.Modulo === "Conectado") {
            if (item.Item.includes("RedesSociais")) {
                conectado += formatItemSection(item, descricao, personalizacao);
            } else if (item.Item.includes("APIPublica")) {
                conectado += formatItemSection(item, personalizacao, item.Nivel);
            } else if (item.Item.includes("APISobMedida")) {
                conectado += `
                    <div class="section">
                        <p><strong>Descrição da API Sob Medida:</strong> ${descricao}</p>
                        <p><strong>Personalização:</strong> ${personalizacao}</p>
                        <p><strong>Alerta:</strong> Preço e prazo sob consulta.</p>
                    </div>`;
            }
        } else if (item.Modulo === "Multimidia") {
            multimidia += formatItemSection(item, personalizacao, item.Nivel);
        } else if (item.Categoria === "Manutencao") {
            suporteMonitoramento += formatItemSection(item, descricao, item.Nivel);
        }
    });

    // Combine all sections into the container
    container.innerHTML = leadInfo + resumoGeral + conversaComIa;

    // Add Conectado section if not empty
    if (conectado) {
        container.innerHTML += `
            <h4>🌐 Conectado</h4>
            <table>
                <thead>
                    <tr><th>Rede Social</th><th>Descrição</th><th>Custo</th><th>Tempo de Implementação</th></tr>
                </thead>
                <tbody>${conectado}</tbody>
            </table>`;
    }

    // Add Multimidia section if not empty
    if (multimidia) {
        container.innerHTML += `
            <h4>🎥 Multimídia</h4>
            <table>
                <thead>
                    <tr><th>Componente</th><th>Descrição</th><th>Custo</th><th>Tempo de Implementação</th></tr>
                </thead>
                <tbody>${multimidia}</tbody>
            </table>`;
    }

    // Add Suporte e Monitoramento Contínuo section
    container.innerHTML += `
        <h4>🛡️ Suporte e Monitoramento Contínuo</h4>
        <table>
            <thead>
                <tr><th>Item</th><th>Descrição</th><th>Custo</th></tr>
            </thead>
            <tbody>${suporteMonitoramento}</tbody>
        </table>`;
}