<?php
/** Página inicial do cliente: resumo do que há em cada área. */
declare(strict_types=1);
require __DIR__ . '/../app/inicio.php';

$User = RequireRole('cliente');
$Id = (int) $User['id'];
$Count = fn(string $v_Sql) => (int) Query($v_Sql, [$Id])->fetchColumn();

$Cards = [
    ['mensagens.php', 'Mensagens', $Count('SELECT COUNT(*) FROM mensagens WHERE cliente_id = ? AND autor_id <> cliente_id AND lida_em IS NULL'), 'não lidas'],
    ['arquivos.php', 'Arquivos', $Count('SELECT COUNT(*) FROM arquivos WHERE cliente_id = ?'), 'disponíveis'],
    ['registros.php?tipo=creditos', 'Créditos para cobrança', $Count('SELECT COUNT(*) FROM creditos WHERE usuario_id = ?'), 'cadastrados'],
    ['registros.php?tipo=operacoes', 'Operações', $Count('SELECT COUNT(*) FROM operacoes WHERE usuario_id = ?'), 'cadastradas'],
    ['registros.php?tipo=contatos', 'Pessoas de contato', $Count('SELECT COUNT(*) FROM contatos WHERE usuario_id = ?'), 'cadastradas'],
];
$Empresa = Row('SELECT razao_social FROM empresas WHERE usuario_id = ?', [$Id]);

PageHead('Início', 'painel.php');
echo '<h1>Olá, ' . E($User['nome']) . '</h1>';
if ($Empresa === null || $Empresa['razao_social'] === '')
    echo '<p class="notice notice-aviso">Seu cadastro ainda está incompleto. <a href="cadastro.php">Preencher os dados da empresa ou fundo</a>.</p>';
echo '<ul class="tiles">';
foreach ($Cards as [$Href, $Label, $Number, $Unit])
    echo '<li><a class="tile" href="' . E($Href) . '"><span class="tile-label">' . E($Label) . '</span>'
        . '<strong>' . $Number . '</strong><span class="tile-unit">' . E($Unit) . '</span></a></li>';
echo '</ul>';
PageFoot();
