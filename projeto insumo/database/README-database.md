# Banco de Dados - Controle de Insumos JNJ

Esta pasta contém arquivos SQL para criar e popular o banco de dados local rapidamente.

## Arquivos

- `schema.sql`: Cria o banco `controle_insumos_jnj` e as tabelas `usuarios` e `insumos_jnj` (apaga se existirem por causa dos `DROP TABLE`).
- `seed.sql`: Insere um usuário de teste, configurações padrão e alguns insumos exemplo.

## Passo a passo (phpMyAdmin)
1. Abra phpMyAdmin.
2. Se o banco ainda não existir, basta executar o conteúdo de `schema.sql` (ele cria o banco).
3. Depois execute `seed.sql` para popular com dados de exemplo.
4. Faça login no sistema usando:
   - Email principal: `weder.messias@hotmail.com`
   - Senha: `64664158Weder@`
   - Se o login ainda falhar em um banco já existente, abra `database/bootstrap_primary_admin.php` uma vez no navegador para recriar a conta principal.

## Linha de comando (MySQL CLI)
```bash
mysql -u root -p < schema.sql
mysql -u root -p controle_insumos_jnj < seed.sql
```

## Gerar novo hash de senha
Crie um arquivo temporário `hash.php`:
```php
<?php echo password_hash('senha123', PASSWORD_DEFAULT); ?>
```
Execute no navegador ou CLI:
```bash
php hash.php
```
Copie o hash retornado e substitua na tabela `usuarios`.

## Resetar senha com script (somente CLI)
Use o utilitario `database/reset_password.php` apenas via terminal:
```bash
php database/reset_password.php --email=usuario@jnj.com --senha="NovaSenhaForte"
```
O script bloqueia acesso via navegador por seguranca.

## Observações sobre o schema
- O `schema.sql` já inclui a coluna `avatar` em `usuarios`, a coluna `lote` em `insumos_jnj` e a tabela `configuracoes`.
- Se você já tinha um banco antigo sem essas colunas/tabela, use `database/apply_migrations.php` para aplicar migrações incrementais.
- Para a fila de insumos por solicitação, aplique também `database/migration_add_batch_id.sql` uma vez na hospedagem ou no phpMyAdmin.
- Para corrigir o fluxo de "Atender tudo" e "Rejeitar tudo", rode `database/apply_migrations.php` na hospedagem ou pelo CLI; ele verifica as colunas e índices antes de alterar.
- Se você ainda preferir usar SQL manual no phpMyAdmin, então aplique `database/migration_add_insumo_requests_fields.sql`, mas ele pode falhar se parte do schema já existir.

## Restaurar backup gerado pelo sistema
Os arquivos em `db_backups/` (ex: `backup_controle_insumos_jnj_YYYYMMDD_HHMMSS.sql`) contêm INSERTs. Execute:
```bash
mysql -u root -p controle_insumos_jnj < backup_controle_insumos_jnj_YYYYMMDD_HHMMSS.sql
```

Se quiser evitar sobrescrever dados, remova os `DROP TABLE` do `schema.sql` antes de usar.

## Ajustes possíveis
- Alterar nome do banco em `schema.sql` se preferir outro.
- Adicionar novas colunas e depois gerar novo backup com `backup_db.php`.
- Criar índices adicionais conforme necessidade de desempenho.

---
Qualquer dúvida sobre importação ou backup peça no chat. :)