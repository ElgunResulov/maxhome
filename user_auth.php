<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isUserLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
}

function currentUserId(): ?int
{
    return isUserLoggedIn() ? (int) $_SESSION['user_id'] : null;
}

function currentUserName(): string
{
    return trim((string) ($_SESSION['user_name'] ?? ''));
}

function maxhomeUsersRoleColumnExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    $stmt = $pdo->query(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'users'
           AND COLUMN_NAME = 'role'
         LIMIT 1"
    );
    $exists = (bool) $stmt->fetchColumn();

    return $exists;
}

function currentUserRole(): string
{
    if (!isUserLoggedIn()) {
        return '';
    }

    $sessionRole = trim((string) ($_SESSION['user_role'] ?? ''));
    if ($sessionRole !== '') {
        return mb_strtolower($sessionRole, 'UTF-8');
    }

    try {
        $pdo = db();
        if (!maxhomeUsersRoleColumnExists($pdo)) {
            return '';
        }
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => currentUserId()]);
        $role = trim((string) $stmt->fetchColumn());
        if ($role !== '') {
            $_SESSION['user_role'] = $role;
        }
        return mb_strtolower($role, 'UTF-8');
    } catch (Throwable $e) {
        return '';
    }
}

function userHasAnyRole(array $allowedRoles): bool
{
    if (!isUserLoggedIn()) {
        return false;
    }

    $role = currentUserRole();
    if ($role === '') {
        return false;
    }

    $normalized = array_map(
        static fn(string $r): string => mb_strtolower(trim($r), 'UTF-8'),
        $allowedRoles
    );

    return in_array($role, $normalized, true);
}

function requireUserAuth(string $redirectTo = 'checkout.php'): void
{
    if (!isUserLoggedIn()) {
        header('Location: user_login.php?redirect=' . urlencode($redirectTo));
        exit;
    }
}
