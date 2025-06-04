<?php
// payments/config-efi.php

// ATENÇÃO: Substitua pelos seus dados reais e coloque o nome do seu certificado.
$efi_pix_client_id = "Client_Id_0147ac1a253abfb6c3fe9c1ba536ba4a73339e25";
$efi_pix_client_secret = "Client_Secret_e5afab09140b72e791cd0f5b9c5826965139f808";
$efi_pix_key = "d1ea4613-8ef1-40a8-a74e-7e83c6476891"; // Sua chave Pix (CPF, CNPJ, Email, Telefone ou EVP)
$efi_cert_path_name = "producao-448246-AudioTO.p12"; // APENAS o nome do arquivo do certificado .p12
$efi_pix_sandbox = false; // true para ambiente de homologação (testes), false para produção
// ...
$efi_pix_webhook_url = "https://audioto.com.br/payments/webhook_efi.php"; // Exemplo
// ...
// Caminho para a pasta de certificados DENTRO da pasta 'payments'
$certsPath = __DIR__ . "/certs/" . $efi_cert_path_name;

return [
    "efiSettings" => [
        "clientId"        => $efi_pix_client_id,
        "clientSecret"    => $efi_pix_client_secret,
        "certificate"     => $certsPath,
        "pwdCertificate"  => "",
        "sandbox"         => filter_var($efi_pix_sandbox, FILTER_VALIDATE_BOOLEAN),
        "debug"           => false, // Mude para true para logs detalhados em desenvolvimento
        "timeout"         => 30,
        "responseHeaders" => true,
        "webhookUrl"      => $efi_pix_webhook_url,
    ],
    "pixSettings" => [
        "chave" => $efi_pix_key,
    ]
];