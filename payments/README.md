# EfiPix API Wrapper  

Este projeto fornece uma implementação em PHP para integração com a API Pix da Efi Bank, facilitando a criação de cobranças e verificação de pagamentos.  

## 📌 Requisitos  
- PHP 8.0 ou superior  
- Composer  
- Certificado da API Pix da Efi Bank (.p12 ou .pem)  
- Conta EfiPay com credenciais válidas  

## Pré-Instalação
```bash
    composer require efipay/sdk-php-apis-efi:^1.12
```


## 🛠 Uso  
- Configure as credenciais no WordPress, ou edite manualmente o arquivo `pix-options.php`.  
### Criando uma cobrança Pix  
```php
$response = EfiPix::getInstance()
    ->createImmediateCharge(
        amount: 150.00,
        cpf: "12345678901",
        nome: "João da Silva"
    );

if ($response === false) {
    die("Erro ao criar cobrança.");
} 

$check = EfiPixCheck::getInstance()->checkPixPayment($response["txid"]);
if ($check) {
    echo "Pagamento confirmado! Detalhes: " . json_encode($check);
} else {
    echo "Erro ao verificar o pagamento.";
}
```
### 🔄 Verificando um pagamento  
Para verificar o status de um pagamento pelo `txid`:  
```php
$txid = $_GET["txid"] ?? null;

if (!$txid) {
    die("TXID não fornecido.");
}

// Busca no banco de dados
$paymentRecord = $db->query("SELECT * FROM pagamentos WHERE txid = ?", [$txid]);

if ($paymentRecord && $paymentRecord["status"] === "confirmado") {
    echo "Pagamento já confirmado no banco de dados!";
} else {
    // Verifica na API da EfiPay
    $check = EfiPixCheck::getInstance()->checkPixPayment($txid);

    if ($check && isset($check["status"]) && $check["status"] === "CONCLUIDO") {
        // Atualiza o banco de dados
        $db->execute("UPDATE pagamentos SET status = 'confirmado', detalhes = ? WHERE txid = ?", [json_encode($check), $txid]);

        echo "Pagamento confirmado pela API! Dados: " . json_encode($check);
    } else {
        echo "Pagamento ainda não confirmado.";
    }
}

```  