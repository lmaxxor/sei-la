<?php
require_once __DIR__ . '/sessao/session_handler.php';
// Usuário não precisa estar logado
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - AudioTO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
<div class="bg-white p-6 rounded shadow w-full max-w-md">
    <h1 class="text-xl font-semibold mb-4 text-center">Recuperar Senha</h1>
    <?php if (!empty($_SESSION['reset_msg'])): ?>
        <div class="mb-4 text-sm text-red-700 bg-red-100 p-2 rounded">
            <?= htmlspecialchars($_SESSION['reset_msg']); unset($_SESSION['reset_msg']); ?>
        </div>
    <?php endif; ?>
    <form action="processa_esqueci_senha.php" method="POST" class="space-y-4">
        <div>
            <label for="email" class="block text-sm font-medium">E-mail</label>
            <input type="email" id="email" name="email" required class="mt-1 w-full border rounded p-2" placeholder="seu.email@exemplo.com">
        </div>
        <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">Enviar link de redefinição</button>
    </form>
    <div class="text-center mt-4">
        <a href="login.php" class="text-sm text-blue-600 hover:underline">Voltar ao login</a>
    </div>
</div>
</body>
</html>
