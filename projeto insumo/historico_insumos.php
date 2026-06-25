<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

function ensureEntradaNotaFiscalSchema(PDO $pdo): void {
  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS entrada_nota_fiscal (
      id INT AUTO_INCREMENT PRIMARY KEY,
      numero_nf VARCHAR(80) NOT NULL,
      fornecedor VARCHAR(190) NOT NULL,
      produto_nome VARCHAR(190) NOT NULL,
      quantidade DECIMAL(12,2) NOT NULL,
      unidade VARCHAR(20) NOT NULL DEFAULT 'UN',
      data_recebimento DATE NOT NULL,
      observacao TEXT NULL,
      insumo_id INT NULL,
      estoque_atualizado TINYINT(1) NOT NULL DEFAULT 0,
      created_by INT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_enf_nf (numero_nf),
      INDEX idx_enf_fornecedor (fornecedor),
      INDEX idx_enf_produto (produto_nome),
      INDEX idx_enf_data (data_recebimento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );

  $columns = $pdo->query('SHOW COLUMNS FROM entrada_nota_fiscal')->fetchAll();
  $existing = [];
  foreach ($columns as $column) {
    $existing[$column['Field']] = true;
  }

  if (empty($existing['unidade'])) {
    $pdo->exec("ALTER TABLE entrada_nota_fiscal ADD COLUMN unidade VARCHAR(20) NOT NULL DEFAULT 'UN' AFTER quantidade");
  }
  if (empty($existing['insumo_id'])) {
    $pdo->exec('ALTER TABLE entrada_nota_fiscal ADD COLUMN insumo_id INT NULL AFTER observacao');
  }
  if (empty($existing['estoque_atualizado'])) {
    $pdo->exec('ALTER TABLE entrada_nota_fiscal ADD COLUMN estoque_atualizado TINYINT(1) NOT NULL DEFAULT 0 AFTER insumo_id');
  }
  if (empty($existing['created_by'])) {
    $pdo->exec('ALTER TABLE entrada_nota_fiscal ADD COLUMN created_by INT NULL AFTER estoque_atualizado');
  }
  if (empty($existing['created_at'])) {
    $pdo->exec('ALTER TABLE entrada_nota_fiscal ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER created_by');
  }
}

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
ensureEntradaNotaFiscalSchema($pdo);
ensureSaidaConsumoSchema($pdo);

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$types = array_values(array_filter(array_map('trim', (array)($_GET['type'] ?? [])), static fn(string $value): bool => $value !== ''));
$allowedTypes = ['entrada', 'saida'];
$types = array_values(array_intersect($types, $allowedTypes));

if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
  $from = '';
}
if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
  $to = '';
}

$whereEntrada = ['1 = 1'];
$paramsEntrada = [];
if ($from !== '') {
  $whereEntrada[] = 'data_recebimento >= ?';
  $paramsEntrada[] = $from;
}
if ($to !== '') {
  $whereEntrada[] = 'data_recebimento <= ?';
  $paramsEntrada[] = $to;
}
$whereSaida = ['1 = 1'];
$paramsSaida = [];
if ($from !== '') {
  $whereSaida[] = 'data_consumo >= ?';
  $paramsSaida[] = $from;
}
if ($to !== '') {
  $whereSaida[] = 'data_consumo <= ?';
  $paramsSaida[] = $to;
}

$sqlParts = [];
if (empty($types) || in_array('entrada', $types, true)) {
  $sqlParts[] = "SELECT 'Entrada' AS movimento, data_recebimento AS data_movimento, produto_nome, quantidade, unidade, fornecedor AS detalhe_1, observacao AS detalhe_2, created_at AS ordenacao
                 FROM entrada_nota_fiscal
                 WHERE " . implode(' AND ', $whereEntrada);
}
if (empty($types) || in_array('saida', $types, true)) {
  $sqlParts[] = "SELECT 'Saída/Consumo' AS movimento, data_consumo AS data_movimento, produto_nome, quantidade, unidade, setor AS detalhe_1, observacao AS detalhe_2, created_at AS ordenacao
                 FROM saida_consumo
                 WHERE " . implode(' AND ', $whereSaida);
}

$rows = [];
if (!empty($sqlParts)) {
  $sql = implode(' UNION ALL ', $sqlParts) . ' ORDER BY ordenacao DESC, data_movimento DESC';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(array_merge($paramsEntrada, $paramsSaida));
  $rows = $stmt->fetchAll() ?: [];
}

