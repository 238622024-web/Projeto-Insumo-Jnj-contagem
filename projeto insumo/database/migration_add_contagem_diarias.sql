-- Migration: add contagem_diarias column to insumos_jnj
ALTER TABLE `insumos_jnj`
  ADD COLUMN `contagem_diarias` INT NOT NULL DEFAULT 0 AFTER `id`;
