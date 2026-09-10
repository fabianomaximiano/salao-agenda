ALTER TABLE pessoas
    ADD UNIQUE KEY uq_pessoas_id_empresa (id, empresa_id);

ALTER TABLE clientes
    ADD COLUMN usuario_id BIGINT UNSIGNED NULL AFTER pessoa_id,
    DROP FOREIGN KEY fk_clientes_pessoa,
    ADD CONSTRAINT fk_clientes_pessoa_empresa
        FOREIGN KEY (pessoa_id, empresa_id)
        REFERENCES pessoas(id, empresa_id)
        ON DELETE CASCADE,
    ADD CONSTRAINT fk_clientes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL,
    ADD UNIQUE KEY uq_clientes_empresa_usuario (empresa_id, usuario_id),
    ADD KEY idx_clientes_usuario (usuario_id);

ALTER TABLE profissionais
    ADD COLUMN usuario_id BIGINT UNSIGNED NULL AFTER pessoa_id,
    DROP FOREIGN KEY fk_profissionais_pessoa,
    ADD CONSTRAINT fk_profissionais_pessoa_empresa
        FOREIGN KEY (pessoa_id, empresa_id)
        REFERENCES pessoas(id, empresa_id)
        ON DELETE CASCADE,
    ADD CONSTRAINT fk_profissionais_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL,
    ADD UNIQUE KEY uq_profissionais_empresa_usuario (empresa_id, usuario_id),
    ADD KEY idx_profissionais_usuario (usuario_id);
