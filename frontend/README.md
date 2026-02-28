# Frontend Angular - Controle de Insumos

> **Opcional:** este frontend Angular faz parte da migração para JavaScript.
> Para usar o sistema como antes, rode o PHP e abra `http://localhost:8000/index.php`.

Frontend migrado para Angular consumindo a API Node.js criada em `../backend`.

## Requisitos

- Node.js 20+
- Backend Node rodando em `http://localhost:3000`

## Instalação

```bash
npm install
```

## Rodar em desenvolvimento

```bash
npm start
```

O script `start` já usa `proxy.conf.json`, então chamadas para `/api/*` são encaminhadas para o backend (`http://localhost:3000`).

Aplicação: `http://localhost:4200`

## Build

```bash
npm run build
```

## Funcionalidades migradas

- Listagem de insumos
- Filtro por período de entrada
- Estatísticas (expirados, 7 dias, 30 dias)
- Cadastro de insumo
- Edição de insumo
- Exclusão de insumo

## Fluxo completo (backend + frontend)

Terminal 1 (backend):

```bash
cd ../backend
npm run dev
```

Terminal 2 (frontend):

```bash
npm start
```

Se aparecer erro de banco no backend, ajuste as credenciais em `../backend/.env`.
