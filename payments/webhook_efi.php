<?php
// payments/webhook_efi.php

// -- Includes Essenciais --
require_once __DIR__ . '/../db/db_connect.php';       // Para $pdo
require_once __DIR__ . '/vendor/autoload.php';      // Autoload do Composer
require_once __DIR__ . '/includes/pix-api.php';     // Para EfiPix::getInstance() e acesso à API
require_once __DIR__ . '/includes/pix-check.php';   // Para EfiPixCheck::getInstance()

// -- Configurações de Log --
// É crucial ter logs detalhados para webhooks
$logFile = __DIR__ . '/webhook_efi.log';
ini_set('log_errors', 1);
ini_set('error_log', $logFile);
error_reporting(E_ALL);

function log_message($message) {
    $timestamp = date("Y-m-d H:i:s");
    error_log("[$timestamp] " . print_r($message, true));
}

log_message("Webhook recebido. Método: " . $_SERVER['REQUEST_METHOD']);

// -- Receber e Validar a Notificação --
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("Método não permitido: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405); // Method Not Allowed
    echo json_encode(["error" => "Método não permitido."]);
    exit;
}

$jsonPayload = file_get_contents('php://input');
log_message("Payload recebido (raw): " . $jsonPayload);

if (empty($jsonPayload)) {
    log_message("Payload vazio.");
    http_response_code(400); // Bad Request
    echo json_encode(["error" => "Payload vazio."]);
    exit;
}

$data = json_decode($jsonPayload, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    log_message("Erro ao decodificar JSON: " . json_last_error_msg());
    http_response_code(400);
    echo json_encode(["error" => "JSON inválido."]);
    exit;
}

log_message("Payload decodificado: " . print_r($data, true));

// Estrutura do payload da Efí para notificações Pix (pode variar, ajuste conforme a documentação da Efí)
// Geralmente, a Efí envia um array 'pix' se houver mais de uma notificação,
// ou diretamente os dados se for um evento específico de uma cobrança.
// Para Pix de cobranças, pode vir dentro de uma estrutura como "notificacoes" ou "pix".
// Vamos assumir que o TXID está acessível.
// Exemplo comum: $data['pix'][0]['txid'] ou $data['txid'] se for um evento de charge.
// Para o SDK da Efí, se você configurou um webhook para uma cobrança específica (ao criá-la),
// a notificação pode ter um formato específico.
// Se for um webhook geral para Pix, você precisa identificar o txid.

// Tentativa de extrair o txid. A Efí pode enviar um array de notificações.
$txid = null;
if (isset($data['pix']) && is_array($data['pix']) && !empty($data['pix'][0]['txid'])) {
    // Comum para notificações de Pix avulsos ou quando o webhook é mais genérico.
    $txid = $data['pix'][0]['txid'];
    $valorNotificacao = $data['pix'][0]['valor'] ?? null; // Pode ser útil para verificar
} elseif (isset($data['txid'])) {
    // Pode ocorrer se a notificação for específica de uma cobrança (charge).
    $txid = $data['txid'];
} elseif (isset($data['notificacoes']) && is_array($data['notificacoes']) && !empty($data['notificacoes'][0]['identificadorPagamento'])) {
    // Outra estrutura possível, onde 'identificadorPagamento' pode ser o e2eid ou txid.
    // Se for e2eid, você precisaria consultar a cobrança de outra forma ou garantir que o txid seja enviado.
    // Vamos priorizar o txid direto.
    // A API da Efí para consultar detalhes da notificação de webhook (`/v2/gn/webhook/:tokenNotificacao`) seria outra forma.
    // Mas o token da notificação não está sendo usado aqui.
    // Vamos focar em verificar a cobrança pelo TXID.
}

if (empty($txid)) {
    log_message("TXID não encontrado no payload da notificação.");
    http_response_code(400);
    echo json_encode(["error" => "TXID não encontrado."]);
    exit;
}

log_message("TXID extraído: " . $txid);

