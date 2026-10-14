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
    posthogRegiao: 'us'

    // A medição vale só para as páginas públicas (site e termos). A área do cliente não
    // carrega nenhum código de terceiros, para não expor a criptografia dos arquivos.
};
