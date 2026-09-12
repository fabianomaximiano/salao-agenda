CREATE TABLE IF NOT EXISTS usuario_tentativas_alteracao_senha (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_ate DATETIME DEFAULT NULL,
    ultimo_erro_em DATETIME DEFAULT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_usuario_tentativas_alteracao_usuario_ip (usuario_id, ip_hash),
    KEY idx_usuario_tentativas_alteracao_bloqueio (bloqueado_ate),

    CONSTRAINT fk_usuario_tentativas_alteracao_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;
