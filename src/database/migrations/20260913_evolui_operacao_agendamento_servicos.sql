-- Salão Agenda
-- Adiciona estado operacional individual por serviço/profissional.

ALTER TABLE agendamento_servicos
    ADD COLUMN status ENUM(
        'agendado',
        'confirmado',
        'em_atendimento',
        'concluido',
        'cancelado',
        'nao_compareceu'
    ) NOT NULL DEFAULT 'agendado' AFTER profissional_id,
    ADD COLUMN iniciado_em DATETIME DEFAULT NULL AFTER fim,
    ADD COLUMN concluido_em DATETIME DEFAULT NULL AFTER iniciado_em,
    ADD KEY idx_agendamento_servicos_profissional_status_inicio
        (profissional_id, status, inicio),
    ADD KEY idx_agendamento_servicos_status_inicio
        (status, inicio);
