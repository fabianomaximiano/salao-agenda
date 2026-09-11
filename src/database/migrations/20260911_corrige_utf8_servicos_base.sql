-- ============================================================================
-- Correcao de UTF-8 dos nomes em servicos_base
-- Data: 2026-09-11
--
-- Esta migration deve ser aplicada no banco que recebeu os nomes corrompidos.
-- Todo o arquivo e ASCII. Os textos UTF-8 sao reconstruidos no proprio MySQL.
-- ============================================================================

START TRANSACTION;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x4D616E6963757265 USING utf8mb4)
WHERE s.slug = 'unhas'
  AND sb.ordem = 10;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x5065646963757265 USING utf8mb4)
WHERE s.slug = 'unhas'
  AND sb.ordem = 20;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x4D616E69637572652065205065646963757265 USING utf8mb4)
WHERE s.slug = 'unhas'
  AND sb.ordem = 30;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x45736D616C7461C3A7C3A36F USING utf8mb4)
WHERE s.slug = 'unhas'
  AND sb.ordem = 40;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x52656D6FC3A7C3A36F2064652065736D616C746520656D2067656C USING utf8mb4)
WHERE s.slug = 'unhas'
  AND sb.ordem = 50;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x436F7274652066656D696E696E6F USING utf8mb4)
WHERE s.slug = 'cabelos'
  AND sb.ordem = 10;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x436F727465206D617363756C696E6F USING utf8mb4)
WHERE s.slug = 'cabelos'
  AND sb.ordem = 20;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x4573636F7661 USING utf8mb4)
WHERE s.slug = 'cabelos'
  AND sb.ordem = 30;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x48696472617461C3A7C3A36F USING utf8mb4)
WHERE s.slug = 'cabelos'
  AND sb.ordem = 40;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x436F6C6F7261C3A7C3A36F USING utf8mb4)
WHERE s.slug = 'cabelos'
  AND sb.ordem = 50;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x50726F6772657373697661 USING utf8mb4)
WHERE s.slug = 'cabelos'
  AND sb.ordem = 60;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x436F727465206D617363756C696E6F USING utf8mb4)
WHERE s.slug = 'barbearia'
  AND sb.ordem = 10;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x436F72746520696E66616E74696C USING utf8mb4)
WHERE s.slug = 'barbearia'
  AND sb.ordem = 20;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x4261726261 USING utf8mb4)
WHERE s.slug = 'barbearia'
  AND sb.ordem = 30;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x41636162616D656E746F202F2070657A696E686F USING utf8mb4)
WHERE s.slug = 'barbearia'
  AND sb.ordem = 40;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x536F6272616E63656C6861 USING utf8mb4)
WHERE s.slug = 'barbearia'
  AND sb.ordem = 50;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x5069676D656E7461C3A7C3A36F206465206261726261 USING utf8mb4)
WHERE s.slug = 'barbearia'
  AND sb.ordem = 60;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x446570696C61C3A7C3A36F206465206178696C6173 USING utf8mb4)
WHERE s.slug = 'depilacao'
  AND sb.ordem = 10;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x446570696C61C3A7C3A36F206465206275C3A76F USING utf8mb4)
WHERE s.slug = 'depilacao'
  AND sb.ordem = 20;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x446570696C61C3A7C3A36F206465206D656961207065726E61 USING utf8mb4)
WHERE s.slug = 'depilacao'
  AND sb.ordem = 30;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x446570696C61C3A7C3A36F206465207065726E6120696E7465697261 USING utf8mb4)
WHERE s.slug = 'depilacao'
  AND sb.ordem = 40;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x446570696C61C3A7C3A36F20646520766972696C6861 USING utf8mb4)
WHERE s.slug = 'depilacao'
  AND sb.ordem = 50;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x446570696C61C3A7C3A36F2066616369616C USING utf8mb4)
WHERE s.slug = 'depilacao'
  AND sb.ordem = 60;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x4D617175696167656D20736F6369616C USING utf8mb4)
WHERE s.slug = 'maquiagem'
  AND sb.ordem = 10;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x4D617175696167656D2070617261206665737461 USING utf8mb4)
WHERE s.slug = 'maquiagem'
  AND sb.ordem = 20;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x4D617175696167656D206465206E6F697661 USING utf8mb4)
WHERE s.slug = 'maquiagem'
  AND sb.ordem = 30;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x44657369676E20646520736F6272616E63656C686173 USING utf8mb4)
WHERE s.slug = 'sobrancelhas'
  AND sb.ordem = 10;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x44657369676E20636F6D2068656E6E61 USING utf8mb4)
WHERE s.slug = 'sobrancelhas'
  AND sb.ordem = 20;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x4D616E7574656EC3A7C3A36F20646520736F6272616E63656C686173 USING utf8mb4)
WHERE s.slug = 'sobrancelhas'
  AND sb.ordem = 30;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x4D6173736167656D2072656C6178616E7465 USING utf8mb4)
WHERE s.slug = 'massagem-estetica'
  AND sb.ordem = 10;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x4472656E6167656D206C696E66C3A174696361 USING utf8mb4)
WHERE s.slug = 'massagem-estetica'
  AND sb.ordem = 20;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x4D6173736167656D206D6F64656C61646F7261 USING utf8mb4)
WHERE s.slug = 'massagem-estetica'
  AND sb.ordem = 30;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x4C696D70657A612064652070656C65 USING utf8mb4)
WHERE s.slug = 'massagem-estetica'
  AND sb.ordem = 40;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x48696472617461C3A7C3A36F2066616369616C USING utf8mb4)
WHERE s.slug = 'massagem-estetica'
  AND sb.ordem = 50;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x4176616C6961C3A7C3A36F20706F646F6CC3B367696361 USING utf8mb4)
WHERE s.slug = 'podologia'
  AND sb.ordem = 10;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x506F646F6C6F6769612070726576656E74697661 USING utf8mb4)
WHERE s.slug = 'podologia'
  AND sb.ordem = 20;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x436F7274652074C3A9636E69636F20646520756E686173 USING utf8mb4)
WHERE s.slug = 'podologia'
  AND sb.ordem = 30;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x54726174616D656E746F2064652063616C6F73696461646573 USING utf8mb4)
WHERE s.slug = 'podologia'
  AND sb.ordem = 40;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x54726174616D656E746F20646520756E686120656E63726176616461 USING utf8mb4)
WHERE s.slug = 'podologia'
  AND sb.ordem = 50;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x42616E686F USING utf8mb4)
WHERE s.slug = 'pet-shop'
  AND sb.ordem = 10;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x546F73612068696769C3AA6E696361 USING utf8mb4)
WHERE s.slug = 'pet-shop'
  AND sb.ordem = 20;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x546F736120636F6D706C657461 USING utf8mb4)
WHERE s.slug = 'pet-shop'
  AND sb.ordem = 30;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x436F72746520646520756E686173 USING utf8mb4)
WHERE s.slug = 'pet-shop'
  AND sb.ordem = 40;

UPDATE servicos_base sb
INNER JOIN segmentos s
    ON s.id = sb.segmento_id
SET sb.nome = CONVERT(0x4C696D70657A61206465206F757669646F73 USING utf8mb4)
WHERE s.slug = 'pet-shop'
  AND sb.ordem = 50;

COMMIT;
