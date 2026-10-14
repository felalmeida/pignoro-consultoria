# Pignoro Consultoria

Site institucional publicado em https://pignoro.felipealmeidaconsultoria.com.br/.

Página única em HTML, CSS e JavaScript, sem etapa de build. O código de servidor é PHP sem
framework: o `contato.php`, que envia o formulário por e-mail, e a área do cliente em `cliente/`.

## Estrutura

| Caminho | Conteúdo |
| --- | --- |
| `index.html` | Todo o texto do site |
| `assets/css/site.css` | Visual (cores no bloco `:root`) |
| `assets/js/config.js` | Link de agenda e Google Analytics |
| `assets/js/site.js` | Menu, formulário e animações |
| `contato.php` | Recebe o formulário e envia o e-mail |
| `config/config.php` | Destino e remetente do e-mail, limites contra spam |
| `.htaccess` | Cabeçalhos de segurança, cache e bloqueios |
| `cliente/` | Área do cliente e, em `cliente/admin/`, a administração |
| `cliente/api.php` | Chamadas do JavaScript: login, chaves e arquivos cifrados |
| `app/` | Código comum: banco, sessão, layout e envio de e-mail (`email.php`). Bloqueada para a web |
| `app/boasvindas.php` | E-mail de boas-vindas enviado ao criar um cliente |
| `assets/js/portal.js` | Criptografia no navegador: senha, chaves, envio e download |
| `bin/criar-admin.php` | Cria um administrador pela linha de comando |
| `arquivos/` | Arquivos cifrados dos clientes, uma pasta por cliente (`arquivos/<id>/`). Bloqueada para a web e fora do Git |

## Publicação

1. Copie o conteúdo do repositório para a raiz do site (Apache com PHP 8).
2. Remova o `index.php` antigo da raiz, se existir.
3. Confira `config/config.php`: o remetente deve ser um endereço do próprio domínio.
4. O formulário guarda o segredo de assinatura, contadores e tokens usados em `config/dados`
   (ou na pasta indicada em `pasta_dados`, em `config/config.php`). Essa pasta precisa pertencer
   ao usuário do PHP e ter permissão 0700:

   ```
   mkdir -p config/dados && chown www-data: config/dados && chmod 700 config/dados
   ```

   Sem isso o formulário responde "indisponível" e registra o motivo no log de erros do PHP.
   Em servidor que não seja Apache, a pasta `config/` precisa ser bloqueada na configuração dele.
5. Envio de e-mail: copie `config/smtp.exemplo.php` para `config/smtp.php` e preencha com a conta
   e a senha de app do Gmail (instruções no próprio arquivo). Deixe o arquivo com permissão 600 e
   dono igual ao usuário do PHP. Sem `smtp.php`, o site usa a função `mail()` do servidor.
6. Os cabeçalhos de segurança do `.htaccess` dependem do módulo `mod_headers` (`a2enmod headers`).

## Teste local

```
php -S 127.0.0.1:8765
```

O envio do formulário falha localmente se a máquina não tiver servidor de e-mail; o restante funciona.

## Agenda online

Com `agendaUrl` vazio em `assets/js/config.js`, os botões "Agendar" abrem o formulário com data e
período de preferência. Preenchendo com um link (Calendly, Google Agenda, Cal.com), os botões
passam a abrir esse link.

## Cache

O Cloudflare guarda CSS e JavaScript por algumas horas. Ao alterar `site.css`, `site.js` ou
`config.js`, aumente o número em `?v=` nas três referências do `index.html`, para os visitantes
receberem a versão nova na hora.

## Área do cliente

Fica em `/cliente/`. Clientes trocam mensagens com a Pignoro, mantêm o cadastro (empresa ou fundo,
pessoas de contato, créditos para cobrança e operações) e baixam arquivos. Administradores criam os
clientes, respondem mensagens, mudam a situação de créditos e operações e enviam arquivos.

### Instalação

