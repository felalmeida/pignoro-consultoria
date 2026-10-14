<?php
/**
 * Envio de e-mail do site, usado pelo formulário de contato e pela área do cliente.
 * Com config/smtp.php, envia por SMTP autenticado; sem ele, usa o mail() do servidor.
 */
declare(strict_types=1);

/** Lê uma resposta SMTP completa (pode ter várias linhas) e devolve [código, texto]. */
function SmtpRead($v_Socket): array
{
    $Text = '';
    while (($Line = fgets($v_Socket, 1024)) !== false) {
        $Text .= $Line;
        if (strlen($Line) < 4 || $Line[3] === ' ')
            break;
    }
    return [(int) substr($Text, 0, 3), trim($Text)];
}

/** Envia um comando e exige o código esperado; lança exceção com a etapa que falhou. */
function SmtpCommand($v_Socket, ?string $v_Command, int $v_Expect, string $v_Step): string
{
    if ($v_Command !== null && fwrite($v_Socket, $v_Command . "\r\n") === false)
        throw new RuntimeException($v_Step . ': falha ao escrever');
    [$Code, $Text] = SmtpRead($v_Socket);
    if ($Code !== $v_Expect)
        throw new RuntimeException($v_Step . ': resposta ' . $Code . ' ' . substr($Text, 4, 160));
    return $Text;
}

/**
 * Cabeçalhos de conteúdo e corpo da mensagem. Só texto, ou texto e HTML como alternativas.
 * Tudo em base64: sem linhas longas e sem risco de uma linha começar com ponto.
 */
function MailContent(string $v_Text, string $v_Html): array
{
    $Encode = fn(string $v_Part) => rtrim(chunk_split(base64_encode($v_Part), 76, "\r\n"));
    if ($v_Html === '')
        return [['Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: base64'], $Encode($v_Text)];

    $Boundary = 'parte-' . bin2hex(random_bytes(12));
    $Body = '';
    foreach (['text/plain' => $v_Text, 'text/html' => $v_Html] as $Type => $Part)
        $Body .= '--' . $Boundary . "\r\nContent-Type: " . $Type . "; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $Encode($Part) . "\r\n";
    return [['Content-Type: multipart/alternative; boundary="' . $Boundary . '"'], $Body . '--' . $Boundary . '--'];
}

/**
 * Envia a mensagem por SMTP autenticado (Gmail, Google Workspace e similares).
 * Devolve true em caso de sucesso; em caso de erro registra a etapa no log (nunca a senha).
 */
function SmtpSend(array $v_Smtp, string $v_To, string $v_ReplyTo, string $v_Subject, string $v_Text, string $v_FromName, string $v_Html = ''): bool
{
    $Host = (string) ($v_Smtp['servidor'] ?? '');
    $Port = (int) ($v_Smtp['porta'] ?? 587);
    $Security = (string) ($v_Smtp['seguranca'] ?? 'starttls'); // starttls | ssl | nenhuma (só para teste local)
    $User = (string) ($v_Smtp['usuario'] ?? '');
    $Pass = str_replace(' ', '', (string) ($v_Smtp['senha'] ?? '')); // o Google mostra a senha de app com espaços
    $From = (string) ($v_Smtp['remetente'] ?? $User);
    $Timeout = 15;

    if ($Host === '' || $User === '' || $Pass === '' || !filter_var($From, FILTER_VALIDATE_EMAIL)) {
        error_log('e-mail: SMTP sem servidor, usuário, senha ou remetente válido em config/smtp.php');
        return false;
    }

    $Socket = null;
    try {
        $Socket = @stream_socket_client(
            ($Security === 'ssl' ? 'ssl://' : 'tcp://') . $Host . ':' . $Port,
            $ErrNo,
            $ErrStr,
            $Timeout
        );
        if ($Socket === false)
            throw new RuntimeException('conexão: ' . $ErrStr);
        stream_set_timeout($Socket, $Timeout);

        $Ehlo = 'EHLO ' . preg_replace('/[^A-Za-z0-9.-]/', '', (string) ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        SmtpCommand($Socket, null, 220, 'saudação');
        SmtpCommand($Socket, $Ehlo, 250, 'EHLO');
        if ($Security === 'starttls') {
            SmtpCommand($Socket, 'STARTTLS', 220, 'STARTTLS');
            if (stream_socket_enable_crypto($Socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true)
                throw new RuntimeException('STARTTLS: negociação TLS falhou');
            SmtpCommand($Socket, $Ehlo, 250, 'EHLO após TLS');
        }
        SmtpCommand($Socket, 'AUTH LOGIN', 334, 'AUTH');
        SmtpCommand($Socket, base64_encode($User), 334, 'usuário');
        SmtpCommand($Socket, base64_encode($Pass), 235, 'senha');
        SmtpCommand($Socket, 'MAIL FROM:<' . $From . '>', 250, 'MAIL FROM');
        SmtpCommand($Socket, 'RCPT TO:<' . $v_To . '>', 250, 'RCPT TO');
        SmtpCommand($Socket, 'DATA', 354, 'DATA');

        [$ContentHeaders, $Body] = MailContent($v_Text, $v_Html);
        $Headers = array_merge([
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . substr((string) strrchr($From, '@'), 1) . '>',
            'From: ' . mb_encode_mimeheader($v_FromName, 'UTF-8') . ' <' . $From . '>',
            'To: <' . $v_To . '>',
        ], $v_ReplyTo !== '' ? ['Reply-To: <' . $v_ReplyTo . '>'] : [], [
            'Subject: ' . mb_encode_mimeheader($v_Subject, 'UTF-8'),
            'MIME-Version: 1.0',
        ], $ContentHeaders);
        SmtpCommand($Socket, implode("\r\n", $Headers) . "\r\n\r\n" . $Body . "\r\n.", 250, 'conteúdo');
        @fwrite($Socket, "QUIT\r\n");
        fclose($Socket);
        return true;
    } catch (Throwable $Error) {
        error_log('e-mail: SMTP falhou em ' . $Error->getMessage());
        if (is_resource($Socket))
            fclose($Socket);
        return false;
    }
}

/**
 * Envia um e-mail com as configurações do site. $v_ReplyTo e $v_Html são opcionais.
 * O remetente é o de config/smtp.php, ou 'remetente' de config/config.php quando o envio é por mail().
 */
function SendMail(array $v_Config, string $v_To, string $v_ReplyTo, string $v_Subject, string $v_Text, string $v_Html = ''): bool
{
    $FromName = (string) $v_Config['nome_site'];
    $SmtpFile = __DIR__ . '/../config/smtp.php';
    if (is_file($SmtpFile))
        return SmtpSend((array) require $SmtpFile, $v_To, $v_ReplyTo, $v_Subject, $v_Text, $FromName, $v_Html);

    [$ContentHeaders, $Body] = MailContent($v_Text, $v_Html);
    $Headers = array_merge(
        ['From: ' . mb_encode_mimeheader($FromName, 'UTF-8') . ' <' . $v_Config['remetente'] . '>'],
        $v_ReplyTo !== '' ? ['Reply-To: ' . $v_ReplyTo] : [],
        ['MIME-Version: 1.0'],
        $ContentHeaders
    );
    return @mail($v_To, mb_encode_mimeheader($v_Subject, 'UTF-8'), $Body, implode("\r\n", $Headers));
}
