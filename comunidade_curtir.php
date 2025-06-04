<?php
require_once __DIR__ . '/sessao/session_handler.php';
requireLogin('login.php');
require_once __DIR__ . '/db/db_connect.php';
header('Content-Type: application/json; charset=utf-8');

$postId = (int)($_POST['id_post'] ?? 0);
$userId = $_SESSION['user_id'] ?? 0;
if (!$postId || !$userId) {
    echo json_encode(['success' => false, 'message' => 'Dados invalidos']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT 1 FROM comunidade_curtidas WHERE id_post=? AND id_utilizador=?');
    $stmt->execute([$postId, $userId]);
    if ($stmt->fetch()) {
        $pdo->prepare('DELETE FROM comunidade_curtidas WHERE id_post=? AND id_utilizador=?')->execute([$postId, $userId]);
        $pdo->prepare('UPDATE comunidade_posts SET total_curtidas = GREATEST(total_curtidas - 1,0) WHERE id_post=?')->execute([$postId]);
        $liked = false;
    } else {
        $pdo->prepare('INSERT INTO comunidade_curtidas(id_post,id_utilizador) VALUES (?,?)')->execute([$postId,$userId]);
        $pdo->prepare('UPDATE comunidade_posts SET total_curtidas = total_curtidas + 1 WHERE id_post=?')->execute([$postId]);
        $liked = true;
    }
    $countStmt = $pdo->prepare('SELECT total_curtidas FROM comunidade_posts WHERE id_post=?');
    $countStmt->execute([$postId]);
    $total = (int)$countStmt->fetchColumn();
    echo json_encode(['success'=>true,'liked'=>$liked,'total'=>$total]);
} catch(PDOException $e){
    error_log('Erro comunidade curtir: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Erro interno']);
}
exit;
?>
