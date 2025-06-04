<?php
// efi_integration/config-efi.php

// ATENÇÃO: Substitua pelos seus dados reais e coloque o nome do seu certificado.
// É altamente recomendável usar variáveis de ambiente para dados sensíveis em produção.
$efi_pix_client_id = "SEU_CLIENT_ID_AQUI";
$efi_pix_client_secret = "SEU_CLIENT_SECRET_AQUI";
$efi_pix_key = "SUA_CHAVE_PIX_AQUI"; // Sua chave Pix (CPF, CNPJ, Email, Telefone ou EVP)
$efi_cert_path_name = "seu_certificado.p12"; // APENAS o nome do arquivo do certificado .p12
$efi_pix_sandbox = true; // true para ambiente de homologação (testes), false para produção
$efi_pix_webhook_url = "URL_DO_SEU_WEBHOOK_AQUI"; // Opcional para este guia, mas recomendado para produção

// Caminho absoluto para a pasta de certificados
$certsPath = __DIR__ . "/EFI_PHPix-main/certs/" . $efi_cert_path_name;

return [
    "efiSettings" => [
        "clientId"        => $efi_pix_client_id,
        "clientSecret"    => $efi_pix_client_secret,
        "certificate"     => $certsPath,
        "pwdCertificate"  => "", // Senha do certificado, se houver. O SDK geralmente não usa isso diretamente para .p12.
                                 // O SDK espera o caminho do arquivo .p12. Se o .p12 tem senha, pode ser necessário convertê-lo
                                 // para .pem (chave + certificado) sem senha ou ajustar o SDK/classe EfiPay se ela suportar .p12 com senha.
                                 // A documentação oficial da Efí para o SDK PHP deve esclarecer o manuseio de .p12 com senha.
                                 // Por padrão, assume-se que o .p12 não requer senha ou já está configurado no servidor/ambiente.
        "sandbox"         => filter_var($efi_pix_sandbox, FILTER_VALIDATE_BOOLEAN),
        "debug"           => false, // Mude para true para logs detalhados em desenvolvimento
        "timeout"         => 30,
        "responseHeaders" => true, // Para obter cabeçalhos na resposta da API
        "webhookUrl"      => $efi_pix_webhook_url, // URL para notificações de webhook
    ],
    "pixSettings" => [
        "chave" => $efi_pix_key, // Chave Pix registrada na Efí
    ]
];