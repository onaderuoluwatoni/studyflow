<?php
/**
 * StudyFlow email sender.
 *
 * SETUP (one-time):
 * 1. Use a Gmail account (or any SMTP provider).
 * 2. If Gmail: turn on 2-Step Verification on that account, then create an
 *    "App Password" at https://myaccount.google.com/apppasswords
 *    (choose "Mail" as the app). Gmail gives you a 16-character code —
 *    use THAT below, not your normal Gmail password.
 * 3. Fill in SMTP_USERNAME and SMTP_PASSWORD below.
 */

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'onaderuoluwatoni@gmail.com');   // <-- change this
define('SMTP_PASSWORD', 'sytrbdgkpobhuzbz'); // <-- change this (Gmail App Password)
define('SMTP_FROM_NAME', 'StudyFlow');

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Generates a random 6-digit numeric code as a string, e.g. "042817".
 */
function sfGenerateCode() {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Sends an HTML email. Returns true on success, false on failure.
 * $errorOut (optional, by reference) is filled with the error message on failure.
 */
function sfSendMail($toEmail, $toName, $subject, $htmlBody, &$errorOut = null) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;

        // XAMPP on Windows often lacks a configured CA certificate bundle,
        // which makes PHP's SSL handshake fail instantly even though the
        // network connection itself is fine. This relaxes verification so
        // local development/testing can send mail. (Fine for local/demo use;
        // a real production server would have proper CA certs configured instead.)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody  = strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        $errorOut = $mail->ErrorInfo;
        return false;
    }
}

/**
 * Sends the 6-digit account verification code.
 */
function sfSendVerificationEmail($toEmail, $toName, $code, &$errorOut = null) {
    $subject = "Your StudyFlow verification code";
    $html = "
        <div style='font-family:Arial,sans-serif;max-width:420px;margin:0 auto;padding:24px;'>
            <h2 style='color:#0b1526;'>Verify your email</h2>
            <p>Hi " . htmlspecialchars($toName) . ",</p>
            <p>Use this code to finish creating your StudyFlow account:</p>
            <div style='font-size:32px;font-weight:700;letter-spacing:6px;background:#f4efe6;color:#0b1526;padding:16px;text-align:center;border-radius:8px;margin:16px 0;'>
                {$code}
            </div>
            <p style='color:#666;font-size:14px;'>This code expires in 15 minutes. If you didn't request this, you can ignore this email.</p>
        </div>
    ";
    return sfSendMail($toEmail, $toName, $subject, $html, $errorOut);
}

/**
 * Sends the 6-digit password reset code.
 */
function sfSendResetEmail($toEmail, $toName, $code, &$errorOut = null) {
    $subject = "Reset your StudyFlow password";
    $html = "
        <div style='font-family:Arial,sans-serif;max-width:420px;margin:0 auto;padding:24px;'>
            <h2 style='color:#0b1526;'>Reset your password</h2>
            <p>Hi " . htmlspecialchars($toName) . ",</p>
            <p>Use this code to reset your StudyFlow password:</p>
            <div style='font-size:32px;font-weight:700;letter-spacing:6px;background:#f4efe6;color:#0b1526;padding:16px;text-align:center;border-radius:8px;margin:16px 0;'>
                {$code}
            </div>
            <p style='color:#666;font-size:14px;'>This code expires in 15 minutes. If you didn't request this, you can ignore this email — your password will stay the same.</p>
        </div>
    ";
    return sfSendMail($toEmail, $toName, $subject, $html, $errorOut);
}
