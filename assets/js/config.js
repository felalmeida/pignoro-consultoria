/* Ajustes do site que não exigem mexer no HTML. Ao alterar, aumente o ?v= de config.js nas páginas. */
window.SITE_CONFIG = {
    // Link de agenda online (Calendly, Google Agenda, Cal.com).
    // Vazio: o botão "Agendar" abre o formulário com data e período de preferência.
    agendaUrl: '',

    // ID de rastreio do Google: do Google Analytics 4 (G-XXXXXXXXXX) ou do
    // Google Tag Manager (GTM-XXXXXXX). Vazio desliga.
    analyticsId: '',

    // PostHog: chave do projeto (começa com phc_) e região em que a conta foi criada,
    // 'us' ou 'eu'. Vazio desliga.
    posthogKey: '',
    posthogRegiao: 'us',

    // Endereço da área do cliente, quando ela estiver num subdomínio próprio
    // (ex.: 'https://cliente.exemplo.com.br/'). Enquanto ela estiver no mesmo endereço do site,
    // Google e PostHog NÃO são carregados, mesmo com os IDs preenchidos: um script de terceiros
    // no mesmo endereço teria acesso à sessão e às chaves dos clientes e poderia abrir os arquivos.
    areaClienteUrl: 'https://cliente.pignoro.felipealmeidaconsultoria.com.br/'
};
