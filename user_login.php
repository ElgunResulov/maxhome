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

                <div id="login-flash" class="flash" hidden></div>
                <?php if ($error !== ''): ?>
                    <div class="flash flash-error"><?php echo e($error); ?></div>
                <?php endif; ?>

                <form id="login-form" method="post">
                    <input type="hidden" name="redirect" value="<?php echo e($redirect); ?>">
                    <div class="field">
                        <input class="control" id="login_email" type="email" name="email" value="<?php echo e($emailValue); ?>" placeholder="E-poct" required>
                    </div>
                    <div class="field password-wrap">
                        <input class="control" id="login_password" type="password" name="password" placeholder="Sifre" required>
                        <button type="button" class="pass-toggle" onclick="togglePassword('login_password', this)">Goster</button>
                    </div>
                    <div class="auth-row">
                        <a href="#" id="forgot-password-toggle">Sifreni unutdun?</a>
                    </div>
                    <button class="auth-btn" type="submit">Daxil ol</button>
                </form>

                <section id="forgot-password-panel" class="forgot-panel" hidden>
                    <div class="forgot-panel__head">
                        <button type="button" class="forgot-panel__back" id="forgot-password-close" aria-label="Giris sehifesine geri qayit">
                            <span class="forgot-panel__back-icon" aria-hidden="true">←</span>
                            <p>Geri</p>
                        </button>
                        <h2 class="forgot-panel__title">Sifre berpasi</h2>
                    </div>

                    <div class="forgot-step" data-step="1">
                        <p class="forgot-panel__text">E-poct unvaniniza dogrulama kodu gondereceyik.</p>
                        <div class="field">
                            <input class="control" id="forgot_email" type="email" placeholder="E-poct" required>
                        </div>
                        <button type="button" class="auth-btn" id="forgot-send-code">Kod gonder</button>
                    </div>

                    <div class="forgot-step" data-step="2" hidden>
                        <p class="forgot-panel__text">Emailinize gelen 6 reqemli kodu daxil edin. Kod 15 deqiqe kecerlidir.</p>
                        <div class="field">
                            <input class="control" id="forgot_code" type="text" inputmode="numeric" maxlength="6" placeholder="Dogrulama kodu" autocomplete="one-time-code">
                        </div>
                        <button type="button" class="auth-btn" id="forgot-verify-code">Kodu tesdiq et</button>
                        <button type="button" class="forgot-link-btn" id="forgot-resend-code">Kodu yeniden gonder</button>
                    </div>

                    <div class="forgot-step" data-step="3" hidden>
                        <p class="forgot-panel__text">Yeni sifrenizi yaradin.</p>
                        <div class="field password-wrap">
                            <input class="control" id="forgot_password" type="password" placeholder="Yeni sifre (min 6 simvol)">
                            <button type="button" class="pass-toggle" onclick="togglePassword('forgot_password', this)">Goster</button>
                        </div>
                        <div class="field password-wrap">
                            <input class="control" id="forgot_password_confirm" type="password" placeholder="Sifreni tesdiq et">
                            <button type="button" class="pass-toggle" onclick="togglePassword('forgot_password_confirm', this)">Goster</button>
                        </div>
                        <button type="button" class="auth-btn" id="forgot-reset-password">Sifreni yenile</button>
                    </div>
                </section>

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

        (function () {
            var loginForm = document.getElementById('login-form');
            var loginEmail = document.getElementById('login_email');
            var forgotToggle = document.getElementById('forgot-password-toggle');
            var forgotClose = document.getElementById('forgot-password-close');
            var forgotPanel = document.getElementById('forgot-password-panel');
            var forgotEmail = document.getElementById('forgot_email');
            var forgotCode = document.getElementById('forgot_code');
            var forgotPassword = document.getElementById('forgot_password');
            var forgotPasswordConfirm = document.getElementById('forgot_password_confirm');
            var flashBox = document.getElementById('login-flash');
            var currentStep = 1;

            function showFlash(message, type) {
                if (!flashBox) return;
                flashBox.hidden = false;
                flashBox.textContent = message;
                flashBox.className = 'flash ' + (type === 'success' ? 'flash-success' : 'flash-error');
            }

            function hideFlash() {
                if (!flashBox) return;
                flashBox.hidden = true;
                flashBox.textContent = '';
                flashBox.className = 'flash';
            }

            function setStep(step) {
                currentStep = step;
                document.querySelectorAll('.forgot-step').forEach(function (el) {
                    el.hidden = Number(el.getAttribute('data-step')) !== step;
                });
            }

            function openForgotPanel() {
                hideFlash();
                forgotPanel.hidden = false;
                loginForm.hidden = true;
                forgotEmail.value = loginEmail.value || '';
                setStep(1);
            }

            function closeForgotPanel() {
                forgotPanel.hidden = true;
                loginForm.hidden = false;
                hideFlash();
                setStep(1);
                forgotCode.value = '';
                forgotPassword.value = '';
                forgotPasswordConfirm.value = '';
            }

            function postReset(action, payload) {
                var body = new URLSearchParams();
                body.set('action', action);
                Object.keys(payload).forEach(function (key) {
                    body.set(key, payload[key]);
                });
                return fetch('ajax/password_reset.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    credentials: 'same-origin',
                    body: body.toString()
                }).then(function (response) {
                    return response.json();
                });
            }

            forgotToggle.addEventListener('click', function (event) {
                event.preventDefault();
                openForgotPanel();
            });

            forgotClose.addEventListener('click', function () {
                closeForgotPanel();
            });

            document.getElementById('forgot-send-code').addEventListener('click', function () {
                var email = forgotEmail.value.trim();
                if (!email) {
                    showFlash('Email daxil edin.', 'error');
                    return;
                }
                hideFlash();
                postReset('send_code', { email: email }).then(function (data) {
                    showFlash(data.message || 'Emeliyyat tamamlandi.', data.ok ? 'success' : 'error');
                    if (data.ok) {
                        setStep(2);
                    }
                }).catch(function () {
                    showFlash('Sorgu gonderilmedi. Yeniden cehd edin.', 'error');
                });
            });

            document.getElementById('forgot-verify-code').addEventListener('click', function () {
                var email = forgotEmail.value.trim();
                var code = forgotCode.value.trim();
                if (!email || code.length !== 6) {
                    showFlash('Email ve 6 reqemli kod daxil edin.', 'error');
                    return;
                }
                hideFlash();
                postReset('verify_code', { email: email, code: code }).then(function (data) {
                    showFlash(data.message || 'Emeliyyat tamamlandi.', data.ok ? 'success' : 'error');
                    if (data.ok) {
                        setStep(3);
                    }
                }).catch(function () {
                    showFlash('Sorgu gonderilmedi. Yeniden cehd edin.', 'error');
                });
            });

            document.getElementById('forgot-resend-code').addEventListener('click', function () {
                hideFlash();
                postReset('send_code', { email: forgotEmail.value.trim() }).then(function (data) {
                    showFlash(data.message || 'Emeliyyat tamamlandi.', data.ok ? 'success' : 'error');
                    if (data.ok) {
                        forgotCode.value = '';
                        setStep(2);
                    }
                }).catch(function () {
                    showFlash('Sorgu gonderilmedi. Yeniden cehd edin.', 'error');
                });
            });

            document.getElementById('forgot-reset-password').addEventListener('click', function () {
                var email = forgotEmail.value.trim();
                var password = forgotPassword.value;
                var passwordConfirm = forgotPasswordConfirm.value;
                if (!email || password.length < 6) {
                    showFlash('Sifre minimum 6 simvol olmalidir.', 'error');
                    return;
                }
                hideFlash();
                postReset('reset_password', {
                    email: email,
                    password: password,
                    password_confirm: passwordConfirm
                }).then(function (data) {
                    showFlash(data.message || 'Emeliyyat tamamlandi.', data.ok ? 'success' : 'error');
                    if (data.ok) {
                        closeForgotPanel();
                        loginEmail.value = email;
                    }
                }).catch(function () {
                    showFlash('Sorgu gonderilmedi. Yeniden cehd edin.', 'error');
                });
            });
        })();
    </script>
</body>
</html>
