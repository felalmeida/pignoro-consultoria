<?php
/** Manual da área do cliente: como os arquivos são protegidos e como baixá-los. */
declare(strict_types=1);
require __DIR__ . '/../app/inicio.php';

$User = CurrentUser();
if ($User === null)
    Redirect('index.php');
$Admin = $User['papel'] === 'admin';

PageHead(T('Ajuda'), 'ajuda.php');
?>
<?php require __DIR__ . '/../app/ajuda/' . Lang() . '.php'; ?>
<?php
PageFoot();
