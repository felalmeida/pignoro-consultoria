<?php
/** Administração: tudo sobre um cliente. cliente.php?id=N&aba=resumo|mensagens|arquivos|creditos|operacoes|contatos */
declare(strict_types=1);
require __DIR__ . '/../../app/inicio.php';
require __DIR__ . '/../../app/registros.php';
require __DIR__ . '/../../app/mensagens.php';
require __DIR__ . '/../../app/boasvindas.php';

$Admin = RequireRole('admin');
$Client = ClientOr404((int) ($_GET['id'] ?? 0));
$Id = (int) $Client['id'];
$Abas = ['resumo' => 'Resumo', 'mensagens' => 'Mensagens', 'arquivos' => 'Arquivos', 'creditos' => 'Créditos',
    'operacoes' => 'Operações', 'contatos' => 'Contatos'];
$Aba = isset($Abas[$_GET['aba'] ?? '']) ? $_GET['aba'] : 'resumo';
$Self = 'admin/cliente.php?id=' . $Id . '&aba=' . $Aba;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    CheckCsrf();
    switch ($_POST['acao'] ?? '') {
        case 'mensagem':
            $Error = MensagemEnviar($Id, (int) $Admin['id']);
            Flash($Error === '' ? 'ok' : 'erro', $Error === '' ? 'Mensagem enviada.' : $Error);
            break;
        case 'situacao':
            $Def = RegistroDef($Aba);
            $Situacao = is_string($_POST['situacao'] ?? null) ? $_POST['situacao'] : '';
            if (isset($Def['situacoes'][$Situacao])) {
                Query("UPDATE {$Def['tabela']} SET situacao = ? WHERE id = ? AND usuario_id = ?", [$Situacao, (int) ($_POST['registro'] ?? 0), $Id]);
                Flash('ok', 'Situação atualizada.');
            }
            break;
        case 'link':
            $Link = NewActivation($Id);
            $_SESSION['link'] = [$Id, $Link];
            // quem ainda não fez o primeiro acesso recebe as boas-vindas de novo, com o link novo
            if ($Client['senha_hash'] !== null)
                Flash('ok', 'Link de redefinição gerado. Envie ao cliente.');
            elseif (SendWelcome($Config, $Client, $Link))
                Flash('ok', 'Novo link gerado e e-mail de boas-vindas reenviado para ' . $Client['email'] . '.');
            else
                Flash('erro', 'Novo link gerado, mas o e-mail de boas-vindas não pôde ser enviado. Envie ao cliente o link abaixo.');
            break;
        case 'ativo':
            Query('UPDATE usuarios SET ativo = 1 - ativo WHERE id = ?', [$Id]);
            Flash('ok', $Client['ativo'] ? 'Acesso desativado.' : 'Acesso reativado.');
            break;
    }
    Redirect($Self);
}

PageHead($Client['nome'], 'admin/index.php');
echo '<p class="crumb"><a href="index.php">Clientes</a></p><h1>' . E($Client['nome']) . '</h1><nav class="tabs" aria-label="Seções do cliente">';
foreach ($Abas as $Key => $Label)
    echo '<a href="cliente.php?id=' . $Id . '&amp;aba=' . $Key . '"' . ($Key === $Aba ? ' aria-current="page"' : '') . '>' . $Label . '</a>';
echo '</nav>';

