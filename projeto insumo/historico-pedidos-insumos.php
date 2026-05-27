<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

$pdo = getPDO();
ensureInsumoRequestsSchema($pdo);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = (string)$_SESSION['csrf_token'];

function buildInsumoRequestGroupKey(array $request): string {
  $batchId = trim((string)($request['batch_id'] ?? ''));
  if ($batchId !== '') {
    return 'batch:' . $batchId;
  }

  return 'legacy:' . implode('|', [
    (string)($request['user_id'] ?? ''),
    trim((string)($request['user_email'] ?? '')),
    trim((string)($request['setor'] ?? '')),
    trim((string)($request['data_solicitada_entrega'] ?? '')),
    trim((string)($request['motivo_usuario'] ?? '')),
    trim((string)($request['requested_at'] ?? '')),
  ]);
}

function normalizeRequestIds($value): array {
  $raw = is_array($value) ? $value : (is_string($value) ? explode(',', $value) : []);
  return array_values(array_filter(array_map('intval', $raw), static function (int $item): bool {
    return $item > 0;
  }));
}

function formatHistoryDate(?string $value, string $format = 'd/m/Y H:i'): string {
  if ($value === null || trim($value) === '') {
    return '-';
  }

  $timestamp = strtotime((string)$value);
  return $timestamp ? date($format, $timestamp) : '-';
}

function sumDeliveredQuantity(array $items): float {
  $total = 0.0;
  foreach ($items as $item) {
    $value = $item['quantidade_entregue'] ?? $item['quantidade'] ?? 0;
    $total += (float)$value;
  }

  return $total;
}

function historyGroupMatchesQuery(array $group, string $q): bool {
  if ($q === '') {
    return true;
  }

  $haystack = implode(' ', [
    (string)($group['sector'] ?? ''),
    (string)($group['user_nome'] ?? ''),
    (string)($group['user_email'] ?? ''),
    (string)($group['motivo_usuario'] ?? ''),
    (string)($group['admin_note'] ?? ''),
    (string)($group['processed_by_nome'] ?? ''),
  ]);

  if (stripos($haystack, $q) !== false) {
    return true;
  }

  foreach (($group['items'] ?? []) as $item) {
    $itemHaystack = implode(' ', [
      (string)($item['insumo_nome'] ?? ''),
      (string)($item['lote'] ?? ''),
      (string)($item['fabricacao'] ?? ''),
      (string)($item['validade'] ?? ''),
    ]);

    if (stripos($itemHaystack, $q) !== false) {
      return true;
    }
  }

  return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedToken = (string)($_POST['csrf_token'] ?? '');
  if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
    flash('error', 'Sessão inválida. Atualize a página e tente novamente.');
    header('Location: historico-pedidos-insumos.php');
    exit;
  }

  $action = (string)($_POST['action'] ?? '');
  $insumoRequestIds = normalizeRequestIds($_POST['insumo_request_ids'] ?? []);

  if ($action === 'insumo_history_delete') {
    if (empty($insumoRequestIds)) {
      flash('error', 'Nenhum documento foi selecionado para exclusão.');
      header('Location: historico-pedidos-insumos.php');
      exit;
    }

    $placeholders = implode(',', array_fill(0, count($insumoRequestIds), '?'));
    $checkStmt = $pdo->prepare('SELECT id FROM insumo_requests WHERE id IN (' . $placeholders . ") AND status = 'approved'");
    $checkStmt->execute($insumoRequestIds);
    $foundIds = array_map('intval', array_column($checkStmt->fetchAll(), 'id'));

    if (count($foundIds) !== count($insumoRequestIds)) {
      flash('error', 'Um ou mais documentos não foram encontrados no histórico.');
      header('Location: historico-pedidos-insumos.php');
      exit;
    }

    $deleteStmt = $pdo->prepare('DELETE FROM insumo_requests WHERE id IN (' . $placeholders . ") AND status = 'approved'");
    $deleteStmt->execute($insumoRequestIds);

    if ($deleteStmt->rowCount() > 0) {
      flash('success', 'Documento removido do histórico com sucesso.');
    } else {
      flash('error', 'Não foi possível excluir este documento.');
    }

    header('Location: historico-pedidos-insumos.php');
    exit;
  }

  header('Location: historico-pedidos-insumos.php');
  exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$historySql = 'SELECT
    ir.id,
    ir.user_id,
    ir.user_nome,
    ir.user_email,
    ir.user_role,
    ir.batch_id,
    ir.setor,
    ir.data_solicitada_entrega,
    ir.insumo_nome,
    ir.quantidade,
    ir.unidade,
    ir.quantidade_entregue,
    ir.lote,
    ir.fabricacao,
    ir.validade,
    ir.motivo_usuario,
    ir.status,
    ir.admin_note,
    ir.requested_at,
    ir.processed_at,
    ir.processed_by,
    u.nome AS processed_by_nome
  FROM insumo_requests ir
  LEFT JOIN usuarios u ON u.id = ir.processed_by';

