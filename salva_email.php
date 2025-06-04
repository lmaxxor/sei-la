<?php
$host = 'localhost';
$db   = 'u582136142_AudioTO';
$user = 'u582136142_AudioTO';
$pass = 'Aceleron0@';


if (isset($_POST['email'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    if ($email) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
            // Verifica se o email já existe
            $stmt = $pdo->prepare("SELECT id FROM audioto_emails WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo 'existe'; // Já cadastrado
                exit;
            }
            // Insere se não existir
            $stmt = $pdo->prepare("INSERT INTO audioto_emails (email) VALUES (?)");
            $stmt->execute([$email]);
            echo 'ok';
        } catch (PDOException $e) {
            echo 'erro';
        }
    } else {
        echo 'erro';
    }
} else {
    echo 'erro';
}
?>
