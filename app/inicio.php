<?php
/**
 * Base da área do cliente: banco SQLite, sessão, autenticação, proteção de formulários e layout.
 * Esta pasta é bloqueada para acesso pela web (ver app/.htaccess).
 */
declare(strict_types=1);

mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Sao_Paulo');

$Config = require __DIR__ . '/../config/config.php';

// Iterações do PBKDF2 feito no navegador para derivar a senha.
const KDF_ITER = 600000;
// Minutos sem atividade até a sessão expirar.
const SESSAO_OCIOSA = 30 * 60;
// Validade do link de primeiro acesso.
const ATIVACAO_VALIDADE = 7 * 86400;
// Tamanho máximo de um arquivo cifrado.
const ARQUIVO_MAX = 50 * 1024 * 1024;

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; "
    . "connect-src 'self'; font-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

function E(?string $v_Str): string
{
    return htmlspecialchars((string) $v_Str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Interrompe com uma página simples de erro e registra o motivo no log do PHP. */
function Abort(int $v_Status, string $v_Message, string $v_Reason = ''): void
{
    if ($v_Reason !== '')
        error_log('[area do cliente] ' . $v_Reason);
    http_response_code($v_Status);
    if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'erro' => $v_Message], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="pt-BR"><meta charset="utf-8"><title>Área do cliente</title><p>' . E($v_Message) . '</p></html>';
    }
    exit;
}

/**
 * Pasta privada do banco e dos arquivos cifrados. Mesmas exigências do formulário de contato:
 * pasta real, do usuário do PHP e sem acesso para outros usuários.
 */
function DataDir(): string
{
    global $Config;
    static $Checked = null;
    if ($Checked !== null)
        return $Checked;

    $Dir = rtrim((string) ($Config['pasta_dados'] ?? ''), '/');
    if ($Dir === '')
        $Dir = __DIR__ . '/../config/dados';

    $Problem = '';
    if (!file_exists($Dir) && !@mkdir($Dir, 0700, true) && !is_dir($Dir))
        $Problem = 'não pôde ser criada';
    elseif (is_link($Dir) || !is_dir($Dir))
        $Problem = 'não é uma pasta real';
    elseif (function_exists('posix_geteuid') && fileowner($Dir) !== posix_geteuid())
        $Problem = 'pertence a outro usuário';
    elseif ((fileperms($Dir) & 0077) !== 0 && !@chmod($Dir, 0700))
        $Problem = 'está acessível a outros usuários';
    elseif (!is_writable($Dir))
        $Problem = 'está sem permissão de escrita';
    if ($Problem !== '')
        Abort(503, 'Área do cliente indisponível no momento.', 'pasta de dados ' . $Problem . ': ' . $Dir);

    return $Checked = $Dir;
}

/** Pasta dos arquivos cifrados, dentro da pasta de dados. */
function FilesDir(): string
{
    $Dir = DataDir() . '/arquivos';
    if (!is_dir($Dir) && !@mkdir($Dir, 0700) && !is_dir($Dir))
        Abort(503, 'Área do cliente indisponível no momento.', 'pasta de arquivos não pôde ser criada: ' . $Dir);
    return $Dir;
}

/** Segredo do servidor, usado para responder de forma uniforme a e-mails não cadastrados. */
function ServerSecret(): string
{
    $File = DataDir() . '/portal.segredo';
    $Secret = is_file($File) ? (string) file_get_contents($File) : '';
    if (strlen($Secret) !== 64) {
        $Secret = bin2hex(random_bytes(32));
        if (file_put_contents($File, $Secret, LOCK_EX) === false)
            Abort(503, 'Área do cliente indisponível no momento.', 'segredo não pôde ser gravado');
        @chmod($File, 0600);
    }
    return $Secret;
}

function Db(): PDO
{
    static $Pdo = null;
    if ($Pdo !== null)
        return $Pdo;
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true))
        Abort(503, 'Área do cliente indisponível no momento.', 'extensão pdo_sqlite do PHP não está instalada');

    $File = DataDir() . '/portal.sqlite';
    $New = !is_file($File);
    $Pdo = new PDO('sqlite:' . $File, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if ($New)
        @chmod($File, 0600);
    $Pdo->exec('PRAGMA foreign_keys = ON');
    $Pdo->exec('PRAGMA journal_mode = WAL');
    $Pdo->exec('PRAGMA busy_timeout = 5000');
    Migrate($Pdo);
    return $Pdo;
}

