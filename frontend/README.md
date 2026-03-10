# Frontend Angular (Migracao)

Interface Angular para consumo da API Node.js do projeto de insumos.

## Contexto

1. Este frontend faz parte da migracao para JavaScript.
2. O sistema principal em operacao continua no PHP (`projeto insumo/`).
3. Este app consome endpoints de `../backend`.

## Requisitos

1. Node.js 20+
2. Backend Node ativo em `http://localhost:3000`

## Instalacao

```bash
npm install
```

## Executar em desenvolvimento

```bash
npm start
```

Aplicacao local:

`http://localhost:4200`

## Proxy de API

O script `start` usa `proxy.conf.json` para encaminhar chamadas `/api/*` ao backend em `http://localhost:3000`.

## Build

```bash
npm run build
```

## Funcionalidades implementadas

1. Listagem de insumos.
2. Filtro por periodo de entrada.
3. Estatisticas de validade (expirados, 7 dias, 30 dias).
4. Cadastro de insumo.
5. Edicao de insumo.
6. Exclusao de insumo.

## Fluxo completo (Backend + Frontend)

1. Iniciar backend:

```bash
cd ../backend
npm run dev
```

2. Iniciar frontend:

```bash
npm start
```

3. Abrir no navegador:

`http://localhost:4200`

## Troubleshooting

1. API nao responde no frontend:
verifique se `../backend` esta ativo na porta 3000.

2. Erros de banco no backend:
ajuste `../backend/.env` com credenciais corretas.

3. Problemas de proxy:
reinicie `npm start` apos alterar `proxy.conf.json`.

## Referencia cruzada

1. README principal: `../README.md`
2. Backend API: `../backend/README.md`
