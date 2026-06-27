<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pdo = getPDO();
ensureInsumoRequestsSchema($pdo);
$current = currentUser() ?: [];
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

if (!function_exists('appDateTimeNow')) {
  function appDateTimeNow(): string {
    $timezone = new DateTimeZone('America/Sao_Paulo');
    return (new DateTimeImmutable('now', $timezone))->format('Y-m-d H:i:s');
  }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedToken = (string)($_POST['csrf_token'] ?? '');
  if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
    flash('error', 'Sessão inválida. Atualize a página e tente novamente.');
    header('Location: solicitar-insumo.php');
    exit;
  }

  $setor = trim((string)($_POST['setor'] ?? ''));
  $dataEntrega = trim((string)($_POST['data_solicitada_entrega'] ?? ''));
  $insumoNomes = $_POST['insumo_nome'] ?? [];
  $quantidadesRaw = $_POST['quantidade'] ?? [];
  $unidadesRaw = $_POST['unidade'] ?? [];
  $motivo = trim((string)($_POST['motivo_usuario'] ?? ''));

  $errors = [];
  if ($setor === '') {
    $errors[] = 'Informe o setor solicitante.';
  } elseif (!in_array($setor, $setoresDisponiveis, true)) {
    $errors[] = 'Selecione um setor válido.';
  }
  if ($dataEntrega !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataEntrega)) {
    $errors[] = 'A data solicitada para entrega é inválida.';
  }
  $items = [];
  foreach ($insumoNomes as $index => $insumoNomeRaw) {
    $insumoNome = trim((string)$insumoNomeRaw);
    $quantidadeRaw = str_replace(',', '.', trim((string)($quantidadesRaw[$index] ?? '')));
    $quantidade = (float)$quantidadeRaw;
    $unidade = strtoupper(trim((string)($unidadesRaw[$index] ?? 'UN')));

    if ($insumoNome === '' && $quantidadeRaw === '') {
      continue;
    }

    if ($insumoNome === '') {
      $errors[] = 'Preencha o tipo de insumo da linha ' . ((int)$index + 1) . '.';
      continue;
    }
    if (!in_array($insumoNome, $nomesInsumos, true)) {
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

    $items[] = [
      'insumo_nome' => mb_substr($insumoNome, 0, 190),
      'quantidade' => number_format($quantidade, 2, '.', ''),
      'unidade' => $unidade,
    ];
  }

  if (empty($items)) {
    $errors[] = 'Adicione pelo menos um insumo para solicitar.';
  }

  if (!$errors) {
    $requestBatchId = bin2hex(random_bytes(16));
    $requestedAt = appDateTimeNow();
    $stmt = $pdo->prepare(
      'INSERT INTO insumo_requests (user_id, user_nome, user_email, user_role, batch_id, setor, data_solicitada_entrega, insumo_nome, quantidade, unidade, motivo_usuario, status, requested_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($items as $item) {
      $stmt->execute([
        (int)($current['id'] ?? 0),
        (string)($current['nome'] ?? ''),
        (string)($current['email'] ?? ''),
        (string)($current['role'] ?? 'user'),
        $requestBatchId,
        $setor,
        $dataEntrega !== '' ? $dataEntrega : null,
        $item['insumo_nome'],
        $item['quantidade'],
        $item['unidade'],
        $motivo,
        'pending',
        $requestedAt,
      ]);
    }

    flash('success', 'Solicitação de insumos enviada com sucesso.');
    header('Location: solicitar-insumo.php');
    exit;
  }

  flash('error', implode(' ', $errors));
  header('Location: solicitar-insumo.php');
  exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="solicitacoes-kicker">Pedido de insumo</span>
          <h1 class="display-6 fw-semibold mb-2">Solicitar insumo</h1>
          <p class="solicitacoes-subtitle mb-0">Preencha a folha de solicitação abaixo. Você pode lançar vários insumos no mesmo pedido, linha por linha.</p>
        </div>
        <div class="text-lg-end">
          <div class="solicitacoes-pill">Página exclusiva de solicitação</div>
          <small class="text-muted d-block mt-2">Adicione as linhas conforme precisar.</small>
          <a href="meus-pedidos-insumos.php" class="btn btn-outline-primary btn-sm mt-3">
            <i class="fa-solid fa-box-archive me-1"></i>Ver meus pedidos
          </a>
        </div>
      </div>
    </div>
  </section>

  <?php if ($m = flash('error')): ?>
    <div class="alert alert-danger"><?= h($m) ?></div>
  <?php endif; ?>
  <?php if ($m = flash('success')): ?>
    <div class="alert alert-success"><?= h($m) ?></div>
  <?php endif; ?>

  <section class="section-card card border-0 shadow-sm">
    <div class="section-card-header card-header bg-white border-0 pt-3 pb-0">
      <h2 class="h5 mb-3"><i class="fa-solid fa-file-signature me-2 text-primary"></i>Solicitação de insumos</h2>
    </div>
    <div class="card-body pt-0">
      <div class="solicitacao-sheet mb-4">
        <div class="solicitacao-sheet-top">
          <div class="sheet-field">
            <label>Solicitante:</label>
            <div class="sheet-value"><?= h((string)($current['nome'] ?? '')) ?></div>
          </div>
          <div class="sheet-field">
            <label>Data e hora:</label>
            <div class="sheet-value"><?= h(date('d/m/Y H:i')) ?></div>
          </div>
          <div class="sheet-field">
            <label>Setor:</label>
            <div class="sheet-value">Preencher abaixo</div>
          </div>
          <div class="sheet-field">
            <label>Data solicitada para entrega:</label>
            <div class="sheet-value">Preencher abaixo</div>
          </div>
        </div>
        <div class="mt-2 small text-muted">A hora do pedido é registrada automaticamente no envio.</div>
      </div>

      <form method="post" class="row g-3 form-responsive">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="col-12 col-md-6">
          <label class="form-label">Setor</label>
          <select class="form-select" name="setor" required>
            <option value="">Selecione o setor</option>
            <?php foreach ($setoresDisponiveis as $setorItem): ?>
              <option value="<?= h($setorItem) ?>" <?= (($_POST['setor'] ?? '') === $setorItem) ? 'selected' : '' ?>><?= h($setorItem) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Data solicitada para entrega</label>
          <input type="date" class="form-control" name="data_solicitada_entrega">
        </div>
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <div class="small text-muted">Adicione ou remova linhas antes de enviar.</div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-insumo-row">
              <i class="fa-solid fa-circle-plus me-1"></i>Adicionar linha
            </button>
          </div>
          <div class="table-responsive request-table-wrap">
            <table class="table table-bordered align-middle mb-0 request-table solicitacao-document-table js-no-datatable" id="solicitacao-itens-table">
              <thead>
                <tr>
                  <th style="width: 32%;">Tipo de insumo</th>
                  <th style="width: 18%;">Quantidade solicitada</th>
                  <th style="width: 12%;">Unidade</th>
                  <th style="width: 12%;">Ação</th>
                </tr>
              </thead>
              <tbody id="solicitacao-itens-body">
                <?php $postedInsumos = (array)($_POST['insumo_nome'] ?? ['']); ?>
                <?php $postedQuantidades = (array)($_POST['quantidade'] ?? ['']); ?>
                <?php $postedUnidades = (array)($_POST['unidade'] ?? ['UN']); ?>
                <?php $initialRows = max(1, count($postedInsumos)); ?>
                <?php for ($i = 0; $i < $initialRows; $i++): ?>
                  <tr data-insumo-row>
                    <td>
                      <select class="form-select" name="insumo_nome[]" required>
                        <option value="">Selecione o material</option>
                        <?php foreach ($nomesInsumos as $nomeItem): ?>
                          <option value="<?= h($nomeItem) ?>" <?= (($postedInsumos[$i] ?? '') === $nomeItem) ? 'selected' : '' ?>><?= h($nomeItem) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td><input type="number" class="form-control" name="quantidade[]" min="0.01" step="0.01" placeholder="0" value="<?= h((string)($postedQuantidades[$i] ?? '')) ?>" required></td>
                    <td>
                      <select class="form-select" name="unidade[]" required>
                        <?php $currentUnit = strtoupper((string)($postedUnidades[$i] ?? 'UN')); ?>
                        <?php foreach ($units as $unitCode => $unitLabel): ?>
                          <option value="<?= h($unitCode) ?>" <?= $currentUnit === $unitCode ? 'selected' : '' ?>><?= h($unitCode) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td class="text-muted small align-middle">
                      <button type="button" class="btn btn-outline-danger btn-sm w-100 btn-remove-insumo-row">
                        <i class="fa-solid fa-trash-can me-1"></i>Remover
                      </button>
                    </td>
                  </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label">Motivo da solicitação</label>
          <textarea class="form-control" name="motivo_usuario" rows="4" placeholder="Explique a necessidade do insumo"></textarea>
        </div>
        <div class="col-12 d-grid d-md-flex justify-content-md-end">
          <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-paper-plane me-2"></i>Enviar solicitação</button>
        </div>
      </form>
    </div>
  </section>
</div>

<template id="solicitacao-insumo-row-template">
  <tr data-insumo-row>
    <td>
      <select class="form-select" name="insumo_nome[]" required>
        <option value="">Selecione o material</option>
        <?php foreach ($nomesInsumos as $nomeItem): ?>
          <option value="<?= h($nomeItem) ?>"><?= h($nomeItem) ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input type="number" class="form-control" name="quantidade[]" min="0.01" step="0.01" placeholder="0" required></td>
    <td>
      <select class="form-select" name="unidade[]" required>
        <?php foreach ($units as $unitCode => $unitLabel): ?>
          <option value="<?= h($unitCode) ?>"><?= h($unitCode) ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td class="text-muted small align-middle">
      <button type="button" class="btn btn-outline-danger btn-sm w-100 btn-remove-insumo-row">
        <i class="fa-solid fa-trash-can me-1"></i>Remover
      </button>
    </td>
  </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const tbody = document.getElementById('solicitacao-itens-body');
  const addButton = document.getElementById('btn-add-insumo-row');
  const template = document.getElementById('solicitacao-insumo-row-template');

  if (!tbody || !addButton || !template) {
    return;
  }

  function updateRemoveButtons() {
    const rows = tbody.querySelectorAll('[data-insumo-row]');
    rows.forEach(function (row) {
      const removeButton = row.querySelector('.btn-remove-insumo-row');
      if (removeButton) {
        removeButton.disabled = rows.length === 1;
      }
    });
  }

  function createRow() {
    const clone = template.content.cloneNode(true);
    const row = clone.querySelector('[data-insumo-row]');
    tbody.appendChild(clone);
    updateRemoveButtons();
    const firstSelect = row ? row.querySelector('select[name="insumo_nome[]"]') : null;
    if (firstSelect) {
      firstSelect.focus();
    }
  }

  tbody.addEventListener('click', function (event) {
    const removeButton = event.target.closest('.btn-remove-insumo-row');
    if (!removeButton) {
      return;
    }

    const row = removeButton.closest('[data-insumo-row]');
    const rows = tbody.querySelectorAll('[data-insumo-row]');
    if (!row || rows.length === 1) {
      return;
    }

    row.remove();
    updateRemoveButtons();
  });

  addButton.addEventListener('click', createRow);
  updateRemoveButtons();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
