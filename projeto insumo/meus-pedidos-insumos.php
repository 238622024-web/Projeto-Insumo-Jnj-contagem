<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireLogin();

$pdo = getPDO();
ensureInsumoRequestsSchema($pdo);
$current = currentUser() ?: [];
$userId = (int)($current['id'] ?? 0);

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
    trim((string)($request['setor'] ?? '')),
    trim((string)($request['data_solicitada_entrega'] ?? '')),
    trim((string)($request['motivo_usuario'] ?? '')),
    trim((string)($request['requested_at'] ?? '')),
  ]);
}

function historyDate(?string $value, string $format = 'd/m/Y H:i'): string {
  if ($value === null || trim($value) === '') {
    return '-';
  }

  $timestamp = strtotime((string)$value);
  return $timestamp ? date($format, $timestamp) : '-';
}

function badgeClassForStatus(string $status): string {
  if ($status === 'approved') {
    return 'bg-success';
  }
  if ($status === 'rejected') {
    return 'bg-danger';
  }
  return 'bg-warning text-dark';
}

function labelForStatus(string $status): string {
  if ($status === 'approved') {
    return 'Aprovado';
  }
  if ($status === 'rejected') {
    return 'Rejeitado';
  }
  return 'Pendente';
}

function isDeletableStatus(string $status): bool {
  return in_array($status, ['pending', 'rejected'], true);
}

$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'pending', 'approved', 'rejected'], true)) {
  $statusFilter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedToken = (string)($_POST['csrf_token'] ?? '');
  if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
    flash('error', 'Sessão inválida. Atualize a página e tente novamente.');
    header('Location: meus-pedidos-insumos.php');
    exit;
  }

  $action = (string)($_POST['action'] ?? '');
  $insumoRequestId = (int)($_POST['insumo_request_id'] ?? 0);

  if ($action === 'delete_request') {
    if ($insumoRequestId <= 0) {
      flash('error', 'Nenhum pedido foi selecionado para exclusão.');
      header('Location: meus-pedidos-insumos.php');
      exit;
    }

    $checkStmt = $pdo->prepare('SELECT id, status FROM insumo_requests WHERE id = ? AND user_id = ? LIMIT 1');
    $checkStmt->execute([$insumoRequestId, $userId]);
    $foundRow = $checkStmt->fetch();

    if (!$foundRow) {
      flash('error', 'Pedido não foi encontrado.');
      header('Location: meus-pedidos-insumos.php');
      exit;
    }

    if (!isDeletableStatus((string)($foundRow['status'] ?? ''))) {
      flash('error', 'Somente pedidos pendentes ou rejeitados podem ser excluídos.');
      header('Location: meus-pedidos-insumos.php');
      exit;
    }

    $deleteStmt = $pdo->prepare('DELETE FROM insumo_requests WHERE id = ? AND user_id = ?');
    $deleteStmt->execute([$insumoRequestId, $userId]);

    if ($deleteStmt->rowCount() > 0) {
      flash('success', 'Pedido excluído com sucesso.');
    } else {
      flash('error', 'Não foi possível excluir o pedido.');
    }

    header('Location: meus-pedidos-insumos.php');
    exit;
  }
}

$historyStmt = $pdo->prepare(
  "SELECT
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
    LEFT JOIN usuarios u ON u.id = ir.processed_by
    WHERE ir.user_id = ?
    ORDER BY COALESCE(ir.processed_at, ir.requested_at) DESC, ir.id DESC"
);
$historyStmt->execute([$userId]);
$rows = $historyStmt->fetchAll();

