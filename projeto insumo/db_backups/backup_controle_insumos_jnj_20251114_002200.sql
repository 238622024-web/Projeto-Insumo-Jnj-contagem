-- Backup Controle de Insumos JNJ
-- Gerado em: 2025-11-14 00:22:00
-- Banco: controle_insumos_jnj

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- Estruturas (execute previamente se precisar)
-- CREATE TABLE insumos_jnj (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(150) NOT NULL, posicao VARCHAR(20) NOT NULL, quantidade INT NOT NULL DEFAULT 0, data_entrada DATE NOT NULL, validade DATE NOT NULL, observacoes TEXT) ENGINE=InnoDB;
-- CREATE TABLE usuarios (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(150) NOT NULL, email VARCHAR(150) NOT NULL UNIQUE, senha_hash VARCHAR(255) NOT NULL, criado_em DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;

-- Dados da tabela `usuarios`
INSERT INTO `usuarios` (`id`,`nome`,`email`,`senha_hash`,`criado_em`) VALUES ('1','WEDER MESSIAS DA SILVA PEREIRA','weder.messias_@hotmail.com','$2y$10$NBq.VEvbIrGHZdTE8A0jGuEPfzvwwWK.2EPNbI.w6EuD9jK.JhHXW','2025-11-13 20:07:06');
INSERT INTO `usuarios` (`id`,`nome`,`email`,`senha_hash`,`criado_em`) VALUES ('2','WEDER MESSIAS DA SILVA PEREIRA','weder.messias@hotmail.com','$2y$10$ZolGki12GGQ/KJhGfnqhrefyhf2YXAdykF/J/iNhGMYMjRCBeDzoO','2025-11-13 20:07:48');

-- Dados da tabela `insumos_jnj`
INSERT INTO `insumos_jnj` (`id`,`nome`,`posicao`,`quantidade`,`data_entrada`,`validade`,`observacoes`) VALUES ('1','Fita gomada','25-45-88','299','2025-11-14','2025-11-15','');

SET FOREIGN_KEY_CHECKS=1;
