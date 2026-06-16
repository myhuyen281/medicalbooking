<?php

require_once __DIR__ . '/../config/mail.php';

function normalizeVietnamPhone($phone) {
    $phone = preg_replace('/\D+/', '', $phone);

    if (strpos($phone, '84') === 0 && strlen($phone) === 11) {
        $phone = '0' . substr($phone, 2);
    }

    return $phone;
}

function formatVietnamPhoneE164($phone) {
    $phone = normalizeVietnamPhone($phone);
    return '+84' . substr($phone, 1);
}

function isValidVietnamMobile($phone) {
    return preg_match('/^0[0-9]{9}$/', $phone) === 1;
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function generateOtpCode() {
    return (string) random_int(100000, 999999);
}

function storeRegistrationOtp($email, $otp) {
    $_SESSION['registration_otp'] = [
        'email' => $email,
        'code_hash' => password_hash($otp, PASSWORD_DEFAULT),
        'expires_at' => time() + 300,
        'attempts' => 0,
        'verified' => false
    ];
}

function getRegistrationOtp() {
    return $_SESSION['registration_otp'] ?? null;
}

function clearRegistrationOtp() {
    unset($_SESSION['registration_otp']);
}

function verifyRegistrationOtp($email, $otp) {
    $otpData = getRegistrationOtp();

    if (!$otpData || ($otpData['email'] ?? '') !== $email) {
        return 'Không tìm thấy mã OTP cho email này.';
    }

    if (($otpData['expires_at'] ?? 0) < time()) {
        clearRegistrationOtp();
        return 'Mã OTP đã hết hạn. Vui lòng gửi lại mã mới.';
    }

    if (($otpData['attempts'] ?? 0) >= 5) {
        clearRegistrationOtp();
        return 'Bạn đã nhập sai OTP quá nhiều lần. Vui lòng gửi lại mã mới.';
    }

    $_SESSION['registration_otp']['attempts'] = ($otpData['attempts'] ?? 0) + 1;

    if (!password_verify($otp, $otpData['code_hash'] ?? '')) {
        return 'Mã OTP không chính xác.';
    }

    $_SESSION['registration_otp']['verified'] = true;
    $_SESSION['registration_verified_email'] = $email;

    return true;
}

function smtpRead($connection) {
    $response = '';

    while ($line = fgets($connection, 515)) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    return $response;
}

function smtpCommand($connection, $command, $expectedCodes) {
    fwrite($connection, $command . "\r\n");
    $response = smtpRead($connection);
    $code = (int) substr($response, 0, 3);

    if (!in_array($code, (array) $expectedCodes, true)) {
        throw new Exception(trim($response));
    }

    return $response;
}

function sendSmtpMail($toEmail, $subject, $body) {
    if (SMTP_USERNAME === 'your-email@gmail.com' || SMTP_PASSWORD === 'your-app-password') {
        throw new Exception('Chưa cấu hình SMTP trong config/mail.php.');
    }

    $connection = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 20);

    if (!$connection) {
        throw new Exception("Không thể kết nối SMTP: $errstr");
    }

    stream_set_timeout($connection, 20);
    smtpRead($connection);
    smtpCommand($connection, 'EHLO localhost', 250);

    if (SMTP_ENCRYPTION === 'tls') {
        smtpCommand($connection, 'STARTTLS', 220);
        if (!stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception('Không thể bật mã hóa TLS cho SMTP.');
        }
        smtpCommand($connection, 'EHLO localhost', 250);
    }

    smtpCommand($connection, 'AUTH LOGIN', 334);
    smtpCommand($connection, base64_encode(SMTP_USERNAME), 334);
    smtpCommand($connection, base64_encode(SMTP_PASSWORD), 235);
    smtpCommand($connection, 'MAIL FROM:<' . SMTP_FROM_EMAIL . '>', 250);
    smtpCommand($connection, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
    smtpCommand($connection, 'DATA', 354);

    $encodedFromName = '=?UTF-8?B?' . base64_encode(SMTP_FROM_NAME) . '?=';
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $fromDomain = substr(strrchr(SMTP_FROM_EMAIL, '@'), 1) ?: 'localhost';
    $headers = [];
    $headers[] = 'From: ' . $encodedFromName . ' <' . SMTP_FROM_EMAIL . '>';
    $headers[] = 'To: <' . $toEmail . '>';
    $headers[] = 'Subject: ' . $encodedSubject;
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $fromDomain . '>';
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';

    $safeBody = str_replace(["\r\n.", "\n."], ["\r\n..", "\n.."], $body);
    fwrite($connection, implode("\r\n", $headers) . "\r\n\r\n" . $safeBody . "\r\n.\r\n");
    $response = smtpRead($connection);
    $code = (int) substr($response, 0, 3);

    if ($code !== 250) {
        throw new Exception(trim($response));
    }

    smtpCommand($connection, 'QUIT', 221);
    fclose($connection);

    return true;
}

function sendVonageSms($toPhone, $message) {
    if (VONAGE_API_KEY === 'your-vonage-api-key' || VONAGE_API_SECRET === 'your-vonage-api-secret') {
        throw new Exception('Chưa cấu hình Vonage trong config/mail.php.');
    }

    $payload = json_encode([
        'messages' => [[
            'channel' => 'sms',
            'message_type' => 'text',
            'to' => ltrim(formatVietnamPhoneE164($toPhone), '+'),
            'from' => VONAGE_FROM,
            'text' => $message
        ]]
    ]);

    $ch = curl_init('https://messages-sandbox.nexmo.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => VONAGE_API_KEY . ':' . VONAGE_API_SECRET,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception('Không thể kết nối Vonage: ' . $error);
    }

    $data = json_decode($response, true);
    if ($statusCode < 200 || $statusCode >= 300) {
        throw new Exception($data['title'] ?? $data['detail'] ?? $data['message'] ?? 'Vonage không gửi được SMS.');
    }

    return true;
}

function sendRegistrationOtp($email, $otp) {
    $subject = 'Mã OTP đăng ký MedicailBooking';
    $message = "Mã OTP MedicailBooking của bạn là: $otp. Mã hết hạn sau 5 phút. Không chia sẻ mã này.";

    try {
        unset($_SESSION['registration_otp_dev_code']);
        sendSmtpMail($email, $subject, $message);
        return true;
    } catch (Exception $e) {
        if (SMTP_USERNAME === 'your-email@gmail.com' || SMTP_PASSWORD === 'your-app-password') {
            $_SESSION['registration_otp_dev_code'] = $otp;
            return true;
        }

        $_SESSION['registration_otp_error'] = $e->getMessage();
        return false;
    }
}
?>
