<?php
/**
 * Arquivos do cliente. A lista e o download são montados por assets/js/portal.js,
 * que decifra os nomes e o conteúdo no navegador.
 */
declare(strict_types=1);
require __DIR__ . '/../app/inicio.php';

$User = RequireRole('cliente');

PageHead(T('Arquivos'), 'arquivos.php');
?>
<h1><?= E(T('Arquivos')) ?></h1>
<p class="page-intro"><?= E(T('Documentos enviados pela Pignoro. Eles ficam cifrados no servidor e só são abertos aqui, no seu navegador.')) ?> <a href="ajuda.php"><?= E(T('Como funciona')) ?></a></p>
<form id="form-destravar" class="pform card-box" hidden novalidate>
    <p class="pfield-wide"><?= E(T('Para abrir os arquivos neste navegador, confirme a sua senha.')) ?></p>
    <label class="pfield pfield-wide"><span><?= E(T('Senha')) ?></span><input type="password" name="senha" autocomplete="current-password" required></label>
    <div class="pform-foot"><button class="btn btn-primary" type="submit"><?= E(T('Abrir arquivos')) ?></button></div>
    <p class="form-msg" role="status" aria-live="polite"></p>
</form>
<div id="arquivos" data-cliente="<?= (int) $User['id'] ?>" data-papel="cliente"><p class="empty"><?= E(T('Carregando...')) ?></p></div>
<?php
PageFoot();
