<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

function ensurePickingSchema(PDO $pdo): void {
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

  $columns = $pdo->query('SHOW COLUMNS FROM saida_consumo')->fetchAll();
  $existing = [];
  foreach ($columns as $column) {
    $existing[$column['Field']] = true;
  }

  if (empty($existing['unidade'])) {
    $pdo->exec("ALTER TABLE saida_consumo ADD COLUMN unidade VARCHAR(20) NOT NULL DEFAULT 'UN' AFTER quantidade");
  }
  if (empty($existing['insumo_id'])) {
    $pdo->exec('ALTER TABLE saida_consumo ADD COLUMN insumo_id INT NULL AFTER observacao');
  }
  if (empty($existing['estoque_atualizado'])) {
    $pdo->exec('ALTER TABLE saida_consumo ADD COLUMN estoque_atualizado TINYINT(1) NOT NULL DEFAULT 0 AFTER insumo_id');
  }
  if (empty($existing['created_by'])) {
    $pdo->exec('ALTER TABLE saida_consumo ADD COLUMN created_by INT NULL AFTER estoque_atualizado');
  }
  if (empty($existing['created_at'])) {
    $pdo->exec('ALTER TABLE saida_consumo ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER created_by');
  }
}

$pdo = getPDO();
ensureUserAuthSchema();
ensurePickingSchema($pdo);

$current = currentUser() ?: [];
$userId = (int)($current['id'] ?? 0);

$setoresDisponiveis = [
  'Outbound',
  'Inbound',
  'Adequaçao',
  'AdequaçãoAdm',
  'DPS/VLM',
  'KIT-DPS',
  'Faturamento',
  'qualidade',
  'INVENTÁRIO',
  'EXPORTAÇÃO REVERSA',
  'JOHSON E JOHSON',
];

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

$product = null;
$lookupCode = trim((string)($_POST['scan_code'] ?? $_GET['scan_code'] ?? ''));
$lookupMode = (string)($_POST['action'] ?? $_GET['action'] ?? '');
$errorMessages = [];
$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $lookupMode === 'consume') {
  $produtoId = (int)($_POST['produto_id'] ?? 0);
  $setor = trim((string)($_POST['setor'] ?? ''));
  $quantidadeRaw = str_replace(',', '.', trim((string)($_POST['quantidade'] ?? '')));
  $unidade = strtoupper(trim((string)($_POST['unidade'] ?? 'UN')));
  $responsavel = trim((string)($_POST['responsavel'] ?? ''));
  $dataConsumo = trim((string)($_POST['data_consumo'] ?? date('Y-m-d')));
  $observacao = trim((string)($_POST['observacao'] ?? ''));

  if ($produtoId <= 0) {
    $errorMessages[] = 'Produto não localizado. Faça a leitura do QR Code novamente.';
  }
  if ($setor === '') {
    $errorMessages[] = 'Informe o setor que consumiu o material.';
  } elseif (!in_array($setor, $setoresDisponiveis, true)) {
    $errorMessages[] = 'Selecione um setor válido.';
  }
  if ($quantidadeRaw === '' || !is_numeric($quantidadeRaw) || (float)$quantidadeRaw <= 0) {
    $errorMessages[] = 'Informe uma quantidade válida.';
  }
  if ($responsavel === '') {
    $errorMessages[] = 'Informe o responsável pelo consumo.';
  }
  if ($dataConsumo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataConsumo)) {
    $errorMessages[] = 'Informe uma data de consumo válida.';
  }
  if (!array_key_exists($unidade, $units)) {
    $unidade = 'UN';
  }

  if (!$errorMessages) {
    $pdo->beginTransaction();
    try {
      $findItem = $pdo->prepare('SELECT id, nome, quantidade FROM insumos_jnj WHERE id = ? LIMIT 1');
      $findItem->execute([$produtoId]);
      $item = $findItem->fetch() ?: null;

      if (!$item) {
        throw new RuntimeException('Produto não encontrado no estoque.');
      }

      $quantity = (float)$quantidadeRaw;
      $currentQuantity = (float)($item['quantidade'] ?? 0);
      if ($currentQuantity < $quantity) {
        throw new RuntimeException('Saldo insuficiente para realizar a baixa.');
      }

      $updateItem = $pdo->prepare('UPDATE insumos_jnj SET quantidade = quantidade - ? WHERE id = ?');
      $updateItem->execute([$quantity, $produtoId]);

      $insert = $pdo->prepare(
        'INSERT INTO saida_consumo
          (setor, produto_nome, quantidade, unidade, responsavel, data_consumo, observacao, insumo_id, estoque_atualizado, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
      );
      $insert->execute([
        mb_substr($setor, 0, 120),
        mb_substr((string)$item['nome'], 0, 190),
        number_format($quantity, 2, '.', ''),
        mb_substr($unidade, 0, 20),
        mb_substr($responsavel, 0, 190),
        $dataConsumo,
        $observacao !== '' ? mb_substr($observacao, 0, 5000) : null,
        $produtoId,
        1,
        $userId > 0 ? $userId : null,
      ]);

      $pdo->commit();
      flash('success', 'Baixa registrada para ' . $item['nome'] . '.');
      header('Location: picking_qrcode.php?scan_code=' . urlencode((string)$lookupCode) . '&action=lookup');
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $errorMessages[] = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível registrar a baixa.';
    }
  }
}

