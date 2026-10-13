<?php
/**
 * Configuração do formulário de contato.
 * Esta pasta é bloqueada para acesso pela web (ver config/.htaccess).
 */
return [
    // Para onde as mensagens do site são enviadas.
    'destino' => 'contato@pignoroconsultoria.com.br',

    // Remetente técnico. Use um endereço do próprio domínio para não cair em spam.
    'remetente' => 'site@pignoroconsultoria.com.br',

    'nome_site' => 'Pignoro Consultoria',

    // Tempo mínimo (segundos) entre abrir o formulário e enviar. Robôs enviam na hora.
    'tempo_minimo' => 3,

    // Validade (segundos) do formulário aberto.
    'tempo_maximo' => 7200,

    // Limite de tentativas de envio por visitante dentro da janela (segundos).
    'limite_envios' => 8,
    'limite_janela' => 3600,

    // Limite de envios aceitos somando todos os visitantes, e de formulários abertos por visitante.
    'limite_total' => 40,
    'limite_tokens' => 30,

    // Pasta privada para o segredo de assinatura, contadores e tokens usados.
    // Vazio usa config/dados (a pasta config/ é bloqueada para a web pelo .htaccess).
    // A pasta precisa pertencer ao usuário do PHP e ter permissão 0700; o ideal é apontar
    // para um caminho fora da área pública do site. Se ela não puder ser usada com segurança,
    // o formulário recusa os envios e registra o motivo no log de erros.
    'pasta_dados' => '',

    // Redes do Cloudflare (https://www.cloudflare.com/ips/). O IP informado no cabeçalho
    // CF-Connecting-IP só é aceito quando a conexão chega de uma destas redes.
    'redes_cloudflare' => [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ],
];
