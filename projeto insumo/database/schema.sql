-- =============================================
-- SCHEMA Controle de Insumos JNJ
-- Gerado em: ${DATE}
-- Ajuste se necessário o nome do banco antes de executar.
-- =============================================

-- Criação do banco (execute apenas se ainda não existir)
CREATE DATABASE IF NOT EXISTS `controle_insumos_jnj`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `controle_insumos_jnj`;

-- Remova os DROP TABLE se não quiser apagar dados existentes
DROP TABLE IF EXISTS `insumos_jnj`;
DROP TABLE IF EXISTS `usuarios`;

-- Tabela de usuários do sistema
CREATE TABLE `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `senha_hash` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255) NULL,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de insumos
CREATE TABLE `insumos_jnj` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(150) NOT NULL,
  `posicao` VARCHAR(20) NOT NULL,
  `lote` VARCHAR(100) NULL,
  `quantidade` INT NOT NULL DEFAULT 0,
  `data_entrada` DATE NOT NULL,
  `validade` DATE NOT NULL,
  `observacoes` TEXT,
  INDEX `idx_validade` (`validade`),
  INDEX `idx_posicao` (`posicao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de configuracoes (chave/valor)
CREATE TABLE IF NOT EXISTS `configuracoes` (
  `chave` VARCHAR(100) NOT NULL,
  `valor` TEXT NOT NULL,
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fim do schema