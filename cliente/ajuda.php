<?php
/** Manual da área do cliente: como os arquivos são protegidos e como baixá-los. */
declare(strict_types=1);
require __DIR__ . '/../app/inicio.php';

$User = CurrentUser();
if ($User === null)
    Redirect('index.php');
$Admin = $User['papel'] === 'admin';

PageHead('Ajuda', 'ajuda.php');
?>
<article class="manual">
    <h1>Como funciona a proteção dos arquivos</h1>
    <p class="page-intro">
        Os arquivos que a Pignoro envia para você são cifrados antes de sair do computador dela e só são
        abertos no seu navegador. No caminho e no servidor eles ficam ilegíveis, inclusive para a própria
        equipe que administra o site.
    </p>

    <ol class="flow" aria-label="Caminho do arquivo">
        <li><strong>Pignoro</strong><span>cifra o arquivo no navegador, antes de enviar</span></li>
        <li><strong>Servidor</strong><span>guarda só o arquivo cifrado, sem ter como abri-lo</span></li>
        <li><strong>Você</strong><span>baixa e decifra no seu navegador, com a sua senha</span></li>
    </ol>

    <h2 id="baixar">Como baixar um arquivo</h2>
    <ol class="steps-list">
        <li>Entre na área do cliente com o seu e-mail e a sua senha.</li>
        <li>Abra <a href="arquivos.php">Arquivos</a> no menu. A lista mostra o nome, o tamanho e a data de cada arquivo.</li>
        <li>Se a página pedir, confirme a sua senha. Isso acontece quando você usa um navegador ou computador
            diferente, ou depois de ter clicado em Sair.</li>
        <li>Clique em <strong>Baixar</strong> ao lado do arquivo. Aparece "Decifrando..." por alguns instantes.</li>
        <li>O arquivo é salvo na pasta de downloads do seu computador, já decifrado e com o nome original.</li>
    </ol>
    <p class="hint">Não é preciso instalar nenhum programa. Tudo é feito pelo próprio navegador (Chrome, Edge, Firefox ou Safari atualizados).</p>
    <p class="notice notice-aviso">Depois de baixado, o arquivo no seu computador não está mais cifrado. Guarde-o em um lugar seguro.</p>

    <h2 id="processo">O que acontece em cada etapa</h2>
    <ol class="steps-list">
        <li><strong>Primeiro acesso.</strong> Quando você cria a senha, o seu navegador gera duas chaves que
            trabalham em par: uma <em>chave pública</em>, que serve só para trancar, e uma <em>chave privada</em>,
            que serve para destrancar. A chave pública vai para o servidor. A chave privada é cifrada com a sua
            senha antes de ser guardada.</li>
        <li><strong>A senha fica com você.</strong> A senha nunca é enviada ao servidor. O navegador a transforma
            em dois códigos: um comprova quem você é no login e o outro destranca a sua chave privada. Este
            segundo código não sai do seu navegador.</li>
        <li><strong>Envio pela Pignoro.</strong> Cada arquivo é cifrado com uma chave própria, criada só para ele.
            Essa chave do arquivo é então trancada com a sua chave pública.</li>
        <li><strong>No servidor.</strong> Ficam guardados o conteúdo, o nome e o tipo do arquivo, todos cifrados,
            além da chave do arquivo trancada. Sem a sua chave privada nada disso pode ser aberto.</li>
        <li><strong>Download.</strong> O navegador baixa o arquivo cifrado, usa a sua chave privada para destrancar
            a chave do arquivo, decifra o conteúdo e entrega o arquivo aberto. Se o arquivo tiver sido alterado
            no caminho, a decifragem falha e você é avisado.</li>
    </ol>

    <h2 id="situacoes">Situações comuns</h2>
    <dl class="faq-list">
        <dt>Apareceu "Arquivo ainda não liberado para esta conta".</dt>
        <dd>O arquivo foi enviado antes do seu primeiro acesso ou antes de uma redefinição de senha, e ainda
            não foi trancado com a sua chave atual. Avise a Pignoro em <a href="mensagens.php">Mensagens</a>
            para que ela libere o arquivo.</dd>

        <dt>Esqueci a minha senha.</dt>
        <dd>A senha não pode ser recuperada, nem pela Pignoro. Peça um novo link de acesso: ao criar a nova
            senha você recebe chaves novas, e os arquivos antigos ficam indisponíveis até a Pignoro liberá-los
            de novo.</dd>

        <dt>Quero trocar a senha.</dt>
        <dd>Use <a href="conta.php">Minha conta</a>. Na troca, a sua chave privada é cifrada de novo com a senha
            nova, e todos os arquivos continuam disponíveis. As outras sessões abertas com a senha antiga são encerradas.</dd>

        <dt>A página de arquivos pediu a minha senha outra vez.</dt>
        <dd>É o esperado. A chave privada aberta fica guardada apenas no navegador em que você entrou e é
            apagada quando você clica em Sair.</dd>

        <dt>Apareceu "O arquivo não pôde ser decifrado".</dt>
        <dd>O arquivo guardado não confere com o que foi enviado. Não use o arquivo e avise a Pignoro.</dd>

        <dt>Estou em um computador que não é meu.</dt>
        <dd>Ao terminar, clique em <strong>Sair</strong> e apague os arquivos baixados que não devam ficar ali.</dd>

        <dt>O que é a impressão digital da chave?</dt>
        <dd>É uma sequência curta que identifica a sua chave pública, mostrada em <a href="conta.php">Minha conta</a>.
            Se quiser, confira por telefone se a Pignoro vê a mesma sequência: isso comprova que os arquivos estão
            sendo trancados para a sua chave, e não para a de outra pessoa.</dd>
    </dl>

    <h2 id="limites">O que esta proteção cobre</h2>
    <ul class="bullets">
        <li><strong>Arquivos:</strong> cifrados de ponta a ponta, como descrito acima.</li>
        <li><strong>Mensagens e cadastros:</strong> trafegam por conexão segura (HTTPS) e exigem login, mas ficam
            legíveis para a equipe da Pignoro, que precisa deles para atender você.</li>
        <li><strong>Seu computador:</strong> a proteção termina no seu navegador. Mantenha o computador e o
            navegador atualizados e use uma senha que você não usa em outro lugar.</li>
    </ul>

    <?php if ($Admin): ?>
        <h2 id="administrador">Para administradores</h2>
        <ol class="steps-list">
            <li><strong>Enviar.</strong> Na ficha do cliente, aba Arquivos, escolha os arquivos e clique em
                "Cifrar e enviar". Cada arquivo é trancado para o cliente e para os administradores com acesso ativo.</li>
            <li><strong>Cliente sem primeiro acesso.</strong> O arquivo fica guardado, trancado só para os administradores.
                Depois que o cliente criar a senha, volte à aba Arquivos e clique em "Liberar para o cliente".</li>
            <li><strong>Redefinição de senha do cliente.</strong> Gere o link na aba Resumo. Quando o cliente criar a
                nova senha, libere os arquivos de novo na aba Arquivos. Não é preciso reenviá-los.</li>
            <li><strong>Sua própria senha.</strong> Se um administrador redefinir a senha, perde o acesso aos arquivos
                enviados antes. Os clientes continuam abrindo os deles.</li>
        </ol>
    <?php endif; ?>

    <h2 id="tecnico">Detalhes técnicos</h2>
    <ul class="bullets">
        <li>Derivação da senha: PBKDF2 com SHA-256 e <?= number_format(KDF_ITER, 0, ',', '.') ?> iterações.</li>
        <li>Par de chaves: RSA-OAEP de 3072 bits com SHA-256.</li>
        <li>Arquivos e chave privada: AES-256 em modo GCM, que também detecta alterações.</li>
        <li>Implementação: Web Crypto API, nativa do navegador, sem bibliotecas de terceiros.</li>
    </ul>
</article>
<?php
PageFoot();
