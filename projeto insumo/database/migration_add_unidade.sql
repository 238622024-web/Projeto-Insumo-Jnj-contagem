-- Migration: add unidade column to insumos_jnj
ALTER TABLE `insumos_jnj`
  ADD COLUMN `unidade` VARCHAR(30) DEFAULT 'UN' AFTER `data_contagem`;
