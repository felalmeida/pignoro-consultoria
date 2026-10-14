<?php
/** Primeiro acesso ou redefinição de senha, a partir do link gerado pela Pignoro. */
declare(strict_types=1);
require __DIR__ . '/../app/inicio.php';

$Token = is_string($_GET['t'] ?? null) ? $_GET['t'] : '';
$User = preg_match('/^[0-9a-f]{64}$/', $Token) !== 1 ? null
    : Row('SELECT nome, email, senha_hash, idioma FROM usuarios WHERE ativacao_hash = ? AND ativacao_expira > ? AND ativo = 1', [hash('sha256', $Token), time()]);

// quem abre o link não deve continuar na sessão de outra pessoa
StartSession();
unset($_SESSION['uid'], $_SESSION['marca']);

// sem escolha feita no seletor, a página abre no idioma definido para o cliente
if ($User !== null && ValidLang($_GET['idioma'] ?? null) === null && ValidLang($_COOKIE['pignoro_idioma'] ?? null) === null)
    Lang($User['idioma']);

PageHead(T('Criar senha'));
echo '<section class="auth-card">';
if ($User === null) {
    echo '<h1>' . E(T('Link inválido')) . '</h1><p class="auth-intro">' . E(T('Este link não é mais válido. Peça um novo à Pignoro.')) . '</p>';
} else {
    ?>
    <h1><?= E(T('Criar senha')) ?></h1>
    <p class="auth-intro"><?= T('Olá, {nome}. Crie a senha de acesso para {email}.', ['nome' => E($User['nome']), 'email' => '<strong>' . E($User['email']) . '</strong>']) ?></p>
    <?php if ($User['senha_hash'] !== null): ?>
        <p class="notice notice-aviso"><?= E(T('Ao criar a nova senha, os arquivos que você já tinha ficam indisponíveis até a Pignoro liberá-los novamente.')) ?></p>
    <?php endif; ?>
    <form id="form-ativar" class="pform" data-token="<?= E($Token) ?>" data-iter="<?= KDF_ITER ?>" novalidate>
        <label class="pfield pfield-wide"><span><?= E(T('Nova senha (mínimo de 12 caracteres)')) ?></span>
            <input type="password" name="senha" autocomplete="new-password" minlength="12" required></label>
        <label class="pfield pfield-wide"><span><?= E(T('Repita a senha')) ?></span>
            <input type="password" name="confirma" autocomplete="new-password" minlength="12" required></label>
        <div class="pform-foot"><button class="btn btn-primary" type="submit"><?= E(T('Criar senha')) ?></button></div>
        <p class="form-msg" role="status" aria-live="polite"></p>
    </form>
    <p class="auth-help"><?= E(T('Guarde bem esta senha. Ela também protege os seus arquivos: nem a Pignoro consegue recuperá-la.')) ?></p>
    <?php
}
echo '</section>';
PageFoot();
