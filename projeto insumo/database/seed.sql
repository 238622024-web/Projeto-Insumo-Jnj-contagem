-- SEED Controle de Insumos JNJ
-- Este arquivo pode ser executado sozinho em um banco novo.

CREATE DATABASE IF NOT EXISTS `controle_insumos_jnj`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `controle_insumos_jnj`;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `senha_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'user',
  `aprovado` TINYINT(1) NOT NULL DEFAULT 0,
  `aprovado_em` DATETIME NULL,
  `aprovado_por` INT NULL,
  `avatar` VARCHAR(255) NULL,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`),
  INDEX `idx_usuarios_aprovado` (`aprovado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `insumos_jnj` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `data_contagem` DATE NULL,
  `contagem_por_id` INT NULL,
  `contagem_por_nome` VARCHAR(150) NULL,
  `contagem_em` DATETIME NULL,
  `unidade` VARCHAR(30) DEFAULT 'UN',
  `nome` VARCHAR(150) NOT NULL,
  `posicao` VARCHAR(20) NOT NULL,
  `lote` VARCHAR(100) NULL,
  `codigo_barra` VARCHAR(100) NULL,
  `quantidade` INT NOT NULL DEFAULT 0,
  `data_entrada` DATE NOT NULL,
  `validade` DATE NOT NULL,
  `observacoes` TEXT,
  INDEX `idx_validade` (`validade`),
  INDEX `idx_posicao` (`posicao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `configuracoes` (
  `chave` VARCHAR(100) NOT NULL,
  `valor` TEXT NOT NULL,
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `usuarios` (`nome`,`email`,`senha_hash`,`role`,`aprovado`,`aprovado_em`,`avatar`)
VALUES
  ('WEDER MESSIAS DA SILVA PEREIRA','weder.messias@hotmail.com','$2y$10$FtDWxiRNq9fM9VwflXBoi.US7TU4m/HZUvgKX7x5amq2fZg11nEKq','admin',1,NOW(),NULL)
ON DUPLICATE KEY UPDATE
  `nome` = VALUES(`nome`),
  `senha_hash` = VALUES(`senha_hash`),
  `role` = 'admin',
  `aprovado` = 1,
  `aprovado_em` = NOW();

INSERT INTO `usuarios` (`nome`,`email`,`senha_hash`,`role`,`aprovado`,`aprovado_em`,`avatar`)
VALUES
  ('Administrador JNJ','admin@jnj.com','$2y$10$wN4p7p1z0G5M0A2n3s4TuO2hQb3g9pJXl9tLrS8o3Tknm2m8o1C2m','admin',1,NOW(),NULL)
ON DUPLICATE KEY UPDATE
  `nome` = VALUES(`nome`),
  `senha_hash` = VALUES(`senha_hash`),
  `role` = 'admin',
  `aprovado` = 1,
  `aprovado_em` = NOW();

INSERT INTO `insumos_jnj` (`nome`,`posicao`,`lote`,`quantidade`,`data_entrada`,`validade`,`observacoes`) VALUES
('Álcool 70%','P01','L1234',50,'2025-11-01','2026-05-01','Uso geral'),
('Luvas Nitrílicas','P02','LN987',200,'2025-10-15','2027-10-01','Tamanho M'),
('Máscaras Cirúrgicas','P03',NULL,500,'2025-09-10','2026-01-10','Caixas com 50 un');

REPLACE INTO `configuracoes` (`chave`,`valor`) VALUES
('tema_padrao','claro'),
('itens_pagina','25'),
('alerta_validade_curta','7'),
('alerta_validade_media','30'),
('mostrar_lote','1');
