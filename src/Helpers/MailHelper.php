<?php

declare(strict_types=1);

namespace App\Helpers;

class MailHelper
{
    public static function sendPasswordResetEmail(string $toEmail, string $toName, string $otp, int $expiryMinutes): array
    {
        $appName = trim((string) (getenv('APP_NAME') ?: 'WhiteCoat'));
        $subject = $appName . ' - Reset your password';

        $safeName = htmlspecialchars($toName !== '' ? $toName : $toEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeOtp = htmlspecialchars($otp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = '<div style="font-family:Arial,sans-serif;line-height:1.5;color:#111">'
            . '<h2 style="margin:0 0 12px">Reset your password</h2>'
            . '<p>Hello ' . $safeName . ',</p>'
            . '<p>We received a request to reset your password.</p>'
            . '<p>Enter this 6-digit OTP on the reset page:</p>'
            . '<p style="font-size:28px;font-weight:700;letter-spacing:4px;margin:10px 0 16px">' . $safeOtp . '</p>'
            . '<p>This OTP expires in ' . (int) $expiryMinutes . ' minutes.</p>'
            . '</div>';

        $text = "Reset your password\n\nOTP: {$otp}\n\nThis OTP expires in {$expiryMinutes} minutes.";

        return self::send($toEmail, $toName, $subject, $html, $text);
    }

    public static function sendVerificationOtpEmail(string $toEmail, string $toName, string $otp, int $expiryMinutes): array
    {
        $appName = trim((string) (getenv('APP_NAME') ?: 'WhiteCoat'));
        $subject = $appName . ' - Your verification code';

        $safeName = htmlspecialchars($toName !== '' ? $toName : $toEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeOtp = htmlspecialchars($otp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = '<div style="font-family:Arial,sans-serif;line-height:1.5;color:#111">'
            . '<h2 style="margin:0 0 12px">Email verification code</h2>'
            . '<p>Hello ' . $safeName . ',</p>'
            . '<p>Use this one-time PIN to verify your account:</p>'
            . '<p style="font-size:28px;font-weight:700;letter-spacing:4px;margin:16px 0">' . $safeOtp . '</p>'
            . '<p>This PIN expires in ' . (int) $expiryMinutes . ' minutes.</p>'
            . '</div>';

        $text = "Your verification code is: {$otp}\nThis code expires in {$expiryMinutes} minutes.";

        return self::send($toEmail, $toName, $subject, $html, $text);
    }

    public static function sendVerificationEmail(string $toEmail, string $toName, string $verifyUrl): array
    {
        $appName = trim((string) (getenv('APP_NAME') ?: 'WhiteCoat'));
        $subject = $appName . ' - Verify your email';

        $safeName = htmlspecialchars($toName !== '' ? $toName : $toEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeUrl = htmlspecialchars($verifyUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = '<div style="font-family:Arial,sans-serif;line-height:1.5;color:#111">'
            . '<h2 style="margin:0 0 12px">Verify your email</h2>'
            . '<p>Hello ' . $safeName . ',</p>'
            . '<p>Thank you for registering. Click the button below to verify your email address.</p>'
            . '<p style="margin:20px 0"><a href="' . $safeUrl . '" style="background:#0034B7;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;display:inline-block">Verify Email</a></p>'
            . '<p>If the button does not work, copy and paste this link into your browser:</p>'
            . '<p><a href="' . $safeUrl . '">' . $safeUrl . '</a></p>'
            . '<p>This link expires in 60 minutes.</p>'
            . '</div>';

        $text = "Verify your email\n\nOpen this link: {$verifyUrl}\n\nThis link expires in 60 minutes.";

        return self::send($toEmail, $toName, $subject, $html, $text);
    }

    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): array
    {
        try {
            $host = trim((string) (getenv('MAIL_HOST') ?: ''));
            $fromAddress = trim((string) (getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@localhost'));
            $fromName = trim((string) (getenv('MAIL_FROM_NAME') ?: (getenv('APP_NAME') ?: 'WhiteCoat')));

            $mailClass = '\\PHPMailer\\PHPMailer\\PHPMailer';
            if (class_exists($mailClass) && $host !== '') {
                $port = (int) (getenv('MAIL_PORT') ?: 587);
                $username = (string) (getenv('MAIL_USERNAME') ?: '');
                $password = (string) (getenv('MAIL_PASSWORD') ?: '');
                $encryption = strtolower(trim((string) (getenv('MAIL_ENCRYPTION') ?: 'tls')));

                $mail = new $mailClass(true);
                $mail->CharSet = 'UTF-8';
                $mail->isSMTP();
                $mail->Host = $host;
                $mail->Port = $port;

                if ($encryption === 'ssl') {
                    $mail->SMTPSecure = $mailClass::ENCRYPTION_SMTPS;
                } elseif ($encryption === 'tls' || $encryption === 'starttls') {
                    $mail->SMTPSecure = $mailClass::ENCRYPTION_STARTTLS;
                }

                $mail->SMTPAuth = $username !== '';
                if ($username !== '') {
                    $mail->Username = $username;
                    $mail->Password = $password;
                }

                $mail->setFrom($fromAddress, $fromName);
                $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
                $mail->Subject = $subject;
                $mail->isHTML(true);
                $mail->Body = $htmlBody;
                $mail->AltBody = $textBody;

                $mail->send();
                return ['ok' => true, 'error' => null];
            }

            if (!function_exists('mail')) {
                return ['ok' => false, 'error' => 'No mail transport available. Configure SMTP (with PHPMailer) or enable PHP mail().'];
            }

            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . $fromName . ' <' . $fromAddress . '>',
            ];

            $ok = @mail($toEmail, $encodedSubject, $htmlBody, implode("\r\n", $headers));
            return ['ok' => $ok, 'error' => $ok ? null : 'PHP mail() failed'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
