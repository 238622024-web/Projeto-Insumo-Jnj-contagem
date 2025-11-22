-- Migration: adicionar coluna avatar à tabela usuarios (MySQL)
ALTER TABLE `usuarios`
  ADD COLUMN `avatar` VARCHAR(255) NULL AFTER `senha_hash`;
