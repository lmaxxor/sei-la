<?php
// register_handler.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir o gestor de sessões e a conexão com o banco de dados
require_once __DIR__ . '/sessao/session_handler.php';
require_once __DIR__ . '/db/db_connect.php'; // Garanta que este caminho está correto

// Redirecionar se o formulário não foi submetido via POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: registrar.php"); // Ou o nome da sua página de registo
    exit;
}

// Obter dados do formulário e sanitizar minimamente
$nome_completo = trim(filter_input(INPUT_POST, 'fullName', FILTER_SANITIZE_STRING));
$email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
$palavra_passe = $_POST['password'] ?? ''; // Não sanitizar palavra-passe antes do hash
$confirmar_palavra_passe = $_POST['confirmPassword'] ?? '';
// Usar null coalescing operator para definir null se vazio, para profissao e crefito
$profissao = trim(filter_input(INPUT_POST, 'profession', FILTER_SANITIZE_STRING));
$profissao = !empty($profissao) ? $profissao : null;

$crefito = trim(filter_input(INPUT_POST, 'crefito', FILTER_SANITIZE_STRING));
$crefito = !empty($crefito) ? $crefito : null;

$termos = isset($_POST['terms']);

// --- Validação dos Dados ---
$erros = [];

if (empty($nome_completo)) {
    $erros[] = "O nome completo é obrigatório.";
}
if (empty($email)) {
    $erros[] = "O email é obrigatório.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erros[] = "O formato do email é inválido.";
}
if (empty($palavra_passe)) {
    $erros[] = "A palavra-passe é obrigatória.";
} elseif (strlen($palavra_passe) < 8) {
    $erros[] = "A palavra-passe deve ter no mínimo 8 caracteres.";
}
if ($palavra_passe !== $confirmar_palavra_passe) {
    $erros[] = "As palavras-passe não coincidem.";
}
if (!$termos) {
    $erros[] = "Deve aceitar os Termos e Condições.";
}

// Verificar se o email já existe
if (empty($erros) && !empty($email)) {
    try {
        $stmt_check_email = $pdo->prepare("SELECT id_utilizador FROM utilizadores WHERE email = :email");
        $stmt_check_email->bindParam(':email', $email);
        $stmt_check_email->execute();
        if ($stmt_check_email->fetch()) {
            $erros[] = "Este endereço de email já está registado.";
        }
    } catch (PDOException $e) {
        error_log("Erro ao verificar email: " . $e->getMessage());
        $erros[] = "Ocorreu um erro ao processar o seu registo. Tente novamente.";
    }
}


// Se houver erros, guardar na sessão e redirecionar de volta para o formulário de registo
if (!empty($erros)) {
    $_SESSION['register_errors'] = $erros;
    $_SESSION['form_data'] = [
        'fullName' => $nome_completo,
        'email' => $email,
        'profession' => $profissao,
        'crefito' => $crefito,
    ];
    header("Location: registrar.php");
    exit;
}

// --- Processar Registo (se não houver erros) ---

$palavra_passe_hashed = password_hash($palavra_passe, PASSWORD_DEFAULT);

// VALORES PADRÃO PARA NOVOS UTILIZADORES
$funcao_padrao = 'utilizador';
$status_inicial_conta = 'ativo'; // Novo status padrão
$id_plano_inicial = 1;          // Novo plano padrão (ID 1 = Essencial)

try {
    // Iniciar transação para garantir atomicidade
    $pdo->beginTransaction();

    $sql_utilizador = "INSERT INTO utilizadores (nome_completo, email, palavra_passe, profissao, crefito, funcao, status_sistema, id_plano_assinatura_ativo, data_registo) 
                       VALUES (:nome_completo, :email, :palavra_passe, :profissao, :crefito, :funcao, :status_sistema, :id_plano_assinatura_ativo, NOW())";
    $stmt_utilizador = $pdo->prepare($sql_utilizador);

    $stmt_utilizador->bindParam(':nome_completo', $nome_completo);
    $stmt_utilizador->bindParam(':email', $email);
    $stmt_utilizador->bindParam(':palavra_passe', $palavra_passe_hashed);
    $stmt_utilizador->bindParam(':profissao', $profissao, $profissao === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt_utilizador->bindParam(':crefito', $crefito, $crefito === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt_utilizador->bindParam(':funcao', $funcao_padrao);
    $stmt_utilizador->bindParam(':status_sistema', $status_inicial_conta); // Adicionado
    $stmt_utilizador->bindParam(':id_plano_assinatura_ativo', $id_plano_inicial, PDO::PARAM_INT); // Adicionado

    if ($stmt_utilizador->execute()) {
        $id_novo_utilizador = $pdo->lastInsertId();

        // Criar um registro na tabela assinaturas_utilizador para o plano inicial
        if ($id_novo_utilizador && $id_plano_inicial) {
            $data_inicio_assinatura = date('Y-m-d H:i:s');
            // Supondo que o plano 1 (Essencial) seja mensal. Ajuste se necessário.
            $data_proxima_cobranca = date('Y-m-d H:i:s', strtotime('+1 month')); 
            $estado_assinatura_inicial = 'ativa'; // Ou 'pendente_pagamento' se precisar de confirmação

            $sql_assinatura = "INSERT INTO assinaturas_utilizador 
                                (id_utilizador, id_plano, data_inicio, data_proxima_cobranca, estado_assinatura, data_criacao) 
                               VALUES 
                                (:id_utilizador, :id_plano, :data_inicio, :data_proxima_cobranca, :estado_assinatura, NOW())";
            $stmt_assinatura = $pdo->prepare($sql_assinatura);
            $stmt_assinatura->execute([
                ':id_utilizador' => $id_novo_utilizador,
                ':id_plano' => $id_plano_inicial,
                ':data_inicio' => $data_inicio_assinatura,
                ':data_proxima_cobranca' => $data_proxima_cobranca, 
                ':estado_assinatura' => $estado_assinatura_inicial
            ]);
        }
        
        $pdo->commit(); // Confirmar a transação

        $_SESSION['register_success'] = "Conta criada com sucesso! Pode agora fazer login.";
        unset($_SESSION['form_data']);
        unset($_SESSION['register_errors']);
        header("Location: login.php");
        exit;

    } else {
        $pdo->rollBack(); // Reverter a transação em caso de falha na inserção do utilizador
        error_log("Erro ao inserir utilizador no BD.");
        $_SESSION['register_errors'] = ["Ocorreu um erro inesperado ao criar a sua conta. Por favor, tente novamente."];
        $_SESSION['form_data'] = $_POST; // Guardar dados para repopular
        header("Location: registrar.php");
        exit;
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack(); // Reverter a transação se uma exceção ocorrer
    }
    error_log("Erro de PDO no registo: " . $e->getMessage());
    $_SESSION['register_errors'] = ["Ocorreu um erro crítico ao processar o seu registo. Contacte o suporte se o problema persistir."];
    $_SESSION['form_data'] = $_POST; // Guardar dados para repopular
    header("Location: registrar.php");
    exit;
}
?>