/** Cria ou atualiza as tabelas. A versão fica em PRAGMA user_version. */
function Migrate(PDO $v_Pdo): void
{
    $Version = (int) $v_Pdo->query('PRAGMA user_version')->fetchColumn();
    if ($Version >= 1)
        return;

    $v_Pdo->exec('BEGIN IMMEDIATE');
    $v_Pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY,
            papel TEXT NOT NULL CHECK (papel IN ('admin', 'cliente')),
            nome TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE COLLATE NOCASE,
            ativo INTEGER NOT NULL DEFAULT 1,
            senha_hash TEXT,
            kdf_sal TEXT,
            kdf_iter INTEGER,
            chave_publica TEXT,
            chave_privada TEXT,
            chave_privada_iv TEXT,
            ativacao_hash TEXT,
            ativacao_expira INTEGER,
            criado_em INTEGER NOT NULL,
            ultimo_acesso INTEGER
        );
        CREATE TABLE IF NOT EXISTS empresas (
            usuario_id INTEGER PRIMARY KEY REFERENCES usuarios(id) ON DELETE CASCADE,
            tipo TEXT NOT NULL DEFAULT '',
            razao_social TEXT NOT NULL DEFAULT '',
            nome_fantasia TEXT NOT NULL DEFAULT '',
            cnpj TEXT NOT NULL DEFAULT '',
            endereco TEXT NOT NULL DEFAULT '',
            cidade TEXT NOT NULL DEFAULT '',
            uf TEXT NOT NULL DEFAULT '',
            cep TEXT NOT NULL DEFAULT '',
            telefone TEXT NOT NULL DEFAULT '',
            atualizado_em INTEGER
        );
        CREATE TABLE IF NOT EXISTS contatos (
            id INTEGER PRIMARY KEY,
            usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
            nome TEXT NOT NULL,
            cargo TEXT NOT NULL DEFAULT '',
            email TEXT NOT NULL DEFAULT '',
            telefone TEXT NOT NULL DEFAULT '',
            criado_em INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS creditos (
            id INTEGER PRIMARY KEY,
            usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
            devedor TEXT NOT NULL,
            documento TEXT NOT NULL DEFAULT '',
            valor INTEGER NOT NULL DEFAULT 0,
            vencimento TEXT NOT NULL DEFAULT '',
            garantia TEXT NOT NULL DEFAULT '',
            observacoes TEXT NOT NULL DEFAULT '',
            situacao TEXT NOT NULL DEFAULT 'novo',
            criado_em INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS operacoes (
            id INTEGER PRIMARY KEY,
            usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
            tipo TEXT NOT NULL,
            descricao TEXT NOT NULL,
            moeda TEXT NOT NULL DEFAULT '',
            valor INTEGER NOT NULL DEFAULT 0,
            data_prevista TEXT NOT NULL DEFAULT '',
            observacoes TEXT NOT NULL DEFAULT '',
            situacao TEXT NOT NULL DEFAULT 'nova',
            criado_em INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS mensagens (
            id INTEGER PRIMARY KEY,
            cliente_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
            autor_id INTEGER NOT NULL REFERENCES usuarios(id),
            texto TEXT NOT NULL,
            criado_em INTEGER NOT NULL,
            lida_em INTEGER
        );
        CREATE INDEX IF NOT EXISTS mensagens_cliente ON mensagens (cliente_id, id);
        CREATE TABLE IF NOT EXISTS arquivos (
            id INTEGER PRIMARY KEY,
            cliente_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
            enviado_por INTEGER NOT NULL REFERENCES usuarios(id),
            armazenado TEXT NOT NULL UNIQUE,
            tamanho INTEGER NOT NULL,
            iv TEXT NOT NULL,
            meta TEXT NOT NULL,
            meta_iv TEXT NOT NULL,
            criado_em INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS arquivos_cliente ON arquivos (cliente_id, id);
        CREATE TABLE IF NOT EXISTS arquivo_chaves (
            arquivo_id INTEGER NOT NULL REFERENCES arquivos(id) ON DELETE CASCADE,
            usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
            chave TEXT NOT NULL,
            PRIMARY KEY (arquivo_id, usuario_id)
        );
        CREATE TABLE IF NOT EXISTS tentativas (
            chave TEXT NOT NULL,
            quando INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS tentativas_chave ON tentativas (chave, quando);
        PRAGMA user_version = 1;
        SQL);
    $v_Pdo->exec('COMMIT');
}

function Query(string $v_Sql, array $v_Params = []): PDOStatement
{
    $Stmt = Db()->prepare($v_Sql);
    $Stmt->execute($v_Params);
    return $Stmt;
}

function Row(string $v_Sql, array $v_Params = []): ?array
{
    $Row = Query($v_Sql, $v_Params)->fetch();
    return $Row === false ? null : $Row;
}

// ---------- visitante e limites ----------

function InCidr(string $v_Ip, string $v_Cidr): bool
{
    [$Net, $Bits] = explode('/', $v_Cidr);
    $IpBin = @inet_pton($v_Ip);
    $NetBin = @inet_pton($Net);
    if ($IpBin === false || $NetBin === false || strlen($IpBin) !== strlen($NetBin))
        return false;
    $Bytes = intdiv((int) $Bits, 8);
    $Rest = (int) $Bits % 8;
    if (substr($IpBin, 0, $Bytes) !== substr($NetBin, 0, $Bytes))
        return false;
    if ($Rest === 0)
        return true;
    $Mask = 0xFF << (8 - $Rest) & 0xFF;
    return (ord($IpBin[$Bytes]) & $Mask) === (ord($NetBin[$Bytes]) & $Mask);
}

/**
 * Conexão vinda do Cloudflare ou de um proxy da própria infraestrutura (config.php).
 * Só então os cabeçalhos de IP e de HTTPS informados por eles são aceitos.
 */
function FromTrustedProxy(): bool
{
    global $Config;
    $Remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $Nets = array_merge((array) ($Config['redes_cloudflare'] ?? []), (array) ($Config['proxies_confiaveis'] ?? []));
    foreach ($Nets as $Cidr) {
        if (InCidr($Remote, (string) $Cidr))
            return true;
    }
    return false;
}

/** IP do visitante: o do Cloudflare, ou o último que o proxy acrescentou em X-Forwarded-For. */
function ClientIp(): string
{
    $Remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $Ip = filter_var($Remote, FILTER_VALIDATE_IP) ? $Remote : '0.0.0.0';
    if (!FromTrustedProxy())
        return $Ip;
    $Chain = explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    foreach ([(string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''), trim((string) end($Chain))] as $Forwarded) {
        if ($Forwarded !== '' && filter_var($Forwarded, FILTER_VALIDATE_IP))
            return $Forwarded;
    }
    return $Ip;
}

function IsHttps(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        return true;
    if (!FromTrustedProxy())
        return false;
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || str_contains((string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''), '"https"');
}

/**
 * Registra uma tentativa na chave e devolve false se o limite da janela já foi atingido.
 * Contagem e registro acontecem na mesma transação exclusiva e antes de a senha ser conferida,
 * para que requisições simultâneas não passem todas pelo limite.
 */
function TakeAttempt(string $v_Key, int $v_Max, int $v_Window): bool
{
    $Pdo = Db();
    $Pdo->exec('BEGIN IMMEDIATE');
    $Count = (int) Query('SELECT COUNT(*) FROM tentativas WHERE chave = ? AND quando > ?', [$v_Key, time() - $v_Window])->fetchColumn();
    $Allowed = $Count < $v_Max;
    if ($Allowed)
        Query('INSERT INTO tentativas (chave, quando) VALUES (?, ?)', [$v_Key, time()]);
    if (random_int(1, 50) === 1)
        Query('DELETE FROM tentativas WHERE quando < ?', [time() - 86400]);
    $Pdo->exec('COMMIT');
    return $Allowed;
}

/** Zera as tentativas da chave depois de um acerto. */
function ClearAttempts(string $v_Key): void
{
    Query('DELETE FROM tentativas WHERE chave = ?', [$v_Key]);
}

/**
 * Hash de uma senha que ninguém tem, conferido quando o e-mail não existe, para que a resposta
 * leve o mesmo tempo que a de uma conta real. Calculado uma vez e guardado na pasta de dados.
 */
function DummyHash(): string
{
    $File = DataDir() . '/portal.hash';
    $Hash = is_file($File) ? trim((string) file_get_contents($File)) : '';
    if ($Hash === '' || password_get_info($Hash)['algo'] === null) {
        $Hash = password_hash(bin2hex(random_bytes(32)), HashAlgo());
        file_put_contents($File, $Hash, LOCK_EX);
        @chmod($File, 0600);
    }
    return $Hash;
}

function HashAlgo(): string
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
}

// ---------- sessão ----------

function StartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE)
        return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('pignoro_sessao');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => IsHttps(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    $Last = (int) ($_SESSION['visto'] ?? 0);
    if (isset($_SESSION['uid']) && $Last > 0 && time() - $Last > SESSAO_OCIOSA) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['aviso'] = ['erro', 'Sua sessão expirou. Entre novamente.'];
    }
    $_SESSION['visto'] = time();
}

