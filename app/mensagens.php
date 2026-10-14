<?php
/** Conversa entre o cliente e a Pignoro, usada nas telas do cliente e do administrador. */
declare(strict_types=1);

/** Grava a mensagem enviada pelo formulário. Devolve o erro, ou vazio se gravou. */
function MensagemEnviar(int $v_ClientId, int $v_AuthorId): string
{
    $Text = CleanText(is_string($_POST['texto'] ?? null) ? $_POST['texto'] : '', 3000);
    if ($Text === '')
        return 'Escreva a mensagem antes de enviar.';
    Query('INSERT INTO mensagens (cliente_id, autor_id, texto, criado_em) VALUES (?, ?, ?, ?)', [$v_ClientId, $v_AuthorId, $Text, time()]);
    return '';
}

/** Mostra a conversa e marca como lidas as mensagens escritas pelo outro lado. */
function MensagemConversa(int $v_ClientId, array $v_Reader, string $v_Action): void
{
    $FromClient = $v_Reader['papel'] === 'admin';
    Query('UPDATE mensagens SET lida_em = ? WHERE cliente_id = ? AND lida_em IS NULL AND (autor_id = cliente_id) = ?',
        [time(), $v_ClientId, (int) $FromClient]);

    $Rows = Query('SELECT m.*, u.nome, u.papel FROM mensagens m JOIN usuarios u ON u.id = m.autor_id
        WHERE m.cliente_id = ? ORDER BY m.id', [$v_ClientId])->fetchAll();

    echo '<div class="thread">';
    if (!$Rows)
        echo '<p class="empty">Nenhuma mensagem até agora.</p>';
    foreach ($Rows as $Row) {
        $Mine = (int) $Row['autor_id'] === (int) $v_Reader['id'];
        echo '<article class="msg' . ($Mine ? ' msg-mine' : '') . '"><header><strong>'
            . E($Row['papel'] === 'admin' ? 'Pignoro' : $Row['nome']) . '</strong><time>' . FormatDateTime((int) $Row['criado_em'])
            . '</time></header><p>' . nl2br(E($Row['texto'])) . '</p></article>';
    }
    echo '</div><form class="pform card-box" method="post" action="' . E($v_Action) . '">' . CsrfField()
        . '<input type="hidden" name="acao" value="mensagem">'
        . '<label class="pfield pfield-wide"><span>Nova mensagem</span><textarea name="texto" rows="4" maxlength="3000" required></textarea></label>'
        . '<div class="pform-foot"><button class="btn btn-primary" type="submit">Enviar</button></div></form>';
}