$groups = [];
foreach ($rows as $row) {
  if ($statusFilter !== 'all' && (string)($row['status'] ?? '') !== $statusFilter) {
    continue;
  }

  $matchesSearch = true;
  if ($q !== '') {
    $haystack = implode(' ', [
      (string)($row['setor'] ?? ''),
      (string)($row['insumo_nome'] ?? ''),
      (string)($row['motivo_usuario'] ?? ''),
      (string)($row['admin_note'] ?? ''),
      (string)($row['lote'] ?? ''),
      (string)($row['fabricacao'] ?? ''),
      (string)($row['validade'] ?? ''),
    ]);
    $matchesSearch = stripos($haystack, $q) !== false;
  }

  if (!$matchesSearch) {
    continue;
  }

  $groupKey = buildInsumoRequestGroupKey($row);
  if (!isset($groups[$groupKey])) {
    $groups[$groupKey] = [
      'batch_id' => trim((string)($row['batch_id'] ?? '')),
      'sector' => trim((string)($row['setor'] ?? '')) !== '' ? trim((string)$row['setor']) : 'Sem setor',
      'requested_at' => $row['requested_at'] ?? null,
      'processed_at' => $row['processed_at'] ?? null,
      'status' => (string)($row['status'] ?? 'pending'),
      'user_nome' => $row['user_nome'] ?? '',
      'user_email' => $row['user_email'] ?? '',
      'motivo_usuario' => $row['motivo_usuario'] ?? '',
      'admin_note' => $row['admin_note'] ?? '',
      'processed_by_nome' => $row['processed_by_nome'] ?? '',
      'items' => [],
      'ids' => [],
    ];
  }

  $groups[$groupKey]['items'][] = $row;
  $groups[$groupKey]['ids'][] = (int)$row['id'];
}

