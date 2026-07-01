<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireLogin();

$pdo = getPDO();
ensureInsumoRequestsSchema($pdo);
$current = currentUser() ?: [];
$userId = (int)($current['id'] ?? 0);
$nomesInsumos = require __DIR__ . '/materiais-lista.php';
sort($nomesInsumos, SORT_NATURAL | SORT_FLAG_CASE);
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
$setoresDisponiveis = [
  'Saidas',
  'Recebimento',
  'AdequaçãoAdm',
  'Adequação',
  'DPS/VLM',
  'KIT-DPS',
  'FATURAMENTO',
  'QUALIDADE',
  'INVENTÁRIO',
  'EXPORTACÃO',
  'REVERSA',
];

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = (string)$_SESSION['csrf_token'];

$editFormOld = [];
if (!empty($_SESSION['meus_pedidos_edit_old']) && is_array($_SESSION['meus_pedidos_edit_old'])) {
  $editFormOld = $_SESSION['meus_pedidos_edit_old'];
  unset($_SESSION['meus_pedidos_edit_old']);
}

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

function canEditRequestStatus(string $status): bool {
  return $status === 'pending';
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

  if ($action === 'update_request_group') {
    $editGroupKey = trim((string)($_POST['edit_group_key'] ?? ''));
    $setor = trim((string)($_POST['setor'] ?? ''));
    $dataEntrega = trim((string)($_POST['data_solicitada_entrega'] ?? ''));
    $motivo = trim((string)($_POST['motivo_usuario'] ?? ''));
    $requestIdsRaw = $_POST['insumo_request_id'] ?? [];
    $insumoNomes = $_POST['insumo_nome'] ?? [];
    $quantidadesRaw = $_POST['quantidade'] ?? [];
    $unidadesRaw = $_POST['unidade'] ?? [];

    $errors = [];
    if ($editGroupKey === '') {
      $errors[] = 'Pedido inválido para edição.';
    }
    if ($setor === '') {
      $errors[] = 'Informe o setor solicitante.';
    } elseif (!in_array($setor, ['Saidas', 'Recebimento', 'AdequaçãoAdm', 'Adequação', 'DPS/VLM', 'KIT-DPS', 'FATURAMENTO', 'QUALIDADE', 'INVENTÁRIO', 'EXPORTACÃO', 'REVERSA'], true)) {
      $errors[] = 'Selecione um setor válido.';
    }
    if ($dataEntrega !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataEntrega)) {
      $errors[] = 'A data solicitada para entrega é inválida.';
    }

    $submittedRowCount = max(
      count((array)$requestIdsRaw),
      count((array)$insumoNomes),
      count((array)$quantidadesRaw),
      count((array)$unidadesRaw)
    );
    if ($submittedRowCount === 0) {
      $errors[] = 'Nenhuma linha foi selecionada para edição.';
    }

    $existingRows = [];
    $existingRowsById = [];
    if (!$errors) {
      $ids = array_values(array_filter(array_map('intval', (array)$requestIdsRaw), static fn(int $id): bool => $id > 0));
      $placeholders = implode(',', array_fill(0, count($ids), '?'));
      $stmt = $pdo->prepare('SELECT * FROM insumo_requests WHERE user_id = ? AND id IN (' . $placeholders . ') ORDER BY id ASC');
      $stmt->execute(array_merge([$userId], $ids));
      $existingRows = $stmt->fetchAll() ?: [];

      if (count($existingRows) !== count($ids)) {
        $errors[] = 'Algumas linhas não foram encontradas para este pedido.';
      } else {
        $currentGroupKey = null;
        foreach ($existingRows as $existingRow) {
          $existingRowsById[(int)($existingRow['id'] ?? 0)] = $existingRow;
          if (!canEditRequestStatus((string)($existingRow['status'] ?? ''))) {
            $errors[] = 'Somente pedidos pendentes podem ser editados.';
            break;
          }

          $rowKey = buildInsumoRequestGroupKey($existingRow);
          if ($currentGroupKey === null) {
            $currentGroupKey = $rowKey;
          } elseif ($currentGroupKey !== $rowKey) {
            $errors[] = 'As linhas selecionadas não pertencem ao mesmo pedido.';
            break;
          }
        }

        if ($currentGroupKey === null || $currentGroupKey !== $editGroupKey) {
          $errors[] = 'O pedido selecionado não corresponde ao grupo de edição.';
        }
      }
    }

    $itemsToUpdate = [];
    $itemsToInsert = [];
    if (!$errors) {
      $baseRow = $existingRows[0] ?? null;
      $baseRequestedAt = trim((string)($baseRow['requested_at'] ?? ''));
      if ($baseRequestedAt === '') {
        $baseRequestedAt = appDateTimeNow();
      }
      $baseBatchId = trim((string)($baseRow['batch_id'] ?? ''));
      $batchIdValue = $baseBatchId !== '' ? $baseBatchId : null;

      for ($index = 0; $index < $submittedRowCount; $index++) {
        $requestId = (int)($requestIdsRaw[$index] ?? 0);
        $postedInsumoNome = trim((string)($insumoNomes[$index] ?? ''));
        $postedQuantidadeText = trim((string)($quantidadesRaw[$index] ?? ''));
        $quantidadeRaw = str_replace(',', '.', $postedQuantidadeText);
        $quantidade = (float)$quantidadeRaw;
        $unidade = strtoupper(trim((string)($unidadesRaw[$index] ?? 'UN')));

        if ($requestId <= 0 && $postedInsumoNome === '' && $postedQuantidadeText === '') {
          continue;
        }

        $sourceRow = $requestId > 0 ? ($existingRowsById[$requestId] ?? null) : $baseRow;
        if ($requestId > 0 && $sourceRow === null) {
          $errors[] = 'Uma das linhas originais não foi encontrada para edição.';
          continue;
        }

        if ($postedInsumoNome === '') {
          $errors[] = 'Preencha o tipo de insumo da linha ' . ((int)$index + 1) . '.';
          continue;
        }

        $originalInsumoNome = trim((string)($sourceRow['insumo_nome'] ?? ''));
        if (!in_array($postedInsumoNome, $nomesInsumos, true) && $postedInsumoNome !== $originalInsumoNome) {
          $errors[] = 'O tipo de insumo da linha ' . ((int)$index + 1) . ' não está na lista disponível.';
          continue;
        }
        if ($quantidade <= 0) {
          $errors[] = 'Preencha uma quantidade válida na linha ' . ((int)$index + 1) . '.';
          continue;
        }
        if (!array_key_exists($unidade, $units)) {
          $unidade = 'UN';
        }

        $rowData = [
          'insumo_nome' => mb_substr($postedInsumoNome, 0, 190),
          'quantidade' => number_format($quantidade, 2, '.', ''),
          'unidade' => $unidade,
        ];

        if ($requestId > 0) {
          $rowData['id'] = $requestId;
          $itemsToUpdate[] = $rowData;
        } else {
          $itemsToInsert[] = $rowData;
        }
      }
    }

    if (empty($itemsToUpdate) && empty($itemsToInsert)) {
      $errors[] = 'Adicione pelo menos um insumo válido para atualizar.';
    }

    if (!$errors) {
      $pdo->beginTransaction();
      try {
        $updateStmt = $pdo->prepare(
          "UPDATE insumo_requests
             SET setor = ?, data_solicitada_entrega = ?, insumo_nome = ?, quantidade = ?, unidade = ?, motivo_usuario = ?, status = 'pending', admin_note = NULL, processed_at = NULL, processed_by = NULL
           WHERE id = ? AND user_id = ? AND status = 'pending'"
        );

        foreach ($itemsToUpdate as $item) {
          $updateStmt->execute([
            $setor,
            $dataEntrega !== '' ? $dataEntrega : null,
            $item['insumo_nome'],
            $item['quantidade'],
            $item['unidade'],
            $motivo,
            $item['id'],
            $userId,
          ]);
        }

        if (!empty($itemsToInsert)) {
          $insertStmt = $pdo->prepare(
            'INSERT INTO insumo_requests (user_id, user_nome, user_email, user_role, batch_id, setor, data_solicitada_entrega, insumo_nome, quantidade, unidade, motivo_usuario, status, requested_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
          );

          foreach ($itemsToInsert as $item) {
            $insertStmt->execute([
              $userId,
              (string)($current['nome'] ?? ''),
              (string)($current['email'] ?? ''),
              (string)($current['role'] ?? 'user'),
              $batchIdValue,
              $setor,
              $dataEntrega !== '' ? $dataEntrega : null,
              $item['insumo_nome'],
              $item['quantidade'],
              $item['unidade'],
              $motivo,
              'pending',
              $baseRequestedAt,
            ]);
          }
        }

        $pdo->commit();
        unset($_SESSION['meus_pedidos_edit_old']);
        flash('success', 'Pedido atualizado com sucesso.');
        header('Location: meus-pedidos-insumos.php');
        exit;
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
        }
        $errors[] = 'Não foi possível atualizar o pedido. Tente novamente.';
      }
    }

    $_SESSION['meus_pedidos_edit_old'] = [
      'setor' => $setor,
      'data_solicitada_entrega' => $dataEntrega,
      'motivo_usuario' => $motivo,
      'insumo_nome' => array_map(static fn($value) => trim((string)$value), (array)$insumoNomes),
      'quantidade' => array_map(static fn($value) => trim((string)$value), (array)$quantidadesRaw),
      'unidade' => array_map(static fn($value) => strtoupper(trim((string)$value)), (array)$unidadesRaw),
    ];

    flash('error', implode(' ', $errors));
    header('Location: meus-pedidos-insumos.php?edit=' . rawurlencode($editGroupKey));
    exit;
  }

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

  if ($action === 'delete_all_requests') {
    $deleteAllStmt = $pdo->prepare('DELETE FROM insumo_requests WHERE user_id = ?');
    $deleteAllStmt->execute([$userId]);

    if ($deleteAllStmt->rowCount() > 0) {
      flash('success', 'Todos os seus pedidos foram apagados com sucesso.');
    } else {
      flash('info', 'Não havia pedidos para apagar.');
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
      'group_key' => $groupKey,
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

$editGroupKey = trim((string)($_GET['edit'] ?? ''));
$editGroup = null;
if ($editGroupKey !== '' && isset($groups[$editGroupKey]) && canEditRequestStatus((string)$groups[$editGroupKey]['status'])) {
  $editGroup = $groups[$editGroupKey];
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
          <?php if ($totalDocuments > 0): ?>
            <form method="post" class="m-0" onsubmit="return confirm('Apagar todos os seus pedidos, inclusive aprovados e rejeitados? Esta ação não pode ser desfeita.');">
              <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
              <input type="hidden" name="action" value="delete_all_requests">
              <button type="submit" class="btn btn-outline-danger w-100">
                <i class="fa-solid fa-trash-can me-1"></i>Apagar tudo
              </button>
            </form>
          <?php endif; ?>
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
    <?php if ($editGroup !== null): ?>
      <div class="section-card card border-0 shadow-sm mb-4 pending-insumos-card">
        <div class="section-card-header card-header bg-white border-0 pt-3 pb-0">
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
            <div>
              <h2 class="h6 mb-1"><i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Editar pedido pendente</h2>
              <div class="text-muted small">Ajuste os itens enviados errado antes do atendimento do admin.</div>
            </div>
            <a href="meus-pedidos-insumos.php" class="btn btn-sm btn-outline-secondary">Cancelar edição</a>
          </div>
        </div>
        <div class="card-body pt-0">
          <form method="post" class="row g-3 form-responsive">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="update_request_group">
            <input type="hidden" name="edit_group_key" value="<?= h($editGroupKey) ?>">

            <div class="col-12 col-md-6">
              <label class="form-label">Setor</label>
              <select class="form-select" name="setor" required>
                <option value="">Selecione o setor</option>
                <?php foreach ($setoresDisponiveis as $setorItem): ?>
                  <option value="<?= h($setorItem) ?>" <?= ((string)($editFormOld['setor'] ?? $editGroup['sector']) === $setorItem) ? 'selected' : '' ?>><?= h($setorItem) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Data solicitada para entrega</label>
              <input type="date" class="form-control" name="data_solicitada_entrega" value="<?= h((string)($editFormOld['data_solicitada_entrega'] ?? $editGroup['items'][0]['data_solicitada_entrega'] ?? '')) ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Motivo da solicitação</label>
              <textarea class="form-control" name="motivo_usuario" rows="3" placeholder="Explique a necessidade do insumo"><?= h((string)($editFormOld['motivo_usuario'] ?? $editGroup['motivo_usuario'] ?? '')) ?></textarea>
            </div>

            <div class="col-12">
              <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <div class="small text-muted">Adicione mais insumos ao mesmo pedido antes de salvar.</div>
                <button type="button" class="btn btn-outline-primary btn-sm solicitacao-add-row-btn" id="btn-add-edit-insumo-row">
                  <i class="fa-solid fa-circle-plus me-1"></i>Adicionar linha
                </button>
              </div>
              <div class="table-responsive request-table-wrap">
                <?php $editRenderedRowCount = max(count($editGroup['items']), count((array)($editFormOld['insumo_nome'] ?? [])), count((array)($editFormOld['quantidade'] ?? [])), count((array)($editFormOld['unidade'] ?? []))); ?>
                <table class="table table-bordered align-middle mb-0 request-table solicitacao-document-table js-no-datatable" id="edit-insumo-itens-table">
                  <thead>
                    <tr>
                      <th style="width: 8%;">#</th>
                      <th style="width: 36%;">Tipo de insumo</th>
                      <th style="width: 18%;">Quantidade solicitada</th>
                      <th style="width: 12%;">Unidade</th>
                    </tr>
                  </thead>
                  <tbody id="edit-insumo-itens-body">
                    <?php for ($index = 0; $index < $editRenderedRowCount; $index++): ?>
                      <?php $item = $editGroup['items'][$index] ?? []; ?>
                      <?php $rowId = (int)($editGroup['ids'][$index] ?? 0); ?>
                      <?php $currentInsumoNome = (string)($editFormOld['insumo_nome'][$index] ?? ($item['insumo_nome'] ?? '')); ?>
                      <?php $currentQuantity = (string)($editFormOld['quantidade'][$index] ?? (isset($item['quantidade']) ? number_format((float)$item['quantidade'], 2, '.', '') : '')); ?>
                      <?php $currentUnit = strtoupper((string)($editFormOld['unidade'][$index] ?? ($item['unidade'] ?? 'UN'))); ?>
                      <tr>
                        <td class="align-middle text-muted small">
                          <input type="hidden" name="insumo_request_id[]" value="<?= (int)$rowId ?>">
                          <?= (int)($index + 1) ?>
                        </td>
                        <td>
                          <select class="form-select" name="insumo_nome[]" required>
                            <option value="">Selecione o material</option>
                            <?php if ($currentInsumoNome !== '' && !in_array($currentInsumoNome, $nomesInsumos, true)): ?>
                              <option value="<?= h($currentInsumoNome) ?>" selected><?= h($currentInsumoNome) ?> (não disponível no cadastro)</option>
                            <?php endif; ?>
                            <?php foreach ($nomesInsumos as $nomeItem): ?>
                              <option value="<?= h($nomeItem) ?>" <?= ($currentInsumoNome === $nomeItem) ? 'selected' : '' ?>><?= h($nomeItem) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                        <td>
                          <input type="number" class="form-control" name="quantidade[]" min="0.01" step="0.01" value="<?= h($currentQuantity) ?>" required>
                        </td>
                        <td>
                          <select class="form-select" name="unidade[]" required>
                            <?php foreach ($units as $unitCode => $unitLabel): ?>
                              <option value="<?= h($unitCode) ?>" <?= $currentUnit === $unitCode ? 'selected' : '' ?>><?= h($unitCode) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                      </tr>
                    <?php endfor; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="col-12 d-grid d-md-flex justify-content-md-end">
              <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-floppy-disk me-2"></i>Salvar alterações</button>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <template id="edit-insumo-row-template">
      <tr>
        <td class="align-middle text-muted small">
          <input type="hidden" name="insumo_request_id[]" value="">
          <span class="edit-row-number"></span>
        </td>
        <td>
          <select class="form-select" name="insumo_nome[]" required>
            <option value="">Selecione o material</option>
            <?php foreach ($nomesInsumos as $nomeItem): ?>
              <option value="<?= h($nomeItem) ?>"><?= h($nomeItem) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td>
          <input type="number" class="form-control" name="quantidade[]" min="0.01" step="0.01" placeholder="0" required>
        </td>
        <td>
          <select class="form-select" name="unidade[]" required>
            <?php foreach ($units as $unitCode => $unitLabel): ?>
              <option value="<?= h($unitCode) ?>" <?= $unitCode === 'UN' ? 'selected' : '' ?>><?= h($unitCode) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
    </template>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
      const tbody = document.getElementById('edit-insumo-itens-body');
      const addButton = document.getElementById('btn-add-edit-insumo-row');
      const template = document.getElementById('edit-insumo-row-template');

      if (!tbody || !addButton || !template) {
        return;
      }

      function renumberRows() {
        const rows = tbody.querySelectorAll('tr');
        rows.forEach(function (row, index) {
          const number = row.querySelector('.edit-row-number');
          if (number) {
            number.textContent = String(index + 1);
          }
        });
      }

      function addRow() {
        const fragment = template.content.cloneNode(true);
        tbody.appendChild(fragment);
        renumberRows();
        const lastRow = tbody.querySelector('tr:last-child');
        const firstField = lastRow ? lastRow.querySelector('select[name="insumo_nome[]"]') : null;
        if (firstField) {
          firstField.focus();
        }
      }

      addButton.addEventListener('click', addRow);
      renumberRows();
    });
    </script>

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
              <div class="d-flex gap-2 flex-wrap">
                <?php if (canEditRequestStatus((string)$group['status'])): ?>
                  <?php $editUrl = 'meus-pedidos-insumos.php?edit=' . rawurlencode((string)($group['group_key'] ?? '')); ?>
                  <a class="btn btn-sm btn-outline-primary" href="<?= h($editUrl) ?>">
                    <i class="fa-solid fa-pen-to-square me-1"></i>Editar pedido
                  </a>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('Excluir este pedido? Esta ação não pode ser desfeita.');">
                  <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                  <input type="hidden" name="insumo_request_id" value="<?= (int)($group['ids'][0] ?? 0) ?>">
                  <input type="hidden" name="action" value="delete_request">
                  <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="fa-solid fa-trash-can me-1"></i>Excluir pedido
                  </button>
                </form>
              </div>
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