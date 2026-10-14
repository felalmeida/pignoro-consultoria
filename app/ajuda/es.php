<?php /* Manual del área del cliente en español. Incluido por cliente/ajuda.php, con $Admin definido. */ ?>
<article class="manual">
    <h1>Cómo se protegen sus archivos</h1>
    <p class="page-intro">
        Los archivos que Pignoro le envía se cifran antes de salir de la computadora de Pignoro y solo se abren
        en su navegador. En el camino y en el servidor son ilegibles, incluso para el equipo que administra el sitio.
    </p>

    <ol class="flow" aria-label="Recorrido del archivo">
        <li><strong>Pignoro</strong><span>cifra el archivo en el navegador, antes de enviarlo</span></li>
        <li><strong>Servidor</strong><span>guarda solo el archivo cifrado, sin forma de abrirlo</span></li>
        <li><strong>Usted</strong><span>lo descarga y lo descifra en su navegador, con su contraseña</span></li>
    </ol>

    <h2 id="baixar">Cómo descargar un archivo</h2>
    <ol class="steps-list">
        <li>Ingrese al área del cliente con su correo electrónico y su contraseña.</li>
        <li>Abra <a href="arquivos.php">Archivos</a> en el menú. La lista muestra el nombre, el tamaño y la fecha de cada archivo.</li>
        <li>Si la página lo pide, confirme su contraseña. Esto ocurre cuando usa otro navegador u otra computadora,
            o después de hacer clic en Salir.</li>
        <li>Haga clic en <strong>Descargar</strong> junto al archivo. Aparece "Descifrando..." por unos instantes.</li>
        <li>El archivo se guarda en la carpeta de descargas de su computadora, ya descifrado y con su nombre original.</li>
    </ol>
    <p class="hint">No es necesario instalar ningún programa. Todo lo hace el propio navegador (Chrome, Edge, Firefox o Safari actualizados).</p>
    <p class="notice notice-aviso">Una vez descargado, el archivo en su computadora ya no está cifrado. Guárdelo en un lugar seguro.</p>

    <h2 id="processo">Qué ocurre en cada etapa</h2>
    <ol class="steps-list">
        <li><strong>Primer acceso.</strong> Cuando crea su contraseña, su navegador genera dos claves que funcionan
            en pareja: una <em>clave pública</em>, que solo sirve para cerrar, y una <em>clave privada</em>, que sirve
            para abrir. La clave pública va al servidor. La clave privada se cifra con su contraseña antes de guardarse.</li>
        <li><strong>La contraseña queda con usted.</strong> La contraseña nunca se envía al servidor. El navegador la
            transforma en dos códigos: uno demuestra quién es usted al ingresar y el otro abre su clave privada.
            Este segundo código no sale de su navegador.</li>
        <li><strong>Envío de Pignoro.</strong> Cada archivo se cifra con una clave propia, creada solo para él.
            Esa clave del archivo se cierra luego con su clave pública.</li>
        <li><strong>En el servidor.</strong> Se guardan el contenido, el nombre y el tipo del archivo, todos cifrados,
            junto con la clave del archivo cerrada. Sin su clave privada nada de esto se puede abrir.</li>
        <li><strong>Descarga.</strong> El navegador descarga el archivo cifrado, usa su clave privada para abrir la clave
            del archivo, descifra el contenido y le entrega el archivo abierto. Si el archivo fue alterado en el camino,
            el descifrado falla y usted recibe un aviso.</li>
    </ol>

    <h2 id="situacoes">Situaciones frecuentes</h2>
    <dl class="faq-list">
        <dt>Aparece "Archivo aún no liberado para esta cuenta".</dt>
        <dd>El archivo se envió antes de su primer acceso o antes de un restablecimiento de contraseña, y todavía no se
            cerró con su clave actual. Avise a Pignoro para que libere el archivo.</dd>

        <dt>Olvidé mi contraseña.</dt>
        <dd>La contraseña no se puede recuperar, ni siquiera por Pignoro. Pida un nuevo enlace de acceso: al crear la
            nueva contraseña usted recibe claves nuevas, y los archivos anteriores quedan no disponibles hasta que
            Pignoro los libere de nuevo.</dd>

        <dt>Quiero cambiar mi contraseña.</dt>
        <dd>Use <a href="conta.php">Mi cuenta</a>. Al cambiarla, su clave privada se cifra de nuevo con la contraseña
            nueva y todos los archivos siguen disponibles. Las demás sesiones abiertas con la contraseña anterior se cierran.</dd>

        <dt>La página de archivos volvió a pedir mi contraseña.</dt>
        <dd>Es lo esperado. La clave privada abierta queda guardada solo en el navegador con el que ingresó y se borra
            cuando hace clic en Salir.</dd>

        <dt>Aparece "No se pudo descifrar el archivo".</dt>
        <dd>El archivo guardado no coincide con el que se envió. No use el archivo y avise a Pignoro.</dd>

        <dt>Estoy en una computadora que no es mía.</dt>
        <dd>Al terminar, haga clic en <strong>Salir</strong> y borre los archivos descargados que no deban quedar allí.</dd>

        <dt>¿Qué es la huella digital de la clave?</dt>
        <dd>Es una secuencia corta que identifica su clave pública, mostrada en <a href="conta.php">Mi cuenta</a>.
            Si lo desea, confirme por teléfono que Pignoro ve la misma secuencia: así comprueba que los archivos se
            cierran para su clave y no para la de otra persona.</dd>
    </dl>

    <h2 id="limites">Qué cubre esta protección</h2>
    <ul class="bullets">
        <li><strong>Archivos:</strong> cifrados de extremo a extremo, como se describe arriba.</li>
        <li><strong>Mensajes y registros:</strong> viajan por una conexión segura (HTTPS) y exigen ingreso, pero el equipo
            de Pignoro puede leerlos, porque los necesita para atenderle.</li>
        <li><strong>Su computadora:</strong> la protección termina en su navegador. Mantenga la computadora y el navegador
            actualizados y use una contraseña que no use en ningún otro lugar.</li>
    </ul>

    <?php if ($Admin): ?>
        <h2 id="administrador">Para administradores</h2>
        <ol class="steps-list">
            <li><strong>Enviar.</strong> En la ficha del cliente, pestaña Archivos, elija los archivos y haga clic en
                "Cifrar y enviar". Cada archivo se cierra para el cliente y para los administradores con acceso activo.</li>
            <li><strong>Cliente sin primer acceso.</strong> El archivo queda guardado, cerrado solo para los administradores.
                Cuando el cliente cree la contraseña, vuelva a la pestaña Archivos y haga clic en "Liberar al cliente".</li>
            <li><strong>Restablecimiento de contraseña del cliente.</strong> Genere el enlace en la pestaña Resumen. Cuando el
                cliente cree la nueva contraseña, libere los archivos otra vez en la pestaña Archivos. No hace falta reenviarlos.</li>
            <li><strong>Su propia contraseña.</strong> Si un administrador restablece su contraseña, pierde el acceso a los
                archivos enviados antes. Los clientes siguen abriendo los suyos.</li>
        </ol>
    <?php endif; ?>

    <h2 id="tecnico">Detalles técnicos</h2>
    <ul class="bullets">
        <li>Derivación de la contraseña: PBKDF2 con SHA-256 y <?= number_format(KDF_ITER, 0, ',', '.') ?> iteraciones.</li>
        <li>Par de claves: RSA-OAEP de 3072 bits con SHA-256.</li>
        <li>Archivos y clave privada: AES-256 en modo GCM, que también detecta alteraciones.</li>
        <li>Implementación: Web Crypto API, nativa del navegador, sin bibliotecas de terceros.</li>
    </ul>
</article>
