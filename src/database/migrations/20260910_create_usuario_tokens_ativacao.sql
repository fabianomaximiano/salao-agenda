-- ============================================================================
-- Migration: cria tabela de tokens de ativação de usuários
-- Data: 2026-09-10
-- ============================================================================

CREATE TABLE usuario_tokens_ativacao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expira_em DATETIME NOT NULL,
    utilizado_em DATETIME NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_usuario_tokens_ativacao_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE,

    UNIQUE KEY uq_usuario_tokens_ativacao_hash (token_hash),
    KEY idx_usuario_tokens_ativacao_usuario (usuario_id),
    KEY idx_usuario_tokens_ativacao_expiracao (expira_em)
) ENGINE=InnoDB;