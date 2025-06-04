<?php
require_once __DIR__ . '/sessao/session_handler.php';
requireLogin('login.php');
require_once __DIR__ . '/sessao/csrf.php';
require_once __DIR__ . '/db/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: comunidade.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    $_SESSION['community_error'] = 'Falha de segurança ao comentar.';
    $pid = (int)($_POST['post_id'] ?? 0);
    header('Location: publicacao.php?id=' . $pid);
    exit;
}

$postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
$texto = trim(filter_input(INPUT_POST, 'texto', FILTER_UNSAFE_RAW));

if (!$postId || $texto === '') {
    $_SESSION['community_error'] = 'Comentário inválido.';
    header('Location: publicacao.php?id=' . (int)$postId);
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO comunidade_comentarios (id_post, id_utilizador, texto) VALUES (:p, :u, :t)');
    $stmt->bindParam(':p', $postId, PDO::PARAM_INT);
    $stmt->bindParam(':u', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindParam(':t', $texto);
    $stmt->execute();
    $pdo->prepare('UPDATE comunidade_posts SET total_comentarios = total_comentarios + 1 WHERE id_post = ?')->execute([$postId]);
    $_SESSION['community_success'] = 'Comentário adicionado!';
} catch (PDOException $e) {
    error_log('Erro ao comentar: ' . $e->getMessage());
    $_SESSION['community_error'] = 'Erro ao gravar comentário.';
}

header('Location: publicacao.php?id=' . $postId);
exit;
?>
