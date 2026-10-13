(function () {
    'use strict';

    var cfg = window.SITE_CONFIG || {};
    var doc = document;
    doc.documentElement.classList.add('js');

    function $(sel, root) { return (root || doc).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || doc).querySelectorAll(sel)); }

    // --- Google Analytics 4 (opcional) ---
    if (cfg.analyticsId) {
        var ga = doc.createElement('script');
        ga.async = true;
        ga.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(cfg.analyticsId);
        doc.head.appendChild(ga);
        window.dataLayer = window.dataLayer || [];
        window.gtag = function () { window.dataLayer.push(arguments); };
        window.gtag('js', new Date());
        window.gtag('config', cfg.analyticsId);
    }

    function track(name, params) {
        if (typeof window.gtag === 'function') window.gtag('event', name, params || {});
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
        if (submitBtn) submitBtn.textContent = agendar ? 'Solicitar agendamento' : 'Enviar';
        var msg = form.elements.mensagem;
        if (msg) msg.placeholder = agendar ? 'Conte em poucas linhas o que gostaria de discutir.' : '';
    }

    function loadToken() {
        if (token || !form) return;
        token = 'pending';
        fetch(form.getAttribute('action') + '?token=1', { headers: { 'Accept': 'application/json' } })
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
                setStatus('error', 'Preencha nome, um e-mail válido e a mensagem.');
                return;
            }
            var data = new FormData(form);
            data.append('token', token && token !== 'pending' ? token : '');
            submitBtn.disabled = true;
            setStatus('', 'Enviando...');

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
                            ? 'Pedido recebido. Vou confirmar o horário por e-mail ou telefone.'
                            : 'Mensagem enviada. Respondo em até um dia útil.');
                        track('contato_formulario', { tipo: agendar ? 'agendar' : 'mensagem' });
                    } else {
                        setStatus('error', res.body.erro || 'Não foi possível enviar agora. Tente novamente em alguns minutos.');
                    }
                })
                .catch(function () {
                    setStatus('error', 'Não foi possível enviar agora. Tente novamente em alguns minutos.');
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
