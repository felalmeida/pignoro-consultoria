<?php
/** Página inicial do cliente: arquivos disponíveis e situação do cadastro. */
declare(strict_types=1);
require __DIR__ . '/../app/inicio.php';

$User = RequireRole('cliente');
$Id = (int) $User['id'];
$Arquivos = (int) Query('SELECT COUNT(*) FROM arquivos WHERE cliente_id = ?', [$Id])->fetchColumn();
$Empresa = Row('SELECT razao_social FROM empresas WHERE usuario_id = ?', [$Id]);

PageHead(T('Início'), 'painel.php');
echo '<h1>' . E(T('Olá, {nome}', ['nome' => $User['nome']])) . '</h1>';
if ($Empresa === null || $Empresa['razao_social'] === '')
    echo '<p class="notice notice-aviso">' . E(T('Seu cadastro ainda está incompleto.')) . ' <a href="cadastro.php">' . E(T('Preencher os dados da empresa ou fundo')) . '</a>.</p>';
echo '<ul class="tiles"><li><a class="tile" href="arquivos.php"><span class="tile-label">' . E(T('Arquivos')) . '</span>'
    . '<strong>' . $Arquivos . '</strong><span class="tile-unit">' . E(T('disponíveis')) . '</span></a></li></ul>';
PageFoot();
