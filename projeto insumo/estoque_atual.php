<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

$pdo = getPDO();
ensureUserAuthSchema();

$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'normal', 'low', 'zero', 'expiring'], true)) {
  $statusFilter = 'all';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$lowStockThreshold = 10;
$nearExpiryDays = 30;

$where = ['1 = 1'];
$params = [];

if ($q !== '') {
  $where[] = '(nome LIKE ? OR posicao LIKE ? OR codigo_barra LIKE ? OR lote LIKE ?)';
  $searchLike = '%' . $q . '%';
  $params[] = $searchLike;
  $params[] = $searchLike;
  $params[] = $searchLike;
  $params[] = $searchLike;
}

$statusHaving = '';
if ($statusFilter !== 'all') {
  switch ($statusFilter) {
    case 'normal':
      $statusHaving = 'HAVING quantidade > ' . $lowStockThreshold;
      break;
    case 'low':
      $statusHaving = 'HAVING quantidade > 0 AND quantidade <= ' . $lowStockThreshold;
      break;
    case 'zero':
      $statusHaving = 'HAVING quantidade <= 0';
      break;
    case 'expiring':
      $statusHaving = 'HAVING validade IS NOT NULL AND DATEDIFF(validade, CURDATE()) BETWEEN 0 AND ' . $nearExpiryDays;
      break;
  }
}

$baseWhereSql = implode(' AND ', $where);

$allItemsStmt = $pdo->prepare("SELECT id, nome, posicao, lote, codigo_barra, quantidade, data_entrada, validade, observacoes, unidade FROM insumos_jnj WHERE $baseWhereSql ORDER BY nome ASC");
$allItemsStmt->execute($params);
$allItems = $allItemsStmt->fetchAll() ?: [];

$items = [];
foreach ($allItems as $item) {
  $quantity = (int)($item['quantidade'] ?? 0);
  $daysToExpire = !empty($item['validade']) ? (int)floor((strtotime((string)$item['validade']) - strtotime(date('Y-m-d'))) / 86400) : null;
  $statusKey = 'normal';
  if ($quantity <= 0) {
    $statusKey = 'zero';
  } elseif ($quantity <= $lowStockThreshold) {
    $statusKey = 'low';
  }
  if ($daysToExpire !== null && $daysToExpire >= 0 && $daysToExpire <= $nearExpiryDays) {
    $statusKey = 'expiring';
  }

  if ($statusFilter !== 'all' && $statusKey !== $statusFilter) {
    continue;
  }

  $item['quantity_int'] = $quantity;
  $item['status_key'] = $statusKey;
  $item['days_to_expire'] = $daysToExpire;
  $items[] = $item;
}

$totalItems = count($items);
$totalPages = max(1, (int)ceil($totalItems / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$pagedItems = array_slice($items, $offset, $perPage);

$counts = [
  'total' => count($allItems),
  'normal' => 0,
  'low' => 0,
  'zero' => 0,
  'expiring' => 0,
];

foreach ($allItems as $item) {
  $quantity = (int)($item['quantidade'] ?? 0);
  $daysToExpire = !empty($item['validade']) ? (int)floor((strtotime((string)$item['validade']) - strtotime(date('Y-m-d'))) / 86400) : null;
  $statusKey = 'normal';
  if ($quantity <= 0) {
    $statusKey = 'zero';
  } elseif ($quantity <= $lowStockThreshold) {
    $statusKey = 'low';
  }
  if ($daysToExpire !== null && $daysToExpire >= 0 && $daysToExpire <= $nearExpiryDays) {
    $statusKey = 'expiring';
  }
  $counts[$statusKey]++;
}

$showingStart = $totalItems > 0 ? $offset + 1 : 0;
$showingEnd = min($totalItems, $offset + $perPage);

$buildQuery = static function (array $overrides = []) use ($q, $statusFilter, $page): string {
  $base = [
    'q' => $q,
    'status' => $statusFilter,
    'page' => $page,
  ];
  $query = array_merge($base, $overrides);
  foreach ($query as $key => $value) {
    if ($value === '' || $value === null) {
      unset($query[$key]);
    }
  }
  return 'estoque_atual.php' . (!empty($query) ? '?' . http_build_query($query) : '');
};

$statusMeta = [
  'normal' => ['label' => 'Normal', 'class' => 'bg-success'],
  'low' => ['label' => 'Baixo', 'class' => 'bg-warning text-dark'],
  'zero' => ['label' => 'Zerado', 'class' => 'bg-danger'],
  'expiring' => ['label' => 'Vencendo', 'class' => 'bg-info text-dark'],
];

include __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="solicitacoes-kicker">Estoque</span>
          <h1 class="display-6 fw-semibold mb-2">Estoque Atual</h1>
          <p class="solicitacoes-subtitle mb-0">Acompanhe saldo, estoque mínimo sugerido, status e localização dos produtos em uma única tela.</p>
        </div>
        <div class="text-lg-end">
          <div class="solicitacoes-pill">Visão consolidada</div>
          <small class="text-muted d-block mt-2">A posição cadastrada no item é usada como localização.</small>
        </div>
      </div>

      <div class="row g-3 mt-4">
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-info"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div>
              <div class="metric-label">Produtos no estoque</div>
              <div class="metric-value"><?= h(number_format((int)$counts['total'], 0, ',', '.')) ?></div>
              <div class="metric-help">Itens cadastrados em [insumos_jnj].</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-success"><i class="fa-solid fa-circle-check"></i></div>
            <div>
              <div class="metric-label">Status normal</div>
              <div class="metric-value"><?= h(number_format((int)$counts['normal'], 0, ',', '.')) ?></div>
              <div class="metric-help">Saldo acima do mínimo sugerido.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div>
              <div class="metric-label">Estoque baixo</div>
              <div class="metric-value"><?= h(number_format((int)$counts['low'], 0, ',', '.')) ?></div>
              <div class="metric-help">Saldo entre 1 e <?= h(number_format($lowStockThreshold, 0, ',', '.')) ?>.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-danger"><i class="fa-solid fa-ban"></i></div>
            <div>
              <div class="metric-label">Zerados / vencendo</div>
              <div class="metric-value"><?= h(number_format((int)$counts['zero'] + (int)$counts['expiring'], 0, ',', '.')) ?></div>
              <div class="metric-help">Itens sem saldo ou próximos do vencimento.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="section-card card border-0 shadow-sm mb-4">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md-6 col-lg-7">
          <label class="form-label small text-muted mb-1">Buscar produto, posição, lote ou código de barras</label>
          <input type="text" class="form-control" name="q" value="<?= h($q) ?>" placeholder="Ex.: caixa, inv, 12345...">
        </div>
        <div class="col-12 col-md-3 col-lg-2">
          <label class="form-label small text-muted mb-1">Status</label>
          <select class="form-select" name="status">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Todos</option>
            <option value="normal" <?= $statusFilter === 'normal' ? 'selected' : '' ?>>Normal</option>
            <option value="low" <?= $statusFilter === 'low' ? 'selected' : '' ?>>Baixo</option>
            <option value="zero" <?= $statusFilter === 'zero' ? 'selected' : '' ?>>Zerado</option>
            <option value="expiring" <?= $statusFilter === 'expiring' ? 'selected' : '' ?>>Vencendo</option>
          </select>
        </div>
        <div class="col-12 col-md-3 col-lg-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          <a href="estoque_atual.php" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i></a>
        </div>
      </form>
    </div>
  </div>

  <div class="section-card card border-0 shadow-sm mb-4">
    <div class="card-body">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-3">
        <div>
          <span class="section-badge mb-2"><i class="fa-solid fa-clipboard-list"></i>Saldo atual</span>
          <h2 class="h5 mb-2">Lista consolidada de itens</h2>
          <p class="section-card-subtitle mb-0">A posição cadastrada em cada produto aparece como localização na coluna de local.</p>
        </div>
        <div class="approved-summary">
          <div class="approved-summary-item">
            <span>Exibindo</span>
            <strong><?= h(number_format($showingStart, 0, ',', '.')) ?> - <?= h(number_format($showingEnd, 0, ',', '.')) ?></strong>
          </div>
          <div class="approved-summary-item">
            <span>Total</span>
            <strong><?= h(number_format($totalItems, 0, ',', '.')) ?></strong>
          </div>
        </div>
      </div>

      <?php if (empty($pagedItems)): ?>
        <div class="alert alert-info mb-0">Nenhum produto encontrado para os filtros aplicados.</div>
      <?php else: ?>
        <div class="table-responsive request-table-wrap">
          <table class="table table-hover align-middle mb-0 request-table js-no-datatable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Produto</th>
                <th>Localização</th>
                <th>Código de barras</th>
                <th>Saldo</th>
                <th>Estoque mínimo</th>
                <th>Status</th>
                <th>Validade</th>
                <th>QR</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pagedItems as $item): ?>
                <?php
                  $statusKey = (string)($item['status_key'] ?? 'normal');
                  $meta = $statusMeta[$statusKey] ?? $statusMeta['normal'];
                  $daysToExpire = $item['days_to_expire'];
                  $validadeText = '-';
                  if (!empty($item['validade'])) {
                    $validadeText = date('d/m/Y', strtotime((string)$item['validade']));
                    if ($statusKey === 'expiring' && $daysToExpire !== null) {
                      $validadeText .= ' (' . max(0, $daysToExpire) . ' dias)';
                    }
                  }
                ?>
                <tr>
                  <td><?= (int)$item['id'] ?></td>
                  <td>
                    <strong><?= h((string)$item['nome']) ?></strong>
                    <?php if (!empty($item['lote'])): ?>
                      <div class="text-muted small">Lote: <?= h((string)$item['lote']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?= h((string)($item['posicao'] ?? '-')) ?></td>
                  <td><?= h((string)($item['codigo_barra'] ?? '-')) ?></td>
                  <td><?= h(number_format((int)$item['quantity_int'], 0, ',', '.')) ?> <?= h((string)($item['unidade'] ?? 'UN')) ?></td>
                  <td><?= h(number_format($lowStockThreshold, 0, ',', '.')) ?></td>
                  <td><span class="badge <?= h($meta['class']) ?>"><?= h($meta['label']) ?></span></td>
                  <td><?= h($validadeText) ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-primary" href="imprimir_qr.php?id=<?= (int)$item['id'] ?>" target="_blank" rel="noopener">
                      <i class="fa-solid fa-print me-1"></i>Imprimir
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($totalPages > 1): ?>
          <nav class="mt-3" aria-label="Paginação do estoque atual">
            <ul class="pagination pagination-sm mb-0">
              <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                  <a class="page-link" href="<?= h($buildQuery(['page' => $p])) ?>"><?= $p ?></a>
                </li>
              <?php endfor; ?>
            </ul>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>