-- ============================================================================
-- Plataforma de Agendamento e Gestão de Atendimentos
-- schema.sql - Versão 2.2
-- Modelo lógico v2.2 - MySQL 8.0
--
-- IMPORTANTE:
-- Este arquivo representa a estrutura-alvo do banco.
-- NÃO execute diretamente sobre o banco atual sem uma migração planejada,
-- pois hoje já existem tabelas usuarios, servicos, funcionario_servicos
-- e agendamentos com estrutura diferente.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS salao_agenda
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE salao_agenda;

-- ============================================================================
-- 1. EMPRESAS / ESTABELECIMENTOS
-- ============================================================================

CREATE TABLE empresas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome_fantasia VARCHAR(150) NOT NULL,
    razao_social VARCHAR(180) NULL,
    tipo_documento ENUM('cpf', 'cnpj', 'outro') NULL,
    documento VARCHAR(20) NULL,
    slug VARCHAR(160) NOT NULL,
    email VARCHAR(190) NULL,
    telefone VARCHAR(30) NULL,
    whatsapp VARCHAR(30) NULL,
    cep VARCHAR(10) NULL,
    logradouro VARCHAR(180) NULL,
    numero VARCHAR(30) NULL,
    complemento VARCHAR(120) NULL,
    bairro VARCHAR(120) NULL,
    cidade VARCHAR(120) NULL,
    estado CHAR(2) NULL,
    timezone VARCHAR(60) NOT NULL DEFAULT 'America/Sao_Paulo',
    logo_url VARCHAR(500) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_empresas_slug (slug),
    UNIQUE KEY uq_empresas_documento (documento),
    KEY idx_empresas_ativo (ativo)
) ENGINE=InnoDB;


CREATE TABLE segmentos (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,

    UNIQUE KEY uq_segmentos_nome (nome),
    UNIQUE KEY uq_segmentos_slug (slug)
) ENGINE=InnoDB;


CREATE TABLE empresa_segmentos (
    empresa_id BIGINT UNSIGNED NOT NULL,
    segmento_id SMALLINT UNSIGNED NOT NULL,

    PRIMARY KEY (empresa_id, segmento_id),

    CONSTRAINT fk_empresa_segmentos_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_empresa_segmentos_segmento
        FOREIGN KEY (segmento_id) REFERENCES segmentos(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;


INSERT INTO segmentos (nome, slug) VALUES
('Unhas', 'unhas'),
('Cabelos', 'cabelos'),
('Barbearia', 'barbearia'),
('Depilação', 'depilacao'),
('Maquiagem', 'maquiagem'),
('Sobrancelhas', 'sobrancelhas'),
('Massagem e Estética', 'massagem-estetica'),
('Podologia', 'podologia'),
('Pet Shop', 'pet-shop'),
('Outros', 'outros');


-- ============================================================================
-- 2. USUÁRIOS / AUTENTICAÇÃO
-- ============================================================================
--
-- usuarios representa exclusivamente a IDENTIDADE DE ACESSO.
-- Ele não define se a pessoa é administrador, profissional ou cliente.
-- Dados pessoais vinculados a um estabelecimento ficam em pessoas.
-- Os contextos funcionais são modelados em tabelas próprias.
-- Login com Google e integração com Google Calendar são responsabilidades
-- distintas e não compartilham credenciais.
-- ============================================================================

CREATE TABLE usuarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    senha_hash VARCHAR(255) NULL,
    google_id VARCHAR(255) NULL,
    foto_url VARCHAR(500) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_acesso_em DATETIME NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_usuarios_email (email),
    UNIQUE KEY uq_usuarios_google_id (google_id),
    KEY idx_usuarios_ativo (ativo)
) ENGINE=InnoDB;


-- ----------------------------------------------------------------------------
-- 2.1 TOKENS DE ATIVAÇÃO DE USUÁRIO
-- ----------------------------------------------------------------------------
--
-- Utilizada no onboarding de administradores e demais convites que exijam
-- definição inicial de senha. O token em texto puro nunca deve ser persistido:
-- a aplicação armazena somente SHA-256 (64 caracteres hexadecimais).
--
-- Fluxo esperado quando o onboarding cria uma NOVA identidade para o
-- administrador:
--   usuarios.senha_hash = NULL
--   usuarios.ativo      = 0
--   token válido        -> definição de senha -> usuarios.ativo = 1
--
-- Se uma identidade já existir, sua ativação global não deve ser alterada
-- apenas por causa de um novo vínculo funcional.
--
-- Ao reenviar um convite, tokens anteriores ainda não utilizados devem ser
-- revogados pela aplicação antes da criação do novo token.
-- ----------------------------------------------------------------------------

CREATE TABLE usuario_tokens_ativacao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expira_em DATETIME NOT NULL,
    utilizado_em DATETIME NULL,
    revogado_em DATETIME NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_usuario_tokens_ativacao_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE,

    UNIQUE KEY uq_usuario_tokens_ativacao_hash (token_hash),
    KEY idx_usuario_tokens_ativacao_usuario_status
        (usuario_id, utilizado_em, revogado_em, expira_em),
    KEY idx_usuario_tokens_ativacao_expiracao (expira_em)
) ENGINE=InnoDB;


