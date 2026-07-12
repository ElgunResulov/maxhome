<?php
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';

const PASSWORD_RESET_CODE_TTL_SECONDS = 900;
const PASSWORD_RESET_SESSION_TTL_SECONDS = 900;

function maxhomePasswordResetExpiresAt(): string
{
    return date('Y-m-d H:i:s', time() + PASSWORD_RESET_CODE_TTL_SECONDS);
}

function maxhomePasswordResetNow(): string
{
    return date('Y-m-d H:i:s');
}

function maxhomeEnsurePasswordResetTable(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_reset_codes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(190) NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_password_reset_email (email),
            INDEX idx_password_reset_expires (expires_at),
            CONSTRAINT fk_password_reset_user
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $ready = true;
}

function maxhomePasswordResetNormalizeEmail(string $email): string
{
    return strtolower(trim($email));
}

function maxhomePasswordResetGenerateCode(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * @return array{ok:bool,message:string}
 */
function maxhomePasswordResetSendCode(PDO $pdo, string $email): array
{
    maxhomeEnsurePasswordResetTable($pdo);

    $email = maxhomePasswordResetNormalizeEmail($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Duzgun email daxil edin.'];
    }

    $userStmt = $pdo->prepare("SELECT id, first_name, status FROM users WHERE email = :email LIMIT 1");
    $userStmt->execute(['email' => $email]);
    $user = $userStmt->fetch();
    if (!$user) {
        return [
            'ok' => true,
            'message' => 'Eger bu email qeydiyyatdadisa, dogrulama kodu gonderildi.',
        ];
    }

    if ((string) ($user['status'] ?? '') !== 'active') {
        return ['ok' => false, 'message' => 'Hesab aktiv deyil.'];
    }

    $limitStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM password_reset_codes
         WHERE email = :email
           AND created_at >= :since
           AND used_at IS NULL
           AND expires_at >= :now"
    );
    $limitStmt->execute([
        'email' => $email,
        'since' => date('Y-m-d H:i:s', time() - 3600),
        'now' => maxhomePasswordResetNow(),
    ]);
    if ((int) $limitStmt->fetchColumn() >= 5) {
        return ['ok' => false, 'message' => 'Cox sayda kod sorgusu. Bir az sonra yeniden cehd edin.'];
    }

    $code = maxhomePasswordResetGenerateCode();
    $userId = (int) $user['id'];

    $name = trim((string) ($user['first_name'] ?? ''));
    $greeting = $name !== '' ? $name : 'istifadeci';
    $subject = 'MAXHOME sifre berpasi kodu';
    $body = "Salam, {$greeting}.\n\n"
        . "Sifre berpasi ucun dogrulama kodunuz: {$code}\n"
        . "Kod 15 deqiqe erzinde kecerlidir.\n\n"
        . "Eger bu sorgunu siz gondermeyibsizse, bu mesaji nezere almayin.\n\n"
        . "MAXHOME";

    if (!maxhomeMailIsConfigured()) {
        return [
            'ok' => false,
            'message' => 'Email gonderilmedi. Admin panelde SMTP ayarlarini konfiqurasiya edin.',
        ];
    }

    if (!maxhomeSendEmail($email, $subject, $body)) {
        return [
            'ok' => false,
            'message' => 'Email gonderilmedi. SMTP melumatlarini yoxlayin ve ya bir az sonra yeniden cehd edin.',
        ];
    }

    $pdo->prepare(
        "UPDATE password_reset_codes
         SET used_at = NOW()
         WHERE email = :email AND used_at IS NULL"
    )->execute(['email' => $email]);

    $insertStmt = $pdo->prepare(
        "INSERT INTO password_reset_codes (user_id, email, code_hash, expires_at)
         VALUES (:user_id, :email, :code_hash, :expires_at)"
    );
    $insertStmt->execute([
        'user_id' => $userId,
        'email' => $email,
        'code_hash' => password_hash($code, PASSWORD_DEFAULT),
        'expires_at' => maxhomePasswordResetExpiresAt(),
    ]);

    return [
        'ok' => true,
        'message' => 'Dogrulama kodu email unvaniniza gonderildi.',
    ];
}

