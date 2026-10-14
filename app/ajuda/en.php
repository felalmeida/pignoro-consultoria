<?php /* Client area manual in English. Included by cliente/ajuda.php, with $Admin defined. */ ?>
<article class="manual">
    <h1>How your files are protected</h1>
    <p class="page-intro">
        The files Pignoro sends you are encrypted before they leave Pignoro's computer and are only opened
        in your browser. In transit and on the server they are unreadable, even to the team that runs the site.
    </p>

    <ol class="flow" aria-label="Path of a file">
        <li><strong>Pignoro</strong><span>encrypts the file in the browser, before sending it</span></li>
        <li><strong>Server</strong><span>stores only the encrypted file, with no way to open it</span></li>
        <li><strong>You</strong><span>download and decrypt it in your browser, with your password</span></li>
    </ol>

    <h2 id="baixar">How to download a file</h2>
    <ol class="steps-list">
        <li>Sign in to the client area with your e-mail and password.</li>
        <li>Open <a href="arquivos.php">Files</a> in the menu. The list shows the name, size and date of each file.</li>
        <li>If the page asks, confirm your password. This happens when you use a different browser or computer,
            or after you have clicked Sign out.</li>
        <li>Click <strong>Download</strong> next to the file. "Decrypting..." appears for a moment.</li>
        <li>The file is saved to your computer's downloads folder, already decrypted and with its original name.</li>
    </ol>
    <p class="hint">No software needs to be installed. Everything is done by the browser itself (an up-to-date Chrome, Edge, Firefox or Safari).</p>
    <p class="notice notice-aviso">Once downloaded, the file on your computer is no longer encrypted. Keep it in a safe place.</p>

    <h2 id="processo">What happens at each step</h2>
    <ol class="steps-list">
        <li><strong>First access.</strong> When you create your password, your browser generates two keys that work
            as a pair: a <em>public key</em>, which can only lock, and a <em>private key</em>, which unlocks.
            The public key goes to the server. The private key is encrypted with your password before it is stored.</li>
        <li><strong>Your password stays with you.</strong> The password is never sent to the server. The browser turns
            it into two codes: one proves who you are when you sign in, and the other unlocks your private key.
            This second code never leaves your browser.</li>
        <li><strong>Sent by Pignoro.</strong> Each file is encrypted with its own key, created just for it.
            That file key is then locked with your public key.</li>
        <li><strong>On the server.</strong> The content, name and type of the file are stored, all encrypted,
            together with the locked file key. Without your private key none of it can be opened.</li>
        <li><strong>Download.</strong> The browser downloads the encrypted file, uses your private key to unlock the
            file key, decrypts the content and hands you the opened file. If the file has been altered along the way,
            decryption fails and you are warned.</li>
    </ol>

    <h2 id="situacoes">Common situations</h2>
    <dl class="faq-list">
        <dt>I see "File not yet released for this account".</dt>
        <dd>The file was sent before your first access or before a password reset, and has not yet been locked with
            your current key. Let Pignoro know so that the file can be released.</dd>

        <dt>I forgot my password.</dt>
        <dd>The password cannot be recovered, not even by Pignoro. Ask for a new access link: when you create the new
            password you get new keys, and older files stay unavailable until Pignoro releases them again.</dd>

        <dt>I want to change my password.</dt>
        <dd>Use <a href="conta.php">My account</a>. When you change it, your private key is encrypted again with the new
            password and all files remain available. Other sessions opened with the old password are signed out.</dd>

        <dt>The files page asked for my password again.</dt>
        <dd>That is expected. The unlocked private key is kept only in the browser you signed in with and is erased
            when you click Sign out.</dd>

        <dt>I see "The file could not be decrypted".</dt>
        <dd>The stored file does not match what was sent. Do not use the file and let Pignoro know.</dd>

        <dt>I am on a computer that is not mine.</dt>
        <dd>When you finish, click <strong>Sign out</strong> and delete any downloaded files that should not stay there.</dd>

        <dt>What is the key fingerprint?</dt>
        <dd>It is a short sequence that identifies your public key, shown in <a href="conta.php">My account</a>.
            If you wish, check by phone that Pignoro sees the same sequence: this confirms that files are being locked
            for your key and not for someone else's.</dd>
    </dl>

    <h2 id="limites">What this protection covers</h2>
    <ul class="bullets">
        <li><strong>Files:</strong> end-to-end encrypted, as described above.</li>
        <li><strong>Messages and records:</strong> travel over a secure connection (HTTPS) and require sign-in, but are
            readable by the Pignoro team, which needs them to serve you.</li>
        <li><strong>Your computer:</strong> protection ends in your browser. Keep your computer and browser up to date
            and use a password you do not use anywhere else.</li>
    </ul>

    <?php if ($Admin): ?>
        <h2 id="administrador">For administrators</h2>
        <ol class="steps-list">
            <li><strong>Sending.</strong> In the client's record, Files tab, choose the files and click "Encrypt and send".
                Each file is locked for the client and for the administrators with active access.</li>
            <li><strong>Client without first access.</strong> The file is stored, locked only for the administrators.
                Once the client creates a password, return to the Files tab and click "Release to the client".</li>
            <li><strong>Client password reset.</strong> Generate the link in the Summary tab. When the client creates the new
                password, release the files again in the Files tab. There is no need to send them again.</li>
            <li><strong>Your own password.</strong> If an administrator resets their password, they lose access to files
                sent before. Clients can still open theirs.</li>
        </ol>
    <?php endif; ?>

    <h2 id="tecnico">Technical details</h2>
    <ul class="bullets">
        <li>Password derivation: PBKDF2 with SHA-256 and <?= number_format(KDF_ITER, 0, '.', ',') ?> iterations.</li>
        <li>Key pair: 3072-bit RSA-OAEP with SHA-256.</li>
        <li>Files and private key: AES-256 in GCM mode, which also detects tampering.</li>
        <li>Implementation: the Web Crypto API built into the browser, with no third-party libraries.</li>
    </ul>
</article>
