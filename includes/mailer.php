<?php
declare(strict_types=1);

/**
 * @return array{
 *   transport:string,
 *   smtp_host:string,
 *   smtp_port:int,
 *   smtp_encryption:string,
 *   smtp_username:string,
 *   smtp_password:string,
 *   from_email:string,
 *   from_name:string
 * }
 */
function maxhomeMailConfig(): array
{
    $config = [
        'transport' => 'smtp',
        'smtp_host' => 'mail.maxhome.az',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'smtp_username' => 'info@maxhome.az',
        'smtp_password' => 'Maxhome2026!',
        'from_email' => 'info@maxhome.az',
        'from_name' => 'MAXHOME',
    ];

    try {
        $pdo = db();
        $config['transport'] = (string) (getSetting($pdo, 'mail_transport', 'smtp') ?? 'smtp');
        $config['smtp_host'] = (string) (getSetting($pdo, 'smtp_host', '') ?? '');
        $config['smtp_port'] = max(1, (int) (getSetting($pdo, 'smtp_port', '587') ?? '587'));
        $config['smtp_encryption'] = (string) (getSetting($pdo, 'smtp_encryption', 'tls') ?? 'tls');
        $config['smtp_username'] = (string) (getSetting($pdo, 'smtp_username', '') ?? '');
        $config['smtp_password'] = (string) (getSetting($pdo, 'smtp_password', '') ?? '');
        $config['from_email'] = (string) (getSetting($pdo, 'mail_from_email', 'noreply@maxhome.az') ?? 'noreply@maxhome.az');
        $config['from_name'] = (string) (getSetting($pdo, 'mail_from_name', 'MAXHOME') ?? 'MAXHOME');
    } catch (Throwable $e) {
        // DB olmasa default ile davam et.
    }

    return [
        'transport' => strtolower(trim($config['transport'])),
        'smtp_host' => trim($config['smtp_host']),
        'smtp_port' => $config['smtp_port'],
        'smtp_encryption' => strtolower(trim($config['smtp_encryption'])),
        'smtp_username' => trim($config['smtp_username']),
        'smtp_password' => $config['smtp_password'],
        'from_email' => trim($config['from_email']),
        'from_name' => trim($config['from_name']),
    ];
}

function maxhomeMailLog(string $to, string $subject, string $body, string $note = ''): void
{
    $logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $suffix = $note !== '' ? ' NOTE=' . $note : '';
    $logLine = sprintf(
        "[%s] TO=%s SUBJECT=%s BODY=%s%s\n",
        date('Y-m-d H:i:s'),
        $to,
        $subject,
        str_replace(["\r", "\n"], [' ', ' '], $body),
        $suffix
    );
    @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'mail.log', $logLine, FILE_APPEND);
}

function maxhomeSmtpReadResponse($socket): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    return $response;
}

function maxhomeSmtpExpect($socket, array $expectedCodes): bool
{
    $response = maxhomeSmtpReadResponse($socket);
    $code = (int) substr($response, 0, 3);

    return in_array($code, $expectedCodes, true);
}

function maxhomeSmtpCommand($socket, string $command, array $expectedCodes): bool
{
    fwrite($socket, $command . "\r\n");

    return maxhomeSmtpExpect($socket, $expectedCodes);
}

/**
 * @param array<string, mixed> $config
 */
