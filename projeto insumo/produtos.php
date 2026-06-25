<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

$pdo = getPDO();
ensureUserAuthSchema();

$units = [
  'UN' => 'Unidade',
  'CX' => 'Caixa',
  'PCT' => 'Pacote',
  'KG' => 'Quilograma',
  'G' => 'Grama',
  'L' => 'Litro',
  'ML' => 'Mililitro',
  'M' => 'Metro',
  'CM' => 'Centímetro',
  'PAR' => 'Par',
  'FD' => 'Fardo',
  'RO' => 'Rolo',
];

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$where = ['1 = 1'];
$params = [];

if ($q !== '') {
  $where[] = '(nome LIKE ? OR posicao LIKE ? OR lote LIKE ? OR codigo_barra LIKE ?)';
  $searchLike = '%' . $q . '%';
  $params = [$searchLike, $searchLike, $searchLike, $searchLike];
}

$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM insumos_jnj WHERE ' . $whereSql);
$countStmt->execute($params);
$totalItems = (int)($countStmt->fetch()['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalItems / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$itemsStmt = $pdo->prepare("SELECT id, nome, posicao, lote, codigo_barra, quantidade, unidade, data_entrada, validade, observacoes FROM insumos_jnj WHERE $whereSql ORDER BY nome ASC LIMIT ? OFFSET ?");
$itemsStmt->execute(array_merge($params, [$perPage, $offset]));
$items = $itemsStmt->fetchAll() ?: [];

$showingStart = $totalItems > 0 ? $offset + 1 : 0;
$showingEnd = min($totalItems, $offset + $perPage);

$summaryStmt = $pdo->query(
  "SELECT
      COUNT(*) AS total_items,
      SUM(CASE WHEN quantidade <= 0 THEN 1 ELSE 0 END) AS zero_items,
      SUM(CASE WHEN quantidade > 0 AND quantidade <= 10 THEN 1 ELSE 0 END) AS low_items
   FROM insumos_jnj"
);
$summary = $summaryStmt->fetch() ?: [];

$buildQuery = static function (array $overrides = []) use ($q, $page): string {
  $base = [
    'q' => $q,
    'page' => $page,
  ];
  $query = array_merge($base, $overrides);
  foreach ($query as $key => $value) {
    if ($value === '' || $value === null) {
      unset($query[$key]);
    }
  }
  return 'produtos.php' . (!empty($query) ? '?' . http_build_query($query) : '');
};

$newProduct = null;
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
  $editStmt = $pdo->prepare('SELECT * FROM insumos_jnj WHERE id = ? LIMIT 1');
  $editStmt->execute([$editId]);
  $newProduct = $editStmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? 0);
  $nome = trim((string)($_POST['nome'] ?? ''));
  $posicao = trim((string)($_POST['posicao'] ?? ''));
  $lote = trim((string)($_POST['lote'] ?? ''));
  $codigoBarra = trim((string)($_POST['codigo_barra'] ?? ''));
  $quantidadeRaw = str_replace(',', '.', trim((string)($_POST['quantidade'] ?? '')));
  $unidade = strtoupper(trim((string)($_POST['unidade'] ?? 'UN')));
  $dataEntrada = trim((string)($_POST['data_entrada'] ?? ''));
  $validade = trim((string)($_POST['validade'] ?? ''));
  $observacoes = trim((string)($_POST['observacoes'] ?? ''));

  $errors = [];

  if ($nome === '') {
    $errors[] = 'Informe o nome do produto.';
  }
  if ($posicao === '') {
    $errors[] = 'Informe a posição.';
  }
  if ($quantidadeRaw === '' || !is_numeric($quantidadeRaw) || (float)$quantidadeRaw < 0) {
    $errors[] = 'Informe uma quantidade válida.';
  }
  if ($dataEntrada === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataEntrada)) {
    $errors[] = 'Informe uma data de entrada válida.';
  }
  if ($validade !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validade)) {
    $errors[] = 'Informe uma validade válida.';
  }

  if (!$errors) {
    $quantity = (int)round((float)$quantidadeRaw);

    if ($id > 0) {
      $stmt = $pdo->prepare('UPDATE insumos_jnj SET nome = ?, posicao = ?, lote = ?, codigo_barra = ?, quantidade = ?, unidade = ?, data_entrada = ?, validade = ?, observacoes = ? WHERE id = ?');
      $stmt->execute([
        mb_substr($nome, 0, 150),
        mb_substr($posicao, 0, 20),
        $lote !== '' ? mb_substr($lote, 0, 100) : null,
        $codigoBarra !== '' ? mb_substr($codigoBarra, 0, 100) : null,
        $quantity,
        $unidade !== '' ? mb_substr($unidade, 0, 30) : 'UN',
        $dataEntrada,
        $validade !== '' ? $validade : null,
        $observacoes !== '' ? mb_substr($observacoes, 0, 5000) : null,
        $id,
      ]);
      flash('success', 'Produto atualizado com sucesso.');
    } else {
      $stmt = $pdo->prepare('INSERT INTO insumos_jnj (nome, posicao, lote, codigo_barra, quantidade, unidade, data_entrada, validade, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
      $stmt->execute([
        mb_substr($nome, 0, 150),
        mb_substr($posicao, 0, 20),
        $lote !== '' ? mb_substr($lote, 0, 100) : null,
        $codigoBarra !== '' ? mb_substr($codigoBarra, 0, 100) : null,
        $quantity,
        $unidade !== '' ? mb_substr($unidade, 0, 30) : 'UN',
        $dataEntrada,
        $validade !== '' ? $validade : null,
        $observacoes !== '' ? mb_substr($observacoes, 0, 5000) : null,
      ]);
      flash('success', 'Produto cadastrado com sucesso.');
    }

    header('Location: produtos.php');
    exit;
  }

  flash('error', implode(' ', $errors));
  $newProduct = array_merge($newProduct ?: [], $_POST);
}

