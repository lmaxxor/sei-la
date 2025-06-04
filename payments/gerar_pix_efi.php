<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Restante do seu código ---
// require_once __DIR__ . '/../sessao/session_handler.php';
// ...
// payments/gerar_pix_efi.php
require_once __DIR__ . '/../sessao/session_handler.php'; // Para $_SESSION e requireLogin()
require_once __DIR__ . '/../db/db_connect.php';       // Para $pdo

requireLogin(); // Garante que o usuário está logado

// Carrega o autoload do Composer e as classes da Efí
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/pix-api.php'; // Nossa classe EfiPix adaptada

header('Content-Type: application/json');

$id_utilizador = $_SESSION['user_id'] ?? null;
if (!$id_utilizador) {
    echo json_encode(['success' => false, 'message' => 'Sessão de usuário inválida.']);
    exit;
}

$amount_str = $_POST['amount'] ?? null;
$cpf_pagador = $_POST['cpf'] ?? null;
$nome_pagador = $_POST['nome'] ?? $_SESSION['user_nome_completo'] ?? 'Comprador AudioTO';
$id_plano = $_POST['planId'] ?? null;

if (!$amount_str || !$cpf_pagador || !$nome_pagador || !$id_plano || !is_numeric($amount_str) || (float)$amount_str <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos para gerar Pix (valor, CPF, nome ou ID do plano ausentes).']);
    exit;
}

$amount = (float)$amount_str;
$cpf_pagador = preg_replace('/[^0-9]/', '', $cpf_pagador); // Remove formatação do CPF

// Inicia transação no banco de dados
$pdo->beginTransaction();

try {
    // 1. Criar registro de assinatura pendente
    $stmt = $pdo->prepare("INSERT INTO assinaturas_utilizador (id_utilizador, id_plano, data_inicio, estado_assinatura) VALUES (?, ?, NOW(), 'pendente_pagamento')");
    $stmt->execute([$id_utilizador, $id_plano]);
    $id_assinatura_criada = $pdo->lastInsertId();

    if (!$id_assinatura_criada) {
        throw new Exception("Não foi possível criar o registro da assinatura pendente.");
    }

    // 2. Preparar dados para a Efí
    $additionalInfoPayload = [
        "Produto" => "Plano AudioTO (ID Plano: " . $id_plano . ")",
        "Cliente" => $nome_pagador,
        "CPF_Cliente" => $cpf_pagador, // O CPF já vai no campo 'devedor', mas pode ser útil aqui também
        "ID_Assinatura_Interna" => (string)$id_assinatura_criada // Enviando o ID da nossa assinatura
    ];

    // 3. Gerar cobrança Pix na Efí
    $efiPix = EfiPix::getInstance();
    $pixResponse = $efiPix->createImmediateCharge(
        (string)$amount,
        (string)$cpf_pagador,
        (string)$nome_pagador,
        $additionalInfoPayload
    );

    if (!isset($pixResponse["txid"]) || !isset($pixResponse["loc"]["id"])) {
         throw new Exception("Resposta da API Efí inválida ao criar cobrança. Verifique os logs da Efí e suas credenciais/certificado.");
    }
    $txid = $pixResponse['txid'];

    // 4. Atualizar assinatura pendente com o txid da Efí
    $stmt = $pdo->prepare("UPDATE assinaturas_utilizador SET id_transacao_gateway = ? WHERE id_assinatura = ?");
    $stmt->execute([$txid, $id_assinatura_criada]);

    // 5. Obter dados do QR Code
    $pixCopiaECola = $pixResponse['pixCopiaECola'] ?? null;
    $qrCodeImageUrl = $efiPix->getPixQrCode($pixResponse); // Passa a resposta completa que contém loc.id

    if (!$pixCopiaECola || !$qrCodeImageUrl) {
         throw new Exception("Não foi possível obter todos os dados do Pix (Copia e Cola ou QR Code Imagem) da resposta da Efí.");
    }

    // Commit da transação no banco de dados
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'txid' => $txid,
        'qrCodeImageUrl' => $qrCodeImageUrl,
        'pixCopiaECola' => $pixCopiaECola,
        'status' => $pixResponse['status'] ?? 'ATIVA', // Status inicial da cobrança Pix
        'id_assinatura' => $id_assinatura_criada // Pode ser útil para o frontend
    ]);

} catch (Exception $e) {
    $pdo->rollBack(); // Desfaz alterações no banco em caso de erro
    error_log("Erro em gerar_pix_efi.php: " . $e->getMessage() . "\nPOST Data: " . print_r($_POST, true) . "\nPIX Response (se houver): " . (isset($pixResponse) ? print_r($pixResponse, true) : 'N/A'));
    echo json_encode(['success' => false, 'message' => 'Erro interno ao processar o Pix: ' . $e->getMessage()]);
}