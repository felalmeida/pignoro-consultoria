/*
 * Área do cliente: senha, chaves e arquivos, tudo cifrado e decifrado aqui no navegador.
 *
 * Senha     PBKDF2-SHA-256 gera 64 bytes. Os 32 primeiros autenticam no servidor; os 32 últimos
 *           viram a chave AES que protege a chave privada. A senha em si nunca é enviada.
 * Chaves    Cada usuário tem um par RSA-OAEP de 3072 bits. O servidor guarda a chave pública
 *           e a chave privada já cifrada.
 * Arquivos  Cada arquivo tem uma chave AES-256-GCM própria, cifrada com a chave pública de cada
 *           pessoa que pode abri-lo. O servidor só vê conteúdo e nome cifrados.
 */
(function () {
    'use strict';

    var doc = document;
    var api = doc.body.getAttribute('data-api');
    var csrfMeta = doc.querySelector('meta[name="csrf"]');
    var csrf = csrfMeta ? csrfMeta.content : '';
    var subtle = window.crypto && window.crypto.subtle;
    var RSA = { name: 'RSA-OAEP', hash: 'SHA-256' };
    var SENHA_MINIMA = 12;

    function $(sel, root) { return (root || doc).querySelector(sel); }

    // Textos do idioma da página: o servidor manda as traduções em data-textos (vazio em português).
    var textos = {};
    try { textos = JSON.parse(doc.body.getAttribute('data-textos') || '{}'); } catch (e) { textos = {}; }
    function t(pt, vars) {
        var texto = textos[pt] || pt;
        Object.keys(vars || {}).forEach(function (k) { texto = texto.split('{' + k + '}').join(vars[k]); });
        return texto;
    }

    // --- utilidades ---

    function toB64(buf) {
        var bytes = new Uint8Array(buf);
        var bin = '';
        for (var i = 0; i < bytes.length; i += 0x8000)
            bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
        return btoa(bin);
    }

    function fromB64(str) {
        var bin = atob(str);
        var bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return bytes;
    }

    function random(n) { return window.crypto.getRandomValues(new Uint8Array(n)); }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(0) + ' KB';
        return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB';
    }

    function setMsg(form, kind, text) {
        var el = $('.form-msg', form);
        if (!el) return;
        el.className = 'form-msg' + (kind ? ' is-' + kind : '');
        el.textContent = text;
    }

    function setBusy(form, busy) {
        var btn = $('button[type="submit"]', form);
        if (btn) btn.disabled = busy;
    }

    function el(tag, className, text) {
        var node = doc.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    // Chamada ao servidor. `dados` (objeto ou FormData) faz a chamada ser POST.
    async function call(acao, dados, query) {
        var url = api + '?acao=' + acao + (query ? '&' + query : '');
        var opts = { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF': csrf } };
        if (dados) {
            var body = dados;
            if (!(dados instanceof FormData)) {
                body = new FormData();
                Object.keys(dados).forEach(function (k) { body.append(k, dados[k]); });
            }
            opts.method = 'POST';
            opts.body = body;
        }
        var resp = await fetch(url, opts);
        var data = await resp.json().catch(function () { return {}; });
        if (!resp.ok) throw new Error(data.erro || t('Não foi possível concluir. Tente de novo em instantes.'));
        return data;
    }

    // --- senha e chave privada ---

    async function derivar(senha, sal, iter) {
        var base = await subtle.importKey('raw', new TextEncoder().encode(senha.normalize('NFKC')), 'PBKDF2', false, ['deriveBits']);
        var bits = new Uint8Array(await subtle.deriveBits(
            { name: 'PBKDF2', hash: 'SHA-256', salt: fromB64(sal), iterations: iter }, base, 512));
        return {
            auth: toB64(bits.slice(0, 32)),
            kek: await subtle.importKey('raw', bits.slice(32), 'AES-GCM', false, ['encrypt', 'decrypt'])
        };
    }

    // Abre a chave privada guardada no servidor. Falha se a senha estiver errada.
    async function abrirPrivada(kek, priv, iv) {
        var pkcs8 = await subtle.decrypt({ name: 'AES-GCM', iv: fromB64(iv) }, kek, fromB64(priv));
        return { pkcs8: pkcs8, chave: await subtle.importKey('pkcs8', pkcs8, RSA, false, ['decrypt']) };
    }

    async function cifrarPrivada(kek, pkcs8) {
        var iv = random(12);
        return { iv: toB64(iv), priv: toB64(await subtle.encrypt({ name: 'AES-GCM', iv: iv }, kek, pkcs8)) };
    }

    // A chave privada aberta fica no IndexedDB deste navegador, sem poder ser exportada,
    // para o cliente não redigitar a senha a cada página. Ela é apagada ao sair.
    function banco() {
        return new Promise(function (resolve, reject) {
            var req = indexedDB.open('pignoro', 1);
            req.onupgradeneeded = function () { req.result.createObjectStore('chaves'); };
            req.onsuccess = function () { resolve(req.result); };
            req.onerror = function () { reject(req.error); };
        });
    }

    function transacao(modo, fn) {
        return banco().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction('chaves', modo);
                var req = fn(tx.objectStore('chaves'));
                tx.oncomplete = function () { resolve(req && req.result); };
                tx.onerror = tx.onabort = function () { reject(tx.error); };
            });
        });
    }

    function guardarPrivada(uid, chave) {
        return transacao('readwrite', function (s) { return s.put({ uid: uid, chave: chave }, 'privada'); })
            .catch(function () { /* sem IndexedDB a senha é pedida de novo na tela de arquivos */ });
    }

    function lerPrivada(uid) {
        return transacao('readonly', function (s) { return s.get('privada'); })
            .then(function (reg) { return reg && reg.uid === uid ? reg.chave : null; })
            .catch(function () { return null; });
    }

    function apagarPrivada() {
        return transacao('readwrite', function (s) { return s.clear(); }).catch(function () {});
    }

    function conferirSenhaNova(form) {
        var senha = form.elements.senha.value;
        if (senha.length < SENHA_MINIMA) return t('A senha precisa ter pelo menos {n} caracteres.', { n: SENHA_MINIMA });
        if (senha !== form.elements.confirma.value) return t('As duas senhas não são iguais.');
        return '';
    }

    // --- entrar ---

    var formEntrar = $('#form-entrar');
    if (formEntrar) formEntrar.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        var email = formEntrar.elements.email.value.trim();
        var senha = formEntrar.elements.senha.value;
        if (!email || !senha) return setMsg(formEntrar, 'error', t('Informe o e-mail e a senha.'));
        setBusy(formEntrar, true);
        setMsg(formEntrar, '', t('Entrando...'));
        try {
            var kdf = await call('sal', { email: email });
            var derivada = await derivar(senha, kdf.sal, kdf.iter);
            var conta = await call('entrar', { email: email, auth: derivada.auth });
            var privada = await abrirPrivada(derivada.kek, conta.priv, conta.iv);
            await guardarPrivada(conta.uid, privada.chave);
            window.location.href = new URL(conta.destino, new URL(api, window.location.href)).href;
        } catch (err) {
            setMsg(formEntrar, 'error', err.message);
            setBusy(formEntrar, false);
        }
    });

    // --- primeiro acesso: cria a senha e o par de chaves ---

    var formAtivar = $('#form-ativar');
    if (formAtivar) formAtivar.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        var problema = conferirSenhaNova(formAtivar);
        if (problema) return setMsg(formAtivar, 'error', problema);
        setBusy(formAtivar, true);
        setMsg(formAtivar, '', t('Gerando as suas chaves. Isto leva alguns segundos...'));
        try {
            var sal = toB64(random(16));
            var derivada = await derivar(formAtivar.elements.senha.value, sal, Number(formAtivar.getAttribute('data-iter')));
            var par = await subtle.generateKey(
                { name: 'RSA-OAEP', modulusLength: 3072, publicExponent: new Uint8Array([1, 0, 1]), hash: 'SHA-256' },
                true, ['encrypt', 'decrypt']);
            var cifrada = await cifrarPrivada(derivada.kek, await subtle.exportKey('pkcs8', par.privateKey));
            await call('ativar', {
                t: formAtivar.getAttribute('data-token'),
                sal: sal,
                auth: derivada.auth,
                pub: toB64(await subtle.exportKey('spki', par.publicKey)),
                priv: cifrada.priv,
                iv: cifrada.iv
            });
            await apagarPrivada();
            window.location.href = 'index.php';
        } catch (err) {
            setMsg(formAtivar, 'error', err.message);
            setBusy(formAtivar, false);
        }
    });

    // --- trocar a senha: a chave privada é aberta com a senha atual e cifrada de novo com a nova ---

    var formSenha = $('#form-senha');
    if (formSenha) formSenha.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        var problema = conferirSenhaNova(formSenha);
        if (problema) return setMsg(formSenha, 'error', problema);
        setBusy(formSenha, true);
        setMsg(formSenha, '', t('Trocando a senha...'));
        try {
            var conta = await call('minha_chave');
            var atual = await derivar(formSenha.elements.atual.value, conta.sal, conta.iter);
            var privada;
            try {
                privada = await abrirPrivada(atual.kek, conta.priv, conta.iv);
            } catch (e) {
                throw new Error(t('A senha atual não confere.'));
            }
            var sal = toB64(random(16));
            var nova = await derivar(formSenha.elements.senha.value, sal, Number(formSenha.getAttribute('data-iter')));
            var cifrada = await cifrarPrivada(nova.kek, privada.pkcs8);
            await call('trocar_senha', { auth_atual: atual.auth, sal: sal, auth: nova.auth, priv: cifrada.priv, iv: cifrada.iv });
            formSenha.reset();
            setMsg(formSenha, 'ok', t('Senha trocada.'));
        } catch (err) {
            setMsg(formSenha, 'error', err.message);
        }
        setBusy(formSenha, false);
    });

    // --- sair: apaga a chave privada deste navegador antes de encerrar a sessão ---

    var formSair = $('form[data-sair]');
    if (formSair) formSair.addEventListener('submit', function (ev) {
        ev.preventDefault();
        apagarPrivada().then(function () { formSair.submit(); });
    });

    // --- confirmações de formulários comuns ---

    doc.addEventListener('submit', function (ev) {
        var pergunta = ev.target.getAttribute && ev.target.getAttribute('data-confirmar');
        if (pergunta && !window.confirm(pergunta)) ev.preventDefault();
    });

    // --- arquivos ---

    var lista = $('#arquivos');
    var formDestravar = $('#form-destravar');
    var formEnviar = $('#form-enviar');
    var ehAdmin = lista && lista.getAttribute('data-papel') === 'admin';
    var clienteId = lista ? lista.getAttribute('data-cliente') : '';
    var minhaPrivada = null;

    // Abre a chave AES de um arquivo com a chave privada de quem está logado.
    async function abrirChaveDoArquivo(arquivo) {
        var bruta = await subtle.decrypt(RSA, minhaPrivada, fromB64(arquivo.chave));
        return { bruta: bruta, aes: await subtle.importKey('raw', bruta, 'AES-GCM', false, ['decrypt']) };
    }

    /**
     * Baixa o arquivo cifrado, decifra no navegador e entrega o download já aberto.
     * `arquivo` é um item da lista devolvida pelo servidor (id, iv, chave) e `meta` o nome e o tipo já decifrados.
     */
    async function baixarArquivoDecifrado(arquivo, meta) {
        var resp = await fetch(api + '?acao=baixar&id=' + arquivo.id, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        if (!resp.ok) throw new Error(t('Não foi possível baixar o arquivo.'));
        var cifrado = await resp.arrayBuffer();
        var chave = await abrirChaveDoArquivo(arquivo);
        var aberto;
        try {
            aberto = await subtle.decrypt({ name: 'AES-GCM', iv: fromB64(arquivo.iv) }, chave.aes, cifrado);
        } catch (e) {
            throw new Error(t('O arquivo não pôde ser decifrado. Ele pode ter sido alterado no servidor.'));
        }
        var url = URL.createObjectURL(new Blob([aberto], { type: meta.tipo || 'application/octet-stream' }));
        var link = el('a');
        link.href = url;
        link.download = meta.nome || 'arquivo';
        doc.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
    }

    // Cifra a chave do arquivo para o cliente, depois que ele criou chaves novas.
    async function liberarParaCliente(arquivos, clientePub) {
        var pub = await subtle.importKey('spki', fromB64(clientePub), RSA, false, ['encrypt']);
        for (var i = 0; i < arquivos.length; i++) {
            var chave = await abrirChaveDoArquivo(arquivos[i]);
            await call('liberar', { id: arquivos[i].id, chave: toB64(await subtle.encrypt(RSA, pub, chave.bruta)) });
        }
    }

    function linhaDoArquivo(arquivo, meta) {
        var item = el('li', 'file');
        var info = el('div', 'file-info');
        info.appendChild(el('strong', '', meta ? meta.nome : t('Arquivo ainda não liberado para esta conta')));
        info.appendChild(el('span', '', (meta ? formatSize(meta.tamanho) + ' · ' : '') + t('enviado em {data}', { data: arquivo.data })));
        item.appendChild(info);

        var acoes = el('div', 'file-actions');
        var status = el('span', 'file-status');
        acoes.appendChild(status);
        if (ehAdmin && !arquivo.cliente_tem_chave) acoes.appendChild(el('span', 'tag tag-alert', t('Não liberado para o cliente')));
        if (meta) {
            var baixar = el('button', 'pbtn', t('Baixar'));
            baixar.type = 'button';
            baixar.addEventListener('click', async function () {
                baixar.disabled = true;
                status.textContent = t('Decifrando...');
                try {
                    await baixarArquivoDecifrado(arquivo, meta);
                    status.textContent = '';
                } catch (err) {
                    status.textContent = err.message;
                }
                baixar.disabled = false;
            });
            acoes.appendChild(baixar);
        }
        if (ehAdmin) {
            var excluir = el('button', 'pbtn pbtn-danger', t('Excluir'));
            excluir.type = 'button';
            excluir.addEventListener('click', async function () {
                if (!window.confirm(t('Excluir este arquivo? O cliente deixa de vê-lo.'))) return;
                try {
                    await call('excluir_arquivo', { id: arquivo.id });
                    carregarArquivos();
                } catch (err) {
                    status.textContent = err.message;
                }
            });
            acoes.appendChild(excluir);
        }
        item.appendChild(acoes);
        return item;
    }

    async function carregarArquivos() {
        var dados = await call('arquivos', null, 'cliente=' + encodeURIComponent(clienteId));
        lista.textContent = '';
        if (!dados.arquivos.length) {
            lista.appendChild(el('p', 'empty', t('Nenhum arquivo até agora.')));
            return;
        }

        var pendentes = [];
        var ul = el('ul', 'files');
        for (var i = 0; i < dados.arquivos.length; i++) {
            var arquivo = dados.arquivos[i];
            var meta = null;
            if (arquivo.chave) {
                try {
                    var chave = await abrirChaveDoArquivo(arquivo);
                    meta = JSON.parse(new TextDecoder().decode(
                        await subtle.decrypt({ name: 'AES-GCM', iv: fromB64(arquivo.meta_iv) }, chave.aes, fromB64(arquivo.meta))));
                    if (!arquivo.cliente_tem_chave) pendentes.push(arquivo);
                } catch (e) {
                    meta = null;
                }
            }
            ul.appendChild(linhaDoArquivo(arquivo, meta));
        }

        if (ehAdmin && pendentes.length && dados.cliente_pub) {
            var aviso = el('div', 'notice notice-aviso');
            aviso.appendChild(el('p', '', t('{n} arquivo(s) ainda não liberado(s) para a chave atual do cliente.', { n: pendentes.length })));
            var botao = el('button', 'pbtn', t('Liberar para o cliente'));
            botao.type = 'button';
            botao.addEventListener('click', async function () {
                botao.disabled = true;
                botao.textContent = t('Liberando...');
                try {
                    await liberarParaCliente(pendentes, dados.cliente_pub);
                    carregarArquivos();
                } catch (err) {
                    botao.textContent = err.message;
                }
            });
            aviso.appendChild(botao);
            lista.appendChild(aviso);
        } else if (!ehAdmin && dados.arquivos.some(function (a) { return !a.chave; })) {
            lista.appendChild(el('p', 'notice notice-aviso',
                t('Alguns arquivos foram enviados antes da sua senha atual e aguardam liberação. Avise a Pignoro pelo e-mail info@pignoro.com.')));
        }
        lista.appendChild(ul);
    }

    function mostrarErroArquivos(err) {
        lista.textContent = '';
        lista.appendChild(el('p', 'notice notice-erro', err.message));
    }

    if (lista) (async function () {
        if (!subtle) return mostrarErroArquivos(new Error(t('Este navegador não permite criptografia nesta página. Use uma conexão HTTPS e um navegador atualizado.')));
        try {
            var conta = await call('minha_chave');
            minhaPrivada = await lerPrivada(conta.uid);
            if (minhaPrivada) return await carregarArquivos();

            // sem chave neste navegador: pede a senha para abri-la
            lista.hidden = true;
            formDestravar.hidden = false;
            formDestravar.addEventListener('submit', async function (ev) {
                ev.preventDefault();
                setBusy(formDestravar, true);
                setMsg(formDestravar, '', t('Abrindo...'));
                try {
                    var derivada = await derivar(formDestravar.elements.senha.value, conta.sal, conta.iter);
                    var privada;
                    try {
                        privada = await abrirPrivada(derivada.kek, conta.priv, conta.iv);
                    } catch (e) {
                        throw new Error(t('Senha incorreta.'));
                    }
                    minhaPrivada = privada.chave;
                    await guardarPrivada(conta.uid, minhaPrivada);
                    formDestravar.hidden = true;
                    lista.hidden = false;
                    await carregarArquivos();
                } catch (err) {
                    setMsg(formDestravar, 'error', err.message);
                }
                setBusy(formDestravar, false);
            });
        } catch (err) {
            mostrarErroArquivos(err);
        }
    })();

    // --- envio (administrador): cifra cada arquivo antes de sair do navegador ---

    // Manda o arquivo cifrado em partes pequenas. O servidor responde quanto já recebeu,
    // então uma parte repetida ou perdida no caminho só faz o envio retomar do ponto certo.
    async function enviarEmPartes(cifrado, aoAvancar) {
        var inicio = await call('enviar_inicio', { cliente: clienteId });
        var pos = 0;
        var falhas = 0;
        while (pos < cifrado.byteLength) {
            var resp = null;
            var data = {};
            try {
                resp = await fetch(api + '?acao=enviar_parte&envio=' + inicio.envio + '&pos=' + pos, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF': csrf, 'Content-Type': 'application/octet-stream' },
                    body: cifrado.slice(pos, pos + inicio.parte)
                });
                data = await resp.json().catch(function () { return {}; });
            } catch (e) {
                resp = null;
            }
            if (resp && typeof data.recebido === 'number' && data.recebido !== pos) {
                pos = data.recebido;
                falhas = 0;
                aoAvancar(pos / cifrado.byteLength);
            } else if (++falhas >= 4 || data.erro && resp.status !== 409) {
                throw new Error(data.erro || t('O envio foi interrompido. Verifique a conexão e tente de novo.'));
            }
        }
        return inicio.envio;
    }

    async function cifrarEEnviar(file, destinatarios, aoAvancar) {
        var chave = await subtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt']);
        var iv = random(12);
        var metaIv = random(12);
        var cifrado = await subtle.encrypt({ name: 'AES-GCM', iv: iv }, chave, await file.arrayBuffer());
        var meta = await subtle.encrypt({ name: 'AES-GCM', iv: metaIv }, chave,
            new TextEncoder().encode(JSON.stringify({ nome: file.name, tipo: file.type, tamanho: file.size })));
        var bruta = await subtle.exportKey('raw', chave);

        var chaves = {};
        for (var i = 0; i < destinatarios.length; i++) {
            var pub = await subtle.importKey('spki', fromB64(destinatarios[i].pub), RSA, false, ['encrypt']);
            chaves[destinatarios[i].id] = toB64(await subtle.encrypt(RSA, pub, bruta));
        }

        await call('enviar', {
            cliente: clienteId,
            envio: await enviarEmPartes(cifrado, aoAvancar),
            tamanho: cifrado.byteLength,
            iv: toB64(iv),
            meta: toB64(meta),
            meta_iv: toB64(metaIv),
            chaves: JSON.stringify(chaves)
        });
    }

    if (formEnviar) formEnviar.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        var arquivos = Array.prototype.slice.call(formEnviar.elements.arquivos.files);
        var maximo = Number(formEnviar.getAttribute('data-max'));
        if (!arquivos.length) return setMsg(formEnviar, 'error', t('Escolha pelo menos um arquivo.'));
        var grande = arquivos.filter(function (f) { return f.size > maximo || f.size === 0; })[0];
        if (grande) return setMsg(formEnviar, 'error', t('"{nome}" está vazio ou passa do limite de {limite}.', { nome: grande.name, limite: formatSize(maximo) }));

        setBusy(formEnviar, true);
        try {
            var destinatarios = (await call('destinatarios', null, 'cliente=' + encodeURIComponent(clienteId))).destinatarios;
            for (var i = 0; i < arquivos.length; i++) {
                var rotulo = t('Cifrando e enviando {i} de {n}: {nome}', { i: i + 1, n: arquivos.length, nome: arquivos[i].name });
                setMsg(formEnviar, '', rotulo);
                await cifrarEEnviar(arquivos[i], destinatarios, function (fracao) {
                    setMsg(formEnviar, '', rotulo + ' (' + Math.round(fracao * 100) + '%)');
                });
            }
            formEnviar.reset();
            setMsg(formEnviar, 'ok', arquivos.length === 1 ? t('Arquivo enviado.') : t('{n} arquivos enviados.', { n: arquivos.length }));
            if (minhaPrivada) await carregarArquivos();
        } catch (err) {
            setMsg(formEnviar, 'error', err.message);
        }
        setBusy(formEnviar, false);
    });

    if (!subtle && (formEntrar || formAtivar || formSenha)) {
        setMsg(formEntrar || formAtivar || formSenha, 'error',
            t('Este navegador não permite criptografia nesta página. Use uma conexão HTTPS e um navegador atualizado.'));
    }
})();