include __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <span class="solicitacoes-kicker">Estoque</span>
      <h1 class="display-6 fw-semibold mb-2">Produtos</h1>
      <p class="solicitacoes-subtitle mb-0">Cadastre, edite, exclua e imprima QR Code dos materiais do estoque.</p>
    </div>
  </section>

  <div class="row g-4 mb-4">
    <div class="col-12 col-md-4">
      <div class="metric-card h-100">
        <div class="metric-icon metric-icon-info"><i class="fa-solid fa-boxes-stacked"></i></div>
        <div>
          <div class="metric-label">Itens cadastrados</div>
          <div class="metric-value"><?= h(number_format((int)($summary['total_items'] ?? 0), 0, ',', '.')) ?></div>
          <div class="metric-help">Base total de produtos do estoque.</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="metric-card h-100">
        <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div>
          <div class="metric-label">Estoque baixo</div>
          <div class="metric-value"><?= h(number_format((int)($summary['low_items'] ?? 0), 0, ',', '.')) ?></div>
          <div class="metric-help">Quantidade entre 1 e 10.</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="metric-card h-100">
        <div class="metric-icon metric-icon-danger"><i class="fa-solid fa-ban"></i></div>
        <div>
          <div class="metric-label">Zerados</div>
          <div class="metric-value"><?= h(number_format((int)($summary['zero_items'] ?? 0), 0, ',', '.')) ?></div>
          <div class="metric-help">Itens sem saldo disponível.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="section-card card border-0 shadow-sm mb-4">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-3">
        <div>
          <span class="section-badge mb-2"><i class="fa-solid fa-magnifying-glass"></i>Buscar</span>
          <h2 class="h5 mb-2">Filtrar produtos</h2>
          <p class="section-card-subtitle mb-0">Pesquise por nome, posição, lote ou código de barras.</p>
        </div>
      </div>

      <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md-9">
          <label class="form-label small text-muted mb-1">Pesquisa</label>
          <input type="text" name="q" class="form-control" value="<?= h($q) ?>" placeholder="Ex.: fita, A1, lote 123...">
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          <a href="produtos.php" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i></a>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12 col-xl-5">
      <div class="section-card card border-0 shadow-sm h-100">
        <div class="card-body p-4 p-lg-5">
          <span class="section-badge mb-2"><i class="fa-solid fa-pen-to-square"></i><?= $newProduct ? 'Editar produto' : 'Novo produto' ?></span>
          <h2 class="h5 mb-3"><?= $newProduct ? 'Atualizar material' : 'Cadastrar material' ?></h2>

          <form method="post" class="row g-3">
            <input type="hidden" name="id" value="<?= h((string)($newProduct['id'] ?? '')) ?>">
            <div class="col-12">
              <label class="form-label">Nome do produto</label>
              <input type="text" name="nome" class="form-control" value="<?= h((string)($newProduct['nome'] ?? '')) ?>" required>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Posição</label>
              <input type="text" name="posicao" class="form-control" value="<?= h((string)($newProduct['posicao'] ?? '')) ?>" required>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Quantidade</label>
              <input type="text" name="quantidade" class="form-control" value="<?= h((string)($newProduct['quantidade'] ?? '0')) ?>" required>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Unidade</label>
              <select name="unidade" class="form-select">
                <?php $currentUnit = strtoupper((string)($newProduct['unidade'] ?? 'UN')); ?>
                <?php foreach ($units as $unitCode => $unitLabel): ?>
                  <option value="<?= h($unitCode) ?>" <?= $currentUnit === $unitCode ? 'selected' : '' ?>><?= h($unitCode) ?> - <?= h($unitLabel) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Lote</label>
              <input type="text" name="lote" class="form-control" value="<?= h((string)($newProduct['lote'] ?? '')) ?>">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Código de barras / QR</label>
              <input type="text" name="codigo_barra" class="form-control" value="<?= h((string)($newProduct['codigo_barra'] ?? '')) ?>" placeholder="Pode ficar em branco">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Data de entrada</label>
              <input type="date" name="data_entrada" class="form-control" value="<?= h((string)($newProduct['data_entrada'] ?? date('Y-m-d'))) ?>" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Validade</label>
              <input type="date" name="validade" class="form-control" value="<?= h((string)($newProduct['validade'] ?? '')) ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Observações</label>
              <textarea name="observacoes" rows="3" class="form-control"><?= h((string)($newProduct['observacoes'] ?? '')) ?></textarea>
            </div>
            <div class="col-12 d-flex gap-2 flex-wrap">
              <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i><?= $newProduct ? 'Salvar edição' : 'Salvar produto' ?></button>
              <?php if ($newProduct): ?>
                <a href="produtos.php" class="btn btn-outline-secondary">Cancelar</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-7">
      <div class="section-card card border-0 shadow-sm h-100">
        <div class="card-body p-4 p-lg-5">
          <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-3">
            <div>
              <span class="section-badge mb-2"><i class="fa-solid fa-list"></i>Lista</span>
              <h2 class="h5 mb-2">Produtos cadastrados</h2>
              <p class="section-card-subtitle mb-0">Cada item pode ser impresso como QR e depois usado na baixa rápida.</p>
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

          <?php if (empty($items)): ?>
            <div class="alert alert-info mb-0">Nenhum produto encontrado.</div>
          <?php else: ?>
            <div class="table-responsive request-table-wrap">
              <table class="table table-hover align-middle mb-0 request-table js-no-datatable">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Produto</th>
                    <th>Posição</th>
                    <th>Saldo</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $item): ?>
                    <tr>
                      <td><?= (int)$item['id'] ?></td>
                      <td>
                        <strong><?= h((string)$item['nome']) ?></strong>
                        <?php if (!empty($item['lote'])): ?>
                          <div class="text-muted small">Lote: <?= h((string)$item['lote']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($item['codigo_barra'])): ?>
                          <div class="text-muted small">QR/Barra: <?= h((string)$item['codigo_barra']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td><?= h((string)($item['posicao'] ?? '-')) ?></td>
                      <td><?= h(number_format((int)$item['quantidade'], 0, ',', '.')) ?> <?= h((string)($item['unidade'] ?? 'UN')) ?></td>
                      <td>
                        <div class="d-flex gap-2 flex-wrap">
                          <a class="btn btn-sm btn-outline-primary" href="produtos.php?edit=<?= (int)$item['id'] ?>"><i class="fa-solid fa-pen me-1"></i>Editar</a>
                          <a class="btn btn-sm btn-outline-secondary" href="imprimir_qr.php?id=<?= (int)$item['id'] ?>" target="_blank" rel="noopener"><i class="fa-solid fa-print me-1"></i>QR</a>
                          <a class="btn btn-sm btn-outline-danger" href="excluir.php?id=<?= (int)$item['id'] ?>"><i class="fa-solid fa-trash-can me-1"></i>Excluir</a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <?php if ($totalPages > 1): ?>
              <nav class="mt-3" aria-label="Paginação de produtos">
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
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>