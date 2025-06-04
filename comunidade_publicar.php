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
    $_SESSION['community_error'] = 'Falha de segurança ao publicar.';
    header('Location: comunidade.php');
    exit;
}

$title = trim(filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING));
$content = trim(filter_input(INPUT_POST, 'content', FILTER_UNSAFE_RAW));

if ($title === '' || $content === '') {
    $_SESSION['community_error'] = 'Título e conteúdo são obrigatórios.';
    header('Location: comunidade.php');
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO comunidade_posts (id_utilizador, titulo, texto) VALUES (:uid, :t, :c)');
    $stmt->bindParam(':uid', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindParam(':t', $title);
    $stmt->bindParam(':c', $content);
    $stmt->execute();
    $_SESSION['community_success'] = 'Publicação criada com sucesso!';
} catch (PDOException $e) {
    error_log('Erro ao criar post: ' . $e->getMessage());
    $_SESSION['community_error'] = 'Erro ao criar publicação.';
}

header('Location: comunidade.php');
exit;
?>
