<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const ADMIN_USERNAME = 'maxhome';
const ADMIN_PASSWORD = 'maxhome2026!';

function isAdminLoggedIn(): bool
{
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function requireAdminAuth(): void
{
    if (!isAdminLoggedIn()) {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $adminBase = rtrim(dirname($scriptName), '/');
        if ($adminBase === '' || $adminBase === '.') {
            $adminBase = '/admin';
        }
        if (str_ends_with($adminBase, '/ajax')) {
            $adminBase = rtrim(dirname($adminBase), '/');
        }
        header('Location: ' . $adminBase . '/admin_login.php');
        exit;
    }
}
