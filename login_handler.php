<?php
require_once __DIR__ . '/sessao/session_handler.php';
require_once __DIR__ . '/db/db_connect.php';

// Só aceita POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

// Captura e sanitiza
$email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
$senha = $_POST['password'] ?? '';

$erros_login = [];
if (empty($email)) {
    $erros_login[] = "O e-mail é obrigatório.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erros_login[] = "O formato do e-mail é inválido.";
}
if (empty($senha)) {
    $erros_login[] = "A senha é obrigatória.";
}

if (!empty($erros_login)) {
    $_SESSION['login_errors'] = $erros_login;
    $_SESSION['login_email_attempt'] = $email;
    header("Location: login.php");
    exit;
}

// Busca no banco
try {
    $stmt = $pdo->prepare("SELECT id_utilizador, nome_completo, email, palavra_passe, funcao, avatar_url, id_plano_assinatura_ativo FROM utilizadores WHERE email = :email LIMIT 1");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($senha, $usuario['palavra_passe'])) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $usuario['id_utilizador'];
        $_SESSION['user_nome_completo'] = $usuario['nome_completo'];
        $_SESSION['user_email'] = $usuario['email'];
        $_SESSION['user_funcao'] = $usuario['funcao'];
        $_SESSION['user_avatar_url'] = $usuario['avatar_url'];
        $_SESSION['user_plano_id'] = (int) $usuario['id_plano_assinatura_ativo']; // ← ESSA LINHA


        unset($_SESSION['login_errors'], $_SESSION['login_email_attempt'], $_SESSION['register_success']);
        header("Location: inicio.php");
        exit;
    } else {
        $erros_login[] = "E-mail ou senha inválidos.";
    }
} catch (PDOException $e) {
    error_log("Erro no login: " . $e->getMessage());
    $erros_login[] = "Erro interno ao tentar fazer login. Tente novamente.";
}

// Se chegou aqui, erro
$_SESSION['login_errors'] = $erros_login;
$_SESSION['login_email_attempt'] = $email;
header("Location: login.php");
exit;
?>
