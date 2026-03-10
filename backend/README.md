# Backend Node.js (Migracao)

Backend REST para o modulo de insumos durante a migracao do sistema PHP para JavaScript.

## Contexto

1. Este backend e opcional no estado atual do projeto.
2. O fluxo principal em producao continua em `projeto insumo/` (PHP).
3. O frontend Angular (`../frontend`) consome esta API.

## Stack

1. Node.js 20+
2. Express
3. MySQL

## Requisitos

1. Node.js 20+ instalado.
2. MySQL ativo com banco `controle_insumos_jnj`.
3. Tabela `insumos_jnj` criada via scripts de `../projeto insumo/database`.

## Configuracao

1. Instalar dependencias:

```bash
npm install
```

2. Criar `.env` a partir do exemplo:

```bash
copy .env.example .env
```

3. Ajustar variaveis:

```env
PORT=3000
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=
DB_NAME=controle_insumos_jnj
```

## Executar

```bash
npm run dev
```

Health check:

`http://localhost:3000/health`

## Endpoints

1. `GET /health`
2. `GET /api/insumos`
3. `GET /api/insumos/:id`
4. `POST /api/insumos`
5. `PUT /api/insumos/:id`
6. `DELETE /api/insumos/:id`

Filtros suportados em `GET /api/insumos`:

1. `start_date=YYYY-MM-DD`
2. `end_date=YYYY-MM-DD`

## Exemplo de consulta

```bash
curl "http://localhost:3000/api/insumos?start_date=2026-01-01&end_date=2026-12-31"
```

## Exemplo de payload (POST/PUT)

```json
{
  "data_contagem": "2026-02-27",
  "unidade": "UN",
  "nome": "Alcool 70%",
  "posicao": "A1",
  "lote": "L123",
  "quantidade": 10,
  "data_entrada": "2026-02-20",
  "validade": "2026-12-31",
  "observacoes": "Uso interno"
}
```

## Seguranca

1. Nunca versione `.env`.
2. Evite usuario `root` em producao.
3. Consulte `SECURITY_ROTATION.md` para rotacao de credenciais.

## Troubleshooting

1. `ECONNREFUSED` ou erro de banco:
verifique MySQL ativo e credenciais no `.env`.

2. `npm run dev` falha:
garanta que o comando esta sendo executado dentro de `backend/`.

## Referencia cruzada

1. README principal: `../README.md`
2. Frontend Angular: `../frontend/README.md`
