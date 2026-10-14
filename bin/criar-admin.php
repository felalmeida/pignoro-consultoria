<?php
/**
 * Cria um administrador da área do cliente e mostra o link para ele definir a senha.
 *   php bin/criar-admin.php "Nome" email@dominio https://endereco-do-site/
 * Rodar com o mesmo usuário do servidor web, para o banco ficar com o dono certo.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli')
    exit(1);
if ($argc < 4 || !filter_var($argv[2], FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php bin/criar-admin.php \"Nome\" email@dominio https://endereco-do-site/\n");
    exit(1);
}

require __DIR__ . '/../app/inicio.php';

$Email = mb_strtolower($argv[2]);
$User = Row('SELECT id, papel FROM usuarios WHERE email = ?', [$Email]);
if ($User !== null && $User['papel'] !== 'admin') {
    fwrite(STDERR, "Este e-mail já pertence a um cliente.\n");
    exit(1);
}
if ($User === null) {
    Query("INSERT INTO usuarios (papel, nome, email, criado_em) VALUES ('admin', ?, ?, ?)", [CleanLine($argv[1], 100), $Email, time()]);
    $User = ['id' => (int) Db()->lastInsertId()];
}
echo "Link para definir a senha (válido por 7 dias):\n" . rtrim($argv[3], '/') . '/' . NewActivation((int) $User['id']) . "\n";
