<?php
/** Página inicial do cliente: resumo do que há em cada área. */
declare(strict_types=1);
require __DIR__ . '/../app/inicio.php';

$User = RequireRole('cliente');
$Id = (int) $User['id'];
$Count = fn(string $v_Sql) => (int) Query($v_Sql, [$Id])->fetchColumn();

$Cards = [
    ['mensagens.php', T('Mensagens'), $Count('SELECT COUNT(*) FROM mensagens WHERE cliente_id = ? AND autor_id <> cliente_id AND lida_em IS NULL'), T('não lidas')],
    ['arquivos.php', T('Arquivos'), $Count('SELECT COUNT(*) FROM arquivos WHERE cliente_id = ?'), T('disponíveis')],
    ['registros.php?tipo=creditos', T('Créditos para cobrança'), $Count('SELECT COUNT(*) FROM creditos WHERE usuario_id = ?'), T('cadastrados')],
    ['registros.php?tipo=operacoes', T('Operações'), $Count('SELECT COUNT(*) FROM operacoes WHERE usuario_id = ?'), T('cadastradas')],
    ['registros.php?tipo=contatos', T('Pessoas de contato'), $Count('SELECT COUNT(*) FROM contatos WHERE usuario_id = ?'), T('cadastradas')],
];
$Empresa = Row('SELECT razao_social FROM empresas WHERE usuario_id = ?', [$Id]);

PageHead(T('Início'), 'painel.php');
echo '<h1>' . E(T('Olá, {nome}', ['nome' => $User['nome']])) . '</h1>';
if ($Empresa === null || $Empresa['razao_social'] === '')
    echo '<p class="notice notice-aviso">' . E(T('Seu cadastro ainda está incompleto.')) . ' <a href="cadastro.php">' . E(T('Preencher os dados da empresa ou fundo')) . '</a>.</p>';
echo '<ul class="tiles">';
foreach ($Cards as [$Href, $Label, $Number, $Unit])
    echo '<li><a class="tile" href="' . E($Href) . '"><span class="tile-label">' . E($Label) . '</span>'
        . '<strong>' . $Number . '</strong><span class="tile-unit">' . E($Unit) . '</span></a></li>';
echo '</ul>';
PageFoot();
