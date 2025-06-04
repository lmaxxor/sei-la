<?php
require_once __DIR__ . '/sessao/session_handler.php';
require_once __DIR__ . '/db/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: esqueci_senha.php');
    exit;
}

$email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL));
if (!$email) {
    $_SESSION['reset_msg'] = 'E-mail inválido.';
    header('Location: esqueci_senha.php');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id_utilizador FROM utilizadores WHERE email = :email');
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $_SESSION['reset_msg'] = 'E-mail não encontrado.';
        header('Location: esqueci_senha.php');
        exit;
    }
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $stmt = $pdo->prepare('UPDATE utilizadores SET token_reset_passe = ?, data_expiracao_token_reset = ? WHERE id_utilizador = ?');
    $stmt->execute([$token, $expiry, $user['id_utilizador']]);

    $resetLink = SITE_URL . '/resetar_senha.php?token=' . urlencode($token);
    $subject = 'Recuperação de senha AudioTO';
    $message = "Clique no link para redefinir sua senha: $resetLink";
    @mail($email, $subject, $message);

    $_SESSION['reset_msg'] = 'Se o e-mail existir em nossa base, enviaremos um link de redefinição.';
    header('Location: esqueci_senha.php');
    exit;
} catch (PDOException $e) {
    error_log('Erro envio reset: ' . $e->getMessage());
    $_SESSION['reset_msg'] = 'Erro ao processar solicitação.';
    header('Location: esqueci_senha.php');
    exit;
}
