========================================
  SETUP RÁPIDO NO LARAGON (RECOMENDADO)
========================================

PASSO 1: Iniciar serviços
- Abra o Laragon
- Clique em "Start All" (Apache + MySQL)

PASSO 2: Colocar o projeto no www
- Caminho padrão do Laragon: C:\laragon\www\
- Copie esta pasta para dentro de www
- Sugestão de nome de pasta: projeto-insumo (sem espaço)

PASSO 3: Configuração de banco
- Arquivo: config.php
- Padrão Laragon local:
  - DB_HOST=localhost
  - DB_NAME=controle_insumos_jnj
  - DB_USER=root
  - DB_PASS= (vazio)
  - DB_PORT=3306

PASSO 4: Inicializar banco (1 clique)
- Abra no navegador (ajuste conforme nome da pasta):
  - http://localhost/projeto-insumo/database/init_db.php
  - ou http://localhost/projeto%20insumo/database/init_db.php

PASSO 5: Abrir sistema
- http://localhost/projeto-insumo/
- ou http://localhost/projeto%20insumo/

LOGIN DE TESTE (após seed):
- Email: usuario@jnj.com
- Senha: senha123

SE DER ERRO DE CONEXÃO:
- Confirme se MySQL do Laragon está iniciado
- Confirme usuário/senha no config.php
- Teste health: /health.php

========================================
Dica: use o arquivo LARAGON-START.bat
========================================
