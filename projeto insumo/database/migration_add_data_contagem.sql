-- Migration: add data_contagem column to insumos_jnj
ALTER TABLE `insumos_jnj`
  ADD COLUMN `data_contagem` DATE NULL AFTER `quantidade`;
