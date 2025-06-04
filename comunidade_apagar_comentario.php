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
    $_SESSION['community_error'] = 'Falha de segurança.';
    $pid = (int)($_POST['post_id'] ?? 0);
    header('Location: publicacao.php?id=' . $pid);
    exit;
}

$commentId = filter_input(INPUT_POST, 'comment_id', FILTER_VALIDATE_INT);
$postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);

if (!$commentId || !$postId) {
    $_SESSION['community_error'] = 'Dados inválidos.';
    header('Location: publicacao.php?id=' . (int)$postId);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id_utilizador FROM comunidade_comentarios WHERE id_comentario = :id');
    $stmt->bindParam(':id', $commentId, PDO::PARAM_INT);
    $stmt->execute();
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$comment) {
        $_SESSION['community_error'] = 'Comentário não encontrado.';
    } elseif ($comment['id_utilizador'] != $_SESSION['user_id'] && !isAdmin()) {
        $_SESSION['community_error'] = 'Sem permissão para excluir.';
    } else {
        $pdo->prepare('DELETE FROM comunidade_comentarios WHERE id_comentario = ?')->execute([$commentId]);
        $pdo->prepare('UPDATE comunidade_posts SET total_comentarios = GREATEST(total_comentarios - 1,0) WHERE id_post = ?')->execute([$postId]);
        $_SESSION['community_success'] = 'Comentário removido.';
    }
} catch (PDOException $e) {
    error_log('Erro ao apagar comentario: ' . $e->getMessage());
    $_SESSION['community_error'] = 'Erro ao apagar comentário.';
}

header('Location: publicacao.php?id=' . $postId);
exit;
?>
