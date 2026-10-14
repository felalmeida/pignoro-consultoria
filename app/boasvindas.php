<?php
/**
 * E-mail de boas-vindas enviado ao cliente quando a Pignoro cria o acesso dele.
 * O HTML usa tabelas e estilos embutidos, que é o que os programas de e-mail aceitam;
 * as cores são as de assets/css/site.css.
 */
declare(strict_types=1);

require_once __DIR__ . '/email.php';

/**
 * Monta [assunto, texto, HTML] no idioma $v_Lang. $v_Link é o endereço completo para o cliente criar a senha.
 */
function WelcomeMail(array $v_Client, string $v_Link, string $v_SiteUrl, string $v_Lang = 'pt'): array
{
    $Tx = fn(string $v_Pt, array $v_Vars = []) => T($v_Pt, $v_Vars, $v_Lang);
    $Nome = E($v_Client['nome']);
    $Email = E($v_Client['email']);
    $Site = E($v_SiteUrl);
    $SiteLabel = E((string) preg_replace('#^https?://|/$#', '', $v_SiteUrl));
    $Validade = FormatDate(date('Y-m-d', time() + ATIVACAO_VALIDADE), $v_Lang);
    $HtmlLang = IDIOMA_HTML[$v_Lang] ?? 'pt-BR';
    // o link abre a página de criação de senha no idioma do e-mail
    $v_Link .= (str_contains($v_Link, '?') ? '&' : '?') . 'idioma=' . $v_Lang;
    $Link = E($v_Link);

    $Subject = $Tx('Boas-vindas à área do cliente da Pignoro');
    $Intro = $Tx('A Pignoro criou o seu acesso à área do cliente. Nela você mantém o cadastro da sua empresa ou fundo e recebe documentos protegidos por criptografia de ponta a ponta.');
    $Guarde = $Tx('Guarde bem a sua senha.');
    $GuardeTexto = $Tx('Ela também protege os seus arquivos e não pode ser recuperada, nem pela Pignoro. A Pignoro nunca pede a sua senha por e-mail, telefone ou mensagem.');
    $Rodape = $Tx('Você recebeu este e-mail porque a Pignoro cadastrou este endereço na área do cliente. Se não reconhece este cadastro, ignore a mensagem: sem a criação da senha, nenhum acesso é aberto.');

    $Text = $Tx('Boas-vindas, {nome}.', ['nome' => $v_Client['nome']]) . "\r\n\r\n"
        . $Intro . "\r\n\r\n"
        . $Tx('Para começar, crie a sua senha neste endereço (válido até {data}):', ['data' => $Validade]) . "\r\n$v_Link\r\n\r\n"
        . $Tx('E-mail de acesso') . ': ' . $v_Client['email'] . "\r\n\r\n"
        . $Guarde . ' ' . $GuardeTexto . "\r\n\r\n"
        . "Pignoro Ltda.\r\n" . $Tx('Av. Paulista, 1106, 16º andar, Bela Vista, São Paulo - SP') . "\r\ninfo@pignoro.com\r\n$v_SiteUrl\r\n\r\n"
        . $Rodape . "\r\n";

    $Subject_ = E($Subject);
    [$Intro, $Guarde, $GuardeTexto, $Rodape] = array_map('E', [$Intro, $Guarde, $GuardeTexto, $Rodape]);
    $Preheader = E($Tx('Crie a sua senha para acessar a área do cliente. O link vale até {data}.', ['data' => $Validade]));
    $AreaLabel = E($Tx('Área do cliente'));
    $Saudacao = $Tx('Boas-vindas, {nome}.', ['nome' => '<span style="color:#d9b866;">' . $Nome . '</span>']);
    $Falta = E($Tx('A Pignoro criou o seu acesso. Falta apenas você definir a sua senha.'));
    $LabelEmail = E($Tx('E-mail de acesso'));
    $LabelValido = E($Tx('Link válido até'));
    $Botao = E($Tx('Criar a minha senha'));
    $SeBotao = E($Tx('Se o botão não abrir, copie este endereço no navegador:'));
    $Encontra = E($Tx('O que você encontra'));
    $Lema = E($Tx('Eu pignoro'));
    $LemaFim = E($Tx('= eu dou uma garantia'));
    $DoLatim = E($Tx('do latim'));
    $Endereco = E($Tx('Av. Paulista, 1106, 16º andar, Bela Vista, São Paulo - SP'));

    $Font = "font-family:'Libre Franklin','Segoe UI',Helvetica,Arial,sans-serif;";
    $Mono = "font-family:Consolas,Menlo,'Courier New',monospace;";
    $Itens = [
        [$Tx('Arquivos protegidos'), $Tx('Documentos cifrados de ponta a ponta, que só você consegue abrir.')],
        [$Tx('Cadastro'), $Tx('Dados da sua empresa ou fundo, sempre atualizados para a Pignoro.')],
    ];
    $Lista = '';
    foreach ($Itens as [$Titulo, $Descricao])
        $Lista .= '<tr><td width="18" valign="top" style="padding:0 0 16px;' . $Font . 'font-size:15px;line-height:24px;color:#8f6d22;">&#9670;</td>'
            . '<td valign="top" style="padding:0 0 16px 6px;' . $Font . 'font-size:15px;line-height:24px;color:#566270;">'
            . '<strong style="color:#1b2530;">' . E($Titulo) . '.</strong> ' . E($Descricao) . '</td></tr>';

    $Html = <<<HTML
<!DOCTYPE html>
<html lang="$HtmlLang">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>$Subject_</title>
<style>@media (max-width:480px){.px{padding-left:22px!important;padding-right:22px!important}.h1{font-size:26px!important;line-height:32px!important}}</style>
</head>
<body style="margin:0;padding:0;background-color:#f6f4ee;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#f6f4ee;">$Preheader</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f6f4ee;">
<tr><td align="center" style="padding:32px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background-color:#ffffff;border:1px solid #e4e0d5;">

<tr><td class="px" style="background-color:#0b1622;padding:22px 40px;border-bottom:1px solid #1f2c3b;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
        <td width="44" height="44" valign="middle" style="width:44px;height:44px;"><img src="{$Site}assets/img/icone-96.png?v=2" width="44" height="44" alt="Pignoro" style="display:block;width:44px;height:44px;border:0;{$Font}font-size:12px;color:#d9b866;"></td>
        <td style="padding-left:12px;">
            <div style="{$Font}font-size:19px;line-height:22px;font-weight:700;color:#eef0f3;">Pignoro</div>
        </td>
    </tr></table>
</td></tr>

<tr><td class="px" style="background-color:#0b1622;padding:44px 40px 46px;">
    <div style="{$Mono}font-size:12px;line-height:16px;letter-spacing:1.5px;text-transform:uppercase;color:#d9b866;">$AreaLabel</div>
    <div class="h1" style="{$Font}font-size:32px;line-height:38px;font-weight:600;color:#eef0f3;padding-top:14px;">$Saudacao</div>
    <div style="{$Font}font-size:16px;line-height:26px;color:#aab4c2;padding-top:16px;">$Falta</div>
</td></tr>
<tr><td height="4" style="height:4px;line-height:4px;font-size:0;background-color:#d9b866;">&nbsp;</td></tr>

<tr><td class="px" style="padding:40px 40px 8px;">
    <div style="{$Font}font-size:16px;line-height:26px;color:#1b2530;">
        $Intro
    </div>
</td></tr>

<tr><td class="px" style="padding:24px 40px 8px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e4e0d5;">
        <tr>
            <td style="padding:14px 18px;border-bottom:1px solid #e4e0d5;{$Font}font-size:13px;line-height:18px;color:#566270;" width="38%">$LabelEmail</td>
            <td style="padding:14px 18px;border-bottom:1px solid #e4e0d5;{$Font}font-size:15px;line-height:20px;font-weight:600;color:#1b2530;word-break:break-all;">$Email</td>
        </tr>
        <tr>
            <td style="padding:14px 18px;{$Font}font-size:13px;line-height:18px;color:#566270;">$LabelValido</td>
            <td style="padding:14px 18px;{$Font}font-size:15px;line-height:20px;font-weight:600;color:#1b2530;">$Validade</td>
        </tr>
    </table>
</td></tr>

<tr><td class="px" align="left" style="padding:28px 40px 8px;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
        <td align="center" style="background-color:#d9b866;border-radius:999px;">
            <a href="$Link" style="display:inline-block;padding:15px 34px;{$Font}font-size:16px;line-height:20px;font-weight:700;color:#0b1622;text-decoration:none;">$Botao</a>
        </td>
    </tr></table>
</td></tr>
<tr><td class="px" style="padding:14px 40px 8px;">
    <div style="{$Font}font-size:13px;line-height:20px;color:#566270;">$SeBotao</div>
    <div style="{$Mono}font-size:12px;line-height:18px;color:#25486b;word-break:break-all;padding-top:4px;"><a href="$Link" style="color:#25486b;">$Link</a></div>
</td></tr>

<tr><td class="px" style="padding:34px 40px 0;">
    <div style="{$Mono}font-size:12px;line-height:16px;letter-spacing:1.5px;text-transform:uppercase;color:#8f6d22;border-top:1px solid #e4e0d5;padding-top:28px;">$Encontra</div>
</td></tr>
<tr><td class="px" style="padding:18px 40px 8px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">$Lista</table>
</td></tr>

<tr><td class="px" style="padding:12px 40px 40px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
        <td style="background-color:#f6f4ee;border-left:4px solid #8f6d22;padding:18px 20px;{$Font}font-size:14px;line-height:22px;color:#1b2530;">
            <strong>$Guarde</strong> $GuardeTexto
        </td>
    </tr></table>
</td></tr>

<tr><td class="px" style="background-color:#0b1622;padding:30px 40px;">
    <div style="{$Font}font-size:16px;line-height:22px;font-weight:600;color:#eef0f3;">$Lema <span style="color:#d9b866;">$LemaFim</span></div>
    <div style="{$Font}font-size:13px;line-height:20px;color:#aab4c2;padding-top:4px;">$DoLatim <em style="font-family:Georgia,'Times New Roman',serif;">pignorā́re</em></div>
    <div style="{$Font}font-size:13px;line-height:20px;color:#aab4c2;padding-top:18px;">Pignoro Ltda. &nbsp;·&nbsp; <a href="$Site" style="color:#d9b866;text-decoration:none;">$SiteLabel</a></div>
    <div style="{$Font}font-size:13px;line-height:20px;color:#aab4c2;padding-top:4px;">$Endereco &nbsp;·&nbsp; <a href="mailto:info@pignoro.com" style="color:#d9b866;text-decoration:none;">info@pignoro.com</a></div>
</td></tr>

</table>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">
<tr><td class="px" style="padding:18px 40px 0;{$Font}font-size:12px;line-height:18px;color:#7b8590;">
    $Rodape
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;

    return [$Subject, $Text, $Html];
}

/** Envia as boas-vindas com o link de primeiro acesso. Devolve false se o e-mail não saiu. */
function SendWelcome(array $v_Config, array $v_Client, string $v_ActivationPath): bool
{
    [$Subject, $Text, $Html] = WelcomeMail($v_Client, PortalUrl() . $v_ActivationPath, SiteUrl(), ValidLang($v_Client['idioma'] ?? null) ?? 'pt');
    // respostas do cliente vão para o endereço de contato da Pignoro (destino do formulário)
    $ReplyTo = filter_var((string) ($v_Config['destino'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string) $v_Config['destino'] : '';
    $Sent = SendMail($v_Config, (string) $v_Client['email'], $ReplyTo, $Subject, $Text, $Html);
    if (!$Sent)
        error_log('[area do cliente] e-mail de boas-vindas não enviado para o cliente ' . ($v_Client['id'] ?? '?'));
    return $Sent;
}
