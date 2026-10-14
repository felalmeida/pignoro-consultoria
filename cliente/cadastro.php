<?php
/** Dados da empresa ou do fundo do cliente. */
declare(strict_types=1);
require __DIR__ . '/../app/inicio.php';

$User = RequireRole('cliente');
$Tipos = ['investidor' => 'Investidor profissional', 'fundo' => 'Fundo de investimento', 'empresa' => 'Empresa'];
// coluna => [rótulo, tamanho máximo]
$Campos = [
    'razao_social' => ['Razão social ou nome', 150], 'nome_fantasia' => ['Nome fantasia', 150], 'cnpj' => ['CNPJ ou CPF', 20],
    'telefone' => ['Telefone', 30], 'endereco' => ['Endereço', 200], 'cidade' => ['Cidade', 80], 'uf' => ['UF', 2], 'cep' => ['CEP', 10],
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    CheckCsrf();
    $Values = ['tipo' => isset($Tipos[$_POST['tipo'] ?? '']) ? $_POST['tipo'] : ''];
    foreach ($Campos as $Name => [$Label, $Max])
        $Values[$Name] = CleanLine(is_string($_POST[$Name] ?? null) ? $_POST[$Name] : '', $Max);
    $Values['uf'] = mb_strtoupper($Values['uf']);

    if ($Values['razao_social'] === '') {
        Flash('erro', 'Preencha a razão social ou o nome.');
    } else {
        $Cols = array_keys($Values);
        Query('INSERT INTO empresas (usuario_id, atualizado_em, ' . implode(', ', $Cols) . ') VALUES (?, ?' . str_repeat(', ?', count($Cols)) . ')
            ON CONFLICT (usuario_id) DO UPDATE SET atualizado_em = excluded.atualizado_em, '
            . implode(', ', array_map(fn($c) => "$c = excluded.$c", $Cols)),
            [$User['id'], time(), ...array_values($Values)]);
        Flash('ok', 'Cadastro salvo.');
    }
    Redirect('cadastro.php');
}

$Empresa = Row('SELECT * FROM empresas WHERE usuario_id = ?', [$User['id']]) ?? [];

PageHead('Cadastro', 'cadastro.php');
?>
<h1>Cadastro</h1>
<p class="page-intro">Dados da sua empresa ou fundo.</p>
<form class="pform card-box" method="post">
    <?= CsrfField() ?>
    <label class="pfield"><span>Tipo</span>
        <select name="tipo">
            <option value="">Selecione</option>
            <?php foreach ($Tipos as $Key => $Text): ?>
                <option value="<?= E($Key) ?>"<?= ($Empresa['tipo'] ?? '') === $Key ? ' selected' : '' ?>><?= E($Text) ?></option>
            <?php endforeach; ?>
        </select></label>
    <?php foreach ($Campos as $Name => [$Label, $Max]): ?>
        <label class="pfield"><span><?= E($Label) ?></span>
            <input type="text" name="<?= $Name ?>" maxlength="<?= $Max ?>" value="<?= E($Empresa[$Name] ?? '') ?>"<?= $Name === 'razao_social' ? ' required' : '' ?>></label>
    <?php endforeach; ?>
    <div class="pform-foot"><button class="btn btn-primary" type="submit">Salvar</button></div>
</form>
<?php
PageFoot();
