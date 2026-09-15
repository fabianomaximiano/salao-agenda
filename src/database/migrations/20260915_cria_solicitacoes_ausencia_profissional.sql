CREATE TABLE profissional_solicitacoes_ausencia (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    profissional_id BIGINT UNSIGNED NOT NULL,
    inicio DATETIME NOT NULL,
    fim DATETIME NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    motivo VARCHAR(160) NULL,
    observacao TEXT NULL,
    status ENUM('pendente','aprovada','rejeitada','cancelada') NOT NULL DEFAULT 'pendente',
    solicitado_por_usuario_id BIGINT UNSIGNED NOT NULL,
    solicitado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    analisado_por_usuario_id BIGINT UNSIGNED NULL,
    analisado_em DATETIME NULL,
    observacao_decisao TEXT NULL,
    bloqueio_id BIGINT UNSIGNED NULL,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ausencia_empresa_status (empresa_id, status),
    KEY idx_ausencia_profissional_periodo (profissional_id, inicio, fim),
    KEY idx_ausencia_bloqueio (bloqueio_id),
    CONSTRAINT fk_ausencia_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    CONSTRAINT fk_ausencia_profissional
        FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE,
    CONSTRAINT fk_ausencia_solicitante
        FOREIGN KEY (solicitado_por_usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ausencia_analisador
        FOREIGN KEY (analisado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_ausencia_bloqueio
        FOREIGN KEY (bloqueio_id) REFERENCES profissional_bloqueios(id) ON DELETE SET NULL,
    CONSTRAINT chk_ausencia_periodo CHECK (fim > inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profissional_solicitacao_ausencia_historico (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    solicitacao_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    acao VARCHAR(40) NOT NULL,
    detalhes TEXT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ausencia_hist_solicitacao (solicitacao_id, criado_em),
    CONSTRAINT fk_ausencia_hist_solicitacao
        FOREIGN KEY (solicitacao_id) REFERENCES profissional_solicitacoes_ausencia(id) ON DELETE CASCADE,
    CONSTRAINT fk_ausencia_hist_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profissional_solicitacao_ausencia_comentarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    solicitacao_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    comentario TEXT NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ausencia_coment_solicitacao (solicitacao_id, criado_em),
    CONSTRAINT fk_ausencia_coment_solicitacao
        FOREIGN KEY (solicitacao_id) REFERENCES profissional_solicitacoes_ausencia(id) ON DELETE CASCADE,
    CONSTRAINT fk_ausencia_coment_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
