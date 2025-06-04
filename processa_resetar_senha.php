<?php
require_once __DIR__ . '/sessao/session_handler.php';
require_once __DIR__ . '/db/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$token = $_POST['token'] ?? '';
$senha = $_POST['senha'] ?? '';
$confirmar = $_POST['confirmar'] ?? '';

if (!$token || strlen($senha) < 8 || $senha !== $confirmar) {
    $_SESSION['reset_msg'] = 'Dados inválidos ou senhas não coincidem (mínimo 8 caracteres).';
    header('Location: resetar_senha.php?token=' . urlencode($token));
    exit;
}

$stmt = $pdo->prepare('SELECT id_utilizador FROM utilizadores WHERE token_reset_passe = ? AND data_expiracao_token_reset > NOW()');
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    $_SESSION['reset_msg'] = 'Token inválido ou expirado.';
    header('Location: esqueci_senha.php');
    exit;
}

$hash = password_hash($senha, PASSWORD_DEFAULT);
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('UPDATE utilizadores SET palavra_passe = ?, token_reset_passe = NULL, data_expiracao_token_reset = NULL WHERE id_utilizador = ?');
    $stmt->execute([$hash, $user['id_utilizador']]);
    $pdo->commit();
    $_SESSION['reset_msg'] = 'Senha alterada com sucesso. Faça login.';
    header('Location: login.php');
    exit;
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Erro reset senha: ' . $e->getMessage());
    $_SESSION['reset_msg'] = 'Erro ao alterar senha.';
    header('Location: resetar_senha.php?token=' . urlencode($token));
    exit;
}
