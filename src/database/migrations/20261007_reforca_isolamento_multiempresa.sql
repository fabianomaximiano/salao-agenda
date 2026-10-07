-- ============================================================================
-- Reforco de isolamento multiempresa em relacionamentos
-- Data: 2026-10-07
--
-- Garante no banco que registros relacionados pertencam a mesma empresa.
--
-- A relacao servicos -> categorias_servicos permanece inalterada porque utiliza
-- ON DELETE SET NULL em categoria_id. Incluir empresa_id nessa FK faria o
-- SET NULL atingir uma coluna NOT NULL, alterando a semantica atual.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Chaves compostas nas tabelas pai
-- Segue o mesmo padrao utilizado por pessoas: (id, empresa_id).
-- ----------------------------------------------------------------------------

ALTER TABLE clientes
    ADD UNIQUE KEY uq_clientes_id_empresa (id, empresa_id);

ALTER TABLE colaboradores
    ADD UNIQUE KEY uq_colaboradores_id_empresa (id, empresa_id);

ALTER TABLE profissionais
    ADD UNIQUE KEY uq_profissionais_id_empresa (id, empresa_id);


-- ----------------------------------------------------------------------------
-- agendamentos -> clientes
-- Preserva ON DELETE RESTRICT.
-- ----------------------------------------------------------------------------

ALTER TABLE agendamentos
    DROP FOREIGN KEY fk_agendamentos_cliente,
    ADD CONSTRAINT fk_agendamentos_cliente_empresa
        FOREIGN KEY (cliente_id, empresa_id)
        REFERENCES clientes(id, empresa_id)
        ON DELETE RESTRICT;


-- ----------------------------------------------------------------------------
-- pets -> clientes
-- Preserva ON DELETE CASCADE.
-- ----------------------------------------------------------------------------

ALTER TABLE pets
    DROP FOREIGN KEY fk_pets_cliente,
    ADD CONSTRAINT fk_pets_cliente_empresa
        FOREIGN KEY (cliente_id, empresa_id)
        REFERENCES clientes(id, empresa_id)
        ON DELETE CASCADE;


-- ----------------------------------------------------------------------------
-- colaborador_solicitacoes_ausencia -> colaboradores
-- Preserva ON DELETE CASCADE.
-- ----------------------------------------------------------------------------

ALTER TABLE colaborador_solicitacoes_ausencia
    DROP FOREIGN KEY fk_colab_ausencia_colaborador,
    ADD CONSTRAINT fk_colab_ausencia_colaborador_empresa
        FOREIGN KEY (colaborador_id, empresa_id)
        REFERENCES colaboradores(id, empresa_id)
        ON DELETE CASCADE;


-- ----------------------------------------------------------------------------
-- profissional_solicitacoes_ausencia -> profissionais
-- Preserva ON DELETE CASCADE.
-- ----------------------------------------------------------------------------

ALTER TABLE profissional_solicitacoes_ausencia
    DROP FOREIGN KEY fk_ausencia_profissional,
    ADD CONSTRAINT fk_ausencia_profissional_empresa
        FOREIGN KEY (profissional_id, empresa_id)
        REFERENCES profissionais(id, empresa_id)
        ON DELETE CASCADE;