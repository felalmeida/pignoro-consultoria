<?php
/**
 * Chamadas feitas pelo JavaScript da área do cliente (assets/js/portal.js).
 *   cliente/api.php?acao=...   Respostas em JSON, exceto "baixar", que devolve o arquivo cifrado.
 *
 * A senha nunca chega aqui. O navegador deriva dela dois valores: um de autenticação, que é o
 * que este arquivo recebe e guarda com password_hash, e a chave que protege a chave privada,
 * que não sai do navegador. Os arquivos chegam e saem sempre cifrados.
 */
declare(strict_types=1);

$_SERVER['HTTP_ACCEPT'] = 'application/json'; // erros de app/inicio.php também saem em JSON
require __DIR__ . '/../app/inicio.php';

header('Content-Type: application/json; charset=utf-8');

function Json(int $v_Status, array $v_Data): void
{
    http_response_code($v_Status);
    echo json_encode($v_Data, JSON_UNESCAPED_UNICODE);
    exit;
}

function Post(string $v_Name): string
{
    return is_string($_POST[$v_Name] ?? null) ? $_POST[$v_Name] : '';
}

/** Campo em base64 com tamanho dentro do limite; qualquer outra coisa encerra com erro 400. */
function PostB64(string $v_Name, int $v_Max): string
{
    $Value = Post($v_Name);
    if ($Value === '' || strlen($Value) > $v_Max || !IsB64($Value))
        Json(400, ['ok' => false, 'erro' => 'Dados inválidos. Recarregue a página e tente de novo.']);
    return $Value;
}

function IsB64(string $v_Str): bool
{
    return preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $v_Str) === 1 && strlen($v_Str) % 4 === 0;
}

$Acao = is_string($_GET['acao'] ?? null) ? $_GET['acao'] : '';
$Method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$Leitura = ['minha_chave', 'destinatarios', 'arquivos', 'baixar'];

if ($Method !== (in_array($Acao, $Leitura, true) ? 'GET' : 'POST'))
    Json(405, ['ok' => false, 'erro' => 'Método não permitido.']);
if ($Method === 'POST')
    CheckCsrf();