$totalDocuments = count($groups);
$approvedDocuments = 0;
$pendingDocuments = 0;
$rejectedDocuments = 0;
foreach ($groups as $group) {
  if ($group['status'] === 'approved') {
    $approvedDocuments++;
  } elseif ($group['status'] === 'rejected') {
    $rejectedDocuments++;
  } else {
    $pendingDocuments++;
  }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page meus-pedidos-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="solicitacoes-kicker">Acompanhamento pessoal</span>
          <h1 class="display-6 fw-semibold mb-2">Meus pedidos de insumo</h1>
          <p class="solicitacoes-subtitle mb-0">Veja todos os documentos enviados, acompanhe o status e revise os pedidos que já foram aprovados ou rejeitados.</p>
        </div>
        <div class="text-lg-end d-flex flex-column gap-2 align-items-lg-end">
          <a href="solicitar-insumo.php" class="btn btn-outline-primary">
            <i class="fa-solid fa-file-signature me-1"></i>Nova solicitação
          </a>
          <small class="text-muted d-block">Você vê apenas os pedidos da sua própria conta.</small>
        </div>
      </div>

      <div class="row g-3 mt-4">
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-info"><i class="fa-solid fa-box-archive"></i></div>
            <div>
              <div class="metric-label">Documentos</div>
              <div class="metric-value"><?= h(number_format($totalDocuments, 0, ',', '.')) ?></div>
              <div class="metric-help">Total de solicitações agrupadas.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-success"><i class="fa-solid fa-circle-check"></i></div>
            <div>
              <div class="metric-label">Aprovados</div>
              <div class="metric-value"><?= h(number_format($approvedDocuments, 0, ',', '.')) ?></div>
              <div class="metric-help">Documentos já concluídos.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-clock"></i></div>
            <div>
              <div class="metric-label">Pendentes</div>
              <div class="metric-value"><?= h(number_format($pendingDocuments, 0, ',', '.')) ?></div>
              <div class="metric-help">Aguardando análise do admin.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-xmark"></i></div>
            <div>
              <div class="metric-label">Rejeitados</div>
              <div class="metric-value"><?= h(number_format($rejectedDocuments, 0, ',', '.')) ?></div>
              <div class="metric-help">Solicitações que não seguiram adiante.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="section-card card border-0 shadow-sm mb-4">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md-7">
          <label class="form-label small text-muted mb-1">Buscar nos meus pedidos</label>
          <input type="text" class="form-control" name="q" value="<?= h($q) ?>" placeholder="Ex.: setor, insumo, motivo, lote...">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label small text-muted mb-1">Status</label>
          <select class="form-select" name="status">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Todos</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pendentes</option>
            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Aprovados</option>
            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejeitados</option>
          </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          <a href="meus-pedidos-insumos.php" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i></a>
        </div>
      </form>
    </div>
  </div>

  <?php if (empty($groups)): ?>
    <div class="alert alert-info">Você ainda não possui pedidos de insumo nesse filtro.</div>
  <?php else: ?>
    <?php foreach ($groups as $group): ?>
      <div class="section-card card border-0 shadow-sm mb-4 pending-insumos-card">
        <div class="section-card-header card-header bg-white border-0 pt-3 pb-0">
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
            <div>
              <h2 class="h6 mb-1"><i class="fa-solid fa-layer-group me-2 text-primary"></i><?= h($group['sector']) ?></h2>
              <div class="text-muted small">Pedido enviado em <?= h(historyDate($group['requested_at'] ?? null)) ?></div>
            </div>
            <span class="badge <?= h(badgeClassForStatus((string)$group['status'])) ?>"><?= h(labelForStatus((string)$group['status'])) ?></span>
          </div>
        </div>
        <div class="card-body pt-0">
          <div class="row g-3 mb-4">
            <div class="col-12 col-lg-4">
              <div class="small text-muted">Solicitante</div>
              <div class="fw-semibold"><?= h((string)$group['user_nome']) ?></div>
              <div class="small text-muted"><?= h((string)$group['user_email']) ?></div>
            </div>
            <div class="col-12 col-lg-4">
              <div class="small text-muted">Data solicitada para entrega</div>
              <div class="fw-semibold"><?= h(historyDate($group['requested_at'] ?? null, 'd/m/Y')) ?></div>
            </div>
            <div class="col-12 col-lg-4">
              <div class="small text-muted">Processado em</div>
              <div class="fw-semibold"><?= h(historyDate($group['processed_at'] ?? null)) ?></div>
            </div>
          </div>

          <div class="table-responsive request-table-wrap mb-4">
            <table class="table table-hover align-middle mb-0 request-table pending-insumos-table">
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
                    </td>
                    <td data-label="Qtd. solicitada"><?= h(number_format((float)$item['quantidade'], 2, ',', '.')) ?> <?= h((string)$item['unidade']) ?></td>
                    <td data-label="Qtd. entregue"><?= !empty($item['quantidade_entregue']) ? h(number_format((float)$item['quantidade_entregue'], 2, ',', '.')) . ' ' . h((string)$item['unidade']) : '-' ?></td>
                    <td data-label="Lote"><?= h((string)($item['lote'] ?? '-')) ?></td>
                    <td data-label="Fabricação"><?= historyDate($item['fabricacao'] ?? null, 'd/m/Y') ?></td>
                    <td data-label="Validade"><?= historyDate($item['validade'] ?? null, 'd/m/Y') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="row g-3">
            <div class="col-12 col-lg-6">
              <div class="small text-muted mb-1">Motivo da solicitação</div>
              <div><?= h((string)($group['motivo_usuario'] !== '' ? $group['motivo_usuario'] : '-')) ?></div>
            </div>
            <div class="col-12 col-lg-6">
              <div class="small text-muted mb-1">Observação do admin</div>
              <div><?= h((string)($group['admin_note'] !== '' ? $group['admin_note'] : '-')) ?></div>
            </div>
          </div>

          <?php if (isDeletableStatus((string)$group['status'])): ?>
            <div class="mt-3 d-flex justify-content-end">
              <form method="post" onsubmit="return confirm('Excluir este pedido? Esta ação não pode ser desfeita.');">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="insumo_request_id" value="<?= (int)($group['ids'][0] ?? 0) ?>">
                <input type="hidden" name="action" value="delete_request">
                <button type="submit" class="btn btn-sm btn-outline-danger">
                  <i class="fa-solid fa-trash-can me-1"></i>Excluir pedido
                </button>
              </form>
            </div>
          <?php endif; ?>

          <?php if (!empty($group['processed_by_nome'])): ?>
            <div class="mt-3 small text-muted">Processado por <?= h((string)$group['processed_by_nome']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>