$historySql .= " WHERE ir.status = 'approved'";

$historySql .= ' ORDER BY COALESCE(ir.processed_at, ir.requested_at) DESC, ir.id DESC';

$historyStmt = $pdo->prepare($historySql);
$historyStmt->execute();
$approvedRequests = $historyStmt->fetchAll();

$historyByGroup = [];
foreach ($approvedRequests as $request) {
  $groupKey = buildInsumoRequestGroupKey($request);

  if (!isset($historyByGroup[$groupKey])) {
    $historyByGroup[$groupKey] = [
      'group_key' => $groupKey,
      'batch_id' => trim((string)($request['batch_id'] ?? '')),
      'sector' => trim((string)($request['setor'] ?? '')) !== '' ? trim((string)$request['setor']) : 'Sem setor',
      'requested_at' => $request['requested_at'] ?? null,
      'processed_at' => $request['processed_at'] ?? null,
      'user_nome' => $request['user_nome'] ?? '',
      'user_email' => $request['user_email'] ?? '',
      'user_role' => $request['user_role'] ?? 'user',
      'motivo_usuario' => $request['motivo_usuario'] ?? '',
      'admin_note' => $request['admin_note'] ?? '',
      'processed_by_nome' => $request['processed_by_nome'] ?? '',
      'items' => [],
      'ids' => [],
    ];
  }

  $historyByGroup[$groupKey]['items'][] = $request;
  $historyByGroup[$groupKey]['ids'][] = (int)$request['id'];
}

if ($q !== '') {
  $historyByGroup = array_filter($historyByGroup, static function (array $group) use ($q): bool {
    return historyGroupMatchesQuery($group, $q);
  });
}

