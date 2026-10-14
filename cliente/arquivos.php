<?php
/**
 * Arquivos do cliente. A lista e o download são montados por assets/js/portal.js,
 * que decifra os nomes e o conteúdo no navegador.
 */
declare(strict_types=1);
require __DIR__ . '/../app/inicio.php';

$User = RequireRole('cliente');

PageHead('Arquivos', 'arquivos.php');
?>
<h1>Arquivos</h1>
<p class="page-intro">Documentos enviados pela Pignoro. Eles ficam cifrados no servidor e só são abertos aqui, no seu navegador.</p>
<form id="form-destravar" class="pform card-box" hidden novalidate>
    <p class="pfield-wide">Para abrir os arquivos neste navegador, confirme a sua senha.</p>
    <label class="pfield pfield-wide"><span>Senha</span><input type="password" name="senha" autocomplete="current-password" required></label>
    <div class="pform-foot"><button class="btn btn-primary" type="submit">Abrir arquivos</button></div>
    <p class="form-msg" role="status" aria-live="polite"></p>
</form>
<div id="arquivos" data-cliente="<?= (int) $User['id'] ?>" data-papel="cliente"><p class="empty">Carregando...</p></div>
<?php
PageFoot();
