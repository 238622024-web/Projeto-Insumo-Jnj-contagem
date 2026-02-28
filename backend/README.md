# Backend Node.js (migração do PHP)

> **Observação:** este backend é opcional e usado apenas para migração.
> O fluxo principal do projeto continua sendo o PHP em `projeto insumo/index.php`.

Este backend foi criado para iniciar a migração do sistema para JavaScript sem interromper o projeto PHP.

## Requisitos

- Node.js 20+
- Banco MySQL com a tabela `insumos_jnj`

## Configuração

1. Copie `.env.example` para `.env`
2. Ajuste credenciais do banco
3. Instale dependências:

```bash
npm install
```

## Executar

```bash
npm run dev
```

## Endpoints

- `GET /health`
- `GET /api/insumos`
  - Query params opcionais:
    - `start_date=YYYY-MM-DD`
    - `end_date=YYYY-MM-DD`
- `GET /api/insumos/:id`
- `POST /api/insumos`
- `PUT /api/insumos/:id`
- `DELETE /api/insumos/:id`

### Exemplo

```bash
curl "http://localhost:3000/api/insumos?start_date=2026-01-01&end_date=2026-12-31"
```

### Exemplo de payload (POST/PUT)

```json
{
  "data_contagem": "2026-02-27",
  "unidade": "UN",
  "nome": "Álcool 70%",
  "posicao": "A1",
  "lote": "L123",
  "quantidade": 10,
  "data_entrada": "2026-02-20",
  "validade": "2026-12-31",
  "observacoes": "Uso interno"
}
```

## Próximos passos sugeridos

- Migrar login/auth para JWT
- Integrar frontend Angular consumindo `/api/insumos`