if ($Aba === 'resumo') {
    $Link = $_SESSION['link'] ?? null;
    unset($_SESSION['link']);
    if (is_array($Link) && $Link[0] === $Id)
        echo '<div class="notice notice-aviso"><p>Link de acesso, válido por 7 dias. Ele é mostrado só agora; copie se precisar enviá-lo por outro canal:</p>'
            . '<p><code class="link-box">' . E(SiteUrl() . $Link[1]) . '</code></p></div>';

    $Status = !$Client['ativo'] ? 'Desativado' : ($Client['senha_hash'] === null ? 'Aguardando primeiro acesso' : 'Ativo');
    $Pending = $Client['ativacao_hash'] !== null && (int) $Client['ativacao_expira'] > time();
    echo '<dl class="facts card-box">'
        . '<div><dt>E-mail de acesso</dt><dd>' . E($Client['email']) . '</dd></div>'
        . '<div><dt>Acesso</dt><dd>' . $Status . ($Pending ? ' (link válido até ' . FormatDateTime((int) $Client['ativacao_expira']) . ')' : '') . '</dd></div>'
        . '<div><dt>Último acesso</dt><dd>' . (FormatDateTime((int) $Client['ultimo_acesso']) ?: 'Nunca') . '</dd></div>'
        . '<div><dt>Impressão digital da chave</dt><dd><code>' . (E(Fingerprint($Client['chave_publica'])) ?: 'Ainda não gerada') . '</code></dd></div></dl>';

    echo '<div class="actions">'
        . '<form method="post" action="' . E(Root() . 'cliente/' . $Self) . '"' . ($Client['senha_hash'] !== null
            ? ' data-confirmar="Gerar um novo link? Quando o cliente criar a nova senha, os arquivos dele precisarão ser liberados de novo."' : '') . '>'
        . CsrfField() . '<input type="hidden" name="acao" value="link"><button class="pbtn" type="submit">'
        . ($Client['senha_hash'] === null ? 'Reenviar e-mail de boas-vindas' : 'Gerar link de redefinição de senha') . '</button></form>'
        . '<form method="post" action="' . E(Root() . 'cliente/' . $Self) . '">' . CsrfField() . '<input type="hidden" name="acao" value="ativo">'
        . '<button class="pbtn' . ($Client['ativo'] ? ' pbtn-danger' : '') . '" type="submit">' . ($Client['ativo'] ? 'Desativar acesso' : 'Reativar acesso') . '</button></form></div>';

    $Empresa = Row('SELECT * FROM empresas WHERE usuario_id = ?', [$Id]);
    echo '<h2>Cadastro</h2>';
    if ($Empresa === null) {
        echo '<p class="empty">O cliente ainda não preencheu o cadastro.</p>';
    } else {
        $Tipos = ['investidor' => 'Investidor profissional', 'fundo' => 'Fundo de investimento', 'empresa' => 'Empresa'];
        $Linhas = ['Tipo' => $Tipos[$Empresa['tipo']] ?? '', 'Razão social ou nome' => $Empresa['razao_social'],
            'Nome fantasia' => $Empresa['nome_fantasia'], 'CNPJ ou CPF' => $Empresa['cnpj'], 'Telefone' => $Empresa['telefone'],
            'Endereço' => trim($Empresa['endereco'] . ', ' . $Empresa['cidade'] . ' ' . $Empresa['uf'] . ' ' . $Empresa['cep'], ', '),
            'Atualizado em' => FormatDateTime((int) $Empresa['atualizado_em'])];
        echo '<dl class="facts card-box">';
        foreach ($Linhas as $Label => $Value)
            echo '<div><dt>' . $Label . '</dt><dd>' . E($Value) . '</dd></div>';
        echo '</dl>';
    }
} elseif ($Aba === 'mensagens') {
    MensagemConversa($Id, $Admin, Root() . 'cliente/' . $Self);
} elseif ($Aba === 'arquivos') {
    ?>
    <form id="form-destravar" class="pform card-box" hidden novalidate>
        <p class="pfield-wide">Para ver os nomes e baixar os arquivos neste navegador, confirme a sua senha.</p>
        <label class="pfield pfield-wide"><span>Senha</span><input type="password" name="senha" autocomplete="current-password" required></label>
        <div class="pform-foot"><button class="btn btn-primary" type="submit">Abrir arquivos</button></div>
        <p class="form-msg" role="status" aria-live="polite"></p>
    </form>
    <form id="form-enviar" class="pform card-box" data-cliente="<?= $Id ?>" data-max="<?= ARQUIVO_MAX ?>" novalidate>
        <label class="pfield pfield-wide"><span>Enviar arquivos para o cliente (até <?= (int) (ARQUIVO_MAX / 1048576) ?> MB cada)</span>
            <input type="file" name="arquivos" multiple required></label>
        <div class="pform-foot"><button class="btn btn-primary" type="submit">Cifrar e enviar</button></div>
        <p class="form-msg" role="status" aria-live="polite"></p>
    </form>
    <?php if ($Client['chave_publica'] === null): ?>
        <p class="notice notice-aviso">O cliente ainda não fez o primeiro acesso. Os arquivos enviados agora ficam guardados e precisam ser liberados para ele depois, nesta mesma tela.</p>
    <?php endif; ?>
    <div id="arquivos" data-cliente="<?= $Id ?>" data-papel="admin"><p class="empty">Carregando...</p></div>
    <?php
} else {
    $Def = RegistroDef($Aba);
    $Rows = Query("SELECT * FROM {$Def['tabela']} WHERE usuario_id = ? ORDER BY id DESC", [$Id])->fetchAll();
    RegistroTabela($Def, $Rows, function (array $v_Row) use ($Def, $Self): string {
        if (!$Def['situacoes'])
            return '';
        $Html = '<form class="inline-form" method="post" action="' . E(Root() . 'cliente/' . $Self) . '">' . CsrfField()
            . '<input type="hidden" name="acao" value="situacao"><input type="hidden" name="registro" value="' . (int) $v_Row['id'] . '">'
            . '<select name="situacao" aria-label="Situação">';
        foreach ($Def['situacoes'] as $Key => $Label)
            $Html .= '<option value="' . E($Key) . '"' . ($Key === $v_Row['situacao'] ? ' selected' : '') . '>' . E($Label) . '</option>';
        return $Html . '</select><button class="pbtn" type="submit">Salvar</button></form>';
    });
}
PageFoot();
