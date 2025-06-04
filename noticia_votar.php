<?php
require_once __DIR__ . '/sessao/session_handler.php';
requireLogin('login.php');
require_once __DIR__ . '/db/db_connect.php';
header('Content-Type: application/json; charset=utf-8');

$idNoticia = (int)($_POST['id_noticia'] ?? 0);
$acao = $_POST['acao'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;
if (!$idNoticia || !in_array($acao, ['up','down'])) {
    echo json_encode(['success'=>false,'message'=>'Dados invalidos']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT valor FROM noticia_votos WHERE id_noticia=? AND id_utilizador=?');
    $stmt->execute([$idNoticia,$userId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        if ($existing['valor'] === $acao) {
            $pdo->prepare('DELETE FROM noticia_votos WHERE id_noticia=? AND id_utilizador=?')->execute([$idNoticia,$userId]);
        } else {
            $pdo->prepare('UPDATE noticia_votos SET valor=? WHERE id_noticia=? AND id_utilizador=?')->execute([$acao,$idNoticia,$userId]);
        }
    } else {
        $pdo->prepare('INSERT INTO noticia_votos(id_noticia,id_utilizador,valor) VALUES (?,?,?)')->execute([$idNoticia,$userId,$acao]);
    }
    $countStmt = $pdo->prepare("SELECT SUM(valor='up') AS ups, SUM(valor='down') AS downs FROM noticia_votos WHERE id_noticia=?");
    $countStmt->execute([$idNoticia]);
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);
    $voteStmt = $pdo->prepare('SELECT valor FROM noticia_votos WHERE id_noticia=? AND id_utilizador=?');
    $voteStmt->execute([$idNoticia,$userId]);
    $current = $voteStmt->fetchColumn();
    echo json_encode(['success'=>true,'up'=>(int)($counts['ups']??0),'down'=>(int)($counts['downs']??0),'vote'=>$current]);
} catch(PDOException $e){
    error_log('Erro votar noticia: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Erro interno']);
}
exit;
?>
