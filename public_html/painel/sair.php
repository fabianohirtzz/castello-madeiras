<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';

auth_sair();

header('Location: index.php');
exit;
