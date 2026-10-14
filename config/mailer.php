<?php

function gmailSmtpConfig(): array
{
    $username = getenv('GMAIL_SMTP_USERNAME') ?: '';
    $password = getenv('GMAIL_SMTP_APP_PASSWORD') ?: '';
    $fromAddress = getenv('GMAIL_SMTP_FROM_ADDRESS') ?: $username;
    $fromName = getenv('GMAIL_SMTP_FROM_NAME') ?: 'NextStop BGC';

    if ($username === '' || $password === '' || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Gmail OTP email is not configured yet. Please contact the administrator.');
    }

    return [
        'username' => $username,
        'password' => preg_replace('/\s+/', '', $password),
        'from_address' => $fromAddress,
        'from_name' => preg_replace('/[\r\n]/', '', $fromName),
    ];
}

function gmailSmtpResponse($socket): array
{
    $response = '';
    do {
        $line = fgets($socket, 515);
        if ($line === false) {
            $metadata = stream_get_meta_data($socket);
            throw new RuntimeException(!empty($metadata['timed_out']) ? 'Gmail SMTP connection timed out.' : 'Gmail SMTP response could not be read.');
        }
        $response .= $line;
    } while (isset($line[3]) && $line[3] === '-');

    return [(int) substr($response, 0, 3), trim($response)];
}

function gmailSmtpCommand($socket, string $command, array $expectedCodes): string
{
    if (fwrite($socket, $command . "\r\n") === false) {
        throw new RuntimeException('Could not write to Gmail SMTP connection.');
    }

    [$code, $response] = gmailSmtpResponse($socket);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('Gmail SMTP rejected a command: ' . $response);
    }

    return $response;
}

function sendGmailOtp(array $config, string $recipient, string $code): void
{
    $socket = stream_socket_client('tcp://smtp.gmail.com:587', $errorCode, $errorMessage, 15, STREAM_CLIENT_CONNECT);
    if ($socket === false) {
        throw new RuntimeException('Could not connect to Gmail SMTP: ' . $errorMessage, $errorCode);
    }

    stream_set_timeout($socket, 15);

    try {
        [$greetingCode] = gmailSmtpResponse($socket);
        if ($greetingCode !== 220) {
            throw new RuntimeException('Gmail SMTP did not accept the connection.');
        }

        gmailSmtpCommand($socket, 'EHLO nextstop.local', [250]);
        gmailSmtpCommand($socket, 'STARTTLS', [220]);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Could not establish secure Gmail SMTP encryption.');
        }

        gmailSmtpCommand($socket, 'EHLO nextstop.local', [250]);
        gmailSmtpCommand($socket, 'AUTH LOGIN', [334]);
        gmailSmtpCommand($socket, base64_encode($config['username']), [334]);
        gmailSmtpCommand($socket, base64_encode($config['password']), [235]);
        gmailSmtpCommand($socket, 'MAIL FROM:<' . $config['from_address'] . '>', [250]);
        gmailSmtpCommand($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
        gmailSmtpCommand($socket, 'DATA', [354]);

        $encodedFromName = '=?UTF-8?B?' . base64_encode($config['from_name']) . '?=';
        $message = implode("\r\n", [
            'From: ' . $encodedFromName . ' <' . $config['from_address'] . '>',
            'To: <' . $recipient . '>',
            'Subject: ' . '=?UTF-8?B?' . base64_encode('Your NextStop password reset code') . '?=',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            "Your NextStop verification code is: {$code}",
            '',
            'This code expires in 10 minutes. If you did not request a password reset, you can ignore this email.',
        ]);
        $message = preg_replace('/(?m)^\./', '..', $message);
        if (fwrite($socket, $message . "\r\n.\r\n") === false) {
            throw new RuntimeException('Could not send the Gmail SMTP message.');
        }
        [$deliveryCode, $deliveryResponse] = gmailSmtpResponse($socket);
        if ($deliveryCode !== 250) {
            throw new RuntimeException('Gmail SMTP did not accept the message: ' . $deliveryResponse);
        }

        gmailSmtpCommand($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}
