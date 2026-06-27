# Projeto Insumo JNJ Contagem

Sistema de controle de insumos para operacao diaria, contagem fisica, entradas, saidas, pedidos e relatórios de consumo. O projeto esta organizado para uso local em PHP/MySQL, com um trilho de migracao em Node.js + Angular mantido no repositorio.

## Visao geral

O sistema possui tres pilares de uso:

1. Controle de estoque e contagem fisica.
2. Fluxo de solicitacao, aprovacao e consumo de insumos.
3. Relatorios, exportacoes e auditoria operacional.

O banco principal e o MySQL `controle_insumos_jnj`.

## Estrutura do repositorio

1. [projeto insumo/](projeto%20insumo/) - aplicacao principal em PHP.
2. [backend/](backend/) - API Node.js + Express da migracao.
3. [frontend/](frontend/) - frontend Angular da migracao.

## O que o sistema faz hoje

### Autenticacao e usuarios

1. Login e logout.
2. Criacao de conta e solicitacao de aprovacao.
3. Alteracao de perfil, senha e avatar.
4. Controle de permissao por papel de usuario.

### Estoque e materiais

1. Cadastro de insumos com nome, posicao, lote, quantidade, unidade, datas e observacoes.
2. Edicao e exclusao de materiais.
3. Listagem de estoque com filtros por data, unidade e validade.
4. Indicadores de validade: expirado, vencendo em 7 dias e vencendo em 30 dias.
5. Exportacao da lista do estoque para Excel e PDF.

### Contagem fisica

1. Contagem por busca de codigo de barras ou nome.
2. Suporte a scanner fisico em modo HID.
3. Leitura por camera no navegador.
4. Feedback visual e sonoro durante a contagem.
5. Atualizacao de quantidade e data de contagem no cadastro do material.

### Saida e consumo

1. Registro de baixa manual de material.
2. Registro de consumo por setor.
3. Consumo por picking/QR Code.
4. Lista de recentes do picking salva no navegador, sem misturar com o historico de atendimento.
5. Botao para zerar saídas registradas diretamente na pagina de consumo.
6. Movimentacao de saida alimentando relatorios de consumo.

### Pedidos de insumo

1. Usuario solicita insumo com setor, unidade, quantidade e motivo.
2. Admin aprova ou rejeita pedidos pendentes.
3. Pedido aprovado gera registro de consumo.
4. Usuario pode editar pedidos pendentes antes do atendimento.
5. Usuario pode apagar todos os proprios pedidos quando necessario.
6. Historico consolidado por status do pedido.
7. Exportacao do historico em PDF.

### Relatorios

1. Consumo por setor.
2. Consumo por produto e setor.
3. Historico de pedidos de insumo.
4. Historico geral de insumos.
5. Estoque baixo.

### Atualizacao de estoque pela contagem

1. O admin baixa a planilha exportada do sistema.
2. Revise os dados no Excel.
3. Suba o arquivo pela area administrativa.
4. O sistema mostra uma pre-visualizacao antes de aplicar.
5. Ao aplicar, o estoque e atualizado com base na contagem aprovada.

## Fluxos principais

### Fluxo de cadastro

1. Abrir [cadastrar.php](projeto%20insumo/cadastrar.php).
2. Informar dados do material.
3. Salvar no MySQL em `insumos_jnj`.

### Fluxo de contagem

1. Abrir [contagem.php](projeto%20insumo/contagem.php).
2. Buscar o item por codigo, nome ou camera.
3. Registrar a contagem e atualizar a quantidade.

### Fluxo de exportacao e aplicacao de contagem

1. Abrir [index.php](projeto%20insumo/index.php).
2. Exportar a lista para Excel.
3. Conferir a planilha.
4. Abrir [Atualizar estoque pela contagem](projeto%20insumo/atualizar_estoque_pela_contagem.php).
5. Enviar o arquivo e revisar a pre-visualizacao.
6. Aplicar a importacao no estoque.

### Fluxo de pedidos de insumo

1. Usuario cria o pedido.
2. Admin acompanha a fila em [pedidos-insumos-pendentes.php](projeto%20insumo/pedidos-insumos-pendentes.php).
3. Admin seleciona a unidade entregue e a quantidade.
4. O sistema registra o consumo e atualiza o historico.

### Fluxo de consumo por tablet

1. Abrir [picking_qrcode.php](projeto%20insumo/picking_qrcode.php) ou [saida_consumo.php](projeto%20insumo/saida_consumo.php).
2. Ler o item pelo tablet ou camera.
3. Dar baixa no estoque.
4. O consumo entra nos relatorios.

### Limpeza rapida de dados operacionais

1. Abra [saida_consumo.php](projeto%20insumo/saida_consumo.php).
2. Use o botao "Zerar saídas registradas" para limpar a tabela `saida_consumo`.
3. Se quiser limpar o historico de pedidos de insumo, use [historico-pedidos-insumos.php](projeto%20insumo/historico-pedidos-insumos.php) ou [meus-pedidos-insumos.php](projeto%20insumo/meus-pedidos-insumos.php), conforme o tipo de dado.

