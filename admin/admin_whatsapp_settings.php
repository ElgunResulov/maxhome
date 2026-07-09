<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

requireAdminAuth();
$pdo = db();

$message = '';
$messageType = 'success';

$settingKey = 'orders_whatsapp_number';
$defaultNumber = '994559375801';
$currentNumber = (string) (getSetting($pdo, $settingKey, $defaultNumber) ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $raw = trim((string) ($_POST['whatsapp_number'] ?? ''));
        $digitsOnly = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digitsOnly !== '' && (strlen($digitsOnly) < 10 || strlen($digitsOnly) > 15)) {
            throw new RuntimeException('WhatsApp nomresi 10-15 reqem araliginda olmalidir (yalniz reqem).');
        }

        setSetting($pdo, $settingKey, $digitsOnly);
        $currentNumber = $digitsOnly;
        $message = 'WhatsApp nomresi ugurla yenilendi.';
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
    <title>Admin - WhatsApp ayarlari</title>
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php adminSidebar('whatsapp_settings'); ?>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <h1 class="admin-title">WhatsApp ayarlari</h1>
                <p class="admin-subtitle">Sifaris tamamlandiqda mesajin gondereceyi WhatsApp nomresini buradan deyisin.</p>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="flash <?php echo $messageType === 'error' ? 'flash-error' : 'flash-success'; ?>">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <section class="card" style="max-width: 720px;">
            <h3 class="section-title">Sifaris WhatsApp nomresi</h3>
            <p class="spec-help" style="margin-top:-6px;">
                Nomreni <strong>yalniz reqem</strong> kimi yazin (mes: <code>994501234567</code>).
                Bos saxlasaniz, <code>wa.me</code> chat-sechim ekranini acar.
            </p>

            <form method="post" style="margin-top: 12px;">
                <div class="form-grid" style="grid-template-columns: 1fr;">
                    <div>
                        <label>WhatsApp nomresi</label>
                        <input
                            name="whatsapp_number"
                            value="<?php echo e($currentNumber); ?>"
                            placeholder="994501234567"
                            inputmode="numeric"
                            autocomplete="off"
                        >
                    </div>
                </div>
                <div class="stack-sm" style="margin-top: 12px;">
                    <button class="btn btn-primary" type="submit">Yaddasda saxla</button>
                </div>
            </form>

            <div class="divider" style="margin: 14px 0;"></div>
            <div class="spec-help">
                Cari yonlendirme linki:
                <code><?php echo e('https://wa.me/' . preg_replace('/\D+/', '', $currentNumber)); ?></code>
            </div>
        </section>
    </main>
</div>
</body>
</html>

