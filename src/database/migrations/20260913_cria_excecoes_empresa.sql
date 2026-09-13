-- Salão Agenda
-- Exceções de funcionamento da empresa para datas específicas.
-- Uma data pode estar fechada ou possuir um ou mais períodos especiais.

CREATE TABLE IF NOT EXISTS empresa_excecoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    data_excecao DATE NOT NULL,
    tipo ENUM('fechado', 'horario_especial') NOT NULL,
    descricao VARCHAR(255) DEFAULT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_por_usuario_id BIGINT UNSIGNED DEFAULT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_empresa_excecoes_empresa_data (empresa_id, data_excecao),
    KEY idx_empresa_excecoes_consulta (empresa_id, data_excecao, ativo),
    KEY fk_empresa_excecoes_usuario (criado_por_usuario_id),

    CONSTRAINT fk_empresa_excecoes_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    CONSTRAINT fk_empresa_excecoes_usuario
        FOREIGN KEY (criado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS empresa_excecao_periodos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_excecao_id BIGINT UNSIGNED NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,

    PRIMARY KEY (id),
    KEY idx_empresa_excecao_periodos_excecao (empresa_excecao_id, hora_inicio),
    CONSTRAINT fk_empresa_excecao_periodos_excecao
        FOREIGN KEY (empresa_excecao_id)
        REFERENCES empresa_excecoes(id) ON DELETE CASCADE,
    CONSTRAINT chk_empresa_excecao_periodos_intervalo
        CHECK (hora_fim > hora_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
