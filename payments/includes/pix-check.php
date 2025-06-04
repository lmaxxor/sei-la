<?php
// payments/includes/pix-check.php

// Esta linha é crucial e deve carregar o arquivo que define a classe EfiPix.
// Certifique-se que payments/includes/pix-api.php existe e não tem erros fatais.
require_once __DIR__ . "/pix-api.php";

use Efi\Exception\EfiException; // Para exceções do SDK da Efí (isto está correto)
// use EfiPix; // << REMOVA OU COMENTE ESTA LINHA

/**
 * Classe Singleton para verificação de pagamentos via API Pix da Efí.
 */
class EfiPixCheck {
    private static ?EfiPixCheck $instance = null;
    private EfiPix $pixApiInstance; // Type hint para a classe EfiPix (global)

    private function __construct() {
        $this->pixApiInstance = EfiPix::getInstance(); // Usa a classe EfiPix global
    }

    public static function getInstance(): EfiPixCheck {
        if (self::$instance === null) {
            self::$instance = new EfiPixCheck();
        }
        return self::$instance;
    }

    /**
     * Verifica o status de um pagamento Pix com base no TXID.
     *
     * @param string $txid Identificador único da transação Pix.
     * @return array|false Retorna os detalhes da transação (array) se bem-sucedida e encontrada, ou false em caso de falha/erro.
     */
    public function checkPixPayment(string $txid): array|false {
        try {
            $params = ['txid' => $txid];
            $sdkEfiPay = $this->pixApiInstance->getApi(); // Obtém a instância do SDK Efi\EfiPay
            $sdkResponseObject = $sdkEfiPay->pixDetailCharge($params); // Chama o método correto do SDK

            $responseBody = $sdkResponseObject->body ?? null; // Pega o corpo da resposta

            if (is_array($responseBody)) {
                return $responseBody;
            } else {
                error_log("EfiPixCheck::checkPixPayment - Resposta da API Efí para txid {$txid} não continha um corpo de dados esperado (array). Resposta: " . print_r($sdkResponseObject, true));
                return false;
            }
        } catch (EfiException $e) {
            error_log("EfiException em EfiPixCheck::checkPixPayment para TXID {$txid}: Code {$e->code} - {$e->error} - {$e->errorDescription}");
            return false;
        } catch (Exception $e) {
            error_log("Exception geral em EfiPixCheck::checkPixPayment para TXID {$txid}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Método estático de conveniência para verificar um pagamento.
     * @param string $txid
     * @return array|false
     */
    public static function check(string $txid): array|false {
        return self::getInstance()->checkPixPayment($txid);
    }
}