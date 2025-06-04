<?php
// db/db_connect.php

// Incluir o ficheiro de configuração
// O __DIR__ garante que o caminho é relativo ao diretório atual do ficheiro db_connect.php
require_once __DIR__ . '/config.php';

// DSN (Data Source Name)
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// Opções para o PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lançar exceções em erros
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Obter resultados como arrays associativos
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Usar prepared statements nativos
];

try {
    // Criar uma instância PDO (PHP Data Objects)
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // Em produção, não exiba detalhes do erro ao utilizador. Logue o erro.
    // error_log("Erro de conexão com o banco de dados: " . $e->getMessage(), 0);
    // die("Ocorreu um erro ao tentar conectar com o banco de dados. Por favor, tente novamente mais tarde ou contacte o suporte.");
    
    // Para desenvolvimento, pode ser útil ver o erro diretamente:
    // (Certifique-se de desativar isto em produção)
    echo "Erro de Conexão: " . $e->getMessage() . "<br>";
    echo "Código do Erro: " . $e->getCode() . "<br>";
    echo "Ficheiro: " . $e->getFile() . "<br>";
    echo "Linha: " . $e->getLine() . "<br>";
    // echo "Trace: <pre>" . $e->getTraceAsString() . "</pre><br>";
    exit; // Interrompe a execução se a conexão falhar
}

// O objeto $pdo agora está disponível para ser usado nos seus scripts
// após incluir este ficheiro com: require_once 'db/db_connect.php';
?>
