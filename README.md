# AudioTO

Plataforma em PHP para gestão de podcasts e conteúdos para Terapia Ocupacional.

## Requisitos
- PHP 8
- MySQL

## Configuração

1. Copie o arquivo `db/config.php` e ajuste as variáveis de ambiente `DB_HOST`, `DB_NAME`, `DB_USER` e `DB_PASS` conforme sua base de dados.
2. Para pagamentos, configure as variáveis `EFI_CLIENT_ID`, `EFI_CLIENT_SECRET`, `EFI_PIX_KEY`, `EFI_CERT_FILE` e `EFI_WEBHOOK_URL` utilizadas em `payments/config-efi.php`.
3. Instale dependências do módulo de pagamentos:

```bash
cd payments
composer install
```

## Segurança

Algumas páginas administrativas agora utilizam tokens CSRF para proteger requisições POST. Certifique-se de manter a sessão do usuário ativa para que o token seja válido.

