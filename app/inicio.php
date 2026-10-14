<?php
/**
 * Base da área do cliente: banco SQLite, sessão, autenticação, proteção de formulários e layout.
 * Esta pasta é bloqueada para acesso pela web (ver app/.htaccess).
 */
declare(strict_types=1);

mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Sao_Paulo');

$Config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/idioma.php';

// Iterações do PBKDF2 feito no navegador para derivar a senha.
const KDF_ITER = 600000;
// Minutos sem atividade até a sessão expirar.
const SESSAO_OCIOSA = 30 * 60;
// Validade do link de primeiro acesso.
const ATIVACAO_VALIDADE = 7 * 86400;
// Tamanho máximo de um arquivo cifrado.
const ARQUIVO_MAX = 50 * 1024 * 1024;
// Tamanho de cada parte do envio. Fica abaixo de 1 MB, o menor limite comum de proxies e do PHP.
const ENVIO_PARTE = 512 * 1024;

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
        echo '<!DOCTYPE html><html lang="' . IDIOMA_HTML[Lang()] . '"><meta charset="utf-8"><title>' . E(T('Área do cliente')) . '</title><p>' . E($v_Message) . '</p></html>';
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
        Abort(503, T('Área do cliente indisponível no momento.'), 'pasta de dados ' . $Problem . ': ' . $Dir);

    return $Checked = $Dir;
}

/**
 * Raiz dos arquivos cifrados: arquivos/ na raiz do projeto, ou 'pasta_arquivos' em config.php.
 * Dentro dela há uma pasta por cliente, com o id dele como nome.
 */
function FilesRoot(): string
{
    global $Config;
    static $Checked = null;
    if ($Checked !== null)
        return $Checked;

    $Dir = rtrim((string) ($Config['pasta_arquivos'] ?? ''), '/');
    if ($Dir === '')
        $Dir = dirname(__DIR__) . '/arquivos';

    $Problem = '';
    if (!file_exists($Dir) && !@mkdir($Dir, 0700, true) && !is_dir($Dir))
        $Problem = 'não existe e não pôde ser criada';
    elseif (is_link($Dir) || !is_dir($Dir))
        $Problem = 'não é uma pasta real';
    // Só o usuário do PHP pode mexer nesta árvore. Assim ninguém mais no servidor consegue trocar
    // uma pasta por um link simbólico entre a conferência e a gravação.
    elseif (function_exists('posix_geteuid') && fileowner($Dir) !== posix_geteuid())
        $Problem = 'pertence a outro usuário (o dono precisa ser o usuário do PHP)';
    elseif ((fileperms($Dir) & 0077) !== 0 && !@chmod($Dir, 0700))
        $Problem = 'está acessível a outros usuários (use permissão 700)';
    elseif (!is_writable($Dir))
        $Problem = 'está sem permissão de escrita';
    if ($Problem !== '')
        Abort(503, T('Área de arquivos indisponível no momento.'), 'pasta de arquivos ' . $Problem . ': ' . $Dir);

    return $Checked = $Dir;
}

/**
 * Pasta de arquivos de um cliente, criada no primeiro envio com acesso só para o usuário do PHP.
 * Recusa link simbólico ou pasta de outro dono, para ninguém mais no servidor desviar as gravações.
 */
function ClientFilesDir(int $v_ClientId): string
{
    $Dir = FilesRoot() . '/' . $v_ClientId;
    $Problem = '';
    if (!file_exists($Dir) && !is_link($Dir) && !@mkdir($Dir, 0700) && !is_dir($Dir))
        $Problem = 'não pôde ser criada';
    elseif (is_link($Dir) || !is_dir($Dir))
        $Problem = 'não é uma pasta real';
    elseif (function_exists('posix_geteuid') && fileowner($Dir) !== posix_geteuid())
        $Problem = 'pertence a outro usuário';
    elseif ((fileperms($Dir) & 0077) !== 0 && !@chmod($Dir, 0700))
        $Problem = 'está acessível a outros usuários';
    if ($Problem !== '')
        Abort(503, T('Área de arquivos indisponível no momento.'), 'pasta do cliente ' . $Problem . ': ' . $Dir);
    return $Dir;
}

/**
 * Caminho de um arquivo guardado (linha da tabela arquivos), ou vazio se ele não está no disco.
 * Arquivos enviados antes da separação por cliente ficavam todos em config/dados/arquivos;
 * ao serem encontrados lá, são movidos para a pasta do cliente.
 */
