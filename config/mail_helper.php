<?php

if (!function_exists('appBaseUrl')) {
    function appBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = function_exists('appBasePath') ? appBasePath() : '';
        return $scheme . '://' . $host . $basePath;
    }
}

if (!function_exists('smtpSendMail')) {
    function smtpSendMail(array $smtp, string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): array
    {
        $host = trim((string)($smtp['smtp_host'] ?? ''));
        $port = (int)($smtp['smtp_port'] ?? 587);
        $username = trim((string)($smtp['smtp_username'] ?? ''));
        $password = (string)($smtp['smtp_password'] ?? '');
        $fromEmail = trim((string)($smtp['smtp_from_email'] ?? $username));
        $fromName = trim((string)($smtp['smtp_from_name'] ?? 'BA Toolkit'));
        $encryption = strtolower(trim((string)($smtp['smtp_encryption'] ?? 'tls')));

        if ($host === '' || $fromEmail === '') {
            return ['success' => false, 'error' => 'SMTP host/from email is not configured.'];
        }

        $transport = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        $socket = @stream_socket_client($transport . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            return ['success' => false, 'error' => "SMTP connect failed: {$errstr} ({$errno})"];
        }

        stream_set_timeout($socket, 20);

        $read = static function ($socketRef): string {
            $response = '';
            while (!feof($socketRef)) {
                $line = fgets($socketRef, 515);
                if ($line === false) {
                    break;
                }
                $response .= $line;
                if (preg_match('/^\d{3}\s/', $line)) {
                    break;
                }
            }
            return $response;
        };

        $command = static function ($socketRef, string $cmd, array $expect = ['250']) use ($read): string {
            fwrite($socketRef, $cmd . "\r\n");
            $response = $read($socketRef);
            foreach ($expect as $code) {
                if (str_starts_with($response, $code)) {
                    return $response;
                }
            }
            throw new RuntimeException(trim($response) !== '' ? trim($response) : "SMTP command failed: {$cmd}");
        };

        try {
            $greeting = $read($socket);
            if (!str_starts_with($greeting, '220')) {
                throw new RuntimeException('SMTP greeting failed: ' . trim($greeting));
            }

            $hostname = gethostname() ?: 'localhost';
            $ehloResponse = $command($socket, 'EHLO ' . $hostname, ['250']);

            if ($encryption === 'tls') {
                $command($socket, 'STARTTLS', ['220']);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Unable to start TLS encryption.');
                }
                $ehloResponse = $command($socket, 'EHLO ' . $hostname, ['250']);
            }

            if ($username !== '') {
                if (stripos($ehloResponse, 'AUTH') === false) {
                    throw new RuntimeException('SMTP server does not advertise AUTH.');
                }
                $command($socket, 'AUTH LOGIN', ['334']);
                $command($socket, base64_encode($username), ['334']);
                $command($socket, base64_encode($password), ['235']);
            }

            $command($socket, 'MAIL FROM:<' . $fromEmail . '>', ['250']);
            $command($socket, 'RCPT TO:<' . $toEmail . '>', ['250', '251']);
            $command($socket, 'DATA', ['354']);

            $boundary = 'btk_' . bin2hex(random_bytes(8));
            $safeSubject = mb_encode_mimeheader($subject, 'UTF-8');
            $safeFromName = mb_encode_mimeheader($fromName, 'UTF-8');
            $text = $textBody !== '' ? $textBody : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));

            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . $safeFromName . ' <' . $fromEmail . '>',
                'To: ' . ($toName !== '' ? mb_encode_mimeheader($toName, 'UTF-8') . ' ' : '') . '<' . $toEmail . '>',
                'Subject: ' . $safeSubject,
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"'
            ];

            $message = implode("\r\n", $headers) . "\r\n\r\n"
                . '--' . $boundary . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . $text . "\r\n\r\n"
                . '--' . $boundary . "\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . $htmlBody . "\r\n\r\n"
                . '--' . $boundary . "--\r\n.";

            fwrite($socket, $message . "\r\n");
            $dataResponse = $read($socket);
            if (!str_starts_with($dataResponse, '250')) {
                throw new RuntimeException('SMTP DATA failed: ' . trim($dataResponse));
            }

            $command($socket, 'QUIT', ['221']);
            fclose($socket);
            return ['success' => true];
        } catch (Throwable $e) {
            fclose($socket);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('sendAppMail')) {
    function sendAppMail(PDO $pdo, string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): array
    {
        $settings = loadSettingsMap($pdo, null);
        return smtpSendMail($settings, $toEmail, $toName, $subject, $htmlBody, $textBody);
    }
}
