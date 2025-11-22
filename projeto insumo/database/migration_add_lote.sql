-- Migration: adicionar coluna lote à tabela insumos_jnj (MySQL)
ALTER TABLE `insumos_jnj`
  ADD COLUMN `lote` VARCHAR(100) NULL AFTER `posicao`;