$summaryStmt = $pdo->query(
  "SELECT
      (SELECT COUNT(*) FROM entrada_nota_fiscal) AS entradas,
      (SELECT COUNT(*) FROM saida_consumo) AS saidas,
      (SELECT COALESCE(SUM(quantidade), 0) FROM entrada_nota_fiscal) AS qtde_entradas,
      (SELECT COALESCE(SUM(quantidade), 0) FROM saida_consumo) AS qtde_saidas"
);
$summary = $summaryStmt->fetch() ?: [];

include __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <span class="solicitacoes-kicker">Relatório de Insumos</span>
      <h1 class="display-6 fw-semibold mb-2">Histórico de Insumos</h1>
      <p class="solicitacoes-subtitle mb-0">Mostra as movimentações registradas de entrada e saída/consumo. Ajustes ainda não possuem tabela própria no sistema.</p>
    </div>
  </section>

  <div class="row g-4 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
      <div class="metric-card h-100">
        <div class="metric-icon metric-icon-success"><i class="fa-solid fa-right-to-bracket"></i></div>
        <div>
          <div class="metric-label">Entradas</div>
          <div class="metric-value"><?= h(number_format((int)($summary['entradas'] ?? 0), 0, ',', '.')) ?></div>
          <div class="metric-help">Notas fiscais registradas.</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
      <div class="metric-card h-100">
        <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
        <div>
          <div class="metric-label">Saídas</div>
          <div class="metric-value"><?= h(number_format((int)($summary['saidas'] ?? 0), 0, ',', '.')) ?></div>
          <div class="metric-help">Movimentos de consumo.</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
      <div class="metric-card h-100">
        <div class="metric-icon metric-icon-info"><i class="fa-solid fa-weight-scale"></i></div>
        <div>
          <div class="metric-label">Qtde. entradas</div>
          <div class="metric-value"><?= h(number_format((float)($summary['qtde_entradas'] ?? 0), 2, ',', '.')) ?></div>
          <div class="metric-help">Total recebido.</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
      <div class="metric-card h-100">
        <div class="metric-icon metric-icon-danger"><i class="fa-solid fa-circle-minus"></i></div>
        <div>
          <div class="metric-label">Qtde. saídas</div>
          <div class="metric-value"><?= h(number_format((float)($summary['qtde_saidas'] ?? 0), 2, ',', '.')) ?></div>
          <div class="metric-help">Total consumido.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="section-card card border-0 shadow-sm mb-4">
    <div class="card-body p-4 p-lg-5">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
          <label class="form-label small text-muted mb-1">Data inicial</label>
          <input type="date" name="from" class="form-control" value="<?= h($from) ?>">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label small text-muted mb-1">Data final</label>
          <input type="date" name="to" class="form-control" value="<?= h($to) ?>">
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label small text-muted mb-1">Tipo</label>
          <select name="type[]" class="form-select" multiple>
            <option value="entrada" <?= empty($types) || in_array('entrada', $types, true) ? 'selected' : '' ?>>Entrada</option>
            <option value="saida" <?= empty($types) || in_array('saida', $types, true) ? 'selected' : '' ?>>Saída/Consumo</option>
          </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          <a href="historico_insumos.php" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i></a>
        </div>
      </form>
    </div>
  </div>

  <div class="section-card card border-0 shadow-sm">
    <div class="card-body p-4 p-lg-5">
      <span class="section-badge mb-2"><i class="fa-solid fa-clock-rotate-left"></i>Linha do tempo</span>
      <h2 class="h5 mb-3">Movimentações registradas</h2>

      <?php if (empty($rows)): ?>
        <div class="alert alert-info mb-0">Nenhuma movimentação encontrada para o filtro selecionado.</div>
      <?php else: ?>
        <div class="table-responsive request-table-wrap">
          <table class="table table-hover align-middle mb-0 request-table js-no-datatable">
            <thead>
              <tr>
                <th>Movimento</th>
                <th>Data</th>
                <th>Produto</th>
                <th>Quantidade</th>
                <th>Detalhe</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td><strong><?= h((string)$row['movimento']) ?></strong></td>
                  <td><?= h(date('d/m/Y', strtotime((string)$row['data_movimento']))) ?></td>
                  <td><?= h((string)$row['produto_nome']) ?></td>
                  <td><?= h(number_format((float)$row['quantidade'], 2, ',', '.')) ?> <?= h((string)$row['unidade']) ?></td>
                  <td>
                    <div><?= h((string)($row['detalhe_1'] ?? '-')) ?></div>
                    <?php if (!empty($row['detalhe_2'])): ?>
                      <div class="text-muted small"><?= h((string)$row['detalhe_2']) ?></div>
                    <?php endif; ?>
                  </td>
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
