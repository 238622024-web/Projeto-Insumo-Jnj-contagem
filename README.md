# Projeto-Insumo-Jnj-contagem

Sistema de controle de insumos com foco em operacao de inventario fisico, incluindo cadastro, listagem, filtros, edicao, exportacoes e fluxo rapido de contagem com scanner.

## Visao geral

Este repositorio possui dois trilhos de aplicacao:

1. `projeto insumo/`:
Aplicacao principal em PHP (uso atual recomendado).

2. `backend/` + `frontend/`:
Migracao para Node.js (API) e Angular (frontend).

Banco de dados utilizado em ambos os trilhos: MySQL (`controle_insumos_jnj`).

## Arquitetura

1. `projeto insumo/`:
PHP, Bootstrap, JavaScript e MySQL.

2. `projeto insumo/database/`:
Schema, seed e scripts utilitarios de banco.

3. `backend/`:
API REST em Node.js + Express para CRUD de insumos.

4. `frontend/`:
SPA Angular consumindo API do backend Node.

## Funcionalidades

1. Autenticacao de usuarios.
2. Cadastro, edicao e exclusao de materiais.
3. Listagem com filtros por data de entrada.
4. Indicadores de validade (expirado, vencendo em 7 dias e 30 dias).
5. Exportacao para Excel e PDF.
6. Contagem fisica com busca por codigo de barras ou nome.
7. Suporte a scanner fisico (HID, ex: Honeywell).
8. Scanner por camera na tela de cadastro e contagem.
9. Alertas e feedback sonoro na operacao de contagem.

## Como o sistema funciona no dia a dia

### Fluxo de cadastro

1. Usuario acessa `cadastrar.php`.
2. Informa dados do material (nome, posicao, lote, quantidade, datas, observacoes).
3. Pode preencher codigo de barras manualmente ou via camera.
4. Sistema grava no MySQL em `insumos_jnj`.

### Fluxo de contagem fisica

1. Usuario acessa `contagem.php`.
2. Campo de leitura recebe foco automaticamente.
3. Operador pode:
  1. Bipar com scanner fisico (HID) e registrar por `Enter`.
  2. Escanear por camera.
  3. Digitar nome/codigo manualmente.
4. Sistema busca nesta ordem:
  1. `codigo_barra` exato.
  2. `nome` exato.
  3. `nome` aproximado (`LIKE`) com validacao de ambiguidade.
5. Quantidade e atualizada e `data_contagem` recebe data atual.
6. Sistema retorna feedback visual e sonoro:
  1. Sucesso: beep simples.
  2. Erro: beep duplo.

## Requisitos

1. PHP 8.x (XAMPP ou Laragon).
2. MySQL 5.7+ ou 8.x.
3. Node.js 20+ (apenas para trilho de migracao Node/Angular).
4. Navegador moderno (Chrome/Edge recomendado para scanner por camera).

## Setup rapido do modo principal (PHP)

1. Inicie Apache e MySQL no XAMPP/Laragon.
2. Abra a raiz do projeto em:
`http://localhost/Projeto-Insumo-Jnj-contagem/projeto%20insumo/`

3. Inicialize banco e dados base:
`http://localhost/Projeto-Insumo-Jnj-contagem/projeto%20insumo/database/init_db.php`

4. Login padrao de teste (seed):
Email: `usuario@jnj.com`
Senha: `senha123`

## Setup da migracao (Node + Angular)

### Backend

```powershell
Set-Location "c:\xampp\htdocs\Projeto-Insumo-Jnj-contagem\backend"
npm install
Copy-Item .env.example .env
```

Preencha o `.env`:

```env
PORT=3000
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=
DB_NAME=controle_insumos_jnj
```

Executar:

```powershell
npm run dev
```

Health check:

`http://localhost:3000/health`

### Frontend

```powershell
Set-Location "c:\xampp\htdocs\Projeto-Insumo-Jnj-contagem\frontend"
npm install
npm start
```

Aplicacao:

`http://localhost:4200`

Observacao: o frontend usa proxy `/api` para `http://localhost:3000`.

## Endpoints da API Node

1. `GET /health`
2. `GET /api/insumos`
3. `GET /api/insumos/:id`
4. `POST /api/insumos`
5. `PUT /api/insumos/:id`
6. `DELETE /api/insumos/:id`

## Scanner fisico (Honeywell e similares)

Para melhor desempenho na contagem:

1. Configurar scanner em modo `USB Keyboard (HID)`.
2. Configurar sufixo de leitura para `Enter/CR`.
3. Manter foco no campo de leitura da tela `contagem.php`.

## Seguranca e boas praticas

1. Nao versionar `.env` com credenciais reais.
2. Nao manter scripts administrativos acessiveis por navegador em producao.
3. Usar usuario de banco dedicado para aplicacao.
4. Revisar backups antes de versionar para evitar dados sensiveis.

## Estrutura resumida

1. `projeto insumo/`: aplicacao PHP principal.
2. `projeto insumo/assets/js/`: scripts de UI e scanner.
3. `projeto insumo/database/`: schema, seed e utilitarios de banco.
4. `backend/`: API Node/Express da migracao.
5. `frontend/`: app Angular da migracao.

## Solucao de problemas

1. Erro de conexao MySQL:
verifique servico ativo, host, porta e credenciais.

2. Scanner fisico nao registra:
confirmar modo HID e sufixo `Enter`.

3. Scanner de camera nao abre:
verificar permissao de camera no navegador.

4. Layout estranho em notebook:
limpar cache do navegador e recarregar (`Ctrl + F5`).

## Documentacao complementar

1. `backend/README.md`
2. `frontend/README.md`
3. `projeto insumo/database/README-database.md`
4. `projeto insumo/README-SETUP.txt`