1. O PHP precisa da extensão SQLite: `apt install php8.3-sqlite3` (e reiniciar o Apache ou o PHP-FPM).
2. O banco `portal.sqlite` é criado sozinho dentro da pasta de dados (`config/dados` ou `pasta_dados`),
   a mesma do formulário de contato e com as mesmas exigências.
   Os arquivos cifrados ficam em `arquivos/`, na raiz do projeto (ou em `pasta_arquivos`, em
   `config/config.php`), numa pasta por cliente cujo nome é o id dele: `arquivos/12/` guarda os
   arquivos do cliente 12. O id aparece no endereço da ficha do cliente (`cliente.php?id=12`).
   A pasta precisa pertencer ao usuário do PHP, com permissão 700, e não pode ser um link simbólico:

   ```
   chown -R www-data: arquivos && chmod 700 arquivos
   ```

   Em Docker, monte `arquivos/` como volume com essa permissão, para os arquivos sobreviverem à
   recriação do contêiner.
3. O site precisa estar em HTTPS: fora dele o navegador não oferece as funções de criptografia.
4. Envio de arquivos de até 50 MB: o arquivo cifrado sobe em partes de 512 KB, então os limites
   padrão do PHP (`upload_max_filesize`, `post_max_size`) e de proxies não precisam ser alterados.
   A pasta temporária do PHP (`upload_tmp_dir` ou `/tmp`) precisa aceitar gravação pelo usuário do
   servidor web: o PHP guarda nela todo corpo de requisição acima de 16 KB e, sem isso, descarta os dados.
5. Crie o primeiro administrador, com o mesmo usuário do servidor web:

   ```
   sudo -u www-data php bin/criar-admin.php "Nome" email@dominio https://endereco-do-site/
   ```

   O comando mostra um link para a pessoa definir a senha. Rodar de novo com o mesmo e-mail gera
   um link de redefinição.

### E-mail de boas-vindas

Ao criar um cliente, o site envia a ele um e-mail de boas-vindas com o link de primeiro acesso, pelo
mesmo caminho do formulário de contato: `config/smtp.php` quando existe, senão o `mail()` do servidor.
Se o envio falhar, o cliente é criado do mesmo jeito e o link aparece na tela para ser enviado por
outro canal. O botão "Reenviar e-mail de boas-vindas", na ficha do cliente, gera um link novo e
reenvia a mensagem enquanto ele não tiver feito o primeiro acesso.

### Como a proteção funciona

- **Senha.** O navegador deriva da senha (PBKDF2-SHA-256, 600 mil iterações) dois valores: um de
  autenticação, que o servidor guarda com `password_hash`, e uma chave que nunca sai do navegador.
  O servidor não recebe a senha.
- **Chaves.** No primeiro acesso o navegador gera um par RSA-OAEP de 3072 bits. A chave privada é
  cifrada com a chave derivada da senha antes de ir para o servidor.
- **Arquivos.** O administrador cifra cada arquivo no navegador com uma chave AES-256-GCM própria.
  Essa chave é cifrada com a chave pública do cliente e dos administradores. O servidor guarda
  conteúdo, nome e tipo do arquivo já cifrados e não tem como abri-los.
- **Download.** A função `baixarArquivoDecifrado`, em `assets/js/portal.js`, baixa o arquivo cifrado,
  decifra no navegador e entrega o arquivo aberto.
- **Mensagens e cadastros** ficam no SQLite sem criptografia de ponta a ponta.

### O que isso implica

- **Senha esquecida não se recupera.** O administrador gera um link de redefinição na tela do cliente.
  Quando o cliente cria a nova senha, ganha chaves novas, e os arquivos antigos ficam bloqueados até o
  administrador abrir a aba Arquivos do cliente e clicar em "Liberar para o cliente".
- **Administrador que esquece a senha** perde o acesso aos arquivos enviados antes. Os clientes
  continuam abrindo os deles. Vale ter mais de um administrador.
- **Limite da proteção.** O JavaScript que cifra é servido pelo próprio site. Quem controlar o
  servidor pode trocar esse código e capturar senhas dali em diante; arquivos já guardados continuam
  ilegíveis sem a senha. A impressão digital da chave, em "Minha conta" e na tela do cliente, permite
  conferir por telefone que a chave pública não foi trocada.
- **Backup.** Copie juntos a pasta de dados (`portal.sqlite`) e a pasta `arquivos/`: um não serve sem o outro.

### Teste local

```
php -S 127.0.0.1:8765
php bin/criar-admin.php "Nome" email@dominio http://127.0.0.1:8765/
```

Em `127.0.0.1` e `localhost` o navegador aceita a criptografia mesmo sem HTTPS.
