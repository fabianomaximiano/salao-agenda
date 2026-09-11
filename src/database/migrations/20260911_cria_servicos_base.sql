-- ============================================================================
-- Catálogo de serviços básicos por segmento
-- Data: 2026-09-11
--
-- A tabela servicos_base contém apenas sugestões iniciais.
-- Os serviços operacionais de cada empresa continuam sendo gravados em servicos.
-- Combos, pacotes, promoções e demais personalizações ficam por conta da empresa.
-- ============================================================================

CREATE TABLE IF NOT EXISTS servicos_base (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    segmento_id SMALLINT UNSIGNED NOT NULL,
    nome VARCHAR(150) NOT NULL,
    duracao_sugerida SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_servicos_base_segmento
        FOREIGN KEY (segmento_id) REFERENCES segmentos(id)
        ON DELETE RESTRICT,

    UNIQUE KEY uq_servicos_base_segmento_nome (segmento_id, nome),
    KEY idx_servicos_base_segmento_ativo (segmento_id, ativo, ordem)
) ENGINE=InnoDB;


INSERT IGNORE INTO servicos_base (
    segmento_id,
    nome,
    duracao_sugerida,
    ordem
)
SELECT
    s.id,
    dados.nome,
    dados.duracao_sugerida,
    dados.ordem
FROM segmentos s
INNER JOIN (
    SELECT 'unhas' AS slug, 'Manicure' AS nome, 45 AS duracao_sugerida, 10 AS ordem
    UNION ALL SELECT 'unhas', 'Pedicure', 45, 20
    UNION ALL SELECT 'unhas', 'Manicure e Pedicure', 90, 30
    UNION ALL SELECT 'unhas', 'Esmaltação', 30, 40
    UNION ALL SELECT 'unhas', 'Remoção de esmalte em gel', 30, 50

    UNION ALL SELECT 'cabelos', 'Corte feminino', 60, 10
    UNION ALL SELECT 'cabelos', 'Corte masculino', 45, 20
    UNION ALL SELECT 'cabelos', 'Escova', 60, 30
    UNION ALL SELECT 'cabelos', 'Hidratação', 60, 40
    UNION ALL SELECT 'cabelos', 'Coloração', 120, 50
    UNION ALL SELECT 'cabelos', 'Progressiva', 180, 60

    UNION ALL SELECT 'barbearia', 'Corte masculino', 45, 10
    UNION ALL SELECT 'barbearia', 'Corte infantil', 45, 20
    UNION ALL SELECT 'barbearia', 'Barba', 30, 30
    UNION ALL SELECT 'barbearia', 'Acabamento / pezinho', 20, 40
    UNION ALL SELECT 'barbearia', 'Sobrancelha', 20, 50
    UNION ALL SELECT 'barbearia', 'Pigmentação de barba', 45, 60

    UNION ALL SELECT 'depilacao', 'Depilação de axilas', 20, 10
    UNION ALL SELECT 'depilacao', 'Depilação de buço', 15, 20
    UNION ALL SELECT 'depilacao', 'Depilação de meia perna', 30, 30
    UNION ALL SELECT 'depilacao', 'Depilação de perna inteira', 45, 40
    UNION ALL SELECT 'depilacao', 'Depilação de virilha', 30, 50
    UNION ALL SELECT 'depilacao', 'Depilação facial', 30, 60

    UNION ALL SELECT 'maquiagem', 'Maquiagem social', 60, 10
    UNION ALL SELECT 'maquiagem', 'Maquiagem para festa', 75, 20
    UNION ALL SELECT 'maquiagem', 'Maquiagem de noiva', 120, 30

    UNION ALL SELECT 'sobrancelhas', 'Design de sobrancelhas', 30, 10
    UNION ALL SELECT 'sobrancelhas', 'Design com henna', 45, 20
    UNION ALL SELECT 'sobrancelhas', 'Manutenção de sobrancelhas', 20, 30

    UNION ALL SELECT 'massagem-estetica', 'Massagem relaxante', 60, 10
    UNION ALL SELECT 'massagem-estetica', 'Drenagem linfática', 60, 20
    UNION ALL SELECT 'massagem-estetica', 'Massagem modeladora', 60, 30
    UNION ALL SELECT 'massagem-estetica', 'Limpeza de pele', 90, 40
    UNION ALL SELECT 'massagem-estetica', 'Hidratação facial', 60, 50

    UNION ALL SELECT 'podologia', 'Avaliação podológica', 30, 10
    UNION ALL SELECT 'podologia', 'Podologia preventiva', 60, 20
    UNION ALL SELECT 'podologia', 'Corte técnico de unhas', 45, 30
    UNION ALL SELECT 'podologia', 'Tratamento de calosidades', 60, 40
    UNION ALL SELECT 'podologia', 'Tratamento de unha encravada', 60, 50

    UNION ALL SELECT 'pet-shop', 'Banho', 60, 10
    UNION ALL SELECT 'pet-shop', 'Tosa higiênica', 60, 20
    UNION ALL SELECT 'pet-shop', 'Tosa completa', 90, 30
    UNION ALL SELECT 'pet-shop', 'Corte de unhas', 20, 40
    UNION ALL SELECT 'pet-shop', 'Limpeza de ouvidos', 20, 50
) AS dados
    ON dados.slug = s.slug;
