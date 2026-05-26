-- Migration: garantir campos usados por pedidos de insumo
ALTER TABLE `insumo_requests`
  ADD COLUMN `batch_id` VARCHAR(64) NULL AFTER `user_role`,
  ADD COLUMN `setor` VARCHAR(120) NULL AFTER `batch_id`,
  ADD COLUMN `data_solicitada_entrega` DATE NULL AFTER `setor`,
  ADD COLUMN `quantidade_entregue` DECIMAL(10,2) NULL AFTER `unidade`,
  ADD COLUMN `lote` VARCHAR(100) NULL AFTER `quantidade_entregue`,
  ADD COLUMN `fabricacao` DATE NULL AFTER `lote`,
  ADD COLUMN `validade` DATE NULL AFTER `fabricacao`,
  ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER `motivo_usuario`,
  ADD COLUMN `admin_note` TEXT NULL AFTER `status`,
  ADD COLUMN `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `admin_note`,
  ADD COLUMN `processed_at` DATETIME NULL AFTER `requested_at`,
  ADD COLUMN `processed_by` INT NULL AFTER `processed_at`;

CREATE INDEX `idx_ir_status` ON `insumo_requests` (`status`);
CREATE INDEX `idx_ir_batch_id` ON `insumo_requests` (`batch_id`);
CREATE INDEX `idx_ir_user` ON `insumo_requests` (`user_id`);
CREATE INDEX `idx_ir_requested_at` ON `insumo_requests` (`requested_at`);
