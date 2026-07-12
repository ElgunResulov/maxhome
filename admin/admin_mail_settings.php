<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/admin_layout.php';

requireAdminAuth();
$pdo = db();

$message = '';
$messageType = 'success';

$config = maxhomeMailConfig();
$form = [
    'mail_transport' => $config['transport'] !== '' ? $config['transport'] : 'smtp',
    'smtp_host' => $config['smtp_host'],
    'smtp_port' => (string) $config['smtp_port'],
    'smtp_encryption' => $config['smtp_encryption'] !== '' ? $config['smtp_encryption'] : 'tls',
    'smtp_username' => $config['smtp_username'],
    'smtp_password' => $config['smtp_password'],
    'mail_from_email' => $config['from_email'],
    'mail_from_name' => $config['from_name'],
    'test_email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = trim((string) ($_POST['action'] ?? 'save'));

        if ($action === 'test') {
            $form['test_email'] = trim((string) ($_POST['test_email'] ?? ''));
            if ($form['test_email'] === '' || !filter_var($form['test_email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Test email duzgun deyil.');
            }
            if (!maxhomeMailIsConfigured()) {
                throw new RuntimeException('Evvelce SMTP ayarlarini saxlayin.');
            }

            $sent = maxhomeSendEmail(
                $form['test_email'],
                'MAXHOME test email',
                "Bu test mesajidir.\n\nSMTP ayarlari isleyir."
            );
            if (!$sent) {
                throw new RuntimeException('Test email gonderilmedi. SMTP melumatlarini yoxlayin.');
            }

            $message = 'Test email ugurla gonderildi.';
        } else {
            $form['mail_transport'] = trim((string) ($_POST['mail_transport'] ?? 'smtp'));
            $form['smtp_host'] = trim((string) ($_POST['smtp_host'] ?? ''));
            $form['smtp_port'] = trim((string) ($_POST['smtp_port'] ?? '587'));
            $form['smtp_encryption'] = trim((string) ($_POST['smtp_encryption'] ?? 'tls'));
            $form['smtp_username'] = trim((string) ($_POST['smtp_username'] ?? ''));
            $form['smtp_password'] = (string) ($_POST['smtp_password'] ?? '');
            $form['mail_from_email'] = trim((string) ($_POST['mail_from_email'] ?? ''));
            $form['mail_from_name'] = trim((string) ($_POST['mail_from_name'] ?? ''));

            if (!in_array($form['mail_transport'], ['smtp', 'mail', 'auto'], true)) {
                throw new RuntimeException('Transport novu sehvdir.');
            }
            if ($form['smtp_host'] === '' || $form['smtp_username'] === '' || $form['smtp_password'] === '') {
                throw new RuntimeException('SMTP host, istifadeci adi ve sifre mecburidir.');
            }
            if (!filter_var($form['mail_from_email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Gonderen email duzgun deyil.');
            }

            setSetting($pdo, 'mail_transport', $form['mail_transport']);
            setSetting($pdo, 'smtp_host', $form['smtp_host']);
            setSetting($pdo, 'smtp_port', $form['smtp_port']);
            setSetting($pdo, 'smtp_encryption', $form['smtp_encryption']);
            setSetting($pdo, 'smtp_username', $form['smtp_username']);
            setSetting($pdo, 'smtp_password', $form['smtp_password']);
            setSetting($pdo, 'mail_from_email', $form['mail_from_email']);
            setSetting($pdo, 'mail_from_name', $form['mail_from_name'] !== '' ? $form['mail_from_name'] : 'MAXHOME');

            $message = 'Email ayarlari ugurla saxlanildi.';
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Email ayarlari</title>
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php adminSidebar('mail_settings'); ?>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <h1 class="admin-title">Email ayarlari</h1>
                <p class="admin-subtitle">Sifre berpasi kodlari ve diger sistem emailleri ucun SMTP konfiqurasiyasi.</p>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="flash <?php echo $messageType === 'error' ? 'flash-error' : 'flash-success'; ?>">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <section class="card" style="max-width: 760px;">
            <h3 class="section-title">SMTP konfiqurasiyasi</h3>
            <p class="spec-help" style="margin-top:-6px;">
                Gmail ucun: host <code>smtp.gmail.com</code>, port <code>587</code>, encryption <code>tls</code>,
                ve Google hesabinda <strong>App Password</strong> istifade edin.
            </p>

            <form method="post" style="margin-top: 12px;">
                <input type="hidden" name="action" value="save">
                <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                    <div>
                        <label>Transport</label>
                        <select name="mail_transport">
                            <option value="smtp" <?php echo $form['mail_transport'] === 'smtp' ? 'selected' : ''; ?>>SMTP</option>
                            <option value="auto" <?php echo $form['mail_transport'] === 'auto' ? 'selected' : ''; ?>>Auto</option>
                            <option value="mail" <?php echo $form['mail_transport'] === 'mail' ? 'selected' : ''; ?>>PHP mail()</option>
                        </select>
                    </div>
                    <div>
                        <label>Encryption</label>
                        <select name="smtp_encryption">
                            <option value="tls" <?php echo $form['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                            <option value="ssl" <?php echo $form['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="none" <?php echo $form['smtp_encryption'] === 'none' ? 'selected' : ''; ?>>None</option>
                        </select>
                    </div>
                    <div>
                        <label>SMTP host</label>
                        <input name="smtp_host" value="<?php echo e($form['smtp_host']); ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div>
                        <label>SMTP port</label>
                        <input name="smtp_port" value="<?php echo e($form['smtp_port']); ?>" placeholder="587">
                    </div>
                    <div>
                        <label>SMTP istifadeci adi</label>
                        <input name="smtp_username" value="<?php echo e($form['smtp_username']); ?>" placeholder="email@gmail.com">
                    </div>
                    <div>
                        <label>SMTP sifre / App Password</label>
                        <input name="smtp_password" type="password" value="<?php echo e($form['smtp_password']); ?>" autocomplete="new-password">
                    </div>
                    <div>
                        <label>Gonderen email</label>
                        <input name="mail_from_email" value="<?php echo e($form['mail_from_email']); ?>" placeholder="noreply@maxhome.az">
                    </div>
                    <div>
                        <label>Gonderen ad</label>
                        <input name="mail_from_name" value="<?php echo e($form['mail_from_name']); ?>" placeholder="MAXHOME">
                    </div>
                </div>
                <div class="stack-sm" style="margin-top: 12px;">
                    <button class="btn btn-primary" type="submit">Yaddasda saxla</button>
                </div>
            </form>

            <div class="divider" style="margin: 14px 0;"></div>

            <form method="post">
                <input type="hidden" name="action" value="test">
                <div class="form-grid" style="grid-template-columns: 1fr;">
                    <div>
                        <label>Test email gonder</label>
                        <input name="test_email" value="<?php echo e($form['test_email']); ?>" placeholder="test@gmail.com">
                    </div>
                </div>
                <div class="stack-sm" style="margin-top: 12px;">
                    <button class="btn btn-muted" type="submit">Test mesaji gonder</button>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
