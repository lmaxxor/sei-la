-- Adiciona campos para recuperação de senha na tabela utilizadores
ALTER TABLE utilizadores
    ADD COLUMN IF NOT EXISTS token_reset_passe VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS data_expiracao_token_reset DATETIME DEFAULT NULL;
