<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_auth.php';

if (isAdminLoggedIn()) {
    header('Location: admin_panel.php');
    exit;
}

header('Location: admin_login.php');
exit;
