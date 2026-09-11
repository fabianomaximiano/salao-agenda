CREATE TABLE usuario_codigos_verificacao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NOT NULL,

    codigo_hash VARCHAR(255) NOT NULL,

    tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0,

    expira_em DATETIME NOT NULL,
    utilizado_em DATETIME NULL,
    revogado_em DATETIME NULL,

    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_usuario_codigos_verificacao_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE CASCADE,

    KEY idx_usuario_codigos_usuario_status (
        usuario_id,
        utilizado_em,
        revogado_em,
        expira_em
    ),

    KEY idx_usuario_codigos_criado (
        usuario_id,
        criado_em
    ),

    KEY idx_usuario_codigos_expiracao (
        expira_em
    )
) ENGINE=InnoDB;