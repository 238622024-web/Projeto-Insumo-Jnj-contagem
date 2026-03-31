# Checklist de Seguranca para Publicacao

Use esta lista antes de cada deploy em servidor.

## 1) Ambiente
- [ ] Em config.php, definir `APP_ENV` como `production`.
- [ ] Confirmar credenciais de banco fortes (nao usar root sem senha em servidor final).

## 2) Bloqueios Web
- [ ] Validar existencia de .htaccess na raiz do projeto.
- [ ] Validar existencia de .htaccess em database/.
- [ ] Validar existencia de .htaccess em db_backups/.
- [ ] Confirmar que listagem de diretorio esta desativada.

## 3) Scripts de Manutencao
- [ ] Confirmar que scripts de suporte estao em modo CLI-only:
  - database/check_login_debug.php
  - database/reset_password.php
  - database/check_avatars.php
  - database/set_avatar.php
- [ ] Confirmar que em producao esses scripts encerram com bloqueio automatico.

## 4) Testes Rapidos de Bloqueio (via navegador)
- [ ] /database/check_login_debug.php retorna acesso negado.
- [ ] /database/reset_password.php retorna acesso negado.
- [ ] /database/check_avatars.php retorna acesso negado.
- [ ] /database/set_avatar.php retorna acesso negado.
- [ ] /db_backups/ retorna acesso negado.

## 5) PHP e Servidor
- [ ] display_errors = Off em producao.
- [ ] log_errors = On em producao.
- [ ] HTTPS ativo no dominio final.

## 6) Backups
- [ ] Manter backups fora da pasta publica sempre que possivel.
- [ ] Se ficar na pasta publica, confirmar bloqueio por .htaccess.

## 7) Git e Publicacao
- [ ] Repositorio remoto atualizado.
- [ ] Branch de deploy correta.
- [ ] Tag/versao criada (opcional).

## 8) Validacao Final
- [ ] Login funciona normalmente.
- [ ] Reset de senha administrativo funciona.
- [ ] Exportacoes PDF/Excel funcionando.
- [ ] Dashboard e filtros funcionando.

---

Observacao:
- Este checklist melhora bastante a seguranca de exposicao web.
- Nao substitui controle de acesso ao servidor/hosting.
