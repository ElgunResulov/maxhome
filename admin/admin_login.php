<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_auth.php';

if (isAdminLoggedIn()) {
    header('Location: admin_panel.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_username'] = $username;
        header('Location: admin_panel.php');
        exit;
    }

    $error = 'Login melumatlari sehvdir.';
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Giris</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-card">
            <h2 class="auth-title">Admin panel girisi</h2>
            <p class="auth-subtitle">Idareetme paneline daxil olmaq ucun melumatlari yazin.</p>
            <?php if ($error !== ''): ?>
                <div class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="row">
                    <label for="username">Istifadeci adi</label>
                    <input id="username" name="username" type="text" required>
                </div>
                <div class="row">
                    <label for="password">Sifre</label>
                    <input id="password" name="password" type="password" required>
                </div>
                <button class="btn btn-primary btn-block" type="submit">Daxil ol</button>
            </form>
            <p class="hint">Default giris: admin / admin123</p>
        </div>
    </div>
</body>
</html>
