<?php
/**
 * Endpoint do formulário de contato.
 *   GET  contato.php?token=1  -> devolve um token assinado com a hora de abertura
 *   POST contato.php          -> valida e envia a mensagem por e-mail
 * Respostas sempre em JSON.
 */
declare(strict_types=1);

mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$Config = require __DIR__ . '/config/config.php';
require __DIR__ . '/app/email.php';
require __DIR__ . '/app/idioma.php';

// idioma da página que enviou o formulário: só as respostas ao visitante mudam; o e-mail interno sai em português
Lang(ValidLang($_POST['idioma'] ?? $_GET['idioma'] ?? null) ?? 'pt');

function Respond(int $v_Status, array $v_Data): void
{
    http_response_code($v_Status);
    echo json_encode($v_Data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Pasta privada para o segredo, os contadores e os tokens usados.
 * Nunca usa a pasta temporária compartilhada do sistema. Sem uma pasta segura o envio é recusado.
 */
function DataDir(): string
{
    global $Config;
    static $Checked = null;
    if ($Checked !== null)
        return $Checked;

    $Dir = rtrim((string) ($Config['pasta_dados'] ?? ''), '/');
    if ($Dir === '')
        $Dir = __DIR__ . '/config/dados'; // config/ é bloqueada para a web pelo .htaccess

    if (!file_exists($Dir) && !@mkdir($Dir, 0700, true) && !is_dir($Dir))
        Fail('pasta de dados não pôde ser criada: ' . $Dir);
    if (is_link($Dir) || !is_dir($Dir))
        Fail('pasta de dados não é uma pasta real: ' . $Dir);
    if (function_exists('posix_geteuid') && fileowner($Dir) !== posix_geteuid())
        Fail('pasta de dados pertence a outro usuário: ' . $Dir);
    if ((fileperms($Dir) & 0077) !== 0 && (!@chmod($Dir, 0700) || (fileperms($Dir) & 0077) !== 0)) {
        clearstatcache(true, $Dir);
        if ((fileperms($Dir) & 0077) !== 0)
            Fail('pasta de dados acessível a outros usuários: ' . $Dir);
    }
    if (!is_writable($Dir))
        Fail('pasta de dados sem permissão de escrita: ' . $Dir);

    return $Checked = $Dir;
}

/** Abre um arquivo de dados sem seguir links simbólicos. */
function OpenDataFile(string $v_File)
{
    if (is_link($v_File))
        Fail('link simbólico inesperado na pasta de dados');
    $Handle = @fopen($v_File, 'c+');
    if ($Handle === false || !flock($Handle, LOCK_EX))
        Fail('não foi possível abrir ou travar ' . basename($v_File));
    @chmod($v_File, 0600);
    return $Handle;
}

/** Substitui o conteúdo de um arquivo já travado. Qualquer erro de gravação recusa o envio. */
function RewriteDataFile($v_Handle, string $v_Content): void
{
    if (!ftruncate($v_Handle, 0) || !rewind($v_Handle)
        || fwrite($v_Handle, $v_Content) !== strlen($v_Content) || !fflush($v_Handle))
        Fail('falha ao gravar na pasta de dados');
}

/** Falha de infraestrutura: registra no log e recusa o envio (nunca libera). */
function Fail(string $v_Reason): void
{
    error_log('contato.php: ' . $v_Reason);
    Respond(503, ['ok' => false, 'erro' => T('Formulário indisponível no momento.')]);
}

/** Segredo aleatório para assinar o token; criado no primeiro uso. */
function GetSecret(): string
{
    static $Secret = null;
    if ($Secret !== null)
        return $Secret;
    $Handle = OpenDataFile(DataDir() . '/secret.key');
    $Value = trim((string) stream_get_contents($Handle));
    if (!preg_match('/^[0-9a-f]{64}$/', $Value)) {
        $Value = bin2hex(random_bytes(32));
        RewriteDataFile($Handle, $Value);
    }
    flock($Handle, LOCK_UN);
    fclose($Handle);
    return $Secret = $Value;
}

/** Token: hora de emissão + valor aleatório, assinados e presos ao IP de quem pediu. */
function MakeToken(): string
{
    $Payload = time() . '.' . bin2hex(random_bytes(12));
    return $Payload . '.' . hash_hmac('sha256', $Payload . '|' . ClientKey(), GetSecret());
}

/** Devolve a idade do token em segundos, ou null se for inválido. */
function TokenAge(string $v_Token): ?int
{
    $Parts = explode('.', $v_Token);
    if (count($Parts) !== 3 || !ctype_digit($Parts[0]) || !ctype_xdigit($Parts[1]))
        return null;
    $Payload = $Parts[0] . '.' . $Parts[1];
    if (!hash_equals(hash_hmac('sha256', $Payload . '|' . ClientKey(), GetSecret()), $Parts[2]))
        return null;
    return time() - (int) $Parts[0];
}

/** Marca o token como usado. Devolve false se ele já foi usado (criação atômica do arquivo). */
function ConsumeToken(string $v_Token, int $v_MaxAge): bool
{
    $Dir = DataDir() . '/usados';
    if (!is_dir($Dir) && !@mkdir($Dir, 0700, true) && !is_dir($Dir))
        Fail('pasta de tokens indisponível');
    $Handle = @fopen($Dir . '/' . hash('sha256', $v_Token), 'x');
    if ($Handle === false)
        return false;
    fclose($Handle);
    if (random_int(1, 20) === 1) { // limpeza ocasional dos vencidos
        foreach ((array) glob($Dir . '/*') as $Old)
            if (is_file($Old) && filemtime($Old) < time() - $v_MaxAge - 60)
                @unlink($Old);
    }
    return true;
}

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

/**
 * IP do visitante. Parte de quem conectou e anda para trás em X-Forwarded-For enquanto o salto
 * for um proxy da própria infraestrutura; o primeiro endereço que não é proxy é o visitante.
 * O cabeçalho CF-Connecting-IP só vale quando o salto anterior é comprovadamente do Cloudflare.
 * Assim, um cabeçalho forjado pelo visitante nunca é aceito como IP dele.
 */
function ClientIp(): string
{
    global $Config;
    static $Cached = null;
    if ($Cached !== null)
        return $Cached;
    $Remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $Hops = array_merge([$Remote], array_reverse(array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')))));
    $Cloudflare = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
    $Ip = '0.0.0.0';
    foreach ($Hops as $Hop) {
        if (!filter_var($Hop, FILTER_VALIDATE_IP))
            break;
        $Ip = $Hop;
        if (InNets($Hop, (array) ($Config['redes_cloudflare'] ?? [])))
            return $Cached = (filter_var($Cloudflare, FILTER_VALIDATE_IP) ? $Cloudflare : $Hop);
        if (!InNets($Hop, (array) ($Config['proxies_confiaveis'] ?? [])))
            break;
    }
    return $Cached = ($Ip);
}

/**
 * Chave do visitante para limites e tokens. Em IPv6 usa a rede /64, porque um mesmo
 * assinante controla todos os endereços dela e poderia trocar de endereço a cada envio.
 */
function ClientKey(): string
{
    $Ip = ClientIp();
    $Bin = @inet_pton($Ip);
    if ($Bin !== false && strlen($Bin) === 16)
        return 'v6:' . bin2hex(substr($Bin, 0, 8));
    return 'v4:' . $Ip;
}

/**
 * Registra uma tentativa na chave informada e devolve false se o limite foi atingido.
 * Leitura e gravação acontecem sob trava exclusiva. Conteúdo ilegível ou erro de
 * gravação recusam o envio, em vez de zerar a contagem.
 */
function RateLimitOk(string $v_Key, int $v_Max, int $v_Window): bool
{
    $Handle = OpenDataFile(DataDir() . '/limite_' . hash('sha256', $v_Key) . '.json');
    $Now = time();
    $Raw = (string) stream_get_contents($Handle);
    $Hits = $Raw === '' ? [] : json_decode($Raw, true);
    if (!is_array($Hits))
        Fail('contador de envios corrompido');
    $Hits = array_values(array_filter($Hits, fn($t) => is_int($t) && $t > $Now - $v_Window && $t <= $Now));
    $Allowed = count($Hits) < $v_Max;
    if ($Allowed)
        $Hits[] = $Now;
    RewriteDataFile($Handle, (string) json_encode($Hits));
    flock($Handle, LOCK_UN);
    fclose($Handle);
    return $Allowed;
}

/** Texto de uma linha: sem quebras nem caracteres de controle. */
function CleanLine(string $v_Str, int $v_Max): string
{
    $Str = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v_Str) ?? '';
    return mb_substr(trim($Str), 0, $v_Max);
}

function CleanText(string $v_Str, int $v_Max): string
{
    $Str = str_replace(["\r\n", "\r"], "\n", $v_Str);
    $Str = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', ' ', $Str) ?? '';
    return mb_substr(trim($Str), 0, $v_Max);
}

function Field(string $v_Name): string
{
    $Value = $_POST[$v_Name] ?? '';
    return is_string($Value) ? $Value : '';
}

$Method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($Method === 'GET') {
    if (isset($_GET['token'])) {
        if (!RateLimitOk('token|' . ClientKey(), (int) $Config['limite_tokens'], (int) $Config['limite_janela']))
            Respond(429, ['ok' => false, 'erro' => T('Muitas tentativas. Tente mais tarde.')]);
        Respond(200, ['token' => MakeToken()]);
    }
    Respond(405, ['ok' => false, 'erro' => T('Método não permitido.')]);
}

if ($Method !== 'POST') {
    header('Allow: GET, POST');
    Respond(405, ['ok' => false, 'erro' => T('Método não permitido.')]);
}

// campo-armadilha: pessoas não o veem; robôs o preenchem. Finge sucesso.
if (Field('website') !== '')
    Respond(200, ['ok' => true]);

// toda tentativa conta para o limite, inclusive as que falham na validação
if (!RateLimitOk('envio|' . ClientKey(), (int) $Config['limite_envios'], (int) $Config['limite_janela']))
    Respond(429, ['ok' => false, 'erro' => T('Muitos envios em pouco tempo. Tente mais tarde.')]);

$Age = TokenAge(Field('token'));
if ($Age === null || $Age > (int) $Config['tempo_maximo'])
    Respond(400, ['ok' => false, 'erro' => T('A página ficou aberta por muito tempo. Recarregue e envie novamente.')]);
if ($Age < (int) $Config['tempo_minimo'])
    Respond(400, ['ok' => false, 'erro' => T('Envio rápido demais. Aguarde alguns segundos e tente de novo.')]);

$Tipo = Field('tipo') === 'agendar' ? 'agendar' : 'mensagem';
$Nome = CleanLine(Field('nome'), 100);
$Email = CleanLine(Field('email'), 150);
$Empresa = CleanLine(Field('empresa'), 100);
$Telefone = CleanLine(Field('telefone'), 30);
$Assunto = CleanLine(Field('assunto'), 80);
$Mensagem = CleanText(Field('mensagem'), 3000);
$Data = CleanLine(Field('data'), 10);
$Periodo = CleanLine(Field('periodo'), 20);

if ($Nome === '' || $Mensagem === '')
    Respond(422, ['ok' => false, 'erro' => T('Preencha nome e mensagem.')]);
if (!filter_var($Email, FILTER_VALIDATE_EMAIL))
    Respond(422, ['ok' => false, 'erro' => T('Informe um e-mail válido.')]);
if ($Data !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $Data))
    $Data = '';

// teto somando todos os visitantes: só conta envios que passaram por toda a validação
if (!RateLimitOk('envio|total', (int) $Config['limite_total'], (int) $Config['limite_janela']))
    Respond(429, ['ok' => false, 'erro' => T('Muitos envios em pouco tempo. Tente mais tarde.')]);

// cada token vale para um único envio
if (!ConsumeToken(Field('token'), (int) $Config['tempo_maximo']))
    Respond(400, ['ok' => false, 'erro' => T('Este formulário já foi enviado. Recarregue a página para enviar outro.')]);

$Linhas = [
    'Tipo: ' . ($Tipo === 'agendar' ? 'Pedido de agendamento' : 'Mensagem'),
    'Nome: ' . $Nome,
    'E-mail: ' . $Email,
    'Empresa: ' . ($Empresa !== '' ? $Empresa : '-'),
    'Telefone: ' . ($Telefone !== '' ? $Telefone : '-'),
    'Assunto: ' . ($Assunto !== '' ? $Assunto : '-'),
];
if ($Tipo === 'agendar') {
    $DataBr = $Data !== '' ? implode('/', array_reverse(explode('-', $Data))) : 'sem preferência';
    $Linhas[] = 'Data de preferência: ' . $DataBr;
    $Linhas[] = 'Período: ' . ($Periodo !== '' ? $Periodo : '-');
}
$Linhas[] = '';
$Linhas[] = 'Mensagem:';
$Linhas[] = $Mensagem;
$Linhas[] = '';
$Linhas[] = '--';
$Linhas[] = 'Enviado pelo site em ' . date('d/m/Y H:i') . ' | IP ' . ClientIp();

$Subject = '[' . $Config['nome_site'] . '] ' . ($Tipo === 'agendar' ? 'Agendamento' : 'Contato') . ': ' . $Nome;
$Body = implode("\r\n", $Linhas);

// config/smtp.php existe: envia por SMTP autenticado. Senão, usa o mail() do servidor.
$Sent = SendMail($Config, (string) $Config['destino'], $Email, $Subject, $Body);

if (!$Sent) {
    error_log('contato.php: mensagem não enviada para ' . $Config['destino']);
    // 500 e não 502: o Cloudflare troca respostas 502 da origem pela página de erro dele
    Respond(500, ['ok' => false, 'erro' => T('Não foi possível enviar a mensagem agora. Tente novamente em alguns minutos.')]);
}

Respond(200, ['ok' => true]);
