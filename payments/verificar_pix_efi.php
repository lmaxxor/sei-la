<?php
// payments/verificar_pix_efi.php
require_once __DIR__ . '/../sessao/session_handler.php';
require_once __DIR__ . '/../db/db_connect.php';

requireLogin();

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/pix-check.php'; // Nossa classe EfiPixCheck adaptada

header('Content-Type: application/json');

$txid = $_GET['txid'] ?? null;
$id_utilizador = $_SESSION['user_id'] ?? null;

if (empty($txid)) {
    echo json_encode(['success' => false, 'message' => 'TXID não fornecido.']);
    exit;
}
if (empty($id_utilizador)) {
    echo json_encode(['success' => false, 'message' => 'Sessão de usuário inválida.']);
    exit;
}

try {
    $efiPixCheck = EfiPixCheck::getInstance();
    $statusResponse = $efiPixCheck->checkPixPayment((string)$txid);

    if ($statusResponse && isset($statusResponse['status'])) {
        $isPaid = (strtoupper($statusResponse['status']) === 'CONCLUIDA'); // Status de pagamento concluído da Efí

        if ($isPaid) {
            $pdo->beginTransaction();
            try {
                // Buscar a assinatura e o plano associado pelo txid
                $stmtAssinatura = $pdo->prepare("SELECT a.*, p.preco_mensal, p.preco_anual FROM assinaturas_utilizador a JOIN planos_assinatura p ON a.id_plano = p.id_plano WHERE a.id_transacao_gateway = ? AND a.id_utilizador = ? AND a.estado_assinatura = 'pendente_pagamento'");
                $stmtAssinatura->execute([$txid, $id_utilizador]);
                $assinatura = $stmtAssinatura->fetch(PDO::FETCH_ASSOC);

                if ($assinatura) {
                    $id_plano_pago = $assinatura['id_plano'];
                    $data_inicio = new DateTime(); // Data atual como início da assinatura

                    // Calcular data_fim e data_proxima_cobranca
                    // Se preco_anual não for nulo e > 0, é anual (plano ID 3 no seu DB)
                    if (!empty($assinatura['preco_anual']) && (float)$assinatura['preco_anual'] > 0) {
                        $intervalo = 'P1Y'; // 1 ano
                    } else {
                        $intervalo = 'P1M'; // 1 mês (para planos ID 1 e 2)
                    }
                    $data_proxima_cobranca_obj = clone $data_inicio;
                    $data_proxima_cobranca_obj->add(new DateInterval($intervalo));
                    
                    // data_fim pode ser igual a data_proxima_cobranca se for pagamento único por período
                    $data_fim_obj = clone $data_proxima_cobranca_obj;

                    // Atualizar a assinatura_utilizador
                    $stmtUpdateAssinatura = $pdo->prepare("UPDATE assinaturas_utilizador SET estado_assinatura = 'ativa', data_inicio = ?, data_fim = ?, data_proxima_cobranca = ? WHERE id_assinatura = ?");
                    $stmtUpdateAssinatura->execute([
                        $data_inicio->format('Y-m-d H:i:s'),
                        $data_fim_obj->format('Y-m-d H:i:s'),
                        $data_proxima_cobranca_obj->format('Y-m-d H:i:s'),
                        $assinatura['id_assinatura']
                    ]);

                    // Atualizar o plano ativo do utilizador na tabela utilizadores
                    $stmtUpdateUtilizador = $pdo->prepare("UPDATE utilizadores SET id_plano_assinatura_ativo = ? WHERE id_utilizador = ?");
                    $stmtUpdateUtilizador->execute([$id_plano_pago, $id_utilizador]);
                    
                    $_SESSION['user_plano_id'] = $id_plano_pago; // Atualiza ID do plano na sessão

                    $pdo->commit();
                } else {
                    // Assinatura não encontrada ou já processada. Pode ser uma verificação tardia.
                    // Não fazer nada ou apenas logar.
                    $pdo->rollBack(); // Se não encontrou, não há o que commitar sobre a assinatura
                    error_log("verificar_pix_efi.php: Assinatura pendente não encontrada para txid {$txid} e utilizador {$id_utilizador}, ou já foi processada.");
                }
            } catch (Exception $dbException) {
                $pdo->rollBack();
                error_log("Erro de DB em verificar_pix_efi.php: " . $dbException->getMessage());
                // Não propaga o erro de DB para o usuário, pois o pagamento na Efí foi bem-sucedido.
                // A notificação de erro já é suficiente para o admin investigar.
            }
        }

        echo json_encode([
            'success' => true,
            'status' => $statusResponse['status'],
            'isPaid' => $isPaid,
            'details' => $statusResponse
        ]);
    } else {
        $errorMessage = 'Não foi possível verificar o status do pagamento ou resposta inválida da Efí.';
        if (isset($statusResponse['nome'])) { // Erro estruturado da Efí
            $errorMessage = "Erro Efí: " . $statusResponse['nome'] . " - " . ($statusResponse['mensagem'] ?? 'Sem detalhes adicionais.');
        }
        error_log("Erro em verificar_pix_efi.php (Efí): " . print_r($statusResponse, true));
        echo json_encode(['success' => false, 'message' => $errorMessage]);
    }
} catch (Exception $e) {
    error_log("Exceção em verificar_pix_efi.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro crítico ao verificar Pix: ' . $e->getMessage()]);
}
