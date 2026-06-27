<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

$pdo = getPDO();
$nomesInsumos = require __DIR__ . '/materiais-lista.php';
sort($nomesInsumos, SORT_NATURAL | SORT_FLAG_CASE);

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$produto = trim((string)($_GET['produto'] ?? ''));
$errors = [];

if ($produto === '' && !empty($_SESSION['report_consumo_produto_last_produto'])) {
  $produto = trim((string)$_SESSION['report_consumo_produto_last_produto']);
}

if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
  $errors[] = 'Data inicial inválida.';
  $from = '';
}
if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
  $errors[] = 'Data final inválida.';
  $to = '';
}

if ($from !== '' && $to === '') {
  $to = date('Y-m-d');
}

$productStmt = $pdo->query(
  "SELECT DISTINCT insumo_nome AS produto_nome
   FROM insumo_requests
   WHERE status = 'approved' AND insumo_nome IS NOT NULL AND insumo_nome <> ''
   ORDER BY insumo_nome ASC"
);
$recentProductOptions = $productStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
$productOptions = array_values(array_unique(array_filter(array_merge($nomesInsumos, $recentProductOptions))));
sort($productOptions, SORT_NATURAL | SORT_FLAG_CASE);

if ($produto !== '' && !in_array($produto, $productOptions, true)) {
  $errors[] = 'Produto inválido.';
  $produto = '';
}

$hasProductSelected = $produto !== '';

if ($produto !== '') {
  $_SESSION['report_consumo_produto_last_produto'] = $produto;
}

$quantityExpr = 'COALESCE(ir.quantidade_entregue, ir.quantidade)';
$unitExpr = "COALESCE(NULLIF(ir.unidade_entregue, ''), ir.unidade)";

$where = ['1 = 1'];
$params = [];
if ($from !== '') {
  $where[] = 'DATE(processed_at) >= ?';
  $params[] = $from;
}
if ($to !== '') {
  $where[] = 'DATE(processed_at) <= ?';
  $params[] = $to;
}
if ($hasProductSelected) {
  $where[] = 'insumo_nome = ?';
  $params[] = $produto;
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare(
  "SELECT setor, insumo_nome AS produto_nome, $unitExpr AS unidade, COUNT(*) AS movimentos, SUM($quantityExpr) AS quantidade_total
   FROM insumo_requests ir
  WHERE ir.status = 'approved' AND ir.processed_at IS NOT NULL AND ir.processed_at <> '' AND ir.insumo_nome IS NOT NULL AND ir.insumo_nome <> '' AND $whereSql
   GROUP BY setor, produto_nome, $unitExpr
   ORDER BY quantidade_total DESC, movimentos DESC, setor ASC, produto_nome ASC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll() ?: [];

$summaryStmt = $pdo->prepare(
  "SELECT COUNT(*) AS movimentos, COALESCE(SUM($quantityExpr), 0) AS quantidade_total
  FROM insumo_requests ir
  WHERE ir.status = 'approved' AND ir.processed_at IS NOT NULL AND ir.processed_at <> '' AND ir.insumo_nome IS NOT NULL AND ir.insumo_nome <> '' AND $whereSql"
);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch() ?: [];

include __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <span class="solicitacoes-kicker">Relatório de Insumos</span>
      <h1 class="display-6 fw-semibold mb-2">Consumo por Produto e Setor</h1>
      <p class="solicitacoes-subtitle mb-0">Mostra quais pedidos aprovados pelo admin foram atendidos, por produto e setor, no período selecionado.</p>
      <?php if ($from !== '' || $to !== ''): ?>
        <div class="insumo-historico-hero-chips mt-3">
          <span class="insumo-historico-chip"><i class="fa-solid fa-user-check"></i>Baseado em pedidos atendidos</span>
          <?php if ($from !== ''): ?>
            <span class="insumo-historico-chip"><i class="fa-solid fa-calendar-day"></i>Atendido desde: <?= h(date('d/m/Y', strtotime($from))) ?></span>
          <?php endif; ?>
          <?php if ($to !== ''): ?>
            <span class="insumo-historico-chip"><i class="fa-solid fa-calendar-check"></i>Atendido até: <?= h(date('d/m/Y', strtotime($to))) ?></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-warning"><?= h(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <div class="row g-4 mb-4">
    <div class="col-12 col-md-6">
      <div class="metric-card h-100">
        <div class="metric-icon metric-icon-info"><i class="fa-solid fa-boxes-stacked"></i></div>
        <div>
          <div class="metric-label">Movimentos</div>
          <div class="metric-value"><?= h(number_format((int)($summary['movimentos'] ?? 0), 0, ',', '.')) ?></div>
          <div class="metric-help">Pedidos aprovados e atendidos no período.</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <div class="metric-card h-100">
        <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-weight-scale"></i></div>
        <div>
          <div class="metric-label">Quantidade total</div>
          <div class="metric-value"><?= h(number_format((float)($summary['quantidade_total'] ?? 0), 2, ',', '.')) ?></div>
          <div class="metric-help">Somatório atendido por produto.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="section-card card border-0 shadow-sm mb-4">
    <div class="card-body p-4 p-lg-5">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
          <label class="form-label small text-muted mb-1">Data inicial</label>
          <input type="date" name="from" class="form-control" value="<?= h($from) ?>">
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label small text-muted mb-1">Data final</label>
          <input type="date" name="to" class="form-control" value="<?= h($to) ?>">
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label small text-muted mb-1">Produto</label>
          <select name="produto" class="form-select" required>
            <option value="" disabled <?= $produto === '' ? 'selected' : '' ?>>Selecione o produto</option>
            <?php foreach ($productOptions as $productOption): ?>
              <option value="<?= h((string)$productOption) ?>" <?= $produto === (string)$productOption ? 'selected' : '' ?>><?= h((string)$productOption) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-4 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          <a href="relatorio_consumo_produto.php" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i>Limpar</a>
        </div>
      </form>
    </div>
  </div>

  <div class="section-card card border-0 shadow-sm">
    <div class="card-body p-4 p-lg-5">
      <span class="section-badge mb-2"><i class="fa-solid fa-ranking-star"></i>Ranking</span>
      <h2 class="h5 mb-3">Produtos mais consumidos</h2>

      <?php if (!$hasProductSelected): ?>
        <div class="alert alert-info mb-0">Escolha um produto acima para mostrar o consumo correspondente.</div>
      <?php elseif (empty($rows)): ?>
        <div class="alert alert-info mb-0">Nenhum consumo encontrado para o produto selecionado no período.</div>
      <?php else: ?>
        <div class="table-responsive request-table-wrap">
          <table class="table table-hover align-middle mb-0 request-table js-no-datatable">
            <thead>
              <tr>
                <th>Setor</th>
                <th>Produto</th>
                <th>Unidade</th>
                <th>Movimentos</th>
                <th>Quantidade total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td><strong><?= h((string)($row['setor'] ?? 'Sem setor')) ?></strong></td>
                  <td><strong><?= h((string)$row['produto_nome']) ?></strong></td>
                  <td><?= h((string)$row['unidade']) ?></td>
                  <td><?= h(number_format((int)$row['movimentos'], 0, ',', '.')) ?></td>
                  <td><?= h(number_format((float)$row['quantidade_total'], 2, ',', '.')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
