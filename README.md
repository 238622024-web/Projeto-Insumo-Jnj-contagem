// ...existing code...
# Projeto-Insumo-Jnj-contagem

Status: Concluído (ambiente de desenvolvimento)

Resumo
- Aplicação para controle de insumos (CRUD, autenticação, upload de arquivos, relatórios).
- Ambiente preparado com Docker Compose (app, MySQL e phpMyAdmin).

O que foi feito
- Containerização:
  - docker-compose com serviços: app, db (MySQL), phpmyadmin.
  - volume nomeado para persistência do MySQL (dados mantidos entre reinícios).
  - recomendação: usar `restart: unless-stopped` para auto-restart.
- Banco de dados:
  - importados `database/schema.sql` e `database/seed.sql`.
  - criado usuário de teste: `teste.auto+01@example.com` / `Senha123!`.
- PHP / Uploads:
  - adicionado `php.ini` custom (upload_max_filesize = 50M, post_max_size = 50M, memory_limit = 256M).
  - montado no container app para evitar erro: "POST Content-Length exceeds the limit".
- Correções feitas:
  - Inicialização do DataTables aprimorada para evitar o aviso "Incorrect column count" (ajuste automático de colspan ou pulo de inicialização quando incompatível).
  - Tratamento de erros de sessão/headers causado por saída anterior (limites PHP ajustados).
- Utilitários:
  - comandos para backup/restore via `mysqldump` incluídos.

Como rodar (desenvolvimento)
1. Abrir a pasta do projeto:
   - PowerShell:
     Set-Location 'C:\Users\Manserv\insumos.jnj\Projeto-Insumo-Jnj-contagem\projeto insumo'
2. Subir containers:
   docker compose up -d --build
3. Verificar:
   docker ps

Links locais
- Site: http://localhost:8080/
- phpMyAdmin: http://localhost:8081/

Credenciais úteis
- App (login de teste): teste.auto+01@example.com / Senha123!
- MySQL:
  - database: controle_insumos_jnj
  - user: jnj / jnj123
  - root: root / rootpass
  - host (do host): localhost:3306  (no compose usar `db`)

Backup / Restore
- Backup:
  docker exec jnj-mysql sh -c "exec mysqldump -uroot -prootpass controle_insumos_jnj" > backup.sql
- Restore:
  docker exec -i jnj-mysql sh -c "mysql -uroot -prootpass controle_insumos_jnj" < backup.sql

Observações importantes
- Não use `docker compose down -v` se quiser manter os dados do banco (apaga volumes).
- Se o Docker Desktop pedir reboot para habilitar WSL/VirtualMachinePlatform, reinicie o Windows.
- Para testar sem Docker: instalar PHP e rodar `php -S localhost:8000` na pasta `projeto insumo` (aviso: sem MySQL, funcionalidades que dependem do banco não funcionarão).

Arquivos de interesse
- docker-compose.yml
- php.ini
- database/schema.sql, database/seed.sql
- includes/footer.php (DataTables init)
- perfil.php, auth.php, db.php (login e perfil)

Contato
- Para ajustes, correções adicionais ou deploy, abra uma issue ou solicite a modificação desejada.
```// filepath: c:\Users\Manserv\insumos.jnj\Projeto-Insumo-Jnj-contagem\README.md
// ...existing code...
# Projeto-Insumo-Jnj-contagem

Status: Concluído (ambiente de desenvolvimento)

Resumo
- Aplicação para controle de insumos (CRUD, autenticação, upload de arquivos, relatórios).
- Ambiente preparado com Docker Compose (app, MySQL e phpMyAdmin).

O que foi feito
- Containerização:
  - docker-compose com serviços: app, db (MySQL), phpmyadmin.
  - volume nomeado para persistência do MySQL (dados mantidos entre reinícios).
  - recomendação: usar `restart: unless-stopped` para auto-restart.
- Banco de dados:
  - importados `database/schema.sql` e `database/seed.sql`.
  - criado usuário de teste: `teste.auto+01@example.com` / `Senha123!`.
- PHP / Uploads:
  - adicionado `php.ini` custom (upload_max_filesize = 50M, post_max_size = 50M, memory_limit = 256M).
  - montado no container app para evitar erro: "POST Content-Length exceeds the limit".
- Correções feitas:
  - Inicialização do DataTables aprimorada para evitar o aviso "Incorrect column count" (ajuste automático de colspan ou pulo de inicialização quando incompatível).
  - Tratamento de erros de sessão/headers causado por saída anterior (limites PHP ajustados).
- Utilitários:
  - comandos para backup/restore via `mysqldump` incluídos.

Como rodar (desenvolvimento)
1. Abrir a pasta do projeto:
   - PowerShell:
     Set-Location 'C:\Users\Manserv\insumos.jnj\Projeto-Insumo-Jnj-contagem\projeto insumo'
2. Subir containers:
   docker compose up -d --build
3. Verificar:
   docker ps

Links locais
- Site: http://localhost:8080/
- phpMyAdmin: http://localhost:8081/

Credenciais úteis
- App (login de teste): teste.auto+01@example.com / Senha123!
- MySQL:
  - database: controle_insumos_jnj
  - user: jnj / jnj123
  - root: root / rootpass
  - host (do host): localhost:3306  (no compose usar `db`)

Backup / Restore
- Backup:
  docker exec jnj-mysql sh -c "exec mysqldump -uroot -prootpass controle_insumos_jnj" > backup.sql
- Restore:
  docker exec -i jnj-mysql sh -c "mysql -uroot -prootpass controle_insumos_jnj" < backup.sql

Observações importantes
- Não use `docker compose down -v` se quiser manter os dados do banco (apaga volumes).
- Se o Docker Desktop pedir reboot para habilitar WSL/VirtualMachinePlatform, reinicie o Windows.
- Para testar sem Docker: instalar PHP e rodar `php -S localhost:8000` na pasta `projeto insumo` (aviso: sem MySQL, funcionalidades que dependem do banco não funcionarão).

Arquivos de interesse
- docker-compose.yml
- php.ini
- database/schema.sql, database/seed.sql
- includes/footer.php (DataTables init)
- perfil.php, auth.php, db.php (login e perfil)

Contato
- Para ajustes, correções adicionais ou deploy, abra uma issue ou solicite a modificação desejada.