// -- Verificar a Cobrança Diretamente na API da Efí (Validação Essencial) --
try {
    $efiPixCheck = EfiPixCheck::getInstance();
    $statusResponse = $efiPixCheck->checkPixPayment((string)$txid); // Este método já usa a API para buscar detalhes

    if (!$statusResponse || !isset($statusResponse['status'])) {
        log_message("Não foi possível obter o status da cobrança da API Efí para o txid: " . $txid . ". Resposta: " . print_r($statusResponse, true));
        // Não retorne erro para a Efí aqui, pois a notificação pode ser válida mas a consulta falhou temporariamente.
        // A Efí tentará novamente. Se o erro persistir, você verá nos logs.
        // No entanto, para segurança, se você não pode validar, pode ser melhor não processar.
        // Por ora, vamos logar e sair, mas sem erro 4xx para não impedir retentativas da Efí se for falha de comunicação.
        http_response_code(200); // Acknowledge, mas logue o erro interno.
        echo json_encode(["warning" => "Notificação recebida, mas falha ao verificar detalhes na API Efí."]);
        exit;
    }

    log_message("Status da cobrança via API para txid " . $txid . ": " . print_r($statusResponse, true));

    $isPaid = (strtoupper($statusResponse['status']) === 'CONCLUIDA');

    if ($isPaid) {
        log_message("Pagamento CONFIRMADO para txid: " . $txid);
        // -- Lógica de Atualização no Banco de Dados (Idempotente) --
        $pdo->beginTransaction();
        try {
            // Busca a assinatura pelo txid E que ainda esteja pendente
            // O id_utilizador não é necessário aqui pois o txid deve ser único e já ligado à assinatura
            $stmtAssinatura = $pdo->prepare(
                "SELECT a.*, p.preco_mensal, p.preco_anual, u.id_utilizador as id_utilizador_da_assinatura
                 FROM assinaturas_utilizador a
                 JOIN planos_assinatura p ON a.id_plano = p.id_plano
                 JOIN utilizadores u ON a.id_utilizador = u.id_utilizador
                 WHERE a.id_transacao_gateway = ? AND a.estado_assinatura = 'pendente_pagamento'"
            );
            $stmtAssinatura->execute([$txid]);
            $assinatura = $stmtAssinatura->fetch(PDO::FETCH_ASSOC);

            if ($assinatura) {
                log_message("Assinatura pendente encontrada: ID " . $assinatura['id_assinatura'] . " para utilizador ID " . $assinatura['id_utilizador_da_assinatura']);

                $id_plano_pago = $assinatura['id_plano'];
                $id_utilizador_assinante = $assinatura['id_utilizador_da_assinatura'];
                $data_inicio = new DateTime();

                if (!empty($assinatura['preco_anual']) && (float)$assinatura['preco_anual'] > 0) {
                    $intervalo = 'P1Y'; // Plano Anual
                } else {
                    $intervalo = 'P1M'; // Plano Mensal
                }
                $data_proxima_cobranca_obj = clone $data_inicio;
                $data_proxima_cobranca_obj->add(new DateInterval($intervalo));
                $data_fim_obj = clone $data_proxima_cobranca_obj; // Para pagamento único, fim = próxima cobrança

                $stmtUpdateAssinatura = $pdo->prepare(
                    "UPDATE assinaturas_utilizador
                     SET estado_assinatura = 'ativa',
                         data_inicio = ?,
                         data_fim = ?,
                         data_proxima_cobranca = ?
                     WHERE id_assinatura = ?"
                );
                $stmtUpdateAssinatura->execute([
                    $data_inicio->format('Y-m-d H:i:s'),
                    $data_fim_obj->format('Y-m-d H:i:s'),
                    $data_proxima_cobranca_obj->format('Y-m-d H:i:s'),
                    $assinatura['id_assinatura']
                ]);

                $stmtUpdateUtilizador = $pdo->prepare(
                    "UPDATE utilizadores SET id_plano_assinatura_ativo = ? WHERE id_utilizador = ?"
                );
                $stmtUpdateUtilizador->execute([$id_plano_pago, $id_utilizador_assinante]);

                $pdo->commit();
                log_message("Assinatura ID " . $assinatura['id_assinatura'] . " ATIVADA e utilizador ID " . $id_utilizador_assinante . " atualizado.");

            } else {
                log_message("Nenhuma assinatura pendente encontrada para txid: " . $txid . " ou já foi processada.");
                $pdo->rollBack(); // Não há o que commitar se não encontrou
            }
        } catch (Exception $dbException) {
            $pdo->rollBack();
            log_message("ERRO DE BANCO DE DADOS ao processar webhook para txid " . $txid . ": " . $dbException->getMessage());
            // Responder 200 para Efí para não reenviar, mas o erro interno precisa ser tratado.
            // Se o erro for crítico e impedir o processamento, talvez um 500 seja apropriado,
            // mas isso pode causar retentativas da Efí.
            http_response_code(500); // Erro interno do servidor
            echo json_encode(["error" => "Erro interno ao atualizar banco de dados."]);
            exit;
        }
    } else {
        log_message("Status do pagamento para txid " . $txid . " não é 'CONCLUIDA'. Status: " . $statusResponse['status']);
    }

    // Se chegou até aqui, a notificação foi processada (ou era um status não relevante para ação)
    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Webhook processado."]);

} catch (Efi\Exception\EfiException $e) {
    log_message("ERRO API EFÍ (EfiException) ao verificar txid " . $txid . ": Code " . $e->code . " - " . $e->error . " - " . $e->errorDescription);
    http_response_code(500); // Indica um problema do nosso lado ao tentar comunicar com a Efí
    echo json_encode(["error" => "Erro ao comunicar com a API Efí."]);
    exit;
} catch (Exception $e) {
    log_message("ERRO GERAL (Exception) ao processar webhook para txid " . ($txid ?? 'N/A') . ": " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(["error" => "Erro interno geral no servidor."]);
    exit;
}

?>