function maxhomeSendEmailViaSmtp(array $config, string $to, string $subject, string $body): bool
{
    $host = (string) $config['smtp_host'];
    $port = (int) $config['smtp_port'];
    $encryption = (string) $config['smtp_encryption'];
    $username = (string) $config['smtp_username'];
    $password = (string) $config['smtp_password'];
    $fromEmail = (string) $config['from_email'];
    $fromName = (string) $config['from_name'];

    if ($host === '' || $username === '' || $password === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $remote = $encryption === 'ssl'
        ? 'ssl://' . $host . ':' . $port
        : 'tcp://' . $host . ':' . $port;

    $socket = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        maxhomeMailLog($to, $subject, $body, 'SMTP_CONNECT_FAIL ' . $errstr);
        return false;
    }

    stream_set_timeout($socket, 20);

    if (!maxhomeSmtpExpect($socket, [220])) {
        fclose($socket);
        return false;
    }

    $ehloHost = 'localhost';
    if (!maxhomeSmtpCommand($socket, 'EHLO ' . $ehloHost, [250])) {
        fclose($socket);
        return false;
    }

    if ($encryption === 'tls') {
        if (!maxhomeSmtpCommand($socket, 'STARTTLS', [220])) {
            fclose($socket);
            return false;
        }
        $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
            fclose($socket);
            maxhomeMailLog($to, $subject, $body, 'SMTP_TLS_FAIL');
            return false;
        }
        if (!maxhomeSmtpCommand($socket, 'EHLO ' . $ehloHost, [250])) {
            fclose($socket);
            return false;
        }
    }

    if (!maxhomeSmtpCommand($socket, 'AUTH LOGIN', [334])) {
        fclose($socket);
        return false;
    }
    if (!maxhomeSmtpCommand($socket, base64_encode($username), [334])) {
        fclose($socket);
        return false;
    }
    if (!maxhomeSmtpCommand($socket, base64_encode($password), [235])) {
        fclose($socket);
        maxhomeMailLog($to, $subject, $body, 'SMTP_AUTH_FAIL');
        return false;
    }

    if (!maxhomeSmtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250])) {
        fclose($socket);
        return false;
    }
    if (!maxhomeSmtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251])) {
        fclose($socket);
        return false;
    }
    if (!maxhomeSmtpCommand($socket, 'DATA', [354])) {
        fclose($socket);
        return false;
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . sprintf('%s <%s>', $fromName, $fromEmail),
        'To: <' . $to . '>',
        'Subject: ' . $encodedSubject,
        'Date: ' . date('r'),
    ];

    $message = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n.", "\n..", str_replace("\r\n", "\n", $body));
    $message = str_replace("\n", "\r\n", $message);

    fwrite($socket, $message . "\r\n.\r\n");
    if (!maxhomeSmtpExpect($socket, [250])) {
        fclose($socket);
        maxhomeMailLog($to, $subject, $body, 'SMTP_DATA_FAIL');
        return false;
    }

    maxhomeSmtpCommand($socket, 'QUIT', [221]);
    fclose($socket);

    return true;
}

function maxhomeSendEmailViaPhpMail(array $config, string $to, string $subject, string $body): bool
{
    $fromEmail = (string) $config['from_email'];
    $fromName = (string) $config['from_name'];
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . sprintf('%s <%s>', $fromName, $fromEmail),
        'Reply-To: ' . $fromEmail,
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    return @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
}

function maxhomeSendEmail(string $to, string $subject, string $body): bool
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $config = maxhomeMailConfig();
    if (!filter_var($config['from_email'], FILTER_VALIDATE_EMAIL)) {
        $config['from_email'] = 'noreply@maxhome.az';
    }
    if ($config['from_name'] === '') {
        $config['from_name'] = 'MAXHOME';
    }

    $transport = $config['transport'];
    $smtpReady = $config['smtp_host'] !== ''
        && $config['smtp_username'] !== ''
        && $config['smtp_password'] !== '';

    if (($transport === 'smtp' || ($transport === 'auto' && $smtpReady)) && $smtpReady) {
        $sent = maxhomeSendEmailViaSmtp($config, $to, $subject, $body);
        if ($sent) {
            return true;
        }
    }

    if ($transport === 'mail' || $transport === 'auto') {
        if (maxhomeSendEmailViaPhpMail($config, $to, $subject, $body)) {
            return true;
        }
    }

    maxhomeMailLog($to, $subject, $body, 'DELIVERY_FAILED');
    return false;
}

function maxhomeMailIsConfigured(): bool
{
    $config = maxhomeMailConfig();
    if ($config['smtp_host'] !== '' && $config['smtp_username'] !== '' && $config['smtp_password'] !== '') {
        return true;
    }

    return $config['transport'] === 'mail';
}
