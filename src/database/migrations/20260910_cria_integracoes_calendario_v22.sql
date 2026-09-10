CREATE TABLE profissional_integracoes_calendario (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profissional_id BIGINT UNSIGNED NOT NULL,
    provedor VARCHAR(40) NOT NULL,
    conta_externa_id VARCHAR(255) NULL,
    conta_email VARCHAR(190) NULL,
    calendario_externo_id VARCHAR(255) NULL,
    access_token_criptografado MEDIUMTEXT NULL,
    refresh_token_criptografado MEDIUMTEXT NULL,
    token_expira_em DATETIME NULL,
    escopos TEXT NULL,
    sincronizacao_ativa TINYINT(1) NOT NULL DEFAULT 1,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_sync_em DATETIME NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_prof_integracoes_calendario_profissional
        FOREIGN KEY (profissional_id) REFERENCES profissionais(id)
        ON DELETE CASCADE,

    UNIQUE KEY uq_prof_integracoes_calendario
        (profissional_id, provedor),
    KEY idx_prof_integracoes_calendario_ativo
        (profissional_id, ativo, sincronizacao_ativa)
) ENGINE=InnoDB;


CREATE TABLE agendamento_eventos_externos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agendamento_servico_id BIGINT UNSIGNED NOT NULL,
    integracao_calendario_id BIGINT UNSIGNED NOT NULL,
    evento_externo_id VARCHAR(255) NOT NULL,
    status_sync ENUM(
        'pendente',
        'sincronizado',
        'erro',
        'removido'
    ) NOT NULL DEFAULT 'pendente',
    ultimo_erro VARCHAR(500) NULL,
    sincronizado_em DATETIME NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_ag_eventos_externos_servico
        FOREIGN KEY (agendamento_servico_id)
        REFERENCES agendamento_servicos(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_ag_eventos_externos_integracao
        FOREIGN KEY (integracao_calendario_id)
        REFERENCES profissional_integracoes_calendario(id)
        ON DELETE CASCADE,

    UNIQUE KEY uq_ag_evento_externo_integracao
        (integracao_calendario_id, evento_externo_id),
    UNIQUE KEY uq_ag_servico_integracao
        (agendamento_servico_id, integracao_calendario_id),
    KEY idx_ag_eventos_externos_status
        (status_sync, sincronizado_em)
) ENGINE=InnoDB;
