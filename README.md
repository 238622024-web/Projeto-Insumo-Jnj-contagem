// ...existing code...
# Projeto-Insumo-Jnj-contagem

## Modo padrão (recomendado agora)

Este projeto deve abrir em **PHP**, como estava originalmente.

### Abrir localmente (PHP)
# Projeto-Insumo-Jnj-contagem

Sistema de controle de insumos com **backend em Node.js** e **frontend em Angular**.

## Arquitetura (atual)

- Backend API: `backend/` (Node.js + Express + MySQL)
- Frontend: `frontend/` (Angular)
- Banco: MySQL (`controle_insumos_jnj`)
- Projeto PHP em `projeto insumo/`: legado/apoio (não é o fluxo principal)

## Requisitos

- Node.js 20+
- MySQL ativo na porta `3306`

## Configuração rápida

### 1) Banco de dados

Crie o banco e aplique o schema/migrations da pasta `projeto insumo/database`.

### 2) Backend

```powershell
Set-Location "c:\Users\weder\Downloads\ProjetoInsumos\Projeto-Insumo-Jnj-contagem\backend"
npm install
Copy-Item .env.example .env
```

Edite o `.env` com suas credenciais de MySQL:

```env
PORT=3000
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=
DB_NAME=controle_insumos_jnj
```

Suba o backend:

```powershell
npm run dev
```

Teste:

```text
http://localhost:3000/health
```

### 3) Frontend

```powershell
Set-Location "c:\Users\weder\Downloads\ProjetoInsumos\Projeto-Insumo-Jnj-contagem\frontend"
npm install
npm start
```

Abra:

```text
http://localhost:4200
```

> O frontend usa proxy para `/api` apontando para `http://localhost:3000`.

## Endpoints principais (backend)

- `GET /health`
- `GET /api/insumos`
- `GET /api/insumos/:id`
- `POST /api/insumos`
- `PUT /api/insumos/:id`
- `DELETE /api/insumos/:id`

## Troubleshooting

- `SQLSTATE[HY000] [2002]` ou `ECONNREFUSED` no backend:
  - MySQL não está ativo, ou credenciais/porta do `.env` estão incorretas.
- `npm run dev` falha:
  - rode dentro da pasta `backend` ou use `npm --prefix "...\backend" run dev`.

## Referências

- Backend: `backend/README.md`
- Frontend: `frontend/README.md`