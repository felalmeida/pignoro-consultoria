<?php
/**
 * Envio do formulário por SMTP autenticado.
 *
 * Copie este arquivo para config/smtp.php e preencha. O arquivo smtp.php NÃO vai para o
 * repositório (está no .gitignore), porque contém senha. Sem ele, o site usa o mail() do servidor.
 *
 * Gmail: a senha NÃO é a senha da conta. É uma "senha de app" de 16 letras, criada em
 * https://myaccount.google.com/apppasswords (exige verificação em duas etapas ativa).
 */
return [
    'servidor' => 'smtp.gmail.com',
    'porta' => 587,
    'seguranca' => 'starttls', // porta 587: starttls | porta 465: ssl
    'usuario' => 'seu.endereco@gmail.com',
    'senha' => 'xxxx xxxx xxxx xxxx',

    // Endereço que aparece como remetente. No Gmail deve ser o mesmo de 'usuario'
    // (ou um alias cadastrado nele); outro endereço é reescrito pelo Google.
    'remetente' => 'seu.endereco@gmail.com',
];
