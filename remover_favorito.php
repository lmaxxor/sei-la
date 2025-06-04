<?php
// remover_favorito.php
require_once __DIR__ . '/sessao/session_handler.php';
requireLogin(); // Garante que o usuário está logado

require_once __DIR__ . '/db/db_connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'ID do favorito não fornecido.'];
$userId = $_SESSION['user_id'] ?? null;

if (isset($_GET['id_favorito']) && $userId) {
    $id_favorito = filter_input(INPUT_GET, 'id_favorito', FILTER_VALIDATE_INT);

    if ($id_favorito) {
        try {
            $stmt = $pdo->prepare("DELETE FROM favoritos WHERE id_favorito = :id_favorito AND id_utilizador = :userId");
            $stmt->bindParam(':id_favorito', $id_favorito, PDO::PARAM_INT);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $response = ['success' => true, 'message' => 'Favorito removido com sucesso.'];
                } else {
                    $response = ['success' => false, 'message' => 'Favorito não encontrado ou não pertence a este usuário.'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Erro ao executar a remoção.'];
            }
        } catch (PDOException $e) {
            error_log("Erro ao remover favorito: " . $e->getMessage());
            $response = ['success' => false, 'message' => 'Erro no banco de dados ao remover favorito.'];
        }
    } else {
        $response = ['success' => false, 'message' => 'ID do favorito inválido.'];
    }
} elseif (!$userId) {
     $response = ['success' => false, 'message' => 'Usuário não autenticado.'];
}

echo json_encode($response);
exit;