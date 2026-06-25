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
$current = currentUser();
$adminId = (int)($current['id'] ?? 0);
$adminNome = trim((string)($current['nome'] ?? ''));

function getPendingInsumoUnitOptions(): array {
  return [
    'CM' => 'Centímetro',
    'CX' => 'Caixa',
    'FD' => 'Fardo',
    'G' => 'Grama',
    'KG' => 'Quilograma',
    'L' => 'Litro',
    'M' => 'Metro',
    'ML' => 'Mililitro',
    'PAR' => 'Par',
    'PCT' => 'Pacote',
    'RO' => 'Rolo',
    'UN' => 'Unidade',
  ];
}

$unitOptions = getPendingInsumoUnitOptions();

function ensureSaidaConsumoSchemaFromPending(PDO $pdo): void {
  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS saida_consumo (
      id INT AUTO_INCREMENT PRIMARY KEY,
      setor VARCHAR(120) NOT NULL,
      produto_nome VARCHAR(190) NOT NULL,
      quantidade DECIMAL(12,2) NOT NULL,
      unidade VARCHAR(20) NOT NULL DEFAULT 'UN',
      responsavel VARCHAR(190) NOT NULL,
      data_consumo DATE NOT NULL,
      observacao TEXT NULL,
      insumo_id INT NULL,
      estoque_atualizado TINYINT(1) NOT NULL DEFAULT 0,
      created_by INT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_sc_setor (setor),
      INDEX idx_sc_produto (produto_nome),
      INDEX idx_sc_data (data_consumo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );
}

function insertApprovedRequestConsumption(PDO $pdo, array $request, int $adminId, string $adminNome, string $processedAt): void {
  ensureSaidaConsumoSchemaFromPending($pdo);

  $deliveredUnit = strtoupper(trim((string)($request['unidade_entregue'] ?? $request['unidade'] ?? 'UN')));

  $insert = $pdo->prepare(
    'INSERT INTO saida_consumo
      (setor, produto_nome, quantidade, unidade, responsavel, data_consumo, observacao, insumo_id, estoque_atualizado, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );

  $insert->execute([
    mb_substr(trim((string)($request['setor'] ?? '')), 0, 120),
    mb_substr(trim((string)($request['insumo_nome'] ?? '')), 0, 190),
    number_format((float)($request['quantidade_entregue'] ?? $request['quantidade'] ?? 0), 2, '.', ''),
    mb_substr($deliveredUnit !== '' ? $deliveredUnit : 'UN', 0, 20),
    mb_substr($adminNome !== '' ? $adminNome : 'Administrador', 0, 190),
    substr($processedAt, 0, 10),
    trim((string)($request['admin_note'] ?? '')) !== '' ? mb_substr((string)$request['admin_note'], 0, 5000) : null,
    !empty($request['insumo_id']) ? (int)$request['insumo_id'] : null,
    1,
    $adminId > 0 ? $adminId : null,
  ]);
}

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

function normalizeNullableDate(string $value): ?string {
  $value = trim($value);
  return $value === '' ? null : $value;
}

if (!function_exists('appDateTimeNow')) {
  function appDateTimeNow(): string {
    $timezone = new DateTimeZone('America/Sao_Paulo');
    return (new DateTimeImmutable('now', $timezone))->format('Y-m-d H:i:s');
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
      flash('error', 'Sessão inválida. Atualize a página e tente novamente.');
      header('Location: pedidos-insumos-pendentes.php');
      exit;
    }

    $action = (string)($_POST['action'] ?? '');
    $insumoRequestId = (int)($_POST['insumo_request_id'] ?? 0);
    $insumoRequestIds = array_values(array_filter(array_map('intval', (array)($_POST['insumo_request_ids'] ?? [])), static function (int $value): bool {
      return $value > 0;
    }));
    $reason = trim((string)($_POST['reason'] ?? ''));

    if ($insumoRequestId > 0 && empty($insumoRequestIds)) {
      $insumoRequestIds = [$insumoRequestId];
    }

    if (!empty($insumoRequestIds)) {
      $placeholders = implode(',', array_fill(0, count($insumoRequestIds), '?'));

      if (in_array($action, ['insumo_process', 'insumo_approve', 'insumo_batch_approve'], true)) {
        $reqStmt = $pdo->prepare('SELECT id FROM insumo_requests WHERE id IN (' . $placeholders . ") AND status = 'pending'");
        $reqStmt->execute($insumoRequestIds);
        $foundIds = array_map('intval', array_column($reqStmt->fetchAll(), 'id'));

        if (count($foundIds) !== count($insumoRequestIds)) {
          flash('error', 'Uma ou mais solicitações não foram encontradas ou já foram processadas.');
          header('Location: pedidos-insumos-pendentes.php');
          exit;
        }

        $quantidadeEntregueMap = (array)($_POST['quantidade_entregue'] ?? []);
        $unidadeEntregueMap = (array)($_POST['unidade_entregue'] ?? []);
        $loteMap = (array)($_POST['lote'] ?? []);
        $fabricacaoMap = (array)($_POST['fabricacao'] ?? []);
        $validadeMap = (array)($_POST['validade'] ?? []);
        $processedAt = appDateTimeNow();

        $updateStmt = $pdo->prepare(
          'UPDATE insumo_requests
          SET quantidade_entregue = ?, unidade_entregue = ?, lote = ?, fabricacao = ?, validade = ?, status = \'approved\', admin_note = ?, processed_at = ?, processed_by = ?
           WHERE id = ? AND status = \'pending\''
        );

        $updatedCount = 0;
        foreach ($insumoRequestIds as $requestId) {
          $rawQuantidade = trim((string)($quantidadeEntregueMap[$requestId] ?? $quantidadeEntregueMap[(string)$requestId] ?? ''));
          if ($rawQuantidade === '') {
            flash('error', 'Informe a quantidade entregue para todos os itens antes de atender a solicitação.');
            header('Location: pedidos-insumos-pendentes.php');
            exit;
          }

          $requestStmt = $pdo->prepare('SELECT * FROM insumo_requests WHERE id = ? LIMIT 1');
          $requestStmt->execute([$requestId]);
          $pendingRequest = $requestStmt->fetch() ?: null;
          if (!$pendingRequest) {
            flash('error', 'Solicitação não encontrada para o item #' . $requestId . '.');
            header('Location: pedidos-insumos-pendentes.php');
            exit;
          }

          $normalizedQuantidade = str_replace(',', '.', $rawQuantidade);
          if (!is_numeric($normalizedQuantidade) || (float)$normalizedQuantidade <= 0) {
            flash('error', 'Quantidade entregue inválida no item #' . $requestId . '.');
            header('Location: pedidos-insumos-pendentes.php');
            exit;
          }

          $updateStmt->execute([
            (float)$normalizedQuantidade,
            mb_substr(strtoupper(trim((string)($unidadeEntregueMap[$requestId] ?? $unidadeEntregueMap[(string)$requestId] ?? ($pendingRequest['unidade'] ?? 'UN')))), 0, 20),
            trim((string)($loteMap[$requestId] ?? $loteMap[(string)$requestId] ?? '')),
            normalizeNullableDate((string)($fabricacaoMap[$requestId] ?? $fabricacaoMap[(string)$requestId] ?? '')),
            normalizeNullableDate((string)($validadeMap[$requestId] ?? $validadeMap[(string)$requestId] ?? '')),
            'Solicitação de insumo aprovada em lote pela administração.',
            $processedAt,
            $adminId,
            $requestId,
          ]);

          if ($updateStmt->rowCount() > 0) {
            $approvedRequest = $pendingRequest;
            $approvedRequest['quantidade_entregue'] = (float)$normalizedQuantidade;
            $approvedRequest['unidade_entregue'] = strtoupper(trim((string)($unidadeEntregueMap[$requestId] ?? $unidadeEntregueMap[(string)$requestId] ?? ($pendingRequest['unidade'] ?? 'UN'))));
            if ($approvedRequest['unidade_entregue'] === '') {
              $approvedRequest['unidade_entregue'] = (string)($pendingRequest['unidade'] ?? 'UN');
            }
            insertApprovedRequestConsumption($pdo, $approvedRequest, $adminId, $adminNome, $processedAt);
              $updatedCount += 1;
          }
        }

        if ($updatedCount > 0) {
          flash('success', 'Solicitação de insumo aprovada em bloco com os dados de entrega salvos.');
          header('Location: historico-pedidos-insumos.php');
          exit;
        }

        flash('error', 'Não foi possível aprovar esta solicitação.');
      } elseif (in_array($action, ['insumo_reject', 'insumo_batch_reject'], true)) {
        if ($reason === '') {
          flash('error', 'Informe o motivo da rejeição da solicitação de insumo.');
          header('Location: pedidos-insumos-pendentes.php');
          exit;
        }

        $reqStmt = $pdo->prepare('SELECT id FROM insumo_requests WHERE id IN (' . $placeholders . ") AND status = 'pending'");
        $reqStmt->execute($insumoRequestIds);
        $foundIds = array_map('intval', array_column($reqStmt->fetchAll(), 'id'));

        if (count($foundIds) !== count($insumoRequestIds)) {
          flash('error', 'Uma ou mais solicitações não foram encontradas ou já foram processadas.');
          header('Location: pedidos-insumos-pendentes.php');
          exit;
        }

        $processedAt = appDateTimeNow();
        $upReq = $pdo->prepare('UPDATE insumo_requests SET status = \'rejected\', admin_note = ?, processed_at = ?, processed_by = ? WHERE id IN (' . $placeholders . ") AND status = 'pending'");
        $upReq->execute(array_merge([$reason, $processedAt, $adminId], $insumoRequestIds));

        if ($upReq->rowCount() > 0) {
          flash('success', 'Solicitação de insumo rejeitada.');
        } else {
          flash('error', 'Não foi possível rejeitar esta solicitação.');
        }
      }
    }

    header('Location: pedidos-insumos-pendentes.php');
    exit;
  } catch (Throwable $e) {
    flash('error', 'Ocorreu um erro ao processar a solicitação: ' . $e->getMessage());
    header('Location: pedidos-insumos-pendentes.php');
    exit;
  }
}

$insumoRequestsStmt = $pdo->prepare(
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
      ir.processed_by
    FROM insumo_requests ir
    WHERE ir.status = 'pending'
    ORDER BY ir.requested_at ASC, ir.id ASC"
);
$insumoRequestsStmt->execute();
$pendingInsumoRequests = $insumoRequestsStmt->fetchAll();
$sectorFilter = trim((string)($_GET['sector'] ?? ''));
if ($sectorFilter !== '') {
  $sectorFilterNormalized = mb_strtolower($sectorFilter);
  $pendingInsumoRequests = array_values(array_filter($pendingInsumoRequests, static function (array $request) use ($sectorFilterNormalized): bool {
    return mb_strtolower(trim((string)($request['setor'] ?? ''))) === $sectorFilterNormalized;
  }));
}
$pendingInsumoCount = count($pendingInsumoRequests);

$pendingInsumoRequestsByGroup = [];
foreach ($pendingInsumoRequests as $request) {
  $groupKey = buildInsumoRequestGroupKey($request);

  if (!isset($pendingInsumoRequestsByGroup[$groupKey])) {
    $pendingInsumoRequestsByGroup[$groupKey] = [
      'group_key' => $groupKey,
      'batch_id' => trim((string)($request['batch_id'] ?? '')),
      'sector' => trim((string)($request['setor'] ?? '')) !== '' ? trim((string)$request['setor']) : 'Sem setor',
      'requested_at' => $request['requested_at'] ?? null,
      'data_solicitada_entrega' => $request['data_solicitada_entrega'] ?? null,
      'user_nome' => $request['user_nome'] ?? '',
      'user_email' => $request['user_email'] ?? '',
      'user_role' => $request['user_role'] ?? 'user',
      'motivo_usuario' => $request['motivo_usuario'] ?? '',
      'items' => [],
      'ids' => [],
    ];
  }

  $pendingInsumoRequestsByGroup[$groupKey]['items'][] = $request;
  $pendingInsumoRequestsByGroup[$groupKey]['ids'][] = (int)$request['id'];
}

$pendingInsumoSolicitationsCount = count($pendingInsumoRequestsByGroup);

require_once __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="solicitacoes-kicker">Fila operacional</span>
          <h1 class="display-6 fw-semibold mb-2">Pedidos de insumo pendentes</h1>
          <p class="solicitacoes-subtitle mb-0">Atenda solicitações abertas em blocos por setor, sem misturar com a administração geral.</p>
          <?php if ($sectorFilter !== ''): ?>
            <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
              <span class="solicitacoes-pill">
                <i class="fa-solid fa-filter"></i>
                Filtro ativo: <?= h($sectorFilter) ?>
              </span>
              <a href="pedidos-insumos-pendentes.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-xmark me-1"></i>Limpar filtro
              </a>
            </div>
          <?php endif; ?>
        </div>
        <div class="text-lg-end d-flex flex-column gap-2 align-items-lg-end">
          <a href="export_pedidos_insumos_pdf.php" class="btn btn-outline-danger">
            <i class="fa-solid fa-file-pdf me-1"></i>Exportar PDF
          </a>
          <a href="historico-pedidos-insumos.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-box-archive me-1"></i>Ver histórico
          </a>
          <a href="solicitacoes.php" class="btn btn-outline-primary">
            <i class="fa-solid fa-arrow-left me-1"></i>Voltar para Administração
          </a>
          <small class="text-muted d-block">Use a fila para atender ou rejeitar a solicitação inteira de uma vez.</small>
        </div>
      </div>

      <div class="row g-3 mt-4">
        <div class="col-12 col-md-6 col-xl-4">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-info"><i class="fa-solid fa-box-open"></i></div>
            <div>
              <div class="metric-label">Solicitações pendentes</div>
              <div class="metric-value"><?= h(number_format($pendingInsumoSolicitationsCount, 0, ',', '.')) ?></div>
              <div class="metric-help"><?= h(number_format($pendingInsumoCount, 0, ',', '.')) ?> itens aguardando análise.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="section-card card border-0 shadow-sm mb-4 pending-insumos-card" id="pedidos-insumo-pendentes">
    <div class="section-card-header card-header bg-white border-0 pt-3 pb-0 pending-insumos-header">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
        <div>
          <h2 class="h5 mb-1"><i class="fa-solid fa-box-open me-2 text-primary"></i>Fila de solicitações</h2>
          <div class="text-muted small">Cada cartão representa uma solicitação inteira. Um clique atende ou rejeita tudo de uma vez.</div>
        </div>
        <div class="pending-insumos-pill">
          <i class="fa-solid fa-clock me-1"></i>
          <?= h(number_format($pendingInsumoSolicitationsCount, 0, ',', '.')) ?> <?= $pendingInsumoSolicitationsCount === 1 ? 'solicitação' : 'solicitações' ?>
        </div>
      </div>
    </div>
    <div class="card-body pt-0 pending-insumos-body">
      <?php if (empty($pendingInsumoRequestsByGroup)): ?>
        <div class="alert alert-info mb-0">
          <?php if ($sectorFilter !== ''): ?>
            Não há pedidos de insumo pendentes para o setor <?= h($sectorFilter) ?> no momento.
          <?php else: ?>
            Não há pedidos de insumo pendentes no momento.
          <?php endif; ?>
        </div>
      <?php else: ?>
        <?php foreach ($pendingInsumoRequestsByGroup as $group): ?>
          <?php $rowFormId = 'insumo-batch-' . md5((string)$group['group_key']); ?>
          <div class="pending-insumos-sector card border-0 shadow-sm mb-4 <?= $sectorFilter !== '' ? 'pending-insumos-sector-filtered' : '' ?>">
            <div class="card-header bg-white border-0 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
              <div>
                <h3 class="h6 mb-1"><i class="fa-solid fa-layer-group me-2 text-primary"></i><?= h($group['sector']) ?></h3>
                <div class="text-muted small"><?= h(number_format(count($group['items']), 0, ',', '.')) ?> item<?= count($group['items']) === 1 ? '' : 's' ?> nesta solicitação</div>
              </div>
              <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                <?php if ($sectorFilter !== ''): ?>
                  <span class="badge rounded-pill text-bg-primary pending-insumos-sector-badge">
                    <i class="fa-solid fa-filter me-1"></i><?= h($sectorFilter) ?>
                  </span>
                <?php endif; ?>
                <span class="badge bg-light text-dark border"><?= h($group['batch_id'] !== '' ? 'Solicitação em lote' : 'Solicitação avulsa') ?></span>
              </div>
            </div>
            <div class="card-body pt-0">
              <div class="row g-3 mb-4">
                <div class="col-12 col-lg-3">
                  <div class="small text-muted">Solicitante</div>
                  <div class="fw-semibold"><?= h((string)$group['user_nome']) ?></div>
                  <div class="small text-muted"><?= h((string)$group['user_email']) ?></div>
                </div>
                <div class="col-12 col-lg-3">
                  <div class="small text-muted">Data solicitada para entrega</div>
                  <div class="fw-semibold"><?= !empty($group['data_solicitada_entrega']) ? h(date('d/m/Y', strtotime((string)$group['data_solicitada_entrega']))) : '-' ?></div>
                </div>
                <div class="col-12 col-lg-3">
                  <div class="small text-muted">Solicitado em</div>
                  <div class="fw-semibold"><?= !empty($group['requested_at']) ? h(date('d/m/Y H:i', strtotime((string)$group['requested_at']))) : '-' ?></div>
                </div>
                <div class="col-12 col-lg-3">
                  <div class="small text-muted">Origem</div>
                  <div class="fw-semibold"><?= h($group['batch_id'] !== '' ? 'Lote único da solicitação' : 'Registro legado') ?></div>
                </div>
              </div>

              <div class="mb-3">
                <div class="small text-muted mb-2">Unidades disponíveis para atendimento</div>
                <div class="d-flex flex-wrap gap-2">
                  <?php foreach ($unitOptions as $unitCode => $unitLabel): ?>
                    <span class="badge rounded-pill bg-light text-dark border pending-insumos-unit-badge">
                      <?= h($unitCode) ?> - <?= h($unitLabel) ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="table-responsive request-table-wrap mb-4">
                <table class="table table-hover align-middle mb-0 request-table pending-insumos-table">
                  <thead>
                    <tr>
                      <th style="width: 18%;">ID</th>
                      <th>Insumo</th>
                      <th style="width: 16%;">Quantidade solicitada</th>
                      <th style="width: 10%;">Unidade solicitada</th>
                      <th style="width: 16%;">Quantidade entregue</th>
                      <th style="width: 10%;">Unidade entregue</th>
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
                        <td data-label="Quantidade solicitada"><?= h(number_format((float)$item['quantidade'], 2, ',', '.')) ?></td>
                        <td data-label="Unidade solicitada">
                          <div class="fw-semibold"><?= h((string)($item['unidade'] ?? 'UN')) ?></div>
                          <div class="text-muted small">Escolhida pelo usuário</div>
                        </td>
                        <td data-label="Quantidade entregue">
                          <div class="pending-insumos-field">
                            <span class="pending-insumos-field-label">Qtd. entregue</span>
                            <input
                              type="number"
                              step="0.01"
                              min="0.01"
                              inputmode="decimal"
                              name="quantidade_entregue[<?= (int)$item['id'] ?>]"
                              form="<?= h($rowFormId) ?>"
                              class="form-control form-control-sm pending-insumos-input"
                              placeholder="Ex: 12,50"
                              required
                              value="<?= h((string)($item['quantidade_entregue'] ?? '')) ?>"
                            >
                          </div>
                        </td>
                        <td data-label="Unidade entregue">
                          <div class="pending-insumos-field">
                            <span class="pending-insumos-field-label">Unidade entregue</span>
                            <select
                              name="unidade_entregue[<?= (int)$item['id'] ?>]"
                              form="<?= h($rowFormId) ?>"
                              class="form-select form-select-sm pending-insumos-input"
                              required
                            >
                              <?php $selectedDeliveredUnit = strtoupper(trim((string)($item['unidade_entregue'] ?? $item['unidade'] ?? 'UN'))); ?>
                              <?php foreach ($unitOptions as $unitCode => $unitLabel): ?>
                                <option value="<?= h($unitCode) ?>" <?= $selectedDeliveredUnit === $unitCode ? 'selected' : '' ?>><?= h($unitCode . ' - ' . $unitLabel) ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                        </td>
                        <td data-label="Lote">
                          <div class="pending-insumos-field">
                            <span class="pending-insumos-field-label">Lote</span>
                            <input
                              type="text"
                              name="lote[<?= (int)$item['id'] ?>]"
                              form="<?= h($rowFormId) ?>"
                              class="form-control form-control-sm pending-insumos-input"
                              placeholder="Informe o lote"
                              value="<?= h((string)($item['lote'] ?? '')) ?>"
                            >
                          </div>
                        </td>
                        <td data-label="Fabricação">
                          <div class="pending-insumos-field">
                            <span class="pending-insumos-field-label">Fabricação</span>
                            <input
                              type="date"
                              name="fabricacao[<?= (int)$item['id'] ?>]"
                              form="<?= h($rowFormId) ?>"
                              class="form-control form-control-sm pending-insumos-input"
                              value="<?= h((string)($item['fabricacao'] ?? '')) ?>"
                            >
                          </div>
                        </td>
                        <td data-label="Validade">
                          <div class="pending-insumos-field">
                            <span class="pending-insumos-field-label">Validade</span>
                            <input
                              type="date"
                              name="validade[<?= (int)$item['id'] ?>]"
                              form="<?= h($rowFormId) ?>"
                              class="form-control form-control-sm pending-insumos-input"
                              value="<?= h((string)($item['validade'] ?? '')) ?>"
                            >
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="mb-3">
                <div class="small text-muted mb-1">Motivo da solicitação</div>
                <div><?= h((string)($group['motivo_usuario'] !== '' ? $group['motivo_usuario'] : '-')) ?></div>
              </div>

              <div class="d-flex flex-column flex-md-row gap-2 justify-content-md-end">
                <form id="<?= h($rowFormId) ?>" method="post" class="m-0 d-inline-flex gap-2 flex-wrap justify-content-end align-items-start" onsubmit="return confirm('Atender esta solicitação inteira?');">
                  <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                  <?php foreach ($group['ids'] as $requestId): ?>
                    <input type="hidden" name="insumo_request_ids[]" value="<?= (int)$requestId ?>">
                  <?php endforeach; ?>
                  <input type="hidden" name="action" value="insumo_batch_approve">
                  <button type="submit" class="btn btn-sm btn-success pending-insumos-approve"><i class="fa-solid fa-check me-1"></i>Atender tudo</button>
                </form>
                <form method="post" class="m-0 d-inline" onsubmit="return requestActionReason(this, 'rejeitar esta solicitação');">
                  <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                  <?php foreach ($group['ids'] as $requestId): ?>
                    <input type="hidden" name="insumo_request_ids[]" value="<?= (int)$requestId ?>">
                  <?php endforeach; ?>
                  <input type="hidden" name="action" value="insumo_batch_reject">
                  <input type="hidden" name="reason" value="">
                  <button type="submit" class="btn btn-sm btn-outline-danger pending-insumos-reject"><i class="fa-solid fa-xmark me-1"></i>Rejeitar tudo</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function requestActionReason(form, actionLabel) {
  var promptText = 'Digite o motivo para ' + actionLabel + ':';
  var reason = window.prompt(promptText, '');

  if (reason === null) {
    return false;
  }

  reason = reason.trim();
  if (reason.length === 0) {
    alert('Motivo é obrigatório.');
    return false;
  }

  var input = form.querySelector('input[name="reason"]');
  if (input) {
    input.value = reason;
  }

  return true;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
