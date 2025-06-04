<?php
// payments/config-efi.php

// ATENÇÃO: Substitua pelos seus dados reais e coloque o nome do seu certificado.
$efi_pix_client_id = getenv('EFI_CLIENT_ID') ?: '';
$efi_pix_client_secret = getenv('EFI_CLIENT_SECRET') ?: '';
$efi_pix_key = getenv('EFI_PIX_KEY') ?: ''; // Sua chave Pix (CPF, CNPJ, Email, Telefone ou EVP)
$efi_cert_path_name = getenv('EFI_CERT_FILE') ?: 'certificado.p12';
$efi_pix_sandbox = filter_var(getenv('EFI_PIX_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
// ...
$efi_pix_webhook_url = getenv('EFI_WEBHOOK_URL') ?: 'https://example.com/webhook.php';
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