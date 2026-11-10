<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_otp_email(string $recipient, string $name, string $otp): void
{
    $settings = require __DIR__ . '/config.php';
    $mail = $settings['mail'];

    $mailer = new PHPMailer(true);

    try {
        $mailer->isSMTP();
        $mailer->Host = $mail['host'];
        $mailer->SMTPAuth = true;
        $mailer->Username = $mail['username'];
        $mailer->Password = $mail['password'];
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Port = (int)$mail['port'];

        // VERY IMPORTANT
        $mailer->SMTPDebug = 0;

        $mailer->setFrom(
            $mail['from_email'],
            $mail['from_name']
        );

        $mailer->addAddress($recipient, $name);

        $mailer->isHTML(true);
        $mailer->Subject = 'Email Verification Code';

        $mailer->Body = "
            <h2>Hello {$name}</h2>
            <p>Your verification code is:</p>
            <h1>{$otp}</h1>
            <p>This code expires in 10 minutes.</p>
        ";

        $mailer->AltBody =
            "Hello {$name}, your verification code is {$otp}";

        $mailer->send();
    } catch (Exception $e) {
        throw new RuntimeException(
            'Email sending failed: ' . $mailer->ErrorInfo
        );
    }
}
