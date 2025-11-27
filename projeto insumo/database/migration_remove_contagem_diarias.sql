-- Migration: remove contagem_diarias column from insumos_jnj
ALTER TABLE `insumos_jnj`
  DROP COLUMN IF EXISTS `contagem_diarias`;