-- ============================================================================
-- 3. PESSOAS
-- ============================================================================
--
-- pessoa é o cadastro pessoal operacional dentro de uma empresa.
-- Não representa o administrador.
-- Isso permite:
-- - cliente sem login;
-- - profissional sem login;
-- - identidade autenticada vinculada opcionalmente a cliente/profissional;
-- - isolamento dos dados entre empresas.
-- ============================================================================

CREATE TABLE pessoas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id BIGINT UNSIGNED NOT NULL,
    nome_completo VARCHAR(160) NOT NULL,
    cpf VARCHAR(14) NULL,
    data_nascimento DATE NULL,
    genero ENUM(
        'masculino',
        'feminino',
        'nao_binario',
        'nao_informado'
    ) NOT NULL DEFAULT 'nao_informado',
    email VARCHAR(190) NULL,
    observacoes TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_pessoas_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,

    UNIQUE KEY uq_pessoas_empresa_cpf (empresa_id, cpf),
    UNIQUE KEY uq_pessoas_id_empresa (id, empresa_id),
    KEY idx_pessoas_empresa_nome (empresa_id, nome_completo),
    KEY idx_pessoas_empresa_email (empresa_id, email),
    KEY idx_pessoas_ativo (empresa_id, ativo)
) ENGINE=InnoDB;


CREATE TABLE telefones_pessoa (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    numero VARCHAR(30) NOT NULL,
    tipo ENUM('celular', 'residencial', 'comercial', 'outro')
        NOT NULL DEFAULT 'celular',
    whatsapp TINYINT(1) NOT NULL DEFAULT 0,
    principal TINYINT(1) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_telefones_pessoa
        FOREIGN KEY (pessoa_id) REFERENCES pessoas(id)
        ON DELETE CASCADE,

    KEY idx_telefones_pessoa (pessoa_id),
    KEY idx_telefones_numero (numero)
) ENGINE=InnoDB;


-- ============================================================================
-- 4. ADMINISTRADORES
-- ============================================================================
--
-- Administrador é um contexto funcional próprio.
-- Não é cliente e não é profissional por consequência automática.
--
-- Nesta versão, cada empresa possui um administrador principal e uma identidade
-- de administrador principal pode administrar somente uma empresa.
--
-- Os dados pessoais do administrador ficam nesta própria entidade, evitando
-- reutilizar pessoas para um contexto administrativo.
--
-- Caso futuramente existam gerente, recepcionista, administrador secundário ou
-- outros acessos administrativos, eles deverão ser modelados separadamente sem
-- transformar administrador, profissional e cliente em um papel genérico.
-- ============================================================================

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


-- ============================================================================
-- 5. CLIENTES / CONSUMIDORES
-- ============================================================================
--
-- Cliente representa o contexto de consumo dentro de UMA empresa.
-- A mesma identidade de acesso pode possuir cadastros de cliente em várias
-- empresas, mas cada histórico permanece isolado pelo empresa_id.
--
-- usuario_id é opcional para permitir clientes cadastrados pelo estabelecimento
-- que ainda não possuem login.
-- ============================================================================

CREATE TABLE clientes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id BIGINT UNSIGNED NOT NULL,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    observacoes TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_clientes_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_clientes_pessoa_empresa
        FOREIGN KEY (pessoa_id, empresa_id)
        REFERENCES pessoas(id, empresa_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_clientes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_clientes_empresa_pessoa (empresa_id, pessoa_id),
    UNIQUE KEY uq_clientes_empresa_usuario (empresa_id, usuario_id),
    KEY idx_clientes_usuario (usuario_id),
    KEY idx_clientes_empresa_ativo (empresa_id, ativo)
) ENGINE=InnoDB;


