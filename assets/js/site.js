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
            'Não foi possível enviar agora. Tente novamente em alguns minutos.': 'Your message could not be sent right now. Please try again in a few minutes.'
        },
        es: {
            'Solicitar agendamento': 'Solicitar una reunión',
            'Enviar': 'Enviar',
            'Conte em poucas linhas o que gostaria de discutir.': 'Cuéntenos en pocas líneas lo que le gustaría conversar.',
            'Preencha nome, um e-mail válido e a mensagem.': 'Complete su nombre, un correo electrónico válido y el mensaje.',
            'Enviando...': 'Enviando...',
            'Pedido recebido. Vamos confirmar o horário por e-mail ou telefone.': 'Solicitud recibida. Confirmaremos el horario por correo electrónico o teléfono.',
            'Mensagem enviada. Respondemos em até um dia útil.': 'Mensaje enviado. Respondemos en un plazo de un día hábil.',
            'Não foi possível enviar agora. Tente novamente em alguns minutos.': 'No fue posible enviar ahora. Inténtelo de nuevo en unos minutos.'
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

    function carregar(src) {
        var s = doc.createElement('script');
        s.async = true;
        s.src = src;
        doc.head.appendChild(s);
    }

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
    var posthogKey = medicaoPermitida ? String(cfg.posthogKey || '').trim() : '';
    if (/^phc_[A-Za-z0-9]+$/.test(posthogKey)) {
        var regiao = cfg.posthogRegiao === 'eu' ? 'eu' : 'us';
        var ph = window.posthog = window.posthog || [];
        ph._i = ph._i || [];
        ['capture', 'identify', 'register', 'reset', 'opt_in_capturing', 'opt_out_capturing', 'set_config'].forEach(function (m) {
            ph[m] = ph[m] || function () { ph.push([m].concat(Array.prototype.slice.call(arguments))); };
        });
        ph.init = function (k, o, n) { ph._i.push([k, o, n]); };
        ph.__SV = 1;
        ph.init(posthogKey, { api_host: 'https://' + regiao + '.i.posthog.com', person_profiles: 'identified_only' });
        var phScript = doc.createElement('script');
        phScript.async = true;
        phScript.crossOrigin = 'anonymous';
        phScript.src = 'https://' + regiao + '-assets.i.posthog.com/static/array.js';
        doc.head.appendChild(phScript);
    }

    function track(name, params) {
        params = params || {};
        if (usaGa4 && typeof window.gtag === 'function') window.gtag('event', name, params);
        if (usaGtm) window.dataLayer.push(Object.assign({ event: name }, params));
        if (window.posthog && typeof window.posthog.capture === 'function') window.posthog.capture(name, params);
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