/**
 * @return array{ok:bool,message:string}
 */
function maxhomePasswordResetVerifyCode(PDO $pdo, string $email, string $code): array
{
    maxhomeEnsurePasswordResetTable($pdo);

    $email = maxhomePasswordResetNormalizeEmail($email);
    $code = preg_replace('/\D+/', '', trim($code)) ?? '';
    if ($email === '' || strlen($code) !== 6) {
        return ['ok' => false, 'message' => 'Email ve 6 reqemli kod teleb olunur.'];
    }

    $stmt = $pdo->prepare(
        "SELECT id, code_hash
         FROM password_reset_codes
         WHERE email = :email
           AND used_at IS NULL
           AND expires_at >= :now
         ORDER BY id DESC"
    );
    $stmt->execute([
        'email' => $email,
        'now' => maxhomePasswordResetNow(),
    ]);
    $rows = $stmt->fetchAll() ?: [];
    $matchedId = 0;
    foreach ($rows as $row) {
        if (password_verify($code, (string) $row['code_hash'])) {
            $matchedId = (int) $row['id'];
            break;
        }
    }

    if ($matchedId <= 0) {
        return ['ok' => false, 'message' => 'Kod sehvdir ve ya vaxti kecib. (Kod 15 deqiqe kecerlidir)'];
    }

    $pdo->prepare("UPDATE password_reset_codes SET used_at = NOW() WHERE id = :id")
        ->execute(['id' => $matchedId]);

    $_SESSION['password_reset_email'] = $email;
    $_SESSION['password_reset_verified_at'] = time();

    return ['ok' => true, 'message' => 'Kod tesdiqlendi. Yeni sifre yarada bilersiniz.'];
}

function maxhomePasswordResetSessionValid(): bool
{
    $email = trim((string) ($_SESSION['password_reset_email'] ?? ''));
    $verifiedAt = (int) ($_SESSION['password_reset_verified_at'] ?? 0);
    if ($email === '' || $verifiedAt <= 0) {
        return false;
    }

    return (time() - $verifiedAt) <= PASSWORD_RESET_SESSION_TTL_SECONDS;
}

function maxhomePasswordResetClearSession(): void
{
    unset($_SESSION['password_reset_email'], $_SESSION['password_reset_verified_at']);
}

/**
 * @return array{ok:bool,message:string}
 */
function maxhomePasswordResetUpdatePassword(PDO $pdo, string $email, string $password, string $passwordConfirm): array
{
    if (!maxhomePasswordResetSessionValid()) {
        return ['ok' => false, 'message' => 'Sifre berpasi sessiyasi bitib. Yeniden kod sorgulayin.'];
    }

    $email = maxhomePasswordResetNormalizeEmail($email);
    $sessionEmail = maxhomePasswordResetNormalizeEmail((string) ($_SESSION['password_reset_email'] ?? ''));
    if ($email === '' || $email !== $sessionEmail) {
        return ['ok' => false, 'message' => 'Email uygun deyil.'];
    }

    if (strlen($password) < 6) {
        return ['ok' => false, 'message' => 'Sifre minimum 6 simvol olmalidir.'];
    }
    if ($password !== $passwordConfirm) {
        return ['ok' => false, 'message' => 'Sifreler uygun galmir.'];
    }

    $userStmt = $pdo->prepare("SELECT id, status FROM users WHERE email = :email LIMIT 1");
    $userStmt->execute(['email' => $email]);
    $user = $userStmt->fetch();
    if (!$user || (string) ($user['status'] ?? '') !== 'active') {
        maxhomePasswordResetClearSession();
        return ['ok' => false, 'message' => 'Istifadeci tapilmadi.'];
    }

    $pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :id")
        ->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => (int) $user['id'],
        ]);

    $pdo->prepare("UPDATE password_reset_codes SET used_at = NOW() WHERE email = :email AND used_at IS NULL")
        ->execute(['email' => $email]);

    maxhomePasswordResetClearSession();

    return ['ok' => true, 'message' => 'Sifreniz ugurla yenilendi. Indi daxil ola bilersiniz.'];
}
