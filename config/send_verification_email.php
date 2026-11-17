
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

function sendVerificationEmail(
    string $recipientEmail,
    string $recipientName,
    string $verificationToken
): bool {

    // Local XAMPP email configuration
    $localConfigPath = 'C:/xampp/gym_mail_config.php';

    // Online hosting configuration
    // This file must be kept outside the public htdocs folder.
    $onlineConfigPath =
        dirname(__DIR__, 2) . '/gym_mail_config.php';

    if (is_file($localConfigPath)) {
        $configPath = $localConfigPath;
    } elseif (is_file($onlineConfigPath)) {
        $configPath = $onlineConfigPath;
    } else {
        error_log('Gym email configuration not found.');
        return false;
    }

    try {
        $config = require $configPath;

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_username'];
        $mail->Password = $config['smtp_password'];
        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)$config['smtp_port'];
        $mail->CharSet = 'UTF-8';

        // Sender address and display name
        $mail->setFrom(
            $config['from_email'],
            $config['from_name'] ??
                '4BROS AND 1BOSS Gym'
        );

        // Recipient
        $mail->addAddress(
            $recipientEmail,
            $recipientName
        );

        // Base URL from configuration
        $baseUrl = rtrim(
            $config['base_url'],
            '/'
        );

        $verificationLink =
            $baseUrl
            . '/verify_email.php?token='
            . urlencode($verificationToken);

        // Escape HTML output
        $safeName = htmlspecialchars(
            $recipientName,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeLink = htmlspecialchars(
            $verificationLink,
            ENT_QUOTES,
            'UTF-8'
        );

        $mail->isHTML(true);

        $mail->Subject =
            'Verify Your 4BROS AND 1BOSS Gym Account';

        $mail->Body = "
            <div style='
                font-family: Arial, sans-serif;
                max-width: 550px;
                margin: auto;
                padding: 25px;
                background: #111827;
                color: #ffffff;
                border-radius: 12px;
            '>

                <h2 style='color: #22c55e;'>
                    4BROS AND 1BOSS Gym
                </h2>

                <p>Hello {$safeName},</p>

                <p>
                    Thank you for registering!
                    Please verify your email address
                    to activate your member account.
                </p>

                <p style='margin: 30px 0;'>
                    <a href='{$safeLink}'
                       style='
                           background: #22c55e;
                           color: #111827;
                           padding: 13px 25px;
                           border-radius: 7px;
                           text-decoration: none;
                           font-weight: bold;
                       '>
                        Verify My Email
                    </a>
                </p>

                <p>
                    This verification link expires
                    in 30 minutes.
                </p>

                <p style='color: #9ca3af;'>
                    If you did not register for this
                    account, you can ignore this email.
                </p>

            </div>
        ";

        $mail->AltBody =
            "Hello {$recipientName},\n\n"
            . "Thank you for registering at "
            . "4BROS AND 1BOSS Gym.\n\n"
            . "Verify your email using this link:\n"
            . $verificationLink
            . "\n\nThis link expires in 30 minutes.";

        return $mail->send();

    } catch (Throwable $e) {

        error_log(
            'Verification email error: '
            . $e->getMessage()
        );

        return false;
    }
}