-- ============================================================================
-- 6. PROFISSIONAIS
-- ============================================================================
--
-- Profissional representa quem presta os serviços.
-- Pode existir sem conta de acesso.
--
-- Caso possua login, usuario_id vincula a identidade de autenticação ao cadastro
-- profissional sem transformar o usuário em administrador ou cliente.
--
-- A mesma identidade pode, futuramente, possuir vínculo profissional com mais
-- de uma empresa; o contexto continua sendo definido pelo empresa_id.
-- ============================================================================

CREATE TABLE profissionais (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id BIGINT UNSIGNED NOT NULL,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    cargo VARCHAR(120) NULL,
    descricao TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_profissionais_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_profissionais_pessoa_empresa
        FOREIGN KEY (pessoa_id, empresa_id)
        REFERENCES pessoas(id, empresa_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_profissionais_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_profissionais_empresa_pessoa (empresa_id, pessoa_id),
    UNIQUE KEY uq_profissionais_empresa_usuario (empresa_id, usuario_id),
    KEY idx_profissionais_usuario (usuario_id),
    KEY idx_profissionais_empresa_ativo (empresa_id, ativo)
) ENGINE=InnoDB;


-- ============================================================================
-- 7. INTEGRAÇÕES DE CALENDÁRIO DO PROFISSIONAL
-- ============================================================================
--
-- Login com Google NÃO é integração com Google Calendar.
--
-- A conta externa conectada ao calendário pode ser diferente do e-mail usado
-- para autenticação no Salão Agenda.
--
-- O Salão Agenda permanece como fonte oficial dos agendamentos.
-- Calendários externos são complementares e opcionais.
--
-- Tokens devem ser criptografados pela aplicação antes de serem persistidos.
-- Nunca armazenar access_token ou refresh_token em texto puro.
-- ============================================================================

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


-- ============================================================================
-- 8. CATEGORIAS E SERVIÇOS
-- ============================================================================

CREATE TABLE categorias_servicos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(120) NOT NULL,
    descricao TEXT NULL,
    ordem INT NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_categorias_servicos_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,

    UNIQUE KEY uq_categoria_empresa_nome (empresa_id, nome),
    KEY idx_categorias_empresa_ativo (empresa_id, ativo, ordem)
) ENGINE=InnoDB;


CREATE TABLE servicos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id BIGINT UNSIGNED NOT NULL,
    categoria_id BIGINT UNSIGNED NULL,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT NULL,
    duracao_minutos SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    intervalo_minutos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    preco DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    permite_agendamento_online TINYINT(1) NOT NULL DEFAULT 1,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_servicos_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_servicos_categoria
        FOREIGN KEY (categoria_id) REFERENCES categorias_servicos(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_servicos_empresa_nome (empresa_id, nome),
    KEY idx_servicos_empresa_ativo (empresa_id, ativo),
    KEY idx_servicos_categoria (categoria_id)
) ENGINE=InnoDB;


CREATE TABLE profissional_servicos (
    profissional_id BIGINT UNSIGNED NOT NULL,
    servico_id BIGINT UNSIGNED NOT NULL,
    duracao_minutos SMALLINT UNSIGNED NULL,
    preco DECIMAL(10,2) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (profissional_id, servico_id),

    CONSTRAINT fk_profissional_servicos_profissional
        FOREIGN KEY (profissional_id) REFERENCES profissionais(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_profissional_servicos_servico
        FOREIGN KEY (servico_id) REFERENCES servicos(id)
        ON DELETE CASCADE,

    KEY idx_profissional_servicos_servico (servico_id, ativo)
) ENGINE=InnoDB;


-- ============================================================================
-- 9. HORÁRIOS DE FUNCIONAMENTO E DISPONIBILIDADE
-- ============================================================================

CREATE TABLE empresa_horarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id BIGINT UNSIGNED NOT NULL,
    dia_semana TINYINT UNSIGNED NOT NULL COMMENT '1=segunda ... 7=domingo',
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,

    CONSTRAINT fk_empresa_horarios_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,

    CONSTRAINT chk_empresa_horarios_dia
        CHECK (dia_semana BETWEEN 1 AND 7),

    CONSTRAINT chk_empresa_horarios_intervalo
        CHECK (hora_fim > hora_inicio),

    KEY idx_empresa_horarios_dia (empresa_id, dia_semana, ativo)
) ENGINE=InnoDB;


