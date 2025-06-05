<?php
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function getMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = getenv('SMTP_HOST');
    $mail->SMTPAuth = true;
    $mail->Username = getenv('SMTP_USER');
    $mail->Password = getenv('SMTP_PASS');
    $mail->SMTPSecure = getenv('SMTP_SECURE') ?: PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = getenv('SMTP_PORT') ?: 587;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom(getenv('SMTP_FROM') ?: getenv('SMTP_USER'), getenv('SMTP_FROM_NAME') ?: 'AudioTO');
    return $mail;
}

function sendMail(string $to, string $subject, string $body): bool {
    $mail = getMailer();
    try {
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        return $mail->send();
    } catch (Exception $e) {
        error_log('Erro ao enviar email: ' . $e->getMessage());
        return false;
    }
}

function getEmailsByPreference(PDO $pdo, string $column): array {
    $stmt = $pdo->prepare(
        "SELECT u.email FROM utilizadores u
         JOIN preferencias_notificacao p ON u.id_utilizador = p.id_utilizador
         LEFT JOIN audioto_emails a ON a.email = u.email
         WHERE p.$column = 1 AND a.email IS NULL AND u.status_sistema = 'ativo'"
    );
    $stmt->execute();
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'email');
}

function notifyUsers(PDO $pdo, string $column, string $subject, string $message): void {
    foreach (getEmailsByPreference($pdo, $column) as $email) {
        sendMail($email, $subject, $message);
    }
}