function CsrfToken(): string
{
    StartSession();
    if (empty($_SESSION['csrf']))
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

/** Exige o token do formulário (campo "csrf") ou do JavaScript (cabeçalho X-CSRF). */
function CheckCsrf(): void
{
    $Sent = (string) ($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '');
    if ($Sent === '' || !hash_equals(CsrfToken(), $Sent))
        Abort(403, 'Formulário expirado. Recarregue a página e tente de novo.');
}

function CsrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . E(CsrfToken()) . '">';
}

function Flash(string $v_Kind, string $v_Text): void
{
    StartSession();
    $_SESSION['aviso'] = [$v_Kind, $v_Text];
}

/** Usuário da sessão, relido do banco a cada requisição para respeitar desativações. */
function CurrentUser(): ?array
{
    static $User = false;
    if ($User !== false)
        return $User;
    StartSession();
    $Id = (int) ($_SESSION['uid'] ?? 0);
    $User = $Id > 0 ? Row('SELECT * FROM usuarios WHERE id = ? AND ativo = 1 AND senha_hash IS NOT NULL', [$Id]) : null;
    // sessão aberta com uma senha que já foi trocada ou redefinida deixa de valer
    if ($User !== null && !hash_equals(SessionMark($User), (string) ($_SESSION['marca'] ?? '')))
        $User = null;
    if ($User === null && $Id > 0)
        unset($_SESSION['uid'], $_SESSION['marca']);
    return $User;
}