switch ($Acao) {

    // Sal do usuário, para o navegador derivar a senha. E-mails desconhecidos recebem um sal
    // estável e sem relação com contas reais, para não revelar quem é cliente.
    case 'sal':
        $Email = mb_strtolower(CleanLine(Post('email'), 150));
        $User = Row('SELECT kdf_sal, kdf_iter FROM usuarios WHERE email = ? AND ativo = 1 AND senha_hash IS NOT NULL', [$Email]);
        // o sal falso é sempre calculado, exista a conta ou não, para a resposta levar o mesmo tempo
        $Fake = base64_encode(substr(hash_hmac('sha256', $Email, ServerSecret(), true), 0, 16));
        Json(200, ['sal' => $User['kdf_sal'] ?? $Fake, 'iter' => (int) ($User['kdf_iter'] ?? KDF_ITER)]);

    case 'entrar':
        $Email = mb_strtolower(CleanLine(Post('email'), 150));
        $Auth = PostB64('auth', 44);
        $KeyEmail = 'entrar|email|' . hash('sha256', $Email);
        $KeyIp = 'entrar|ip|' . ClientIp();
        // a tentativa é contada antes de conferir a senha; a ordem fixa evita gastar o limite do IP à toa
        if (!TakeAttempt($KeyIp, 40, 900) || !TakeAttempt($KeyEmail, 8, 900))
            Json(429, ['ok' => false, 'erro' => 'Muitas tentativas. Aguarde alguns minutos e tente de novo.']);

        $User = Row('SELECT * FROM usuarios WHERE email = ? AND ativo = 1 AND senha_hash IS NOT NULL', [$Email]);
        // sem usuário, confere contra um hash fixo: o mesmo trabalho de uma conta real
        if (!password_verify($Auth, $User['senha_hash'] ?? DummyHash()) || $User === null)
            Json(401, ['ok' => false, 'erro' => 'E-mail ou senha incorretos.']);
        ClearAttempts($KeyEmail);

        StartSession();
        session_regenerate_id(true);
        $_SESSION = ['uid' => (int) $User['id'], 'marca' => SessionMark($User), 'visto' => time()];
        Query('UPDATE usuarios SET ultimo_acesso = ? WHERE id = ?', [time(), $User['id']]);
        Json(200, [
            'ok' => true,
            'uid' => (int) $User['id'],
            'priv' => $User['chave_privada'],
            'iv' => $User['chave_privada_iv'],
            'destino' => $User['papel'] === 'admin' ? 'admin/index.php' : 'painel.php',
        ]);

    // Primeiro acesso ou redefinição: o navegador gerou o par de chaves e envia a chave pública
    // e a chave privada já cifrada. As chaves de arquivo antigas deixam de servir e são apagadas.
    case 'ativar':
        $Token = Post('t');
        $User = preg_match('/^[0-9a-f]{64}$/', $Token) !== 1 ? null
            : Row('SELECT id FROM usuarios WHERE ativacao_hash = ? AND ativacao_expira > ? AND ativo = 1', [hash('sha256', $Token), time()]);
        if ($User === null)
            Json(400, ['ok' => false, 'erro' => 'Este link não é mais válido. Peça um novo à Pignoro.']);

        $Sal = PostB64('sal', 44);
        $Auth = PostB64('auth', 44);
        $Pub = PostB64('pub', 1200);
        $Priv = PostB64('priv', 6000);
        $Iv = PostB64('iv', 24);

        Db()->beginTransaction();
        Query('UPDATE usuarios SET senha_hash = ?, kdf_sal = ?, kdf_iter = ?, chave_publica = ?, chave_privada = ?,
                chave_privada_iv = ?, ativacao_hash = NULL, ativacao_expira = NULL WHERE id = ?',
            [password_hash($Auth, HashAlgo()), $Sal, KDF_ITER, $Pub, $Priv, $Iv, $User['id']]);
        Query('DELETE FROM arquivo_chaves WHERE usuario_id = ?', [$User['id']]);
        Db()->commit();

        // as sessões abertas com a senha anterior deixam de valer (ver SessionMark)
        StartSession();
        unset($_SESSION['uid'], $_SESSION['marca']);
        Flash('ok', 'Senha criada. Entre com o seu e-mail e a nova senha.');
        Json(200, ['ok' => true]);

    case 'minha_chave':
        $User = CurrentUser() ?? Json(401, ['ok' => false, 'erro' => 'Sessão encerrada. Entre novamente.']);
        Json(200, ['uid' => (int) $User['id'], 'sal' => $User['kdf_sal'], 'iter' => (int) $User['kdf_iter'],
            'priv' => $User['chave_privada'], 'iv' => $User['chave_privada_iv']]);

    // Troca de senha: o navegador abriu a chave privada com a senha atual e a cifrou de novo com a nova.
    case 'trocar_senha':
        $User = CurrentUser() ?? Json(401, ['ok' => false, 'erro' => 'Sessão encerrada. Entre novamente.']);
        $Key = 'senha|' . $User['id'];
        if (!TakeAttempt($Key, 8, 900))
            Json(429, ['ok' => false, 'erro' => 'Muitas tentativas. Aguarde alguns minutos e tente de novo.']);
        if (!password_verify(PostB64('auth_atual', 44), (string) $User['senha_hash']))
            Json(401, ['ok' => false, 'erro' => 'A senha atual não confere.']);
        ClearAttempts($Key);

        $Hash = password_hash(PostB64('auth', 44), HashAlgo());
        Query('UPDATE usuarios SET senha_hash = ?, kdf_sal = ?, kdf_iter = ?, chave_privada = ?, chave_privada_iv = ? WHERE id = ?',
            [$Hash, PostB64('sal', 44), KDF_ITER, PostB64('priv', 6000), PostB64('iv', 24), $User['id']]);
        // esta sessão continua; as outras do mesmo usuário caem, porque a marca delas é da senha antiga
        session_regenerate_id(true);
        $_SESSION['marca'] = SessionMark(['senha_hash' => $Hash]);
        Json(200, ['ok' => true]);

    // Chaves públicas de quem deve conseguir abrir um arquivo do cliente: ele e os administradores.
    case 'destinatarios':
        RequireRole('admin');
        $Client = ClientOr404((int) ($_GET['cliente'] ?? 0));
        $List = Query("SELECT id, papel, chave_publica AS pub FROM usuarios
            WHERE chave_publica IS NOT NULL AND ativo = 1 AND (papel = 'admin' OR id = ?)", [$Client['id']])->fetchAll();
        Json(200, ['destinatarios' => $List]);

    case 'enviar':
        $Admin = RequireRole('admin');
        if (empty($_FILES['arquivo']) || !is_array($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK)
            Json(413, ['ok' => false, 'erro' => 'O arquivo não chegou ao servidor. Ele pode ser maior que o limite aceito.']);
        $Client = ClientOr404((int) Post('cliente'));
        $Size = (int) $_FILES['arquivo']['size'];
        if ($Size < 17 || $Size > ARQUIVO_MAX + 16)
            Json(413, ['ok' => false, 'erro' => 'Arquivo vazio ou maior que o limite aceito.']);

        $Iv = PostB64('iv', 24);
        $Meta = PostB64('meta', 2400);
        $MetaIv = PostB64('meta_iv', 24);
        $Keys = json_decode(Post('chaves'), true);
        if (!is_array($Keys) || !isset($Keys[$Admin['id']]))
            Json(400, ['ok' => false, 'erro' => 'Dados inválidos. Recarregue a página e tente de novo.']);
        $Allowed = Query("SELECT id FROM usuarios WHERE chave_publica IS NOT NULL AND ativo = 1 AND (papel = 'admin' OR id = ?)",
            [$Client['id']])->fetchAll(PDO::FETCH_COLUMN);
        foreach ($Keys as $UserId => $Key) {
            if (!in_array((int) $UserId, array_map('intval', $Allowed), true) || !is_string($Key) || strlen($Key) > 700 || !IsB64($Key))
                Json(400, ['ok' => false, 'erro' => 'Dados inválidos. Recarregue a página e tente de novo.']);
        }

        $Stored = bin2hex(random_bytes(16)) . '.bin';
        $Path = FilesDir() . '/' . $Stored;
        if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $Path))
            Abort(500, 'Não foi possível guardar o arquivo.', 'falha ao mover o arquivo enviado para ' . $Path);
        @chmod($Path, 0600);

        Db()->beginTransaction();
        Query('INSERT INTO arquivos (cliente_id, enviado_por, armazenado, tamanho, iv, meta, meta_iv, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$Client['id'], $Admin['id'], $Stored, $Size, $Iv, $Meta, $MetaIv, time()]);
        $FileId = (int) Db()->lastInsertId();
        foreach ($Keys as $UserId => $Key)
            Query('INSERT INTO arquivo_chaves (arquivo_id, usuario_id, chave) VALUES (?, ?, ?)', [$FileId, (int) $UserId, $Key]);
        Db()->commit();
        Json(200, ['ok' => true, 'id' => $FileId]);

    // Lista de arquivos de um cliente, com a chave de cada arquivo cifrada para quem está pedindo.
    case 'arquivos':
        $User = CurrentUser() ?? Json(401, ['ok' => false, 'erro' => 'Sessão encerrada. Entre novamente.']);
        $Client = $User['papel'] === 'admin' ? ClientOr404((int) ($_GET['cliente'] ?? 0)) : $User;
        $Files = Query('SELECT a.id, a.tamanho, a.iv, a.meta, a.meta_iv, a.criado_em, minha.chave,
                cli.chave IS NOT NULL AS cliente_tem_chave
            FROM arquivos a
            LEFT JOIN arquivo_chaves minha ON minha.arquivo_id = a.id AND minha.usuario_id = ?
            LEFT JOIN arquivo_chaves cli ON cli.arquivo_id = a.id AND cli.usuario_id = a.cliente_id
            WHERE a.cliente_id = ? ORDER BY a.id DESC', [$User['id'], $Client['id']])->fetchAll();
        foreach ($Files as &$File) {
            $File['data'] = FormatDateTime((int) $File['criado_em']);
            $File['cliente_tem_chave'] = (bool) $File['cliente_tem_chave'];
            unset($File['criado_em']);
        }
        Json(200, ['arquivos' => $Files, 'cliente_pub' => $User['papel'] === 'admin' ? $Client['chave_publica'] : null]);

    case 'baixar':
        $User = CurrentUser() ?? Json(401, ['ok' => false, 'erro' => 'Sessão encerrada. Entre novamente.']);
        $File = Row('SELECT * FROM arquivos WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
        if ($File === null || ($User['papel'] !== 'admin' && (int) $File['cliente_id'] !== (int) $User['id']))
            Json(404, ['ok' => false, 'erro' => 'Arquivo não encontrado.']);
        $Path = FilesDir() . '/' . basename($File['armazenado']);
        if (!is_file($Path))
            Abort(404, 'Arquivo não encontrado.', 'arquivo ' . $File['id'] . ' está no banco mas não no disco');
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($Path));
        readfile($Path);
        exit;

    case 'excluir_arquivo':
        RequireRole('admin');
        $File = Row('SELECT * FROM arquivos WHERE id = ?', [(int) Post('id')]) ?? Json(404, ['ok' => false, 'erro' => 'Arquivo não encontrado.']);
        Query('DELETE FROM arquivos WHERE id = ?', [$File['id']]);
        @unlink(FilesDir() . '/' . basename($File['armazenado']));
        Json(200, ['ok' => true]);

    // Depois que o cliente redefine a senha, o administrador cifra a chave do arquivo para a chave nova dele.
    case 'liberar':
        RequireRole('admin');
        $File = Row('SELECT a.id, a.cliente_id, u.chave_publica FROM arquivos a JOIN usuarios u ON u.id = a.cliente_id WHERE a.id = ?',
            [(int) Post('id')]) ?? Json(404, ['ok' => false, 'erro' => 'Arquivo não encontrado.']);
        if ((string) $File['chave_publica'] === '')
            Json(409, ['ok' => false, 'erro' => 'O cliente ainda não fez o primeiro acesso.']);
        Query('INSERT OR REPLACE INTO arquivo_chaves (arquivo_id, usuario_id, chave) VALUES (?, ?, ?)',
            [$File['id'], $File['cliente_id'], PostB64('chave', 700)]);
        Json(200, ['ok' => true]);

    default:
        Json(404, ['ok' => false, 'erro' => 'Ação desconhecida.']);
}
