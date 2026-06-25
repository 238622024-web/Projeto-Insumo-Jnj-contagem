<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

$pdo = getPDO();
$threshold = 10;

$q = trim((string)($_GET['q'] ?? ''));
$where = ['quantidade <= ?'];
$params = [$threshold];
if ($q !== '') {
  $where[] = '(nome LIKE ? OR posicao LIKE ? OR codigo_barra LIKE ? OR lote LIKE ?)';
  $like = '%' . $q . '%';
  $params = array_merge($params, [$like, $like, $like, $like]);
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT id, nome, posicao, lote, codigo_barra, quantidade, unidade, validade FROM insumos_jnj WHERE $whereSql ORDER BY quantidade ASC, nome ASC");
$stmt->execute($params);
$rows = $stmt->fetchAll() ?: [];

$summaryStmt = $pdo->query(
  "SELECT
      COUNT(*) AS total,
      SUM(CASE WHEN quantidade <= 0 THEN 1 ELSE 0 END) AS zero_items,
      SUM(CASE WHEN quantidade > 0 AND quantidade <= 10 THEN 1 ELSE 0 END) AS low_items
   FROM insumos_jnj"
);
$summary = $summaryStmt->fetch() ?: [];

include __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <span class="solicitacoes-kicker">Relatório de Insumos</span>
      <h1 class="display-6 fw-semibold mb-2">Estoque Baixo</h1>
      <p class="solicitacoes-subtitle mb-0">Lista os produtos abaixo do estoque mínimo sugerido.</p>
    </div>
  </section>

  <div class="row g-4 mb-4">
    <div class="col-12 col-md-4">
      <div class="metric-card h-100">
        <div class="metric-icon metric-icon-info"><i class="fa-solid fa-boxes-stacked"></i></div>
        <div>
          <div class="metric-label">Produtos críticos</div>
          <div class="metric-value"><?= h(number_format((int)($summary['low_items'] ?? 0), 0, ',', '.')) ?></div>
          <div class="metric-help">Entre 1 e <?= h(number_format($threshold, 0, ',', '.')) ?> unidades.</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="metric-card h-100">
        <div class="metric-icon metric-icon-danger"><i class="fa-solid fa-ban"></i></div>
        <div>
          <div class="metric-label">Zerados</div>
          <div class="metric-value"><?= h(number_format((int)($summary['zero_items'] ?? 0), 0, ',', '.')) ?></div>
          <div class="metric-help">Sem saldo disponível.</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="metric-card h-100">
        <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div>
          <div class="metric-label">Limite usado</div>
          <div class="metric-value"><?= h(number_format($threshold, 0, ',', '.')) ?></div>
          <div class="metric-help">Valor padrão para alerta visual.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="section-card card border-0 shadow-sm mb-4">
    <div class="card-body p-4 p-lg-5">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md-9">
          <label class="form-label small text-muted mb-1">Buscar</label>
          <input type="text" class="form-control" name="q" value="<?= h($q) ?>" placeholder="Produto, posição, lote ou código de barras">
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          <a href="estoque_baixo.php" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i></a>
        </div>
      </form>
    </div>
  </div>

  <div class="section-card card border-0 shadow-sm">
    <div class="card-body p-4 p-lg-5">
      <span class="section-badge mb-2"><i class="fa-solid fa-triangle-exclamation"></i>Itens em alerta</span>
      <h2 class="h5 mb-3">Produtos abaixo do mínimo</h2>

      <?php if (empty($rows)): ?>
        <div class="alert alert-success mb-0">Nenhum produto abaixo do estoque mínimo foi encontrado.</div>
      <?php else: ?>
        <div class="table-responsive request-table-wrap">
          <table class="table table-hover align-middle mb-0 request-table js-no-datatable">
            <thead>
              <tr>
                <th>Produto</th>
                <th>Posição</th>
                <th>Saldo</th>
                <th>Unidade</th>
                <th>Código de barras</th>
                <th>Validade</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
                <?php
                  $statusClass = ((int)$row['quantidade'] <= 0) ? 'badge bg-danger' : 'badge bg-warning text-dark';
                ?>
                <tr>
                  <td>
                    <strong><?= h((string)$row['nome']) ?></strong>
                    <?php if (!empty($row['lote'])): ?>
                      <div class="text-muted small">Lote: <?= h((string)$row['lote']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?= h((string)$row['posicao']) ?></td>
                  <td><span class="<?= h($statusClass) ?>"><?= h(number_format((float)$row['quantidade'], 0, ',', '.')) ?></span></td>
                  <td><?= h((string)$row['unidade']) ?></td>
                  <td><?= h((string)($row['codigo_barra'] ?? '-')) ?></td>
                  <td><?= !empty($row['validade']) ? h(date('d/m/Y', strtotime((string)$row['validade']))) : '-' ?></td>
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
