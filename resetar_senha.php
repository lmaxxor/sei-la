<?php
require_once __DIR__ . '/sessao/session_handler.php';
require_once __DIR__ . '/db/db_connect.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    echo 'Token inválido.';
    exit;
}

$stmt = $pdo->prepare('SELECT id_utilizador FROM utilizadores WHERE token_reset_passe = ? AND data_expiracao_token_reset > NOW()');
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    echo 'Link expirado ou inválido.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - AudioTO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
<div class="bg-white p-6 rounded shadow w-full max-w-md">
    <h1 class="text-xl font-semibold mb-4 text-center">Definir Nova Senha</h1>
    <?php if (!empty($_SESSION['reset_msg'])): ?>
        <div class="mb-4 text-sm text-red-700 bg-red-100 p-2 rounded">
            <?= htmlspecialchars($_SESSION['reset_msg']); unset($_SESSION['reset_msg']); ?>
        </div>
    <?php endif; ?>
    <form action="processa_resetar_senha.php" method="POST" class="space-y-4">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token); ?>">
        <div>
            <label for="senha" class="block text-sm font-medium">Nova senha</label>
            <input type="password" id="senha" name="senha" required class="mt-1 w-full border rounded p-2">
        </div>
        <div>
            <label for="confirmar" class="block text-sm font-medium">Confirmar senha</label>
            <input type="password" id="confirmar" name="confirmar" required class="mt-1 w-full border rounded p-2">
        </div>
        <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">Alterar senha</button>
    </form>
</div>
</body>
</html>
