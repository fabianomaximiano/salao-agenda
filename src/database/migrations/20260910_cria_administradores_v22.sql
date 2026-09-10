CREATE TABLE administradores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    nome_completo VARCHAR(160) NOT NULL,
    cpf VARCHAR(14) NOT NULL,
    email VARCHAR(190) NOT NULL,
    telefone VARCHAR(30) NULL,
    whatsapp VARCHAR(30) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_administradores_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_administradores_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE RESTRICT,

    UNIQUE KEY uq_administradores_empresa (empresa_id),
    UNIQUE KEY uq_administradores_usuario (usuario_id),
    UNIQUE KEY uq_administradores_cpf (cpf),
    UNIQUE KEY uq_administradores_email (email),
    KEY idx_administradores_ativo (ativo)
) ENGINE=InnoDB;