$historyDocumentsCount = count($historyByGroup);
$historyItemsCount = count($approvedRequests);
$historyDeliveredTotal = 0.0;
foreach ($historyByGroup as $group) {
  $historyDeliveredTotal += sumDeliveredQuantity($group['items']);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page insumo-historico-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden insumo-historico-hero">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="solicitacoes-kicker">Arquivo operacional</span>
          <h1 class="display-6 fw-semibold mb-2">Histórico de pedidos de insumo</h1>
          <p class="solicitacoes-subtitle mb-0">Os documentos aprovados ficam arquivados aqui com os dados de entrega, lote, fabricação e validade já consolidados.</p>
          <div class="insumo-historico-hero-chips mt-3">
            <span class="insumo-historico-chip"><i class="fa-solid fa-folder-tree"></i>Arquivo consolidado</span>
            <span class="insumo-historico-chip"><i class="fa-solid fa-magnifying-glass"></i>Busca por setor, insumo ou observação</span>
          </div>
        </div>
        <div class="text-lg-end d-flex flex-column gap-2 align-items-lg-end">
          <a href="export_historico_pedidos_insumos_pdf.php" class="btn btn-outline-danger">
            <i class="fa-solid fa-file-pdf me-1"></i>Exportar histórico em PDF
          </a>
          <a href="pedidos-insumos-pendentes.php" class="btn btn-outline-primary">
            <i class="fa-solid fa-box-open me-1"></i>Voltar às pendências
          </a>
          <small class="text-muted d-block">Use a busca para localizar um documento já aprovado.</small>
        </div>
      </div>

      <div class="insumo-historico-summary mt-4">
        <div class="insumo-historico-summary-item">
          <span>Documentos</span>
          <strong><?= h(number_format($historyDocumentsCount, 0, ',', '.')) ?></strong>
        </div>
        <div class="insumo-historico-summary-item">
          <span>Itens</span>
          <strong><?= h(number_format($historyItemsCount, 0, ',', '.')) ?></strong>
        </div>
        <div class="insumo-historico-summary-item">
          <span>Quantidade entregue</span>
          <strong><?= h(number_format($historyDeliveredTotal, 2, ',', '.')) ?></strong>
        </div>
      </div>

      <div class="row g-3 mt-4">
        <div class="col-12 col-md-6 col-xl-4">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-success"><i class="fa-solid fa-box-archive"></i></div>
            <div>
              <div class="metric-label">Documentos aprovados</div>
              <div class="metric-value"><?= h(number_format($historyDocumentsCount, 0, ',', '.')) ?></div>
              <div class="metric-help">Cada cartão representa uma solicitação concluída.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-info"><i class="fa-solid fa-list-check"></i></div>
            <div>
              <div class="metric-label">Itens aprovados</div>
              <div class="metric-value"><?= h(number_format($historyItemsCount, 0, ',', '.')) ?></div>
              <div class="metric-help">Itens individuais aprovados e lançados no histórico.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div>
              <div class="metric-label">Quantidade entregue</div>
              <div class="metric-value"><?= h(number_format($historyDeliveredTotal, 2, ',', '.')) ?></div>
              <div class="metric-help">Soma dos itens já aprovados no arquivo.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="section-card card border-0 shadow-sm mb-4 pending-insumos-card insumo-historico-card" id="historico-pedidos-insumo">
    <div class="section-card-header card-header bg-white border-0 pt-3 pb-0 pending-insumos-header insumo-historico-header">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
        <div>
          <h2 class="h5 mb-1"><i class="fa-solid fa-box-archive me-2 text-primary"></i>Documentos arquivados</h2>
          <div class="section-card-subtitle">Visualize o documento completo, exporte em PDF ou remova do histórico quando necessário.</div>
        </div>
        <div class="pending-insumos-pill insumo-historico-pill">
          <i class="fa-solid fa-clock me-1"></i>
          <?= h(number_format($historyDocumentsCount, 0, ',', '.')) ?> <?= $historyDocumentsCount === 1 ? 'documento' : 'documentos' ?>
        </div>
      </div>
    </div>
    <div class="card-body pt-0 pending-insumos-body">
      <div class="card border-0 shadow-sm mb-4 insumo-historico-filter-card">
        <div class="card-body">
          <form method="get" class="row g-2 align-items-end">
            <div class="col-12 col-md-8">
              <label class="form-label small text-muted mb-1">Buscar por solicitante, setor, insumo ou observação</label>
              <input type="text" class="form-control" name="q" value="<?= h($q) ?>" placeholder="Ex.: setor logistica, luva, aprovação...">
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
              <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
              <a href="historico-pedidos-insumos.php" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i>Limpar</a>
            </div>
          </form>
        </div>
      </div>

      <?php if (empty($historyByGroup)): ?>
        <div class="alert alert-info mb-0">Ainda não há pedidos de insumo aprovados para exibir no histórico.</div>
      <?php else: ?>
        <?php foreach ($historyByGroup as $group): ?>
          <?php $groupExportHref = 'export_historico_pedidos_insumos_pdf.php?' . http_build_query(['ids' => $group['ids']]); ?>
          <div class="pending-insumos-sector card border-0 shadow-sm mb-4 insumo-historico-document">
            <div class="card-header bg-white border-0 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
              <div>
                <h3 class="h6 mb-1"><i class="fa-solid fa-layer-group me-2 text-primary"></i><?= h($group['sector']) ?></h3>
                <div class="section-card-subtitle mb-0"><?= h(number_format(count($group['items']), 0, ',', '.')) ?> item<?= count($group['items']) === 1 ? '' : 's' ?> neste documento</div>
              </div>
              <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                <span class="badge bg-light text-dark border insumo-historico-badge"><?= h($group['batch_id'] !== '' ? 'Documento em lote' : 'Documento avulso') ?></span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle insumo-historico-badge"><?= h(formatHistoryDate($group['processed_at'] ?? null, 'd/m/Y')) ?></span>
              </div>
            </div>
            <div class="card-body pt-0">
              <div class="row g-3 mb-4 insumo-historico-meta-grid">
                <div class="col-12 col-lg-3">
                  <div class="insumo-historico-meta-card">
                    <div class="small text-muted">Solicitante</div>
                    <div class="fw-semibold"><?= h((string)$group['user_nome']) ?></div>
                    <div class="small text-muted"><?= h((string)$group['user_email']) ?></div>
                  </div>
                </div>
                <div class="col-12 col-lg-3">
                  <div class="insumo-historico-meta-card">
                    <div class="small text-muted">Data solicitada para entrega</div>
                    <div class="fw-semibold"><?= formatHistoryDate($group['data_solicitada_entrega'] ?? null, 'd/m/Y') ?></div>
                  </div>
                </div>
                <div class="col-12 col-lg-3">
                  <div class="insumo-historico-meta-card">
                    <div class="small text-muted">Solicitado em</div>
                    <div class="fw-semibold"><?= formatHistoryDate($group['requested_at'] ?? null) ?></div>
                  </div>
                </div>
                <div class="col-12 col-lg-3">
                  <div class="insumo-historico-meta-card">
                    <div class="small text-muted">Aprovado em</div>
                    <div class="fw-semibold"><?= formatHistoryDate($group['processed_at'] ?? null) ?></div>
                  </div>
                </div>
              </div>

              <div class="row g-3 mb-4">
                <div class="col-12 col-lg-6">
                  <div class="insumo-historico-meta-card">
                    <div class="small text-muted">Aprovado por</div>
                    <div class="fw-semibold"><?= h((string)($group['processed_by_nome'] !== '' ? $group['processed_by_nome'] : 'Administrador')) ?></div>
                  </div>
                </div>
                <div class="col-12 col-lg-6">
                  <div class="insumo-historico-meta-card">
                    <div class="small text-muted">Origem</div>
                    <div class="fw-semibold"><?= h($group['batch_id'] !== '' ? 'Lote único da solicitação' : 'Registro legado') ?></div>
                  </div>
                </div>
              </div>

              <div class="table-responsive request-table-wrap mb-4">
                <table class="table table-hover align-middle mb-0 request-table pending-insumos-table insumo-historico-table">
                  <thead>
                    <tr>
                      <th style="width: 10%;">ID</th>
                      <th>Insumo</th>
                      <th style="width: 16%;">Qtd. solicitada</th>
                      <th style="width: 16%;">Qtd. entregue</th>
                      <th style="width: 12%;">Lote</th>
                      <th style="width: 10%;">Fabricação</th>
                      <th style="width: 10%;">Validade</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($group['items'] as $item): ?>
                      <tr>
                        <td data-label="ID"><?= (int)$item['id'] ?></td>
                        <td data-label="Insumo">
                          <strong><?= h((string)$item['insumo_nome']) ?></strong>
                          <?php if (($item['user_role'] ?? 'user') === 'admin'): ?>
                            <span class="badge bg-warning text-dark ms-1">Administrador</span>
                          <?php endif; ?>
                        </td>
                        <td data-label="Qtd. solicitada"><?= h(number_format((float)$item['quantidade'], 2, ',', '.')) ?> <?= h((string)$item['unidade']) ?></td>
                        <td data-label="Qtd. entregue"><?= h(number_format((float)($item['quantidade_entregue'] ?? $item['quantidade'] ?? 0), 2, ',', '.')) ?> <?= h((string)$item['unidade']) ?></td>
                        <td data-label="Lote"><?= h((string)($item['lote'] ?? '-')) ?></td>
                        <td data-label="Fabricação"><?= formatHistoryDate($item['fabricacao'] ?? null, 'd/m/Y') ?></td>
                        <td data-label="Validade"><?= formatHistoryDate($item['validade'] ?? null, 'd/m/Y') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="mb-3">
                <div class="small text-muted mb-1">Motivo da solicitação</div>
                <div><?= h((string)($group['motivo_usuario'] !== '' ? $group['motivo_usuario'] : '-')) ?></div>
              </div>

              <div class="mb-4">
                <div class="small text-muted mb-1">Observação da aprovação</div>
                <div><?= h((string)($group['admin_note'] !== '' ? $group['admin_note'] : '-')) ?></div>
              </div>

              <div class="d-flex flex-column flex-md-row gap-2 justify-content-md-end insumo-historico-actions">
                <a href="<?= h($groupExportHref) ?>" class="btn btn-sm btn-outline-danger">
                  <i class="fa-solid fa-file-pdf me-1"></i>Exportar este documento
                </a>
                <form method="post" class="m-0" onsubmit="return confirm('Excluir este documento do histórico? Esta ação não pode ser desfeita.');">
                  <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                  <?php foreach ($group['ids'] as $requestId): ?>
                    <input type="hidden" name="insumo_request_ids[]" value="<?= (int)$requestId ?>">
                  <?php endforeach; ?>
                  <input type="hidden" name="action" value="insumo_history_delete">
                  <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="fa-solid fa-trash-can me-1"></i>Excluir documento
                  </button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>