if ($lookupCode !== '' && $lookupMode !== 'consume') {
  if (preg_match('/^item:(\d+)$/i', $lookupCode, $matches)) {
    $candidateStmt = $pdo->prepare(
      'SELECT id, nome, posicao, lote, codigo_barra, quantidade, unidade, validade, observacoes
       FROM insumos_jnj
       WHERE id = ?
       LIMIT 1'
    );
    $candidateStmt->execute([(int)$matches[1]]);
    $product = $candidateStmt->fetch() ?: null;
    if (!$product) {
      $errorMessages[] = 'Nenhum produto encontrado para o QR Code lido.';
    }
  } else {
    $candidateStmt = $pdo->prepare(
      'SELECT id, nome, posicao, lote, codigo_barra, quantidade, unidade, validade, observacoes
       FROM insumos_jnj
       WHERE codigo_barra = ? OR LOWER(nome) = LOWER(?)
       ORDER BY CASE WHEN codigo_barra = ? THEN 0 ELSE 1 END, id ASC
       LIMIT 2'
    );
    $candidateStmt->execute([$lookupCode, $lookupCode, $lookupCode]);
    $candidates = $candidateStmt->fetchAll() ?: [];

    if (count($candidates) === 1) {
      $product = $candidates[0];
    } elseif (count($candidates) > 1) {
      $errorMessages[] = 'Mais de um produto encontrado para este QR Code. Use um código único.';
    } else {
      $errorMessages[] = 'Nenhum produto encontrado para o QR Code lido.';
    }
  }
}

$recentStmt = $pdo->query(
  'SELECT sc.id, sc.setor, sc.produto_nome, sc.quantidade, sc.unidade, sc.responsavel, sc.data_consumo, sc.created_at, u.nome AS created_by_name
   FROM saida_consumo sc
   LEFT JOIN usuarios u ON u.id = sc.created_by
   ORDER BY sc.created_at DESC, sc.id DESC
   LIMIT 6'
);
$recentConsumptions = $recentStmt->fetchAll() ?: [];

$summaryStmt = $pdo->query(
  "SELECT
      COUNT(*) AS total_entries,
      SUM(quantidade) AS total_quantity
   FROM saida_consumo"
);
$summary = $summaryStmt->fetch() ?: [];