CREATE TABLE profissional_horarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profissional_id BIGINT UNSIGNED NOT NULL,
    dia_semana TINYINT UNSIGNED NOT NULL COMMENT '1=segunda ... 7=domingo',
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,

    CONSTRAINT fk_profissional_horarios_profissional
        FOREIGN KEY (profissional_id) REFERENCES profissionais(id)
        ON DELETE CASCADE,

    CONSTRAINT chk_profissional_horarios_dia
        CHECK (dia_semana BETWEEN 1 AND 7),

    CONSTRAINT chk_profissional_horarios_intervalo
        CHECK (hora_fim > hora_inicio),

    KEY idx_profissional_horarios_dia
        (profissional_id, dia_semana, ativo)
) ENGINE=InnoDB;


CREATE TABLE profissional_bloqueios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profissional_id BIGINT UNSIGNED NOT NULL,
    inicio DATETIME NOT NULL,
    fim DATETIME NOT NULL,
    motivo VARCHAR(255) NULL,
    criado_por_usuario_id BIGINT UNSIGNED NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_profissional_bloqueios_profissional
        FOREIGN KEY (profissional_id) REFERENCES profissionais(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_profissional_bloqueios_usuario
        FOREIGN KEY (criado_por_usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL,

    CONSTRAINT chk_profissional_bloqueios_periodo
        CHECK (fim > inicio),

    KEY idx_profissional_bloqueios_periodo
        (profissional_id, inicio, fim)
) ENGINE=InnoDB;


-- ============================================================================
-- 10. AGENDAMENTOS
-- ============================================================================
--
-- O cabeçalho não possui servico_id nem profissional_id.
-- Isso permite múltiplos serviços e até profissionais diferentes no mesmo
-- agendamento através de agendamento_servicos.
-- ============================================================================

CREATE TABLE agendamentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    inicio DATETIME NOT NULL,
    fim DATETIME NOT NULL,
    status ENUM(
        'pendente',
        'confirmado',
        'em_atendimento',
        'concluido',
        'cancelado',
        'nao_compareceu'
    ) NOT NULL DEFAULT 'pendente',
    origem ENUM(
        'site',
        'painel',
        'telefone',
        'whatsapp',
        'recepcao',
        'api'
    ) NOT NULL DEFAULT 'painel',
    observacoes_cliente TEXT NULL,
    observacoes_internas TEXT NULL,
    valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    criado_por_usuario_id BIGINT UNSIGNED NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_agendamentos_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_agendamentos_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_agendamentos_criado_por
        FOREIGN KEY (criado_por_usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL,

    CONSTRAINT chk_agendamentos_periodo
        CHECK (fim > inicio),

    KEY idx_agendamentos_empresa_inicio (empresa_id, inicio),
    KEY idx_agendamentos_cliente_inicio (cliente_id, inicio),
    KEY idx_agendamentos_status (empresa_id, status, inicio)
) ENGINE=InnoDB;


CREATE TABLE agendamento_servicos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agendamento_id BIGINT UNSIGNED NOT NULL,
    servico_id BIGINT UNSIGNED NOT NULL,
    profissional_id BIGINT UNSIGNED NOT NULL,
    inicio DATETIME NOT NULL,
    fim DATETIME NOT NULL,
    duracao_minutos SMALLINT UNSIGNED NOT NULL,
    valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ordem SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_agendamento_servicos_agendamento
        FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_agendamento_servicos_servico
        FOREIGN KEY (servico_id) REFERENCES servicos(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_agendamento_servicos_profissional
        FOREIGN KEY (profissional_id) REFERENCES profissionais(id)
        ON DELETE RESTRICT,

    CONSTRAINT chk_agendamento_servicos_periodo
        CHECK (fim > inicio),

    UNIQUE KEY uq_agendamento_servicos_ordem (agendamento_id, ordem),
    KEY idx_agendamento_servicos_profissional_periodo
        (profissional_id, inicio, fim),
    KEY idx_agendamento_servicos_servico (servico_id)
) ENGINE=InnoDB;


-- ----------------------------------------------------------------------------
-- 10.1 EVENTOS EM CALENDÁRIOS EXTERNOS
-- ----------------------------------------------------------------------------
--
-- O vínculo do evento externo fica associado ao item de serviço/profissional,
-- e não ao cabeçalho do agendamento. Isso permite que um agendamento possua
-- múltiplos profissionais, cada um com sua própria integração de calendário.
-- ----------------------------------------------------------------------------

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


CREATE TABLE agendamento_historico (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agendamento_id BIGINT UNSIGNED NOT NULL,
    status_anterior ENUM(
        'pendente',
        'confirmado',
        'em_atendimento',
        'concluido',
        'cancelado',
        'nao_compareceu'
    ) NULL,
    status_novo ENUM(
        'pendente',
        'confirmado',
        'em_atendimento',
        'concluido',
        'cancelado',
        'nao_compareceu'
    ) NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    observacao VARCHAR(500) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_agendamento_historico_agendamento
        FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_agendamento_historico_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL,

    KEY idx_agendamento_historico_agendamento
        (agendamento_id, criado_em)
) ENGINE=InnoDB;


-- ============================================================================
-- 11. EXTENSÃO PARA PET SHOP
-- ============================================================================

CREATE TABLE pets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(120) NOT NULL,
    especie VARCHAR(80) NULL,
    raca VARCHAR(120) NULL,
    sexo ENUM('macho', 'femea', 'nao_informado')
        NOT NULL DEFAULT 'nao_informado',
    data_nascimento DATE NULL,
    peso_kg DECIMAL(6,2) NULL,
    observacoes TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_pets_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_pets_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON DELETE CASCADE,

    KEY idx_pets_cliente (cliente_id, ativo)
) ENGINE=InnoDB;


