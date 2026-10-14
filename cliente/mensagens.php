<?php
declare(strict_types=1);
require __DIR__ . '/../app/inicio.php';
require __DIR__ . '/../app/mensagens.php';

$User = RequireRole('cliente');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    CheckCsrf();
    $Error = MensagemEnviar((int) $User['id'], (int) $User['id']);
    Flash($Error === '' ? 'ok' : 'erro', $Error === '' ? T('Mensagem enviada.') : $Error);
    Redirect('mensagens.php');
}

PageHead(T('Mensagens'), 'mensagens.php');
echo '<h1>' . E(T('Mensagens')) . '</h1><p class="page-intro">' . E(T('Conversa com a equipe da Pignoro.')) . '</p>';
MensagemConversa((int) $User['id'], $User, 'mensagens.php');
PageFoot();