include __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="solicitacoes-kicker">Estoque</span>
          <h1 class="display-6 fw-semibold mb-2">Picking por QR Code</h1>
          <p class="solicitacoes-subtitle mb-0">Leia o QR Code do produto, confirme o item e dê baixa no estoque direto pelo celular ou tablet.</p>
        </div>
        <div class="text-lg-end">
          <div class="solicitacoes-pill">Fluxo em dois passos</div>
          <small class="text-muted d-block mt-2">Primeiro localizar, depois confirmar a baixa.</small>
        </div>
      </div>

      <div class="row g-3 mt-4">
        <div class="col-12 col-md-6">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-info"><i class="fa-solid fa-qrcode"></i></div>
            <div>
              <div class="metric-label">Baixas registradas</div>
              <div class="metric-value"><?= h(number_format((int)($summary['total_entries'] ?? 0), 0, ',', '.')) ?></div>
              <div class="metric-help">Movimentos já confirmados por picking.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-weight-scale"></i></div>
            <div>
              <div class="metric-label">Qtd. consumida</div>
              <div class="metric-value"><?= h(number_format((float)($summary['total_quantity'] ?? 0), 2, ',', '.')) ?></div>
              <div class="metric-help">Somatório das baixas feitas pela câmera.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="row g-4">
    <div class="col-12 col-xl-5">
      <div class="section-card card border-0 shadow-sm h-100">
        <div class="card-body p-4 p-lg-5">
          <span class="section-badge mb-2"><i class="fa-solid fa-qrcode"></i>Leitura</span>
          <h2 class="h5 mb-3">Ler QR Code</h2>
          <p class="section-card-subtitle mb-3">Aponte a câmera para o QR Code do produto. O código pode ser o nome do item ou o código de barras cadastrado.</p>

          <div class="d-flex gap-2 flex-wrap mb-3">
            <button type="button" class="btn btn-primary" id="btn-start-picking-scan"><i class="fa-solid fa-camera me-1"></i>Iniciar câmera</button>
            <button type="button" class="btn btn-outline-secondary" id="btn-stop-picking-scan" style="display:none;"><i class="fa-solid fa-stop me-1"></i>Parar</button>
          </div>

          <form method="get" class="row g-2 align-items-end mb-3" id="picking-lookup-form">
            <div class="col-12">
              <label class="form-label">Código lido</label>
              <input type="text" class="form-control" name="scan_code" id="picking-scan-code" value="<?= h($lookupCode) ?>" placeholder="Aguardando leitura..." autocomplete="off">
            </div>
            <div class="col-12 d-flex gap-2">
              <input type="hidden" name="action" value="lookup">
              <button type="submit" class="btn btn-outline-primary flex-fill"><i class="fa-solid fa-magnifying-glass me-1"></i>Localizar produto</button>
              <a href="picking_qrcode.php" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i></a>
            </div>
          </form>

          <div id="reader-picking" class="border rounded p-2 mb-3" style="display:none; min-height: 280px;"></div>

          <?php if (!empty($errorMessages)): ?>
            <div class="alert alert-danger mb-0">
              <ul class="mb-0 ps-3">
                <?php foreach ($errorMessages as $message): ?>
                  <li><?= h($message) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-7">
      <div class="section-card card border-0 shadow-sm h-100">
        <div class="card-body p-4 p-lg-5">
          <span class="section-badge mb-2"><i class="fa-solid fa-mobile-screen-button"></i>Conferência</span>
          <h2 class="h5 mb-3">Produto localizado</h2>

          <?php if ($product): ?>
            <div class="metric-card mb-3">
              <div class="metric-icon metric-icon-success"><i class="fa-solid fa-box"></i></div>
              <div>
                <div class="metric-label">Produto identificado</div>
                <div class="metric-value fs-3 mb-2"><?= h((string)$product['nome']) ?></div>
                <div class="metric-help">Saldo atual: <?= h(number_format((int)$product['quantidade'], 0, ',', '.')) ?> <?= h((string)($product['unidade'] ?? 'UN')) ?></div>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-12 col-md-6">
                <div class="metric-card h-100">
                  <div>
                    <div class="metric-label">Localização</div>
                    <div class="metric-value fs-4"><?= h((string)($product['posicao'] ?? '-')) ?></div>
                    <div class="metric-help">Posição cadastrada do item.</div>
                  </div>
                </div>
              </div>
              <div class="col-12 col-md-6">
                <div class="metric-card h-100">
                  <div>
                    <div class="metric-label">Validade</div>
                    <div class="metric-value fs-4"><?= !empty($product['validade']) ? h(date('d/m/Y', strtotime((string)$product['validade']))) : '-' ?></div>
                    <div class="metric-help">Use esta referência antes da baixa.</div>
                  </div>
                </div>
              </div>
            </div>

            <?php if (!empty($product['observacoes'])): ?>
              <div class="alert alert-info">
                <strong>Observação:</strong> <?= h((string)$product['observacoes']) ?>
              </div>
            <?php endif; ?>

            <form method="post" class="row g-3">
              <input type="hidden" name="action" value="consume">
              <input type="hidden" name="produto_id" value="<?= (int)$product['id'] ?>">
              <input type="hidden" name="scan_code" value="<?= h($lookupCode) ?>">

              <div class="col-12 col-md-6">
                <label class="form-label">Setor</label>
                <select name="setor" class="form-select" required>
                  <option value="">Selecione o setor</option>
                  <?php $selectedSector = (string)($_POST['setor'] ?? ''); ?>
                  <?php foreach ($setoresDisponiveis as $sector): ?>
                    <option value="<?= h($sector) ?>" <?= $selectedSector === $sector ? 'selected' : '' ?>><?= h($sector) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12 col-md-3">
                <label class="form-label">Quantidade</label>
                <input type="number" step="0.01" min="0.01" name="quantidade" class="form-control" value="<?= h((string)($_POST['quantidade'] ?? '')) ?>" required>
              </div>

              <div class="col-12 col-md-3">
                <label class="form-label">Unidade</label>
                <select name="unidade" class="form-select">
                  <?php $selectedUnit = strtoupper(trim((string)($_POST['unidade'] ?? ($product['unidade'] ?? 'UN')))); ?>
                  <?php foreach ($units as $unitCode => $unitLabel): ?>
                    <option value="<?= h($unitCode) ?>" <?= $selectedUnit === $unitCode ? 'selected' : '' ?>><?= h($unitCode) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">Responsável</label>
                <input type="text" name="responsavel" class="form-control" value="<?= h((string)($_POST['responsavel'] ?? '')) ?>" placeholder="Quem retirou/solicitou" required>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">Data do consumo</label>
                <input type="date" name="data_consumo" class="form-control" value="<?= h((string)($_POST['data_consumo'] ?? date('Y-m-d'))) ?>" required>
              </div>

              <div class="col-12">
                <label class="form-label">Observação</label>
                <textarea name="observacao" class="form-control" rows="3" placeholder="Informação complementar"><?= h((string)($_POST['observacao'] ?? '')) ?></textarea>
              </div>

              <div class="col-12 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-circle-check me-1"></i>Confirmar baixa</button>
                <a href="estoque_atual.php" class="btn btn-outline-secondary"><i class="fa-solid fa-clipboard-list me-1"></i>Ver estoque atual</a>
              </div>
            </form>
          <?php else: ?>
            <div class="alert alert-info mb-0">
              Leia o QR Code ou digite um código válido para carregar o produto e liberar a baixa.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="section-card card border-0 shadow-sm mt-4">
    <div class="card-body">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-3">
        <div>
          <span class="section-badge mb-2"><i class="fa-solid fa-clock-rotate-left"></i>Auditoria</span>
          <h2 class="h5 mb-2">Baixas recentes via picking</h2>
          <p class="section-card-subtitle mb-0">Últimos consumos confirmados para rastreabilidade rápida.</p>
        </div>
      </div>

      <?php if (empty($recentConsumptions)): ?>
        <div class="alert alert-info mb-0">Nenhuma baixa registrada ainda.</div>
      <?php else: ?>
        <div class="table-responsive request-table-wrap">
          <table class="table table-hover align-middle mb-0 request-table js-no-datatable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Setor</th>
                <th>Produto</th>
                <th>Quantidade</th>
                <th>Responsável</th>
                <th>Data</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentConsumptions as $consumption): ?>
                <tr>
                  <td><?= (int)$consumption['id'] ?></td>
                  <td><?= h((string)$consumption['setor']) ?></td>
                  <td>
                    <?= h((string)$consumption['produto_nome']) ?>
                    <?php if (!empty($consumption['created_by_name'])): ?>
                      <div class="text-muted small">por <?= h((string)$consumption['created_by_name']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?= h(number_format((float)$consumption['quantidade'], 2, ',', '.')) ?> <?= h((string)$consumption['unidade']) ?></td>
                  <td><?= h((string)$consumption['responsavel']) ?></td>
                  <td><?= !empty($consumption['created_at']) ? h(date('d/m/Y H:i', strtotime((string)$consumption['created_at']))) : '-' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="assets/vendor/html5-qrcode/html5-qrcode.min.js" defer></script>
<script src="assets/js/picking-qrcode.js" defer></script>

<?php include __DIR__ . '/includes/footer.php'; ?>