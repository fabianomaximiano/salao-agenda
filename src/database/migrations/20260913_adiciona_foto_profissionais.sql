-- Salão Agenda
-- Foto operacional do profissional.
-- A coluna armazena a URL da versão grande (-g.webp); as versões -p e -m
-- são derivadas pelo mesmo nome-base.

ALTER TABLE profissionais
    ADD COLUMN foto_url VARCHAR(500) NULL AFTER descricao;
