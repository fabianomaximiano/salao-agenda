ALTER TABLE usuario_tokens_ativacao
    ADD COLUMN revogado_em DATETIME NULL AFTER utilizado_em,
    DROP INDEX idx_usuario_tokens_ativacao_usuario,
    ADD KEY idx_usuario_tokens_ativacao_usuario_status
        (usuario_id, utilizado_em, revogado_em, expira_em);
