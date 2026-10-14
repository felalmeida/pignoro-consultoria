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
$Abas = ['resumo' => T('Resumo'), 'mensagens' => T('Mensagens'), 'arquivos' => T('Arquivos'), 'creditos' => T('Créditos'),
    'operacoes' => T('Operações'), 'contatos' => T('Contatos')];
$Aba = isset($Abas[$_GET['aba'] ?? '']) ? $_GET['aba'] : 'resumo';
$Self = 'admin/cliente.php?id=' . $Id . '&aba=' . $Aba;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    CheckCsrf();
    switch ($_POST['acao'] ?? '') {
        case 'mensagem':
            $Error = MensagemEnviar($Id, (int) $Admin['id']);
            Flash($Error === '' ? 'ok' : 'erro', $Error === '' ? T('Mensagem enviada.') : $Error);
            break;
        case 'situacao':
            $Def = RegistroDef($Aba);
            $Situacao = is_string($_POST['situacao'] ?? null) ? $_POST['situacao'] : '';
            if (isset($Def['situacoes'][$Situacao])) {
                Query("UPDATE {$Def['tabela']} SET situacao = ? WHERE id = ? AND usuario_id = ?", [$Situacao, (int) ($_POST['registro'] ?? 0), $Id]);
                Flash('ok', T('Situação atualizada.'));
            }
            break;
        case 'link':
            $Link = NewActivation($Id);
            $_SESSION['link'] = [$Id, $Link];
            // quem ainda não fez o primeiro acesso recebe as boas-vindas de novo, com o link novo
            if ($Client['senha_hash'] !== null)
                Flash('ok', T('Link de redefinição gerado. Envie ao cliente.'));
            elseif (SendWelcome($Config, $Client, $Link))
                Flash('ok', T('Novo link gerado e e-mail de boas-vindas reenviado para {email}.', ['email' => $Client['email']]));
            else
                Flash('erro', T('Novo link gerado, mas o e-mail de boas-vindas não pôde ser enviado. Envie ao cliente o link abaixo.'));
            break;
        case 'ativo':
            Query('UPDATE usuarios SET ativo = 1 - ativo WHERE id = ?', [$Id]);
            Flash('ok', $Client['ativo'] ? T('Acesso desativado.') : T('Acesso reativado.'));
            break;
        case 'idioma':
            $Idioma = ValidLang($_POST['idioma'] ?? null);
            if ($Idioma !== null) {
                Query('UPDATE usuarios SET idioma = ? WHERE id = ?', [$Idioma, $Id]);
                Flash('ok', T('Idioma do cliente atualizado.'));
            }
            break;
    }
    Redirect($Self);
}

$Action = E(Root() . 'cliente/' . $Self);

PageHead($Client['nome'], 'admin/index.php');
echo '<p class="crumb"><a href="index.php">' . E(T('Clientes')) . '</a></p><h1>' . E($Client['nome']) . '</h1><nav class="tabs" aria-label="' . E(T('Seções do cliente')) . '">';
foreach ($Abas as $Key => $Label)
    echo '<a href="cliente.php?id=' . $Id . '&amp;aba=' . $Key . '"' . ($Key === $Aba ? ' aria-current="page"' : '') . '>' . E($Label) . '</a>';
echo '</nav>';

