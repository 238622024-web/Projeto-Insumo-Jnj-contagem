-- Migration: adicionar coluna codigo_barra à tabela insumos_jnj (MySQL)
ALTER TABLE `insumos_jnj`
  ADD COLUMN `codigo_barra` VARCHAR(100) NULL AFTER `lote`;
