-- Salão Agenda
-- Cria estrutura de colaboradores administrativos da empresa.

CREATE TABLE IF NOT EXISTS colaboradores (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    cargo VARCHAR(120) DEFAULT NULL,

    pode_agenda TINYINT(1) NOT NULL DEFAULT 1,
    pode_clientes TINYINT(1) NOT NULL DEFAULT 1,
    pode_profissionais TINYINT(1) NOT NULL DEFAULT 1,
    pode_servicos TINYINT(1) NOT NULL DEFAULT 1,
    pode_financeiro TINYINT(1) NOT NULL DEFAULT 0,
    pode_relatorios TINYINT(1) NOT NULL DEFAULT 0,
    pode_configuracoes TINYINT(1) NOT NULL DEFAULT 0,

    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_colaboradores_empresa_pessoa (empresa_id, pessoa_id),
    UNIQUE KEY uq_colaboradores_empresa_usuario (empresa_id, usuario_id),
    KEY idx_colaboradores_empresa_ativo (empresa_id, ativo),
    KEY idx_colaboradores_usuario (usuario_id),
    KEY fk_colaboradores_pessoa_empresa (pessoa_id, empresa_id),

    CONSTRAINT fk_colaboradores_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    CONSTRAINT fk_colaboradores_pessoa_empresa
        FOREIGN KEY (pessoa_id, empresa_id)
        REFERENCES pessoas(id, empresa_id) ON DELETE CASCADE,
    CONSTRAINT fk_colaboradores_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