## Menus principais da aplicacao

1. Administração.
2. Estoque.
3. Relatorio de Insumos.
4. Perfil e configuracoes.

## Estado atual do sistema

1. Relatorios de consumo por setor e por produto usam pedidos atendidos/aprovados como base principal.
2. O picking por QR mostra os recentes lidos neste navegador, nao o historico de atendimento.
3. A pagina de saida manual tem acao para zerar todas as baixas registradas.
4. A sidebar e os submenus foram ajustados para uso em celular e tablet.

## Telas mais importantes

1. [index.php](projeto%20insumo/index.php) - painel/listagem do estoque.
2. [cadastrar.php](projeto%20insumo/cadastrar.php) - cadastro de materiais.
3. [contagem.php](projeto%20insumo/contagem.php) - contagem fisica.
4. [saida_consumo.php](projeto%20insumo/saida_consumo.php) - saida manual/consumo.
5. [picking_qrcode.php](projeto%20insumo/picking_qrcode.php) - baixa por QR.
6. [pedidos-insumos-pendentes.php](projeto%20insumo/pedidos-insumos-pendentes.php) - aprovacao de pedidos.
7. [historico-pedidos-insumos.php](projeto%20insumo/historico-pedidos-insumos.php) - historico consolidado.
8. [relatorio_consumo_setor.php](projeto%20insumo/relatorio_consumo_setor.php) - consumo por setor.
9. [relatorio_consumo_produto.php](projeto%20insumo/relatorio_consumo_produto.php) - consumo por produto e setor.
10. [atualizar_estoque_pela_contagem.php](projeto%20insumo/atualizar_estoque_pela_contagem.php) - importacao administrativa da contagem.

## Requisitos

1. PHP 8.x.
2. MySQL 5.7+ ou 8.x.
3. Apache via XAMPP, Laragon ou similar.
4. Navegador moderno para camera e leitura por QR.

## Instalacao rapida no modo PHP

1. Inicie Apache e MySQL.
2. Abra o projeto dentro do `htdocs` ou `www`.
3. Execute a inicializacao do banco em [database/init_db.php](projeto%20insumo/database/init_db.php).
4. Abra a aplicacao principal em [projeto insumo/](projeto%20insumo/).

## Credenciais de teste

O projeto pode vir com seed de teste no banco. Confira os dados criados pelo script de inicializacao do seu ambiente.

## Trilho de migracao

O repositorio tambem mantem uma arquitetura alternativa em:

1. [backend/](backend/) - API Node.js.
2. [frontend/](frontend/) - frontend Angular.

Esses modulos podem ser usados como base de migracao, mas o fluxo operacional atual esta em PHP.

## Arquitetura tecnica

### PHP principal

1. Bootstrap, Font Awesome, jQuery e DataTables.
2. Sessao de usuario com permissao por papel.
3. `includes/header.php` e `includes/footer.php` padronizando layout.
4. `style.css` como base visual principal.

### Banco de dados

1. Tabela `insumos_jnj` para materiais e contagem.
2. Tabela `insumo_requests` para pedidos de insumo.
3. Tabela `saida_consumo` para consumos e saidas.
4. Tabela `usuarios` para login e permissao.

## Boas praticas adotadas

1. Atualizacao de estoque com validacao antes de aplicar.
2. Pre-visualizacao da importacao para evitar erro operacional.
3. Registro de consumo separado por setor e produto.
4. Menus e telas responsivos para uso em tablet e celular.
5. Historico consolidado para rastreabilidade.

## Problemas comuns

1. Erro de conexao com o banco: verifique Apache, MySQL e credenciais.
2. Camera ou QR nao funcionando: libere permissao do navegador.
3. Importacao da contagem falhando: confirme se a planilha foi exportada pelo proprio sistema.
4. Tela apertada no celular: o layout foi ajustado para responsividade, mas use navegador atualizado.

## Documentacao complementar

1. [projeto insumo/README-SETUP.txt](projeto%20insumo/README-SETUP.txt)
2. [projeto insumo/ESTRUTURA_PROJETO.md](projeto%20insumo/ESTRUTURA_PROJETO.md)
3. [projeto insumo/database/README-database.md](projeto%20insumo/database/README-database.md)
4. [backend/README.md](backend/README.md)
5. [frontend/README.md](frontend/README.md)

## Resumo rapido

Este sistema hoje cobre:

1. Cadastro e manutencao de materiais.
2. Contagem fisica com scanner e camera.
3. Exportacao para Excel e PDF.
4. Importacao administrativa da contagem para atualizar o estoque.
5. Saida, consumo e picking por tablet.
6. Pedidos de insumo, aprovacao e historico.
7. Relatorios por produto, setor e historico geral.