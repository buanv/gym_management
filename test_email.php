
<?php

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

$config = require 'C:/xampp/gym_mail_config.php';

$mail = new PHPMailer(true);

try {

    $mail->isSMTP();

    $mail->Host = $config['smtp_host'];
    $mail->SMTPAuth = true;

    $mail->Username = $config['smtp_username'];
    $mail->Password = $config['smtp_password'];

    $mail->SMTPSecure =
        PHPMailer::ENCRYPTION_STARTTLS;

    $mail->Port = (int)$config['smtp_port'];

    $mail->CharSet = 'UTF-8';

    $mail->setFrom(
        $config['from_email'],
        '4BROS AND 1BOSS Gym'
    );

    // Test recipient
    $mail->addAddress('vanessabuan29@gmail.com');

    $mail->isHTML(true);

    $mail->Subject =
        '4BROS AND 1BOSS - Test Email';

    $mail->Body = '
        <h2>4BROS AND 1BOSS</h2>
        <p>Hello!</p>
        <p>This is a test email from your gym system.</p>
        <p>Your Gmail SMTP configuration is working.</p>
    ';

    $mail->AltBody =
        'This is a test email from your gym system.';

    $mail->send();

    echo 'Test email sent successfully!';

} catch (Throwable $e) {

    error_log(
        'Gym email test failed: ' . $e->getMessage()
    );

    echo 'Unable to send test email. '
       . 'Check your SMTP settings and PHP error log.';
}
