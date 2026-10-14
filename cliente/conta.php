<?php
/** Dados de acesso do usuário logado (cliente ou administrador) e troca de senha. */
declare(strict_types=1);
require __DIR__ . '/../app/inicio.php';

$User = CurrentUser();
if ($User === null)
    Redirect('index.php');

PageHead(T('Minha conta'), 'conta.php');
?>
<h1><?= E(T('Minha conta')) ?></h1>
<dl class="facts card-box">
    <div><dt><?= E(T('Nome')) ?></dt><dd><?= E($User['nome']) ?></dd></div>
    <div><dt><?= E(T('E-mail de acesso')) ?></dt><dd><?= E($User['email']) ?></dd></div>
    <div><dt><?= E(T('Idioma')) ?></dt><dd><?= E(IDIOMAS[ValidLang($User['idioma']) ?? 'pt']) ?></dd></div>
    <div><dt><?= E(T('Impressão digital da chave')) ?></dt><dd><code><?= E(Fingerprint($User['chave_publica'])) ?></code></dd></div>
</dl>
<p class="hint"><?= E(T('A impressão digital identifica a chave que protege os seus arquivos. Se quiser, confira por telefone se a Pignoro vê a mesma sequência.')) ?></p>

<h2><?= E(T('Trocar a senha')) ?></h2>
<form id="form-senha" class="pform card-box" data-iter="<?= KDF_ITER ?>" novalidate>
    <label class="pfield pfield-wide"><span><?= E(T('Senha atual')) ?></span><input type="password" name="atual" autocomplete="current-password" required></label>
    <label class="pfield"><span><?= E(T('Nova senha (mínimo de 12 caracteres)')) ?></span><input type="password" name="senha" autocomplete="new-password" minlength="12" required></label>
    <label class="pfield"><span><?= E(T('Repita a nova senha')) ?></span><input type="password" name="confirma" autocomplete="new-password" minlength="12" required></label>
    <div class="pform-foot"><button class="btn btn-primary" type="submit"><?= E(T('Trocar a senha')) ?></button></div>
    <p class="form-msg" role="status" aria-live="polite"></p>
</form>
<?php
PageFoot();