function StoredFilePath(array $v_File): string
{
    $Name = basename((string) $v_File['armazenado']);
    $Dir = FilesRoot() . '/' . (int) $v_File['cliente_id'];
    $Path = $Dir . '/' . $Name;
    // só arquivo comum, em pasta real: link simbólico plantado no caminho não é seguido
    if (!is_link($Dir) && !is_link($Path) && is_file($Path))
        return $Path;
    $Old = DataDir() . '/arquivos/' . $Name;
    if (is_link($Old) || !is_file($Old))
        return '';
    ClientFilesDir((int) $v_File['cliente_id']);
    return @rename($Old, $Path) ? $Path : $Old;
}

/** Segredo do servidor, usado para responder de forma uniforme a e-mails não cadastrados. */
function ServerSecret(): string
{
    $File = DataDir() . '/portal.segredo';
    $Secret = is_file($File) ? (string) file_get_contents($File) : '';
    if (strlen($Secret) !== 64) {
        $Secret = bin2hex(random_bytes(32));
        if (file_put_contents($File, $Secret, LOCK_EX) === false)
            Abort(503, T('Área do cliente indisponível no momento.'), 'segredo não pôde ser gravado');
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
        Abort(503, T('Área do cliente indisponível no momento.'), 'extensão pdo_sqlite do PHP não está instalada');

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
    if ($Version >= 3)
        return;
    if ($Version >= 1) {
        $v_Pdo->exec('BEGIN IMMEDIATE');
        if ($Version === 1)
            $v_Pdo->exec("ALTER TABLE usuarios ADD COLUMN idioma TEXT NOT NULL DEFAULT 'pt'");
        // versão 3: mensagens, contatos, créditos e operações saíram da área do cliente
        $v_Pdo->exec('DROP TABLE IF EXISTS mensagens; DROP TABLE IF EXISTS contatos; DROP TABLE IF EXISTS creditos; DROP TABLE IF EXISTS operacoes');
        $v_Pdo->exec('PRAGMA user_version = 3');
        $v_Pdo->exec('COMMIT');
        $v_Pdo->exec('VACUUM');
        return;
    }

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
            ultimo_acesso INTEGER,
            idioma TEXT NOT NULL DEFAULT 'pt'
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
        PRAGMA user_version = 3;
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

function InNets(string $v_Ip, array $v_Nets): bool
{
    foreach ($v_Nets as $Cidr) {
        if (InCidr($v_Ip, (string) $Cidr))
            return true;
    }
    return false;
}

/** Conexão vinda do Cloudflare ou de um proxy da própria infraestrutura (config.php). */
function FromTrustedProxy(): bool
{
    global $Config;
    $Remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return InNets($Remote, (array) ($Config['redes_cloudflare'] ?? [])) || InNets($Remote, (array) ($Config['proxies_confiaveis'] ?? []));
}

/**
 * IP do visitante. Parte de quem conectou e anda para trás em X-Forwarded-For enquanto o salto
 * for um proxy da própria infraestrutura; o primeiro endereço que não é proxy é o visitante.
 * O cabeçalho CF-Connecting-IP só vale quando o salto anterior é comprovadamente do Cloudflare.
 * Assim, um cabeçalho forjado pelo visitante nunca é aceito como IP dele.
 */
function ClientIp(): string
{
    global $Config;
    $Remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $Hops = array_merge([$Remote], array_reverse(array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')))));
    $Cloudflare = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
    $Ip = '0.0.0.0';
    foreach ($Hops as $Hop) {
        if (!filter_var($Hop, FILTER_VALIDATE_IP))
            break;
        $Ip = $Hop;
        if (InNets($Hop, (array) ($Config['redes_cloudflare'] ?? [])))
            return (filter_var($Cloudflare, FILTER_VALIDATE_IP) ? $Cloudflare : $Hop);
        if (!InNets($Hop, (array) ($Config['proxies_confiaveis'] ?? [])))
            break;
    }
    return ($Ip);
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
        $_SESSION['aviso'] = ['erro', T('Sua sessão expirou. Entre novamente.')];
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
        Abort(403, T('Formulário expirado. Recarregue a página e tente de novo.'));
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

/**
 * Define o idioma da requisição. Ordem: ?idioma= (troca pelo seletor, que fica gravada no cookie
 * e na conta), cookie, idioma da conta e, por fim, o idioma preferido do navegador.
 */
function ResolveLang(): void
{
    $Chosen = ValidLang($_GET['idioma'] ?? null);
    if ($Chosen !== null) {
        setcookie('pignoro_idioma', $Chosen, ['expires' => time() + 365 * 86400, 'path' => '/',
            'secure' => IsHttps(), 'httponly' => true, 'samesite' => 'Lax']);
        $_COOKIE['pignoro_idioma'] = $Chosen;
    }
    $Lang = $Chosen ?? ValidLang($_COOKIE['pignoro_idioma'] ?? null);
    if ($Lang === null && isset($_COOKIE['pignoro_sessao'])) {
        $User = CurrentUser();
        $Lang = ValidLang($User['idioma'] ?? null);
    }
    Lang($Lang ?? BrowserLang());
    if ($Chosen !== null && isset($_COOKIE['pignoro_sessao']) && ($User = CurrentUser()) !== null && $User['idioma'] !== $Chosen)
        Query('UPDATE usuarios SET idioma = ? WHERE id = ?', [$Chosen, $User['id']]);
}

/** Endereço da página atual com o idioma trocado, para o seletor. */
function LangUrl(string $v_Lang): string
{
    $Query = $_GET;
    $Query['idioma'] = $v_Lang;
    return basename((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH)) . '?' . http_build_query($Query);
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
            Abort(401, T('Sessão encerrada. Entre novamente.'));
        Redirect('index.php');
    }
    if ($User['papel'] !== $v_Role) {
        if ($Json)
            Abort(403, T('Acesso não permitido.'));
        Redirect($User['papel'] === 'admin' ? 'admin/index.php' : 'painel.php');
    }
    return $User;
}

/** Cliente com o id informado, ou erro 404. */
function ClientOr404(int $v_Id): array
{
    $Client = Row("SELECT * FROM usuarios WHERE id = ? AND papel = 'cliente'", [$v_Id]);
    if ($Client === null)
        Abort(404, T('Cliente não encontrado.'));
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

/** Endereço configurado em config.php, normalizado com barra no fim, ou vazio se ausente ou inválido. */
function ConfiguredUrl(string $v_Key): string
{
    global $Config;
    $Url = trim((string) ($Config[$v_Key] ?? ''));
    return preg_match('#^https?://[A-Za-z0-9.-]+(:\d+)?(/[^\s"<>]*)?$#', $Url) === 1 ? rtrim($Url, '/') . '/' : '';
}

/**
 * Endereço completo do site público. Vem de 'url_site' em config.php; o cabeçalho Host da
 * requisição pode ser forjado e só é usado quando essa configuração está vazia (teste local).
 */
function SiteUrl(): string
{
    $Fixed = ConfiguredUrl('url_site');
    if ($Fixed !== '')
        return $Fixed;

    $Host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (preg_match('/^[A-Za-z0-9.-]+(:\d+)?$/', $Host) !== 1)
        $Host = 'localhost';
    $Base = rtrim(dirname(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/cliente/x'))), '/');
    if (str_ends_with($Base, '/cliente'))
        $Base = substr($Base, 0, -8);
    return (IsHttps() ? 'https' : 'http') . '://' . $Host . $Base . '/';
}

/** Endereço da área do cliente: 'url_area_cliente' quando configurado, senão o do site. */
function PortalUrl(): string
{
    return ConfiguredUrl('url_area_cliente') ?: SiteUrl();
}

/** Início do site público a partir de uma página da área do cliente. */
function PublicRoot(): string
{
    return ConfiguredUrl('url_area_cliente') !== '' ? SiteUrl() : Root();
}

/**
 * Com a área do cliente em endereço próprio, ela não responde pelo endereço do site: quem chega
 * por ele é levado ao mesmo caminho no endereço da área do cliente. Isso mantém sessão, chaves e
 * arquivos numa origem onde não roda código de terceiros (medição do site público).
 */
function EnforcePortalHost(): void
{
    $Portal = ConfiguredUrl('url_area_cliente');
    if ($Portal === '' || PHP_SAPI === 'cli')
        return;
    $Wanted = strtolower((string) parse_url($Portal, PHP_URL_HOST)) . (($Port = parse_url($Portal, PHP_URL_PORT)) ? ':' . $Port : '');
    if (strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')) === $Wanted)
        return;
    $Path = ltrim((string) ($_SERVER['REQUEST_URI'] ?? '/'), '/');
    $Base = (string) parse_url($Portal, PHP_URL_PATH);
    if ($Base !== '' && $Base !== '/' && str_starts_with('/' . $Path, $Base))
        $Path = substr('/' . $Path, strlen($Base));
    header('Location: ' . $Portal . $Path, true, 307);
    exit;
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

// Datas e valores: dia/mês/ano e vírgula decimal em português e espanhol; mês/dia/ano e ponto em inglês.

function FormatDateTime(?int $v_Time, ?string $v_Lang = null): string
{
    return $v_Time ? date(($v_Lang ?? Lang()) === 'en' ? 'm/d/Y H:i' : 'd/m/Y H:i', $v_Time) : '';
}

function FormatDate(string $v_Iso, ?string $v_Lang = null): string
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v_Iso, $M))
        return '';
    return ($v_Lang ?? Lang()) === 'en' ? "$M[2]/$M[3]/$M[1]" : "$M[3]/$M[2]/$M[1]";
}

function FormatMoney(int $v_Cents): string
{
    return Lang() === 'en' ? number_format($v_Cents / 100, 2, '.', ',') : number_format($v_Cents / 100, 2, ',', '.');
}

/**
 * Converte um valor digitado em centavos: "1.234,56" em português e espanhol, "1,234.56" em inglês.
 * Devolve null se não for um valor válido.
 */
function ParseMoney(string $v_Str): ?int
{
    $Str = preg_replace('/[\s\x{00A0}]|R\$|US\$|\$/u', '', $v_Str);
    if ($Str === '')
        return 0;
    if (Lang() === 'en')
        $Str = str_replace(',', '', $Str);
    elseif (str_contains($Str, ','))
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
        $Nav = ['painel.php' => T('Início'), 'arquivos.php' => T('Arquivos'), 'cadastro.php' => T('Cadastro'),
            'conta.php' => T('Minha conta'), 'ajuda.php' => T('Ajuda')];
    elseif ($User !== null)
        $Nav = ['admin/index.php' => T('Clientes'), 'conta.php' => T('Minha conta'), 'ajuda.php' => T('Ajuda')];
    $Lang = Lang();

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="' . IDIOMA_HTML[$Lang] . '"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex, nofollow">'
        . '<meta name="csrf" content="' . E(CsrfToken()) . '">'
        . '<title>' . E($v_Title) . ' | Pignoro</title>'
        . '<link rel="icon" href="' . $Root . 'assets/img/icone-32.png?v=2" sizes="32x32" type="image/png">'
        . '<link rel="apple-touch-icon" href="' . $Root . 'assets/img/icone-180.png?v=2">'
        . '<link rel="stylesheet" href="' . $Root . 'assets/css/site.css?v=7">'
        . '<link rel="stylesheet" href="' . $Root . 'assets/css/portal.css?v=4">'
        . '<script defer src="' . $Root . 'assets/js/portal.js?v=5"></script>'
        . '</head><body class="portal" data-api="' . $Root . 'cliente/api.php"'
        . ($Lang !== 'pt' ? ' data-textos="' . E((string) json_encode(Dictionary($Lang, 'js'), JSON_UNESCAPED_UNICODE)) . '"' : '') . '>';

    echo '<header class="portal-header"><div class="container portal-header-inner">'
        . '<a class="brand" href="' . E(PublicRoot()) . '"><img src="' . $Root . 'assets/img/icone-96.png?v=2" alt="" width="32" height="32">'
        . '<span class="brand-text"><strong>Pignoro</strong><span>' . E(T('Área do cliente')) . '</span></span></a>'
        . '<div class="portal-user"><nav class="lang-switch" aria-label="' . E(T('Idioma')) . '">';
    foreach (IDIOMAS as $Code => $Name)
        echo '<a href="' . E(LangUrl($Code)) . '" hreflang="' . $Code . '" lang="' . $Code . '" title="' . E($Name) . '"'
            . ($Code === $Lang ? ' aria-current="true"' : '') . '>' . strtoupper($Code) . '</a>';
    echo '</nav>';
    if ($User !== null)
        echo '<span class="portal-user-name">' . E($User['nome']) . '</span>'
            . '<form method="post" action="' . $Root . 'cliente/sair.php" data-sair>' . CsrfField()
            . '<button class="btn btn-ghost btn-small" type="submit">' . E(T('Sair')) . '</button></form>';
    echo '</div></div>';
    if ($Nav) {
        echo '<nav class="portal-nav" aria-label="' . E(T('Área do cliente')) . '"><div class="container">';
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
    echo '</div></main><footer class="portal-footer"><div class="container portal-footer-inner">'
        . '<p>© ' . date('Y') . ' Pignoro Ltda. | ' . E(T('Todos os Direitos Reservados')) . '<br>'
        . '<a href="' . E(PublicRoot()) . (Lang() === 'pt' ? '' : Lang() . '/') . 'termos/">'
        . E(T('Termos de Uso e Política de Privacidade')) . '</a></p>'
        . '<address>' . E(T('Av. Paulista, 1106, 16º andar, Bela Vista, São Paulo - SP')) . '<br>'
        . '<a href="mailto:info@pignoro.com">info@pignoro.com</a></address>'
        . '</div></footer></body></html>';
}

EnforcePortalHost();
ResolveLang();
