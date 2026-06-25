<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

function ensureSaidaConsumoSchema(PDO $pdo): void {
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
ensureSaidaConsumoSchema($pdo);

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

$nomesInsumos = require __DIR__ . '/materiais-lista.php';
sort($nomesInsumos, SORT_NATURAL | SORT_FLAG_CASE);

$errorMessages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $setor = trim((string)($_POST['setor'] ?? ''));
  $produtoNome = trim((string)($_POST['produto_nome'] ?? ''));
  $quantidadeRaw = str_replace(',', '.', trim((string)($_POST['quantidade'] ?? '')));
  $unidade = strtoupper(trim((string)($_POST['unidade'] ?? 'UN')));
  $responsavel = trim((string)($_POST['responsavel'] ?? ''));
  $dataConsumo = trim((string)($_POST['data_consumo'] ?? ''));
  $observacao = trim((string)($_POST['observacao'] ?? ''));

  if ($setor === '') {
    $errorMessages[] = 'Informe o setor que consumiu o material.';
  } elseif (!in_array($setor, $setoresDisponiveis, true)) {
    $errorMessages[] = 'Selecione um setor válido.';
  }
  if ($produtoNome === '') {
    $errorMessages[] = 'Informe o produto.';
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

  if ($unidade === '' || !array_key_exists($unidade, $units)) {
    $unidade = 'UN';
  }

  if (!$errorMessages) {
    $pdo->beginTransaction();
    try {
      $quantity = (float)$quantidadeRaw;

      $findItem = $pdo->prepare('SELECT id, nome, quantidade FROM insumos_jnj WHERE LOWER(nome) = LOWER(?) LIMIT 1');
      $findItem->execute([$produtoNome]);
      $item = $findItem->fetch() ?: null;

      if (!$item) {
        throw new RuntimeException('Produto não encontrado no estoque.');
      }

      $insumoId = (int)$item['id'];
      $currentQuantity = (float)($item['quantidade'] ?? 0);
      if ($currentQuantity < $quantity) {
        throw new RuntimeException('Saldo insuficiente para realizar a saída.');
      }

      $updateItem = $pdo->prepare('UPDATE insumos_jnj SET quantidade = quantidade - ? WHERE id = ?');
      $updateItem->execute([$quantity, $insumoId]);

      $insert = $pdo->prepare(
        'INSERT INTO saida_consumo
          (setor, produto_nome, quantidade, unidade, responsavel, data_consumo, observacao, insumo_id, estoque_atualizado, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
      );
      $insert->execute([
        mb_substr($setor, 0, 120),
        mb_substr($produtoNome, 0, 190),
        number_format($quantity, 2, '.', ''),
        mb_substr($unidade, 0, 20),
        mb_substr($responsavel, 0, 190),
        $dataConsumo,
        $observacao !== '' ? mb_substr($observacao, 0, 5000) : null,
        $insumoId,
        1,
        $userId > 0 ? $userId : null,
      ]);

      $pdo->commit();
      flash('success', 'Saída registrada e estoque atualizado para ' . $produtoNome . '.');
      header('Location: saida_consumo.php');
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $errorMessages[] = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível registrar a saída. Verifique os dados e tente novamente.';
    }
  }
}

$summaryStmt = $pdo->query(
  "SELECT
      COUNT(*) AS total_entries,
      SUM(quantidade) AS total_quantity
   FROM saida_consumo"
);
$summary = $summaryStmt->fetch() ?: [];
$summary['total_quantity'] = 0;

include __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="solicitacoes-kicker">Movimentação de estoque</span>
          <h1 class="display-6 fw-semibold mb-2">Saída / Consumo</h1>
          <p class="solicitacoes-subtitle mb-0">Registre a baixa do material, mantenha o saldo atualizado e acompanhe os últimos lançamentos com rastreabilidade.</p>
          <div class="insumo-historico-hero-chips mt-3">
            <span class="insumo-historico-chip"><i class="fa-solid fa-arrow-right-from-bracket"></i>Baixa automática</span>
            <span class="insumo-historico-chip"><i class="fa-solid fa-shield-halved"></i>Rastreabilidade por setor</span>
          </div>
        </div>
        <div class="text-lg-end">
          <div class="solicitacoes-pill">Baixa automática</div>
          <small class="text-muted d-block mt-2">O saldo só é atualizado se houver quantidade suficiente.</small>
        </div>
      </div>

      <div class="row g-3 mt-4">
        <div class="col-12 col-md-6">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-info"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
            <div>
              <div class="metric-label">Saídas registradas</div>
              <div class="metric-value"><?= h(number_format((int)($summary['total_entries'] ?? 0), 0, ',', '.')) ?></div>
              <div class="metric-help">Total de baixas de consumo.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-weight-scale"></i></div>
            <div>
              <div class="metric-label">Qtd. total consumida</div>
              <div class="metric-value"><?= h(number_format((float)($summary['total_quantity'] ?? 0), 2, ',', '.')) ?></div>
              <div class="metric-help">Somatório das saídas já registradas.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="row g-4">
    <div class="col-12 col-xl-7">
      <div class="section-card card border-0 shadow-sm h-100">
        <div class="card-body p-4 p-lg-5">
          <span class="section-badge mb-2"><i class="fa-solid fa-clipboard-check"></i>Novo consumo</span>
          <h2 class="h5 mb-3">Registrar saída</h2>

          <?php if (!empty($errorMessages)): ?>
            <div class="alert alert-danger">
              <strong>Corrija os campos abaixo:</strong>
              <ul class="mb-0 mt-2">
                <?php foreach ($errorMessages as $message): ?>
                  <li><?= h($message) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form method="post" class="row g-3">
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
            <div class="col-12 col-md-6">
              <label class="form-label">Responsável</label>
              <input type="text" name="responsavel" class="form-control" value="<?= h((string)($_POST['responsavel'] ?? '')) ?>" placeholder="Nome de quem solicitou/retirou" required>
            </div>
            <div class="col-12 col-md-8">
              <label class="form-label">Produto</label>
              <input list="produtos-consumo" type="text" name="produto_nome" class="form-control" value="<?= h((string)($_POST['produto_nome'] ?? '')) ?>" placeholder="Digite ou selecione o produto" required>
              <datalist id="produtos-consumo">
                <?php foreach ($nomesInsumos as $nomeInsumo): ?>
                  <option value="<?= h($nomeInsumo) ?>"></option>
                <?php endforeach; ?>
              </datalist>
            </div>
            <div class="col-12 col-md-2">
              <label class="form-label">Quantidade</label>
              <input type="number" step="0.01" min="0.01" name="quantidade" class="form-control" value="<?= h((string)($_POST['quantidade'] ?? '')) ?>" placeholder="0,00" required>
            </div>
            <div class="col-12 col-md-2">
              <label class="form-label">Unidade de medida</label>
              <select name="unidade" class="form-select">
                <?php $selectedUnit = strtoupper(trim((string)($_POST['unidade'] ?? 'UN'))); ?>
                <?php foreach ($units as $unitCode => $unitLabel): ?>
                  <option value="<?= h($unitCode) ?>" <?= $selectedUnit === $unitCode ? 'selected' : '' ?>><?= h($unitCode . ' - ' . $unitLabel) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Data do consumo</label>
              <input type="date" name="data_consumo" class="form-control" value="<?= h((string)($_POST['data_consumo'] ?? date('Y-m-d'))) ?>" required>
            </div>
            <div class="col-12">
              <label class="form-label">Observação</label>
              <textarea name="observacao" class="form-control" rows="4" placeholder="Detalhes do consumo ou complemento de rastreio"><?= h((string)($_POST['observacao'] ?? '')) ?></textarea>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-primary"><i class="fa-solid fa-circle-check me-1"></i>Registrar saída</button>
              <a href="estoque_atual.php" class="btn btn-outline-secondary"><i class="fa-solid fa-clipboard-list me-1"></i>Ver saldo atual</a>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-5">
      <div class="section-card card border-0 shadow-sm h-100">
        <div class="card-body p-4 p-lg-5">
          <span class="section-badge mb-2"><i class="fa-solid fa-circle-info"></i>Regras</span>
          <h2 class="h5 mb-3">Como a baixa funciona</h2>
          <div class="d-flex flex-column gap-3 text-muted">
            <div><i class="fa-solid fa-1 me-2 text-primary"></i>O produto precisa existir em <strong>Produtos</strong> ou no estoque atual.</div>
            <div><i class="fa-solid fa-2 me-2 text-primary"></i>A quantidade informada é descontada do saldo existente.</div>
            <div><i class="fa-solid fa-3 me-2 text-primary"></i>A unidade de medida fica registrada junto com o consumo.</div>
            <div><i class="fa-solid fa-4 me-2 text-primary"></i>Se o saldo ficar insuficiente, a baixa é bloqueada.</div>
          </div>
          <div class="alert alert-warning mt-4 mb-0">
            O picking por QR Code pode usar este mesmo fluxo para consumir rapidamente no celular ou tablet.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
