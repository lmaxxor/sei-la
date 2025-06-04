<?php
// logout_handler.php

// Incluir o gestor de sessões para garantir que session_start() é chamado
// e que as configurações de sessão são aplicadas.
// Se logout_handler.php está na raiz e session_handler.php também, o caminho é este:
require_once __DIR__ . '/sessao/session_handler.php';

// 1. Desfazer (unset) todas as variáveis de sessão.
$_SESSION = array();

// 2. Se for desejado destruir a sessão completamente, apague também o cookie de sessão.
// Nota: Isto destruirá a sessão, e não apenas os dados da sessão!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, // Define um tempo no passado para expirar o cookie
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Finalmente, destruir a sessão.
session_destroy();

// 4. Redirecionar para a página de login.
// Certifique-se de que 'login.php' é o nome correto da sua página de login e está na raiz.
header("Location: ./login.php");
exit;
?>
