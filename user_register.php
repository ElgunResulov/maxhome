<?php
declare(strict_types=1);
require_once __DIR__ . '/user_auth.php';

if (isUserLoggedIn()) {
    header('Location: shop_page.php');
    exit;
}

$pdo = db();
$error = '';
$success = '';

$form = [
    'username' => '',
    'email' => '',
    'phone' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['username'] = trim((string) ($_POST['username'] ?? ''));
    $form['email'] = trim((string) ($_POST['email'] ?? ''));
    $form['phone'] = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($form['username'] === '' || $form['email'] === '' || $form['phone'] === '' || $password === '') {
        $error = 'Lutfen butun mecburi alanlari doldurun.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Email formati duzgun deyil.';
    } elseif (strlen($password) < 6) {
        $error = 'Sifre minimum 6 simvol olmalidir.';
    } else {
        try {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $check->execute(['email' => $form['email']]);
            if ($check->fetchColumn()) {
                throw new RuntimeException('Bu email artiq qeydiyyatdan kecib.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO users (first_name, last_name, email, password_hash, status)
                 VALUES (:first_name, :last_name, :email, :password_hash, 'active')"
            );
            $stmt->execute([
                'first_name' => $form['username'],
                'last_name' => '',
                'email' => strtolower($form['email']),
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            $userId = (int) $pdo->lastInsertId();
            if ($userId > 0) {
                $pdo->prepare("UPDATE users SET phone = :phone WHERE id = :id")
                    ->execute(['phone' => $form['phone'], 'id' => $userId]);
            }

            $success = 'Qeydiyyat tamamlandi. Indi daxil ola bilersiniz.';
            $form = ['username' => '', 'email' => '', 'phone' => ''];
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qeydiyyat | MAXHOME</title>
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
                <h1 class="auth-title">Qeydiyyat</h1>

                <?php if ($error !== ''): ?><div class="flash flash-error"><?php echo e($error); ?></div><?php endif; ?>
                <?php if ($success !== ''): ?><div class="flash flash-success"><?php echo e($success); ?></div><?php endif; ?>

                <form method="post">
                    <div class="field">
                        <input class="control" name="username" value="<?php echo e($form['username']); ?>" placeholder="Istifadeci adi" required>
                    </div>
                    <div class="field">
                        <input class="control" type="email" name="email" value="<?php echo e($form['email']); ?>" placeholder="E-poct" required>
                    </div>
                    <div class="field">
                        <input class="control" name="phone" value="<?php echo e($form['phone']); ?>" placeholder="Telefon nomresi (+994 XX XXX XX XX)" required>
                    </div>
                    <div class="field password-wrap">
                        <input class="control" id="register_password" type="password" name="password" placeholder="Sifre (min 6 simvol)" required>
                        <button type="button" class="pass-toggle" onclick="togglePassword('register_password', this)">Goster</button>
                    </div>
                    <button class="auth-btn" type="submit">Qeydiyyat</button>
                </form>

                <div class="auth-links">
                    Artiq hesabiniz var? <a href="user_login.php">Daxil ol</a>
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