if ($Aba === 'resumo') {
    $Link = $_SESSION['link'] ?? null;
    unset($_SESSION['link']);
    if (is_array($Link) && $Link[0] === $Id)
        echo '<div class="notice notice-aviso"><p>' . E(T('Link de acesso, válido por 7 dias. Ele é mostrado só agora; copie se precisar enviá-lo por outro canal:')) . '</p>'
            . '<p><code class="link-box">' . E(SiteUrl() . $Link[1]) . '</code></p></div>';

    $Status = !$Client['ativo'] ? T('Desativado') : ($Client['senha_hash'] === null ? T('Aguardando primeiro acesso') : T('Ativo'));
    $Pending = $Client['ativacao_hash'] !== null && (int) $Client['ativacao_expira'] > time();
    echo '<dl class="facts card-box">'
        . '<div><dt>' . E(T('E-mail de acesso')) . '</dt><dd>' . E($Client['email']) . '</dd></div>'
        . '<div><dt>' . E(T('Acesso')) . '</dt><dd>' . E($Status) . ($Pending ? ' ' . E(T('(link válido até {data})', ['data' => FormatDateTime((int) $Client['ativacao_expira'])])) : '') . '</dd></div>'
        . '<div><dt>' . E(T('Último acesso')) . '</dt><dd>' . E(FormatDateTime((int) $Client['ultimo_acesso']) ?: T('Nunca')) . '</dd></div>'
        . '<div><dt>' . E(T('Impressão digital da chave')) . '</dt><dd><code>' . E(Fingerprint($Client['chave_publica']) ?: T('Ainda não gerada')) . '</code></dd></div>'
        . '<div><dt>' . E(T('Idioma do cliente')) . '</dt><dd><form class="inline-form" method="post" action="' . $Action . '">' . CsrfField()
        . '<input type="hidden" name="acao" value="idioma"><select name="idioma" aria-label="' . E(T('Idioma do cliente')) . '">';
    foreach (IDIOMAS as $Code => $Name)
        echo '<option value="' . $Code . '"' . ($Code === $Client['idioma'] ? ' selected' : '') . '>' . E($Name) . '</option>';
    echo '</select><button class="pbtn" type="submit">' . E(T('Salvar')) . '</button></form></dd></div></dl>';

    echo '<div class="actions">'
        . '<form method="post" action="' . $Action . '"' . ($Client['senha_hash'] !== null
            ? ' data-confirmar="' . E(T('Gerar um novo link? Quando o cliente criar a nova senha, os arquivos dele precisarão ser liberados de novo.')) . '"' : '') . '>'
        . CsrfField() . '<input type="hidden" name="acao" value="link"><button class="pbtn" type="submit">'
        . E($Client['senha_hash'] === null ? T('Reenviar e-mail de boas-vindas') : T('Gerar link de redefinição de senha')) . '</button></form>'
        . '<form method="post" action="' . $Action . '">' . CsrfField() . '<input type="hidden" name="acao" value="ativo">'
        . '<button class="pbtn' . ($Client['ativo'] ? ' pbtn-danger' : '') . '" type="submit">' . E($Client['ativo'] ? T('Desativar acesso') : T('Reativar acesso')) . '</button></form></div>';

    $Empresa = Row('SELECT * FROM empresas WHERE usuario_id = ?', [$Id]);
    echo '<h2>' . E(T('Cadastro')) . '</h2>';
    if ($Empresa === null) {
        echo '<p class="empty">' . E(T('O cliente ainda não preencheu o cadastro.')) . '</p>';
    } else {
        $Tipos = ['investidor' => T('Investidor profissional'), 'fundo' => T('Fundo de investimento'), 'empresa' => T('Empresa')];
        $Linhas = [T('Tipo') => $Tipos[$Empresa['tipo']] ?? '', T('Razão social ou nome') => $Empresa['razao_social'],
            T('Nome fantasia') => $Empresa['nome_fantasia'], T('CNPJ ou CPF') => $Empresa['cnpj'], T('Telefone') => $Empresa['telefone'],
            T('Endereço') => trim($Empresa['endereco'] . ', ' . $Empresa['cidade'] . ' ' . $Empresa['uf'] . ' ' . $Empresa['cep'], ', '),
            T('Atualizado em') => FormatDateTime((int) $Empresa['atualizado_em'])];
        echo '<dl class="facts card-box">';
        foreach ($Linhas as $Label => $Value)
            echo '<div><dt>' . E($Label) . '</dt><dd>' . E($Value) . '</dd></div>';
        echo '</dl>';
    }
} elseif ($Aba === 'mensagens') {
    MensagemConversa($Id, $Admin, Root() . 'cliente/' . $Self);
} elseif ($Aba === 'arquivos') {
    ?>
    <form id="form-destravar" class="pform card-box" hidden novalidate>
        <p class="pfield-wide"><?= E(T('Para ver os nomes e baixar os arquivos neste navegador, confirme a sua senha.')) ?></p>
        <label class="pfield pfield-wide"><span><?= E(T('Senha')) ?></span><input type="password" name="senha" autocomplete="current-password" required></label>
        <div class="pform-foot"><button class="btn btn-primary" type="submit"><?= E(T('Abrir arquivos')) ?></button></div>
        <p class="form-msg" role="status" aria-live="polite"></p>
    </form>
    <form id="form-enviar" class="pform card-box" data-cliente="<?= $Id ?>" data-max="<?= ARQUIVO_MAX ?>" novalidate>
        <label class="pfield pfield-wide"><span><?= E(T('Enviar arquivos para o cliente (até {mb} MB cada)', ['mb' => (int) (ARQUIVO_MAX / 1048576)])) ?></span>
            <input type="file" name="arquivos" multiple required></label>
        <div class="pform-foot"><button class="btn btn-primary" type="submit"><?= E(T('Cifrar e enviar')) ?></button></div>
        <p class="form-msg" role="status" aria-live="polite"></p>
    </form>
    <?php if ($Client['chave_publica'] === null): ?>
        <p class="notice notice-aviso"><?= E(T('O cliente ainda não fez o primeiro acesso. Os arquivos enviados agora ficam guardados e precisam ser liberados para ele depois, nesta mesma tela.')) ?></p>
    <?php endif; ?>
    <div id="arquivos" data-cliente="<?= $Id ?>" data-papel="admin"><p class="empty"><?= E(T('Carregando...')) ?></p></div>
    <?php
} else {
    $Def = RegistroDef($Aba);
    $Rows = Query("SELECT * FROM {$Def['tabela']} WHERE usuario_id = ? ORDER BY id DESC", [$Id])->fetchAll();
    RegistroTabela($Def, $Rows, function (array $v_Row) use ($Def, $Action): string {
        if (!$Def['situacoes'])
            return '';
        $Html = '<form class="inline-form" method="post" action="' . $Action . '">' . CsrfField()
            . '<input type="hidden" name="acao" value="situacao"><input type="hidden" name="registro" value="' . (int) $v_Row['id'] . '">'
            . '<select name="situacao" aria-label="' . E(T('Situação')) . '">';
        foreach ($Def['situacoes'] as $Key => $Label)
            $Html .= '<option value="' . E($Key) . '"' . ($Key === $v_Row['situacao'] ? ' selected' : '') . '>' . E($Label) . '</option>';
        return $Html . '</select><button class="pbtn" type="submit">' . E(T('Salvar')) . '</button></form>';
    });
}
PageFoot();
