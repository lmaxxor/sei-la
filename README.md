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

4. Execute as migrações SQL em `db/migrations` para criar tabelas adicionais, incluindo as da comunidade e dos novos recursos de curtidas e votos:

```bash
mysql -u USER -p DATABASE < db/migrations/002_create_comunidade.sql
mysql -u USER -p DATABASE < db/migrations/003_create_comunidade_likes.sql
mysql -u USER -p DATABASE < db/migrations/004_create_noticia_votos.sql
mysql -u USER -p DATABASE < db/migrations/005_add_password_reset_fields.sql
```

## Comunidade

A funcionalidade de comunidade permite publicar tópicos, comentar e agora curtir publicações. Use `comunidade_publicar.php`, `comunidade_comentar.php`, `comunidade_curtir.php` e `publicacao.php` após aplicar as migrações.

O módulo de notícias suporta votos positivos e negativos através do endpoint `noticia_votar.php`.

## Recuperação de Senha

Caso o utilizador esqueça a palavra‑passe, utilize `esqueci_senha.php` para enviar um link de redefinição. Após receber o e‑mail, o utilizador acessa `resetar_senha.php` com o token recebido para definir uma nova senha.


## Segurança

Algumas páginas administrativas agora utilizam tokens CSRF para proteger requisições POST. Certifique-se de manter a sessão do usuário ativa para que o token seja válido.

