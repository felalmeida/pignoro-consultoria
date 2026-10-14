(function () {
    'use strict';

    var cfg = window.SITE_CONFIG || {};
    var doc = document;
    doc.documentElement.classList.add('js');

    function $(sel, root) { return (root || doc).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || doc).querySelectorAll(sel)); }

    // --- idioma: pt (padrão), en ou es, conforme o atributo lang da página ---
    var idioma = (doc.documentElement.lang || 'pt').slice(0, 2);
    var textos = {
        en: {
            'Solicitar agendamento': 'Request a meeting',
            'Enviar': 'Send',
            'Conte em poucas linhas o que gostaria de discutir.': 'Tell us in a few lines what you would like to discuss.',
            'Preencha nome, um e-mail válido e a mensagem.': 'Please fill in your name, a valid e-mail and the message.',
            'Enviando...': 'Sending...',
            'Pedido recebido. Vamos confirmar o horário por e-mail ou telefone.': 'Request received. We will confirm the time by e-mail or phone.',
            'Mensagem enviada. Respondemos em até um dia útil.': 'Message sent. We reply within one business day.',
            'Não foi possível enviar agora. Tente novamente em alguns minutos.': 'Your message could not be sent right now. Please try again in a few minutes.',
            'Aviso de cookies': 'Cookie notice',
            'Usamos cookies necessários para o site funcionar e, com a sua permissão, ferramentas de medição de audiência para entender como o site é usado. Você pode mudar a escolha a qualquer momento em "Preferências de cookies", no rodapé.': 'We use cookies that are necessary for the website to work and, with your permission, audience measurement tools to understand how the website is used. You can change your choice at any time under "Cookie preferences" in the footer.',
            'Saiba mais': 'Learn more',
            'Recusar': 'Decline',
            'Aceitar': 'Accept'
        },
        es: {
            'Solicitar agendamento': 'Solicitar una reunión',
            'Enviar': 'Enviar',
            'Conte em poucas linhas o que gostaria de discutir.': 'Cuéntenos en pocas líneas lo que le gustaría conversar.',
            'Preencha nome, um e-mail válido e a mensagem.': 'Complete su nombre, un correo electrónico válido y el mensaje.',
            'Enviando...': 'Enviando...',
            'Pedido recebido. Vamos confirmar o horário por e-mail ou telefone.': 'Solicitud recibida. Confirmaremos el horario por correo electrónico o teléfono.',
            'Mensagem enviada. Respondemos em até um dia útil.': 'Mensaje enviado. Respondemos en un plazo de un día hábil.',
            'Não foi possível enviar agora. Tente novamente em alguns minutos.': 'No fue posible enviar ahora. Inténtelo de nuevo en unos minutos.',
            'Aviso de cookies': 'Aviso de cookies',
            'Usamos cookies necessários para o site funcionar e, com a sua permissão, ferramentas de medição de audiência para entender como o site é usado. Você pode mudar a escolha a qualquer momento em "Preferências de cookies", no rodapé.': 'Usamos cookies necesarias para que el sitio funcione y, con su permiso, herramientas de medición de audiencia para entender cómo se usa el sitio. Puede cambiar su elección en cualquier momento en "Preferencias de cookies", en el pie de página.',
            'Saiba mais': 'Más información',
            'Recusar': 'Rechazar',
            'Aceitar': 'Aceptar'
        }
    };
    if (!textos[idioma]) idioma = 'pt';
    function t(pt) { return (textos[idioma] && textos[idioma][pt]) || pt; }

    // --- medição (opcional, ver config.js) ---
    // Só roda se a área do cliente estiver em outro endereço (origem): no mesmo endereço, um script
    // de terceiros alcançaria a sessão e as chaves dos clientes.
    var areaOrigem = location.origin;
    try { if (cfg.areaClienteUrl) areaOrigem = new URL(cfg.areaClienteUrl, location.href).origin; } catch (e) { areaOrigem = location.origin; }
    var medicaoPermitida = areaOrigem !== location.origin;
    if (!medicaoPermitida && (cfg.analyticsId || cfg.posthogKey) && window.console)
        console.warn('Medição desligada: a área do cliente está no mesmo endereço do site (ver areaClienteUrl em config.js).');

    // chaves de quem entrou na área do cliente quando ela ainda ficava neste endereço
    if (medicaoPermitida && window.indexedDB) {
        try { indexedDB.deleteDatabase('pignoro'); } catch (e) { /* sem IndexedDB, nada a apagar */ }
    }

    var googleId = medicaoPermitida ? String(cfg.analyticsId || '').trim() : '';
    var usaGa4 = /^G-[A-Z0-9]+$/.test(googleId);
    var usaGtm = /^GTM-[A-Z0-9]+$/.test(googleId);
    var posthogKey = medicaoPermitida ? String(cfg.posthogKey || '').trim() : '';
    var usaPosthog = /^phc_[A-Za-z0-9]+$/.test(posthogKey);
    var temMedicao = usaGa4 || usaGtm || usaPosthog;
    var medicaoAtiva = false;

    function carregar(src, anonimo) {
        var s = doc.createElement('script');
        s.async = true;
        if (anonimo) s.crossOrigin = 'anonymous';
        s.src = src;
        doc.head.appendChild(s);
    }

    // Carrega as ferramentas de medição. Só é chamada depois do consentimento do visitante.
    function iniciarMedicao() {
        if (medicaoAtiva) return;
        medicaoAtiva = true;
        window.dataLayer = window.dataLayer || [];
        if (usaGa4) {
            carregar('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(googleId));
            window.gtag = function () { window.dataLayer.push(arguments); };
            window.gtag('js', new Date());
            window.gtag('config', googleId);
        } else if (usaGtm) {
            window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
            carregar('https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(googleId));
        }

        // PostHog: fila de chamadas até a biblioteca carregar, como no trecho oficial de instalação
        if (usaPosthog) {
            var regiao = cfg.posthogRegiao === 'eu' ? 'eu' : 'us';
            var ph = window.posthog = window.posthog || [];
            ph._i = ph._i || [];
            ['capture', 'identify', 'register', 'reset', 'opt_in_capturing', 'opt_out_capturing', 'set_config'].forEach(function (m) {
                ph[m] = ph[m] || function () { ph.push([m].concat(Array.prototype.slice.call(arguments))); };
            });
            ph.init = function (k, o, n) { ph._i.push([k, o, n]); };
            ph.__SV = 1;
            ph.init(posthogKey, { api_host: 'https://' + regiao + '.i.posthog.com', person_profiles: 'identified_only' });
            carregar('https://' + regiao + '-assets.i.posthog.com/static/array.js', true);
        }
    }

    function track(name, params) {
        if (!medicaoAtiva) return;
        params = params || {};
        if (usaGa4 && typeof window.gtag === 'function') window.gtag('event', name, params);
        if (usaGtm) window.dataLayer.push(Object.assign({ event: name }, params));
        if (window.posthog && typeof window.posthog.capture === 'function') window.posthog.capture(name, params);
    }

    // --- consentimento de cookies ---
    // A escolha fica no armazenamento local do navegador. Sem escolha (ou se o navegador não deixar
    // guardar), o aviso aparece e nada de medição é carregado.
    var CHAVE_COOKIES = 'pignoro_cookies';

    function lerEscolha() {
        try {
            var v = JSON.parse(localStorage.getItem(CHAVE_COOKIES) || 'null');
            return v && v.versao === 1 ? v.escolha : null;
        } catch (e) {
            return null;
        }
    }

    function gravarEscolha(escolha) {
        try { localStorage.setItem(CHAVE_COOKIES, JSON.stringify({ escolha: escolha, versao: 1, data: new Date().toISOString() })); } catch (e) { /* sem armazenamento: o aviso volta na próxima visita */ }
    }

    // Ao recusar depois de ter aceitado: apaga os cookies e dados que as ferramentas deixaram.
    function apagarDadosDeMedicao() {
        var partes = location.hostname.split('.');
        var dominios = [''];
        for (var i = 0; i < partes.length - 1; i++) dominios.push('; domain=.' + partes.slice(i).join('.'));
        doc.cookie.split(';').forEach(function (c) {
            var nome = c.split('=')[0].trim();
            if (!/^(_ga|_gid|_gat|_gcl|ph_|__ph)/.test(nome)) return;
            dominios.forEach(function (d) { doc.cookie = nome + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' + d; });
        });
        [window.localStorage, window.sessionStorage].forEach(function (store) {
            try {
                Object.keys(store).forEach(function (k) { if (/^(ph_|__ph|_ga)/.test(k)) store.removeItem(k); });
            } catch (e) { /* armazenamento indisponível */ }
        });
    }

    var aviso = null;

    function mostrarAviso() {
        if (aviso) { aviso.hidden = false; $('button', aviso).focus(); return; }
        var termos = $('.footer-legal a[href$="termos/"]');
        aviso = doc.createElement('section');
        aviso.className = 'cookie-banner';
        aviso.setAttribute('role', 'region');
        aviso.setAttribute('aria-label', t('Aviso de cookies'));

        var texto = doc.createElement('p');
        texto.textContent = t('Usamos cookies necessários para o site funcionar e, com a sua permissão, ferramentas de medição de audiência para entender como o site é usado. Você pode mudar a escolha a qualquer momento em "Preferências de cookies", no rodapé.') + ' ';
        var saiba = doc.createElement('a');
        saiba.href = (termos ? termos.getAttribute('href') : 'termos/') + '#cookies';
        saiba.textContent = t('Saiba mais');
        texto.appendChild(saiba);
        aviso.appendChild(texto);

        var botoes = doc.createElement('div');
        botoes.className = 'cookie-actions';
        [['recusado', t('Recusar')], ['aceito', t('Aceitar')]].forEach(function (op) {
            var b = doc.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-small cookie-btn';
            b.textContent = op[1];
            b.addEventListener('click', function () {
                var antes = lerEscolha();
                gravarEscolha(op[0]);
                aviso.hidden = true;
                if (op[0] === 'aceito') iniciarMedicao();
                else if (medicaoAtiva || antes === 'aceito') { apagarDadosDeMedicao(); location.reload(); }
            });
            botoes.appendChild(b);
        });
        aviso.appendChild(botoes);
        doc.body.appendChild(aviso);
    }

    if (temMedicao) {
        var escolha = lerEscolha();
        if (escolha === 'aceito') iniciarMedicao();
        else if (escolha !== 'recusado') mostrarAviso();
        $all('[data-cookies]').forEach(function (p) {
            p.hidden = false;
            var b = $('button', p);
            if (b) b.addEventListener('click', mostrarAviso);
        });
    }

    // --- ano do rodapé ---
    $all('[data-year]').forEach(function (el) { el.textContent = new Date().getFullYear(); });

    // --- menu no celular ---
    var toggle = $('.nav-toggle');
    var nav = $('.site-nav');
    if (toggle && nav) {
        var setOpen = function (open) {
            nav.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };
        toggle.addEventListener('click', function () {
            setOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });
        nav.addEventListener('click', function (ev) {
            if (ev.target.closest('a')) setOpen(false);
        });
        doc.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') setOpen(false);
        });
    }

    // --- canais de contato ---
    $all('[data-channel]').forEach(function (a) {
        var kind = a.getAttribute('data-channel');
        a.addEventListener('click', function () { track('contato_canal', { canal: kind }); });
    });

    // --- formulário ---
    var form = $('#form-contato');
    var agendaRow = form ? $('.form-agenda', form) : null;
    var statusEl = form ? $('.form-status', form) : null;
    var submitBtn = form ? $('button[type="submit"]', form) : null;
    var token = null;

    function setMode(mode) {
        if (!form) return;
        var radio = $('input[name="tipo"][value="' + mode + '"]', form);
        if (radio) radio.checked = true;
        var agendar = mode === 'agendar';
        if (agendaRow) agendaRow.hidden = !agendar;
        if (submitBtn) submitBtn.textContent = agendar ? t('Solicitar agendamento') : t('Enviar');
        var msg = form.elements.mensagem;
        if (msg) msg.placeholder = agendar ? t('Conte em poucas linhas o que gostaria de discutir.') : '';
    }

    function loadToken() {
        if (token || !form) return;
        token = 'pending';
        fetch(form.getAttribute('action') + '?token=1&idioma=' + idioma, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) { token = data && data.token ? data.token : null; })
            .catch(function () { token = null; });
    }

    function setStatus(kind, text) {
        if (!statusEl) return;
        statusEl.className = 'form-status' + (kind ? ' is-' + kind : '');
        statusEl.textContent = text;
    }

    function validate() {
        var ok = true;
        var first = null;
        ['nome', 'email', 'mensagem'].forEach(function (name) {
            var el = form.elements[name];
            var value = el.value.trim();
            var valid = value.length > 0;
            if (valid && name === 'email') valid = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
            el.classList.toggle('is-invalid', !valid);
            el.setAttribute('aria-invalid', valid ? 'false' : 'true');
            if (!valid) { ok = false; first = first || el; }
        });
        if (first) first.focus();
        return ok;
    }

    if (form) {
        var dateInput = form.elements.data;
        if (dateInput) {
            var now = new Date();
            var pad = function (n) { return (n < 10 ? '0' : '') + n; };
            dateInput.min = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
        }

        $all('input[name="tipo"]', form).forEach(function (radio) {
            radio.addEventListener('change', function () { setMode(radio.value); });
        });

        form.addEventListener('focusin', loadToken, { once: true });

        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            if (!validate()) {
                setStatus('error', t('Preencha nome, um e-mail válido e a mensagem.'));
                return;
            }
            var data = new FormData(form);
            data.append('token', token && token !== 'pending' ? token : '');
            data.append('idioma', idioma);
            submitBtn.disabled = true;
            setStatus('', t('Enviando...'));

            fetch(form.getAttribute('action'), { method: 'POST', body: data, headers: { 'Accept': 'application/json' } })
                .then(function (r) {
                    return r.json().then(
                        function (body) { return { ok: r.ok, body: body }; },
                        function () { return { ok: false, body: {} }; }
                    );
                })
                .then(function (res) {
                    if (res.ok && res.body.ok) {
                        var agendar = data.get('tipo') === 'agendar';
                        form.reset();
                        setMode('mensagem');
                        token = null;
                        setStatus('ok', agendar
                            ? t('Pedido recebido. Vamos confirmar o horário por e-mail ou telefone.')
                            : t('Mensagem enviada. Respondemos em até um dia útil.'));
                        track('contato_formulario', { tipo: agendar ? 'agendar' : 'mensagem' });
                    } else {
                        setStatus('error', res.body.erro || t('Não foi possível enviar agora. Tente novamente em alguns minutos.'));
                    }
                })
                .catch(function () {
                    setStatus('error', t('Não foi possível enviar agora. Tente novamente em alguns minutos.'));
                })
                .then(function () { submitBtn.disabled = false; });
        });
    }

    // --- botões "Agendar" ---
    $all('[data-agendar]').forEach(function (a) {
        if (cfg.agendaUrl) {
            a.href = cfg.agendaUrl;
            a.target = '_blank';
            a.rel = 'noopener';
            a.addEventListener('click', function () { track('contato_canal', { canal: 'agenda' }); });
            return;
        }
        a.addEventListener('click', function () {
            setMode('agendar');
            loadToken();
            window.setTimeout(function () {
                if (form && form.elements.nome) form.elements.nome.focus({ preventScroll: true });
            }, 600);
        });
    });

    // --- entrada suave das seções ---
    var targets = $all('.section-head, .situations li, .card, .ai-card, .agro-block, .model, .steps li, .cloud, .about-copy, .about-block, .faq, .channels, .form');
    if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -8% 0px' });
        targets.forEach(function (el) { el.classList.add('reveal'); io.observe(el); });
    }
})();
