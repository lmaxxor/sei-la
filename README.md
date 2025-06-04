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

4. Execute as migrações SQL em `db/migrations` para criar tabelas adicionais, incluindo as da comunidade:

```bash
mysql -u USER -p DATABASE < db/migrations/002_create_comunidade.sql
```

## Comunidade

A funcionalidade de comunidade permite publicar topicos e comentar nas discussoes. Utilize os scripts `comunidade_publicar.php`, `comunidade_comentar.php` e `publicacao.php` apos aplicar as migracoes.


## Segurança

Algumas páginas administrativas agora utilizam tokens CSRF para proteger requisições POST. Certifique-se de manter a sessão do usuário ativa para que o token seja válido.