/** Marca que liga a sessão à senha vigente: muda sempre que a senha muda. */
function SessionMark(array $v_User): string
{
    return hash_hmac('sha256', (string) $v_User['senha_hash'], ServerSecret());
}

/** Caminho da raiz do site a partir da página atual (cliente/ ou cliente/admin/). */
function Root(): string
{
    return str_ends_with(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/admin') ? '../../' : '../';
}

function Redirect(string $v_Path): void
{
    header('Location: ' . Root() . 'cliente/' . $v_Path, true, 303);
    exit;
}

/** Garante usuário logado com o papel pedido; do contrário manda para o login ou para o painel certo. */
function RequireRole(string $v_Role): array
{
    $User = CurrentUser();
    $Json = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    if ($User === null) {
        if ($Json)
            Abort(401, 'Sessão encerrada. Entre novamente.');
        Redirect('index.php');
    }
    if ($User['papel'] !== $v_Role) {
        if ($Json)
            Abort(403, 'Acesso não permitido.');
        Redirect($User['papel'] === 'admin' ? 'admin/index.php' : 'painel.php');
    }
    return $User;
}

/** Cliente com o id informado, ou erro 404. */
function ClientOr404(int $v_Id): array
{
    $Client = Row("SELECT * FROM usuarios WHERE id = ? AND papel = 'cliente'", [$v_Id]);
    if ($Client === null)
        Abort(404, 'Cliente não encontrado.');
    return $Client;
}

/** Gera um link de primeiro acesso (ou de redefinição) e devolve o endereço relativo à raiz do site. */
function NewActivation(int $v_UserId): string
{
    $Token = bin2hex(random_bytes(32));
    Query('UPDATE usuarios SET ativacao_hash = ?, ativacao_expira = ? WHERE id = ?',
        [hash('sha256', $Token), time() + ATIVACAO_VALIDADE, $v_UserId]);
    return 'cliente/ativar.php?t=' . $Token;
}

/** Endereço completo do site, para montar links que serão enviados por fora. */
function SiteUrl(): string
{
    $Host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $Base = rtrim(dirname(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/cliente/x'))), '/');
    if (str_ends_with($Base, '/cliente'))
        $Base = substr($Base, 0, -8);
    return (IsHttps() ? 'https' : 'http') . '://' . $Host . $Base . '/';
}

/** Impressão digital da chave pública, para conferência por telefone ou outro canal. */
function Fingerprint(?string $v_PublicKey): string
{
    if ((string) $v_PublicKey === '')
        return '';
    $Raw = base64_decode((string) $v_PublicKey, true);
    return $Raw === false ? '' : strtoupper(implode(' ', str_split(substr(hash('sha256', $Raw), 0, 32), 4)));
}

// ---------- formatação ----------

function FormatDateTime(?int $v_Time): string
{
    return $v_Time ? date('d/m/Y H:i', $v_Time) : '';
}

function FormatDate(string $v_Iso): string
{
    return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v_Iso, $M) ? "$M[3]/$M[2]/$M[1]" : '';
}

function FormatMoney(int $v_Cents): string
{
    return number_format($v_Cents / 100, 2, ',', '.');
}

/** Converte "1.234,56" ou "1234.56" em centavos. Devolve null se não for um valor válido. */
function ParseMoney(string $v_Str): ?int
{
    $Str = preg_replace('/[\s\x{00A0}]|R\$/u', '', $v_Str);
    if ($Str === '')
        return 0;
    if (str_contains($Str, ','))
        $Str = str_replace(',', '.', str_replace('.', '', $Str));
    if (!preg_match('/^\d{1,13}(\.\d{1,2})?$/', $Str))
        return null;
    return (int) round((float) $Str * 100);
}

function CleanLine(string $v_Str, int $v_Max): string
{
    $Str = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v_Str));
    return mb_substr($Str, 0, $v_Max);
}

