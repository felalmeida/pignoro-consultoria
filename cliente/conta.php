<?php
/** Dados de acesso do usuário logado (cliente ou administrador) e troca de senha. */
declare(strict_types=1);
require __DIR__ . '/../app/inicio.php';

$User = CurrentUser();
if ($User === null)
    Redirect('index.php');

PageHead('Minha conta', 'conta.php');
?>
<h1>Minha conta</h1>
<dl class="facts card-box">
    <div><dt>Nome</dt><dd><?= E($User['nome']) ?></dd></div>
    <div><dt>E-mail de acesso</dt><dd><?= E($User['email']) ?></dd></div>
    <div><dt>Impressão digital da chave</dt><dd><code><?= E(Fingerprint($User['chave_publica'])) ?></code></dd></div>
</dl>
<p class="hint">A impressão digital identifica a chave que protege os seus arquivos. Se quiser, confira por telefone se a Pignoro vê a mesma sequência.</p>

<h2>Trocar a senha</h2>
<form id="form-senha" class="pform card-box" data-iter="<?= KDF_ITER ?>" novalidate>
    <label class="pfield pfield-wide"><span>Senha atual</span><input type="password" name="atual" autocomplete="current-password" required></label>
    <label class="pfield"><span>Nova senha (mínimo de 12 caracteres)</span><input type="password" name="senha" autocomplete="new-password" minlength="12" required></label>
    <label class="pfield"><span>Repita a nova senha</span><input type="password" name="confirma" autocomplete="new-password" minlength="12" required></label>
    <div class="pform-foot"><button class="btn btn-primary" type="submit">Trocar a senha</button></div>
    <p class="form-msg" role="status" aria-live="polite"></p>
</form>
<?php
PageFoot();
