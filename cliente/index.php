<?php
/** Entrada da área do cliente. A senha é derivada no navegador; ver assets/js/portal.js. */
declare(strict_types=1);
require __DIR__ . '/../app/inicio.php';

$User = CurrentUser();
if ($User !== null)
    Redirect($User['papel'] === 'admin' ? 'admin/index.php' : 'painel.php');

PageHead(T('Entrar'));
?>
<section class="auth-card">
    <h1><?= E(T('Área do cliente')) ?></h1>
    <p class="auth-intro"><?= E(T('Acesso restrito a clientes da Pignoro.')) ?></p>
    <form id="form-entrar" class="pform" novalidate>
        <label class="pfield pfield-wide"><span><?= E(T('E-mail')) ?></span>
            <input type="email" name="email" autocomplete="username" maxlength="150" required></label>
        <label class="pfield pfield-wide"><span><?= E(T('Senha')) ?></span>
            <input type="password" name="senha" autocomplete="current-password" required></label>
        <div class="pform-foot"><button class="btn btn-primary" type="submit"><?= E(T('Entrar')) ?></button></div>
        <p class="form-msg" role="status" aria-live="polite"></p>
    </form>
    <noscript><p class="notice notice-erro"><?= E(T('A área do cliente precisa de JavaScript, porque a senha e os arquivos são protegidos no próprio navegador.')) ?></p></noscript>
    <p class="auth-help"><?= E(T('Esqueceu a senha? Fale com a Pignoro para receber um novo link de acesso.')) ?></p>
</section>
<?php
PageFoot();
