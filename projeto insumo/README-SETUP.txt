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


========================================
  SETUP RAPIDO NO XAMPP (WINDOWS)
========================================

PASSO 1: Iniciar servicos
- Abra o XAMPP Control Panel
- Inicie Apache e MySQL

PASSO 2: Colocar o projeto no htdocs
- Caminho padrao do XAMPP: C:\xampp\htdocs\
- Copie esta pasta para dentro de htdocs

PASSO 3: Configuracao de banco
- Arquivo: config.php
- Padrao XAMPP local:
  - DB_HOST=localhost
  - DB_NAME=controle_insumos_jnj
  - DB_USER=root
  - DB_PASS= (vazio)
  - DB_PORT=3306

PASSO 4: Inicializar banco
- Abra no navegador:
  - http://localhost/projeto%20insumo/database/init_db.php

PASSO 5: Abrir sistema
- http://localhost/projeto%20insumo/

Atalho de 1 clique:
- Use o arquivo XAMPP-START.bat
- Ele tenta iniciar o XAMPP, abre init_db.php e abre o sistema


========================================
  USAR COMO SOFTWARE NO PC (ATALHO)
========================================

1) Execute INSTALAR-ATALHO-APP.bat
- O script cria um atalho na Area de Trabalho:
  - Projeto Insumo JNJ
- O instalador funciona sozinho (nao depende de APP-LAUNCHER.bat)

2) Abra pelo atalho da Area de Trabalho
- O atalho executa INSTALAR-ATALHO-APP.bat em modo de abertura
- Ele tenta iniciar o XAMPP e abre o sistema em janela tipo app
- Prioridade de abertura: Edge app mode, depois Chrome app mode

3) Se nao abrir em modo app
- Instale/atualize Edge ou Chrome
- Como fallback, abre no navegador padrao
