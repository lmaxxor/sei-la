<?php
require_once __DIR__.'/sessao/session_handler.php';
requireLogin('login.php');
require_once __DIR__.'/db/db_connect.php';

$id = (int)($_POST['id_oportunidade'] ?? 0);
$userId = $_SESSION['user_id'] ?? 0;

$q = $pdo->prepare("SELECT 1 FROM favoritos_oportunidade WHERE id_utilizador=? AND id_oportunidade=?");
$q->execute([$userId, $id]);
$existe = $q->fetch();
if ($existe) {
    $pdo->prepare("DELETE FROM favoritos_oportunidade WHERE id_utilizador=? AND id_oportunidade=?")->execute([$userId, $id]);
    echo json_encode(['status'=>'ok','favoritado'=>false]); exit;
} else {
    $pdo->prepare("INSERT IGNORE INTO favoritos_oportunidade(id_utilizador,id_oportunidade) VALUES (?,?)")->execute([$userId, $id]);
    echo json_encode(['status'=>'ok','favoritado'=>true]); exit;
}
