-- SEED Controle de Insumos JNJ
-- Ajuste o hash da senha se necessário (ver README-database.md).

USE `controle_insumos_jnj`;

-- Usuário de teste
-- Senha recomendada: senha123 (gere um hash com PHP se este não funcionar)
INSERT INTO `usuarios` (`nome`,`email`,`senha_hash`,`avatar`)
VALUES
  ('Usuário JNJ','usuario@jnj.com','$2y$10$wN4p7p1z0G5M0A2n3s4TuO2hQb3g9pJXl9tLrS8o3Tknm2m8o1C2m',NULL);

-- Insumos de exemplo
INSERT INTO `insumos_jnj` (`nome`,`posicao`,`lote`,`quantidade`,`data_entrada`,`validade`,`observacoes`) VALUES
('Álcool 70%','P01','L1234',50,'2025-11-01','2026-05-01','Uso geral'),
('Luvas Nitrílicas','P02','LN987',200,'2025-10-15','2027-10-01','Tamanho M'),
('Máscaras Cirúrgicas','P03',NULL,500,'2025-09-10','2026-01-10','Caixas com 50 un');

-- Configurações padrão
REPLACE INTO `configuracoes` (`chave`,`valor`) VALUES
('tema_padrao','claro'),
('itens_pagina','25'),
('alerta_validade_curta','7'),
('alerta_validade_media','30'),
('mostrar_lote','1');
