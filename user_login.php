<?php
declare(strict_types=1);
require_once __DIR__ . '/user_auth.php';

$redirect = trim((string) ($_GET['redirect'] ?? $_POST['redirect'] ?? 'shop_page.php'));
if ($redirect === '' || str_contains($redirect, '://')) {
    $redirect = 'shop_page.php';
}

if (isUserLoggedIn()) {
    header('Location: ' . $redirect);
    exit;
}

$pdo = db();
$error = '';
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $emailValue = $email;
    $password = (string) ($_POST['password'] ?? '');

    $roleSelect = maxhomeUsersRoleColumnExists($pdo) ? ', role' : '';
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, password_hash, status{$roleSelect} FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => strtolower($email)]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        $error = 'Email ve ya sifre sehvdir.';
    } elseif ((string) $user['status'] !== 'active') {
        $error = 'Hesab aktiv deyil.';
    } else {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_email'] = (string) $user['email'];
        $_SESSION['user_name'] = trim((string) $user['first_name'] . ' ' . $user['last_name']);
        $_SESSION['user_role'] = trim((string) ($user['role'] ?? ''));
        header('Location: ' . $redirect);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daxil ol | MAXHOME</title>
    <link rel="stylesheet" href="assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/user_auth.css">
</head>
<body>
    <main class="auth-page">
        <section class="auth-shell">
            <div class="auth-brand">
                <div>
                    <h2 class="brand-title">MAXHOME</h2>
                    <div class="brand-sub">Digital Store Platform</div>
                </div>
            </div>
            <div class="auth-form-pane">
                <h1 class="auth-title">Daxil ol</h1>

                <?php if ($error !== ''): ?>
                    <div class="flash flash-error"><?php echo e($error); ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="redirect" value="<?php echo e($redirect); ?>">
                    <div class="field">
                        <input class="control" type="email" name="email" value="<?php echo e($emailValue); ?>" placeholder="E-poct" required>
                    </div>
                    <div class="field password-wrap">
                        <input class="control" id="login_password" type="password" name="password" placeholder="Sifre" required>
                        <button type="button" class="pass-toggle" onclick="togglePassword('login_password', this)">Goster</button>
                    </div>
                    <div class="auth-row">
                        <a href="#" onclick="return false;">Sifreni unutdun?</a>
                    </div>
                    <button class="auth-btn" type="submit">Daxil ol</button>
                </form>

                <div class="auth-links">
                    Hesabiniz yoxdur? <a href="user_register.php">Qeydiyyat</a><br>
                    <a href="index.php" style="display:inline-block; margin-top:8px;">Ana sehife</a>
                </div>
            </div>
        </section>
    </main>
    <script>
        function togglePassword(inputId, btn) {
            var input = document.getElementById(inputId);
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = 'Gizlet';
            } else {
                input.type = 'password';
                btn.textContent = 'Goster';
            }
        }
    </script>
</body>
</html>
