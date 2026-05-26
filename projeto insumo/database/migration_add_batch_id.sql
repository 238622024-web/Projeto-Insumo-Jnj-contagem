-- Migration: agrupar pedidos de insumo por solicitação em lote
ALTER TABLE `insumo_requests`
  ADD COLUMN `batch_id` VARCHAR(64) NULL AFTER `user_role`;

ALTER TABLE `insumo_requests`
  ADD INDEX `idx_ir_batch_id` (`batch_id`);