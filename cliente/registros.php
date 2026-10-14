<?php
/** Contatos, créditos e operações do cliente: registros.php?tipo=contatos|creditos|operacoes */
declare(strict_types=1);
require __DIR__ . '/../app/inicio.php';
require __DIR__ . '/../app/registros.php';

$User = RequireRole('cliente');
$Tipo = is_string($_GET['tipo'] ?? null) ? $_GET['tipo'] : '';
$Def = RegistroDef($Tipo);
$Self = 'registros.php?tipo=' . $Tipo;
$Find = fn(int $v_Id) => Row("SELECT * FROM {$Def['tabela']} WHERE id = ? AND usuario_id = ?", [$v_Id, $User['id']]);

$Values = [];
$EditId = (int) ($_GET['editar'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    CheckCsrf();
    $Id = (int) ($_POST['id'] ?? 0);
    $Row = $Id > 0 ? $Find($Id) : null;
    if ($Id > 0 && ($Row === null || !RegistroEditavel($Def, $Row))) {
        Flash('erro', 'Este registro não pode mais ser alterado. Envie uma mensagem à Pignoro se precisar corrigi-lo.');
        Redirect($Self);
    }
    if (($_POST['acao'] ?? '') === 'excluir') {
        Query("DELETE FROM {$Def['tabela']} WHERE id = ? AND usuario_id = ?", [$Id, $User['id']]);
        Flash('ok', 'Registro excluído.');
        Redirect($Self);
    }
    [$Values, $Error] = RegistroLer($Def, $_POST);
    if ($Error === '') {
        RegistroSalvar($Def, (int) $User['id'], $Values, $Id);
        Flash('ok', 'Registro salvo.');
        Redirect($Self);
    }
    // com erro, a página é mostrada de novo com o que foi digitado
    Flash('erro', $Error);
    $Values = array_filter($_POST, 'is_string');
    $EditId = $Id;
} elseif ($EditId > 0) {
    $Values = $Find($EditId) ?? [];
    if (!$Values || !RegistroEditavel($Def, $Values))
        Redirect($Self);
}

$Rows = Query("SELECT * FROM {$Def['tabela']} WHERE usuario_id = ? ORDER BY id DESC", [$User['id']])->fetchAll();

PageHead($Def['titulo'], $Self);
echo '<h1>' . E($Def['titulo']) . '</h1><p class="page-intro">' . E($Def['intro']) . '</p>';

RegistroTabela($Def, $Rows, function (array $v_Row) use ($Def, $Self): string {
    if (!RegistroEditavel($Def, $v_Row))
        return '';
    return '<a class="pbtn" href="' . E($Self . '&editar=' . $v_Row['id']) . '#formulario">Alterar</a>'
        . '<form method="post" action="' . E($Self) . '" data-confirmar="Excluir este registro?">' . CsrfField()
        . '<input type="hidden" name="id" value="' . (int) $v_Row['id'] . '"><input type="hidden" name="acao" value="excluir">'
        . '<button class="pbtn pbtn-danger" type="submit">Excluir</button></form>';
});

echo '<h2 id="formulario">' . E($EditId > 0 ? 'Alterar registro' : $Def['novo']) . '</h2>'
    . '<form class="pform card-box" method="post" action="' . E($Self) . '">' . CsrfField()
    . '<input type="hidden" name="id" value="' . $EditId . '">';
RegistroCampos($Def, $Values);
echo '<div class="pform-foot"><button class="btn btn-primary" type="submit">Salvar</button>'
    . ($EditId > 0 ? '<a class="pbtn" href="' . E($Self) . '">Cancelar</a>' : '') . '</div></form>';
PageFoot();
