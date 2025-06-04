<?php
use Efi\Exception\EfiException;
use Efi\EfiPay;

/**
 * Classe EfiPix (Singleton) para integração com a API Pix da EfiPay.
 * 
 * Esta classe permite criar cobranças Pix imediatas, gerar QR Codes de pagamento e verificar o status de pagamentos.
 * 
 * @package EfiPix
 */
class EfiPix {
    /**
     * Instância única da classe (Singleton).
     *
     * @var EfiPix|null
     */
    private static ?EfiPix $instance = null;

    /**
     * Instância da API EfiPay.
     *
     * @var EfiPay
     */
    private EfiPay $api;

    /**
     * Configurações da API.
     *
     * @var array
     */
    private array $options;

    /**
     * Construtor privado para impedir múltiplas instâncias.
     * Carrega as configurações e inicializa a API EfiPay.
     */
    private function __construct() {
// efi_integration/EFI_PHPix-main/includes/pix-api.php
// Ajuste o caminho conforme a sua estrutura final
// payments/includes/pix-api.php
$this->options = require_once __DIR__ . "/../config-efi.php"; // Sobe um nível de 'includes/' para 'payments/' e pega 'config-efi.php'
$this->api = new EfiPay($this->options["efiSettings"]);
    }

    /**
     * Retorna a instância única da classe EfiPix.
     *
     * @return EfiPix
     */
    public static function getInstance(): EfiPix {
        if (self::$instance === null) {
            self::$instance = new EfiPix();
        }
        return self::$instance;
    }

    /**
     * Cria uma cobrança Pix imediata utilizando a API EfiPay.
     *
     * @param string $amount        Valor da cobrança em reais (ex: "150.00").
     * @param string $cpf           CPF do pagador (ex: "12345678901").
     * @param string $nome          Nome do pagador (ex: "João da Silva").
     * @param array  $additionalInfo Informações adicionais da cobrança (ex: ["Pedido" => "Pedido #1234"]).
     * 
     * @return array                Retorna os detalhes da cobrança Pix, incluindo o TXID e o QR Code.
     * 
     * @throws Exception            Lança exceção em caso de erro na requisição ou resposta inválida.
     */
    public function createImmediateCharge(
        string $amount,
        string $cpf,
        string $nome,
        array $additionalInfo = []
    ): array {
        $amount = number_format((float)$amount, 2, '.', '');
        
        $body = [
            "calendario" => ["expiracao" => 3600],
            "devedor" => ["cpf" => $cpf, "nome" => $nome],
            "valor" => ["original" => $amount],
            "chave" => $this->options["pixSettings"]["chave"],
            "solicitacaoPagador" => "Número do pedido ou identificador.",
            "infoAdicionais" => array_map(
                fn($name, $value) => ["nome" => $name, "valor" => $value],
                array_keys($additionalInfo),
                $additionalInfo
            )
        ];

        try {
            $responsePix = $this->api->pixCreateImmediateCharge([], $body);
            $responseBodyPix = $this->options["efiSettings"]["responseHeaders"] ?? false ? $responsePix->body : $responsePix;

            if (!isset($responseBodyPix["txid"])) {
                throw new Exception("Erro: A resposta da API não contém um TXID válido.");
            }

            return $responseBodyPix;
        } catch (EfiException $e) {
            throw new Exception("Erro na cobrança: {$e->code} - {$e->error}. Descrição: {$e->errorDescription}");
        } catch (Exception $e) {
            throw new Exception("Erro inesperado: " . $e->getMessage());
        }
    }

    /**
     * Gera o QR Code de pagamento a partir da resposta da API Pix.
     *
     * @param array $pixResponse Resposta da API Pix ao criar a cobrança.
     * 
     * @return string|false URL da imagem do QR Code ou false em caso de erro.
     */
    public function getPixQrCode(array $pixResponse): string|false {
        if (!$pixResponse || !isset($pixResponse["loc"]["id"])) {
            return false;
        }

        $params = ["id" => $pixResponse["loc"]["id"]];

        try {
            $responseQrcode = $this->api->pixGenerateQRCode($params);
            $responseBodyQrcode = $this->options["efiSettings"]["responseHeaders"] ?? false ? $responseQrcode->body : $responseQrcode; // LINHA CORRIGIDA

            return $responseBodyQrcode["imagemQrcode"] ?? false;
        } catch (EfiException $e) {
            error_log("Erro ao gerar QR Code: {$e->code} - {$e->error}. Descrição: {$e->errorDescription}");
            return false;
        } catch (Exception $e) {
            error_log("Erro inesperado ao gerar QR Code: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cria uma cobrança Pix e retorna os detalhes da cobrança e o QR Code.
     *
     * @param string $amount        Valor da cobrança em reais.
     * @param string $cpf           CPF do pagador.
     * @param string $nome          Nome do pagador.
     * @param array  $additionalInfo Informações adicionais.
     * 
     * @return array|false           Dados da cobrança e QR Code ou false em caso de erro.
     */
    public function createImmediateChargeAndGetQrCode(string $amount, string $cpf, string $nome, array $additionalInfo = []): array|false {
        $pixResponse = @$this->createImmediateCharge($amount, $cpf, $nome, $additionalInfo);
        if (!$pixResponse) return false;

        $qrCodeUrl = @$this->getPixQrCode(["id" => $pixResponse["txid"]]);
        if (!$qrCodeUrl) return false;

        return [
            "pixResponse" => $pixResponse,
            "qrCodeUrl"   => $qrCodeUrl
        ];
    }


    public function getApi(): EfiPay {
        return $this->api;
    }
}
