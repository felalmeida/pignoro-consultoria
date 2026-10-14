<?php
/** Administração: lista de clientes e cadastro de um novo. */
declare(strict_types=1);
require __DIR__ . '/../../app/inicio.php';
require __DIR__ . '/../../app/boasvindas.php';

$Admin = RequireRole('admin');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    CheckCsrf();
    $Nome = CleanLine(is_string($_POST['nome'] ?? null) ? $_POST['nome'] : '', 100);
    $Email = mb_strtolower(CleanLine(is_string($_POST['email'] ?? null) ? $_POST['email'] : '', 150));
    $Idioma = ValidLang($_POST['idioma'] ?? null) ?? 'pt';
    if ($Nome === '' || !filter_var($Email, FILTER_VALIDATE_EMAIL)) {
        Flash('erro', T('Informe o nome e um e-mail válido.'));
    } elseif (Row('SELECT 1 FROM usuarios WHERE email = ?', [$Email]) !== null) {
        Flash('erro', T('Já existe uma conta com este e-mail.'));
    } else {
        Query("INSERT INTO usuarios (papel, nome, email, idioma, criado_em) VALUES ('cliente', ?, ?, ?, ?)", [$Nome, $Email, $Idioma, time()]);
        $Id = (int) Db()->lastInsertId();
        $Link = NewActivation($Id);
        $_SESSION['link'] = [$Id, $Link];
        if (SendWelcome($Config, ['id' => $Id, 'nome' => $Nome, 'email' => $Email, 'idioma' => $Idioma], $Link))
            Flash('ok', T('Cliente criado. O e-mail de boas-vindas, com o link de primeiro acesso, foi enviado para {email}.', ['email' => $Email]));
        else
            Flash('erro', T('Cliente criado, mas o e-mail de boas-vindas não pôde ser enviado. Envie a ele o link abaixo.'));
        Redirect('admin/cliente.php?id=' . $Id);
    }
    Redirect('admin/index.php');
}

$Clients = Query("SELECT u.*, e.razao_social,
        (SELECT COUNT(*) FROM mensagens m WHERE m.cliente_id = u.id AND m.autor_id = u.id AND m.lida_em IS NULL) AS nao_lidas
    FROM usuarios u LEFT JOIN empresas e ON e.usuario_id = u.id
    WHERE u.papel = 'cliente' ORDER BY u.nome COLLATE NOCASE")->fetchAll();

PageHead(T('Clientes'), 'admin/index.php');
echo '<h1>' . E(T('Clientes')) . '</h1>';
if (!$Clients) {
    echo '<p class="empty">' . E(T('Nenhum cliente cadastrado.')) . '</p>';
} else {
    echo '<div class="table-wrap"><table class="table"><thead><tr><th>' . E(T('Cliente')) . '</th><th>' . E(T('E-mail')) . '</th><th>'
        . E(T('Acesso')) . '</th><th>' . E(T('Mensagens')) . '</th></tr></thead><tbody>';
    foreach ($Clients as $Client) {
        $Status = !$Client['ativo'] ? T('Desativado') : ($Client['senha_hash'] === null ? T('Aguardando primeiro acesso') : T('Ativo'));
        echo '<tr><td data-label="' . E(T('Cliente')) . '"><a href="cliente.php?id=' . (int) $Client['id'] . '"><strong>' . E($Client['nome']) . '</strong></a>'
            . ($Client['razao_social'] ? '<br><small>' . E($Client['razao_social']) . '</small>' : '') . '</td>'
            . '<td data-label="' . E(T('E-mail')) . '">' . E($Client['email']) . '</td>'
            . '<td data-label="' . E(T('Acesso')) . '"><span class="tag">' . E($Status) . '</span></td>'
            . '<td data-label="' . E(T('Mensagens')) . '">' . ((int) $Client['nao_lidas'] > 0
                ? '<span class="tag tag-alert">' . E(T('{n} não lidas', ['n' => (int) $Client['nao_lidas']])) . '</span>' : '') . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
?>
<h2><?= E(T('Novo cliente')) ?></h2>
<form class="pform card-box" method="post">
    <?= CsrfField() ?>
    <label class="pfield"><span><?= E(T('Nome')) ?></span><input type="text" name="nome" maxlength="100" required></label>
    <label class="pfield"><span><?= E(T('E-mail de acesso')) ?></span><input type="email" name="email" maxlength="150" required></label>
    <label class="pfield"><span><?= E(T('Idioma do cliente')) ?></span>
        <select name="idioma">
            <?php foreach (IDIOMAS as $Code => $Name): ?>
                <option value="<?= $Code ?>"><?= E($Name) ?></option>
            <?php endforeach; ?>
        </select></label>
    <div class="pform-foot"><button class="btn btn-primary" type="submit"><?= E(T('Criar cliente')) ?></button></div>
</form>
<p class="hint"><?= E(T('O cliente recebe um e-mail de boas-vindas, no idioma escolhido, com o link para criar a própria senha. A Pignoro não define nem conhece a senha dele.')) ?></p>
<?php
PageFoot();