function CleanText(string $v_Str, int $v_Max): string
{
    $Str = str_replace(["\r\n", "\r"], "\n", $v_Str);
    $Str = trim((string) preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', '', $Str));
    return mb_substr($Str, 0, $v_Max);
}

// ---------- layout ----------

/**
 * Abre a página. $v_Nav é a lista [arquivo => rótulo] do menu e $v_Active o arquivo atual.
 * Sem usuário (login e primeiro acesso) a página sai sem menu.
 */
function PageHead(string $v_Title, string $v_Active = ''): void
{
    $Root = Root();
    $User = CurrentUser();
    $Nav = [];
    if ($User !== null && $User['papel'] === 'cliente')
        $Nav = ['painel.php' => 'Início', 'mensagens.php' => 'Mensagens', 'arquivos.php' => 'Arquivos',
            'cadastro.php' => 'Cadastro', 'registros.php?tipo=contatos' => 'Contatos',
            'registros.php?tipo=creditos' => 'Créditos', 'registros.php?tipo=operacoes' => 'Operações',
            'conta.php' => 'Minha conta'];
    elseif ($User !== null)
        $Nav = ['admin/index.php' => 'Clientes', 'conta.php' => 'Minha conta'];

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex, nofollow">'
        . '<meta name="csrf" content="' . E(CsrfToken()) . '">'
        . '<title>' . E($v_Title) . ' | Pignoro</title>'
        . '<link rel="icon" href="' . $Root . 'assets/img/logo.svg" type="image/svg+xml">'
        . '<link rel="stylesheet" href="' . $Root . 'assets/css/site.css?v=1">'
        . '<link rel="stylesheet" href="' . $Root . 'assets/css/portal.css?v=1">'
        . '<script defer src="' . $Root . 'assets/js/portal.js?v=1"></script>'
        . '</head><body class="portal" data-api="' . $Root . 'cliente/api.php">';

    echo '<header class="portal-header"><div class="container portal-header-inner">'
        . '<a class="brand" href="' . $Root . '"><img src="' . $Root . 'assets/img/logo-dark.svg" alt="" width="32" height="32">'
        . '<span class="brand-text"><strong>Pignoro</strong><span>Área do cliente</span></span></a>';
    if ($User !== null)
        echo '<div class="portal-user"><span>' . E($User['nome']) . '</span>'
            . '<form method="post" action="' . $Root . 'cliente/sair.php" data-sair>' . CsrfField()
            . '<button class="btn btn-ghost btn-small" type="submit">Sair</button></form></div>';
    echo '</div>';
    if ($Nav) {
        echo '<nav class="portal-nav" aria-label="Área do cliente"><div class="container">';
        foreach ($Nav as $File => $Label)
            echo '<a href="' . $Root . 'cliente/' . E($File) . '"' . ($File === $v_Active ? ' aria-current="page"' : '') . '>' . E($Label) . '</a>';
        echo '</div></nav>';
    }
    echo '</header><main class="portal-main"><div class="container">';

    if (!empty($_SESSION['aviso'])) {
        [$Kind, $Text] = $_SESSION['aviso'];
        unset($_SESSION['aviso']);
        echo '<p class="notice notice-' . E($Kind) . '" role="status">' . E($Text) . '</p>';
    }
}

function PageFoot(): void
{
    echo '</div></main><footer class="portal-footer"><div class="container">© ' . date('Y') . ' Pignoro Consultoria.</div></footer></body></html>';
}
