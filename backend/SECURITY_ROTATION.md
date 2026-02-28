# Rotação de credenciais (MySQL)

Este projeto já removeu `backend/.env` do versionamento, mas como ele chegou a ser commitado antes, aplique rotação por segurança.

## 1) Criar usuário de aplicação (não usar `root`)

No MySQL, execute:

```sql
CREATE USER IF NOT EXISTS 'app_insumos'@'localhost' IDENTIFIED BY 'SENHA_FORTE_AQUI';
GRANT SELECT, INSERT, UPDATE, DELETE ON controle_insumos_jnj.* TO 'app_insumos'@'localhost';
FLUSH PRIVILEGES;
```

## 2) Atualizar `backend/.env` local

Use:

```dotenv
PORT=3000
DB_HOST=localhost
DB_PORT=3306
DB_USER=app_insumos
DB_PASSWORD=SENHA_FORTE_AQUI
DB_NAME=controle_insumos_jnj
```

## 3) Validar backend

```bash
cd backend
npm install
npm run dev
```

Depois teste:

```bash
curl http://localhost:3000/health
```

## 4) Se o repositório for público

- Revogue/alterne qualquer senha antiga que já tenha sido usada.
- No GitHub, revise *Settings > Security > Secret scanning*.
- Se quiser limpeza total do histórico, faça reescrita de histórico e `push --force` (somente com alinhamento do time).
