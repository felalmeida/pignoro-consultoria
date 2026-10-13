# Pignoro Consultoria

Site institucional da Pignoro Consultoria. O domínio `pignoroconsultoria.com.br` usado nos arquivos é provisório:
confirme-o em `index.html`, `robots.txt`, `sitemap.xml` e `config/config.php` antes de publicar.

Página única em HTML, CSS e JavaScript, sem etapa de build. O único código de servidor é o
`contato.php`, que envia o formulário por e-mail.

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