CREATE TABLE agendamento_pets (
    agendamento_id BIGINT UNSIGNED NOT NULL,
    pet_id BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (agendamento_id, pet_id),

    CONSTRAINT fk_agendamento_pets_agendamento
        FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_agendamento_pets_pet
        FOREIGN KEY (pet_id) REFERENCES pets(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;


-- ============================================================================
-- OBSERVAÇÕES IMPORTANTES DE REGRA DE NEGÓCIO
-- ============================================================================
--
-- 1. Conflito de horários de profissional NÃO pode ser resolvido somente com
--    UNIQUE KEY no MySQL, pois os períodos são intervalos.
--    A aplicação deverá verificar sobreposição de horários dentro de uma
--    transação antes de confirmar o agendamento.
--
-- 2. A disponibilidade real será a interseção entre:
--       - horário de funcionamento da empresa;
--       - horário do profissional;
--       - bloqueios do profissional;
--       - agendamentos já existentes;
--       - duração/intervalo dos serviços.
--
-- 3. O WordPress não acessará este banco diretamente.
--    A integração externa deverá ocorrer através da API.
--
-- 4. O isolamento multiempresa deve ser aplicado também no código:
--    toda consulta operacional deve ser limitada à empresa do contexto atual.
--    O empresa_id recebido do cliente nunca deve ser aceito sem validar se a
--    identidade autenticada possui acesso ao contexto solicitado.
--
-- 5. usuarios representa identidade/autenticação. Administrador, profissional
--    e cliente são entidades funcionais diferentes e não devem ser inferidos
--    apenas pela existência de um usuário autenticado.
--
-- 6. O administrador principal é exclusivo:
--       - uma empresa possui no máximo um administrador principal;
--       - uma identidade de administrador principal pertence a uma empresa.
--    A tabela administradores é a fonte desse vínculo e mantém os dados
--    cadastrais próprios do administrador, sem reutilizar pessoas.
--
-- 7. Profissionais podem existir sem login. Quando houver usuario_id, o vínculo
--    significa apenas que aquela identidade pode acessar o contexto daquele
--    profissional.
--
-- 8. Clientes podem existir sem login. Uma identidade de consumidor pode estar
--    vinculada como cliente a várias empresas, mas cada cadastro, histórico e
--    agendamento permanece isolado pelo empresa_id.
--
-- 9. Login com Google e Google Calendar são integrações diferentes.
--    usuarios.google_id serve somente para autenticação com Google.
--    Credenciais de calendário ficam em profissional_integracoes_calendario.
--
-- 10. O Salão Agenda é a fonte oficial dos agendamentos. Google Calendar,
--     Microsoft Outlook ou outros calendários são integrações complementares.
--
-- 11. Administradores criados durante o onboarding de uma NOVA identidade
--     devem nascer com senha_hash = NULL e ativo = 0. O acesso só é liberado
--     após a definição de senha por token válido, não utilizado, não revogado
--     e dentro do prazo de expiração.
--
-- 12. O token de ativação em texto puro deve existir apenas no link enviado ao
--     usuário. No banco deve ser armazenado somente o hash SHA-256 do token.
--
-- 13. Ao reenviar ativação, tokens anteriores válidos devem ser revogados.
--
-- 14. Tokens OAuth de calendários externos nunca devem ser armazenados em texto
--     puro; a aplicação deve criptografá-los com chave mantida fora do banco.
--
-- 15. Módulo financeiro completo (pagamentos, comissões, caixa e despesas)
--     ficará para uma fase posterior.
-- ============================================================================
