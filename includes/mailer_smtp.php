<?php
/**
 * Minimal, dependency-free SMTP client (AUTH LOGIN, STARTTLS/SSL).
 * No Composer/PHPMailer required — works on plain shared hosting.
 * Used only when app_notification_settings.email_provider = 'smtp'.
 */
function smtp_send(array $cfg, string $toEmail, string $toName, string $subject, string $html): array {
    $host = $cfg['smtp_host'] ?? '';
    $port = (int)($cfg['smtp_port'] ?? 587);
    $user = $cfg['smtp_username'] ?? '';
    $pass = $cfg['smtp_password'] ?? '';
    $enc  = $cfg['smtp_encryption'] ?? 'tls';
    $fromEmail = $cfg['from_email'] ?? '';
    $fromName  = $cfg['from_name'] ?? 'NaukriScan';

    if ($host === '' || $fromEmail === '') return ['ok' => false, 'error' => 'SMTP is not configured.'];

    $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host;
    $sock = @stream_socket_client("$remote:$port", $errno, $errstr, 15);
    if (!$sock) return ['ok' => false, 'error' => "Connect failed: $errstr"];

    $read = function () use ($sock) {
        $data = '';
        while ($line = fgets($sock, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function (string $c) use ($sock, $read) {
        fwrite($sock, $c . "\r\n");
        return $read();
    };

    $read(); // greeting
    $cmd('EHLO naukriscan.in');

    if ($enc === 'tls') {
        $resp = $cmd('STARTTLS');
        if (strpos($resp, '220') !== 0) { fclose($sock); return ['ok' => false, 'error' => 'STARTTLS refused']; }
        if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($sock); return ['ok' => false, 'error' => 'TLS negotiation failed'];
        }
        $cmd('EHLO naukriscan.in');
    }

    if ($user !== '') {
        $cmd('AUTH LOGIN');
        $cmd(base64_encode($user));
        $resp = $cmd(base64_encode($pass));
        if (strpos($resp, '235') !== 0) { fclose($sock); return ['ok' => false, 'error' => 'SMTP auth failed']; }
    }

    $cmd("MAIL FROM:<$fromEmail>");
    $resp = $cmd("RCPT TO:<$toEmail>");
    if (strpos($resp, '250') !== 0) { fclose($sock); return ['ok' => false, 'error' => 'Recipient rejected']; }

    $cmd('DATA');
    $boundary = uniqid('nsc_');
    $headers = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$fromEmail>\r\n"
        . "To: $toEmail\r\n"
        . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Date: " . date('r') . "\r\n\r\n";
    $body = str_replace("\n.", "\n..", $html);
    $resp = $cmd($headers . $body . "\r\n.");
    $cmd('QUIT');
    fclose($sock);

    if (strpos($resp, '250') !== 0) return ['ok' => false, 'error' => 'Server rejected message'];
    return ['ok' => true];
}
