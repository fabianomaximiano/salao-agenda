CREATE TABLE clientes_identidades (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    cpf VARCHAR(14) NOT NULL,
    email VARCHAR(190) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_clientes_identidades_usuario (usuario_id),
    UNIQUE KEY uq_clientes_identidades_cpf (cpf),
    UNIQUE KEY uq_clientes_identidades_email (email),
    CONSTRAINT fk_clientes_identidades_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE clientes
    ADD COLUMN cliente_identidade_id BIGINT UNSIGNED NULL AFTER empresa_id,
    ADD KEY idx_clientes_identidade (cliente_identidade_id),
    ADD UNIQUE KEY uq_clientes_empresa_identidade (empresa_id, cliente_identidade_id),
    ADD CONSTRAINT fk_clientes_identidade FOREIGN KEY (cliente_identidade_id) REFERENCES clientes_identidades(id) ON DELETE RESTRICT;
