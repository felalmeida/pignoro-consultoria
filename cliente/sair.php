<?php
declare(strict_types=1);
require __DIR__ . '/../app/inicio.php';

StartSession();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    CheckCsrf();
    $_SESSION = [];
    session_regenerate_id(true);
}
Redirect('index.php');
