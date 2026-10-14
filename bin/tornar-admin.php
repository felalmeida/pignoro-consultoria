<?php
/**
 * Converte um cliente da área do cliente em administrador. Ele continua entrando com a mesma senha.
 *   php bin/tornar-admin.php email@dominio
 * Rodar com o mesmo usuário do servidor web, para o banco manter o dono certo.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli')
    exit(1);
if ($argc < 2 || !filter_var($argv[1], FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php bin/tornar-admin.php email@dominio\n");
    exit(1);
}

require __DIR__ . '/../app/inicio.php';

$User = Row('SELECT * FROM usuarios WHERE email = ?', [mb_strtolower($argv[1])]);
if ($User === null) {
    fwrite(STDERR, "Nenhuma conta com este e-mail.\n");
    exit(1);
}
if ($User['papel'] === 'admin') {
    echo "{$User['nome']} já é administrador.\n";
    exit(0);
}

Query("UPDATE usuarios SET papel = 'admin' WHERE id = ?", [$User['id']]);
echo "{$User['nome']} ({$User['email']}) agora é administrador.\n";
echo $User['senha_hash'] === null
    ? "Ele ainda não criou a senha: use o link de primeiro acesso que já recebeu, ou gere outro com bin/criar-admin.php.\n"
    : "Ele entra com a mesma senha de antes e cai na lista de clientes.\n";
echo "Arquivos já enviados a outros clientes continuam fechados para ele; os enviados daqui em diante abrem.\n";
echo "O cadastro, as mensagens e os arquivos que ele tinha como cliente ficam guardados, mas não aparecem mais na lista de clientes.\n";
