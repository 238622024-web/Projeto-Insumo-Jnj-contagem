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

$pdo = getPDO();
ensureUserAuthSchema();
ensureEntradaNotaFiscalSchema($pdo);

$current = currentUser() ?: [];
$userId = (int)($current['id'] ?? 0);

$nomesInsumos = require __DIR__ . '/materiais-lista.php';
sort($nomesInsumos, SORT_NATURAL | SORT_FLAG_CASE);

$recentProductsStmt = $pdo->query("SELECT id, nome, quantidade, codigo_barra FROM insumos_jnj ORDER BY nome ASC LIMIT 200");
$recentProducts = $recentProductsStmt->fetchAll() ?: [];
$produtosEntrada = array_values(array_unique(array_filter(array_merge(
  $nomesInsumos,
  array_map(static fn(array $produto): string => trim((string)($produto['nome'] ?? '')), $recentProducts)
), static fn(string $nome): bool => $nome !== '')));
sort($produtosEntrada, SORT_NATURAL | SORT_FLAG_CASE);
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

$errorMessages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $numeroNf = trim((string)($_POST['numero_nf'] ?? ''));
  $fornecedor = trim((string)($_POST['fornecedor'] ?? ''));
  $produtoNome = trim((string)($_POST['produto_nome'] ?? ''));
  $quantidadeRaw = str_replace(',', '.', trim((string)($_POST['quantidade'] ?? '')));
  $unidade = strtoupper(trim((string)($_POST['unidade'] ?? 'UN')));
  $dataRecebimento = trim((string)($_POST['data_recebimento'] ?? ''));
  $observacao = trim((string)($_POST['observacao'] ?? ''));

  if ($numeroNf === '') {
    $errorMessages[] = 'Informe o número da nota fiscal.';
  }
  if ($fornecedor === '') {
    $errorMessages[] = 'Informe o fornecedor.';
  }
  if ($produtoNome === '') {
    $errorMessages[] = 'Informe o produto.';
  }
  if ($quantidadeRaw === '' || !is_numeric($quantidadeRaw) || (float)$quantidadeRaw <= 0) {
    $errorMessages[] = 'Informe uma quantidade válida.';
  }
  if ($dataRecebimento === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRecebimento)) {
    $errorMessages[] = 'Informe uma data de recebimento válida.';
  }

  if ($unidade === '') {
    $unidade = 'UN';
  }

  if (!$errorMessages) {
    $pdo->beginTransaction();
    try {
      $quantity = (float)$quantidadeRaw;

      $findItem = $pdo->prepare('SELECT id, nome, quantidade FROM insumos_jnj WHERE LOWER(nome) = LOWER(?) LIMIT 1');
      $findItem->execute([$produtoNome]);
      $item = $findItem->fetch() ?: null;

      $insumoId = null;
      $estoqueAtualizado = 0;

      if ($item) {
        $insumoId = (int)$item['id'];
        $updateItem = $pdo->prepare('UPDATE insumos_jnj SET quantidade = quantidade + ? WHERE id = ?');
        $updateItem->execute([$quantity, $insumoId]);
        $estoqueAtualizado = 1;
      }

      $insert = $pdo->prepare(
        'INSERT INTO entrada_nota_fiscal
          (numero_nf, fornecedor, produto_nome, quantidade, unidade, data_recebimento, observacao, insumo_id, estoque_atualizado, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
      );
      $insert->execute([
        mb_substr($numeroNf, 0, 80),
        mb_substr($fornecedor, 0, 190),
        mb_substr($produtoNome, 0, 190),
        number_format($quantity, 2, '.', ''),
        mb_substr($unidade, 0, 20),
        $dataRecebimento,
        $observacao !== '' ? mb_substr($observacao, 0, 5000) : null,
        $insumoId,
        $estoqueAtualizado,
        $userId > 0 ? $userId : null,
      ]);

      $pdo->commit();

      if ($estoqueAtualizado === 1) {
        flash('success', 'Entrada registrada e estoque atualizado para ' . $produtoNome . '.');
      } else {
        flash('warning', 'Entrada registrada. O produto ainda não existe no estoque e precisa ser cadastrado primeiro em Produtos.');
      }

      header('Location: entrada_nota_fiscal.php');
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $errorMessages[] = 'Não foi possível registrar a entrada. Verifique os dados e tente novamente.';
    }
  }
}

$totalsStmt = $pdo->query(
  "SELECT
      COUNT(*) AS total_entries,
      SUM(CASE WHEN estoque_atualizado = 1 THEN 1 ELSE 0 END) AS linked_entries,
      SUM(quantidade) AS quantity_total
   FROM entrada_nota_fiscal"
);
$totals = $totalsStmt->fetch() ?: [];

$recentEntriesStmt = $pdo->query(
  'SELECT enf.id, enf.numero_nf, enf.fornecedor, enf.produto_nome, enf.quantidade, enf.unidade, enf.data_recebimento, enf.observacao, enf.estoque_atualizado, enf.created_at, u.nome AS created_by_name
   FROM entrada_nota_fiscal enf
   LEFT JOIN usuarios u ON u.id = enf.created_by
   ORDER BY enf.created_at DESC, enf.id DESC
   LIMIT 8'
);
$recentEntries = $recentEntriesStmt->fetchAll() ?: [];

include __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="solicitacoes-kicker">Estoque</span>
          <h1 class="display-6 fw-semibold mb-2">Entrada por Nota Fiscal</h1>
          <p class="solicitacoes-subtitle mb-0">Registre entradas de materiais com NF, fornecedor, produto, quantidade, unidade de medida, data e observação.</p>
        </div>
        <div class="text-lg-end">
          <div class="solicitacoes-pill">Lançamento de recebimento</div>
          <small class="text-muted d-block mt-2">Se o produto já existir, o saldo é atualizado automaticamente.</small>
        </div>
      </div>

      <div class="row g-3 mt-4">
        <div class="col-12 col-md-4">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-info"><i class="fa-solid fa-file-invoice"></i></div>
            <div>
              <div class="metric-label">Entradas registradas</div>
              <div class="metric-value"><?= h(number_format((int)($totals['total_entries'] ?? 0), 0, ',', '.')) ?></div>
              <div class="metric-help">Total de lançamentos de nota fiscal.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-success"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div>
              <div class="metric-label">Atualizações de estoque</div>
              <div class="metric-value"><?= h(number_format((int)($totals['linked_entries'] ?? 0), 0, ',', '.')) ?></div>
              <div class="metric-help">Entradas associadas a produtos já cadastrados.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-weight-scale"></i></div>
            <div>
              <div class="metric-label">Qtd. total recebida</div>
              <div class="metric-value"><?= h(number_format((float)($totals['quantity_total'] ?? 0), 2, ',', '.')) ?></div>
              <div class="metric-help">Somatório das quantidades lançadas.</div>
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
          <span class="section-badge mb-2"><i class="fa-solid fa-pen-to-square"></i>Novo lançamento</span>
          <h2 class="h5 mb-3">Registrar entrada</h2>

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
            <div class="col-12 col-md-4">
              <label class="form-label">Número da NF</label>
              <input type="text" name="numero_nf" class="form-control" value="<?= h((string)($_POST['numero_nf'] ?? '')) ?>" placeholder="Ex.: 123456" required>
            </div>
            <div class="col-12 col-md-8">
              <label class="form-label">Fornecedor</label>
              <input type="text" name="fornecedor" class="form-control" value="<?= h((string)($_POST['fornecedor'] ?? '')) ?>" placeholder="Razão social do fornecedor" required>
            </div>
            <div class="col-12 col-md-8">
              <label class="form-label">Produto</label>
              <input list="produtos-cadastrados" type="text" name="produto_nome" class="form-control" value="<?= h((string)($_POST['produto_nome'] ?? '')) ?>" placeholder="Digite ou selecione o produto" required>
              <datalist id="produtos-cadastrados">
                <?php foreach ($produtosEntrada as $nomeInsumo): ?>
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
              <label class="form-label">Data de recebimento</label>
              <input type="date" name="data_recebimento" class="form-control" value="<?= h((string)($_POST['data_recebimento'] ?? date('Y-m-d'))) ?>" required>
            </div>
            <div class="col-12">
              <label class="form-label">Observação</label>
              <textarea name="observacao" class="form-control" rows="4" placeholder="Informações complementares sobre a entrada"><?= h((string)($_POST['observacao'] ?? '')) ?></textarea>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Registrar entrada</button>
              <a href="estoque_atual.php" class="btn btn-outline-secondary"><i class="fa-solid fa-clipboard-list me-1"></i>Ver estoque atual</a>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-5">
      <div class="section-card card border-0 shadow-sm h-100">
        <div class="card-body p-4 p-lg-5">
          <span class="section-badge mb-2"><i class="fa-solid fa-circle-info"></i>Como funciona</span>
          <h2 class="h5 mb-3">Fluxo da entrada</h2>
          <div class="d-flex flex-column gap-3 text-muted">
            <div><i class="fa-solid fa-1 me-2 text-primary"></i>Digite os dados da nota fiscal e do fornecedor.</div>
            <div><i class="fa-solid fa-2 me-2 text-primary"></i>Escolha o produto já cadastrado ou digite um nome existente.</div>
            <div><i class="fa-solid fa-3 me-2 text-primary"></i>Se o produto já existir em <strong>Produtos</strong>, o saldo é somado automaticamente.</div>
            <div><i class="fa-solid fa-4 me-2 text-primary"></i>A unidade de medida fica registrada junto com a entrada para manter a rastreabilidade.</div>
            <div><i class="fa-solid fa-5 me-2 text-primary"></i>O lançamento fica salvo na lista de entradas recentes para auditoria.</div>
          </div>
          <div class="alert alert-warning mt-4 mb-0">
            Para inserir um item novo no estoque com controle completo, primeiro cadastre o produto na tela <a href="produtos.php">Produtos</a>.
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="section-card card border-0 shadow-sm mt-4">
    <div class="card-body">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-3">
        <div>
          <span class="section-badge mb-2"><i class="fa-solid fa-clock-rotate-left"></i>Auditoria</span>
          <h2 class="h5 mb-2">Entradas recentes</h2>
          <p class="section-card-subtitle mb-0">Histórico dos últimos lançamentos de nota fiscal e atualização de saldo.</p>
        </div>
      </div>

      <?php if (empty($recentEntries)): ?>
        <div class="alert alert-info mb-0">Nenhuma entrada registrada ainda.</div>
      <?php else: ?>
        <div class="table-responsive request-table-wrap">
          <table class="table table-hover align-middle mb-0 request-table js-no-datatable">
            <thead>
              <tr>
                <th>ID</th>
                <th>NF</th>
                <th>Fornecedor</th>
                <th>Produto</th>
                <th>Quantidade</th>
                <th>Status</th>
                <th>Data</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentEntries as $entry): ?>
                <?php $updated = (int)($entry['estoque_atualizado'] ?? 0) === 1; ?>
                <tr>
                  <td><?= (int)$entry['id'] ?></td>
                  <td><?= h((string)$entry['numero_nf']) ?></td>
                  <td><?= h((string)$entry['fornecedor']) ?></td>
                  <td>
                    <?= h((string)$entry['produto_nome']) ?>
                    <?php if (!empty($entry['created_by_name'])): ?>
                      <div class="text-muted small">por <?= h((string)$entry['created_by_name']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?= h(number_format((float)$entry['quantidade'], 2, ',', '.')) ?> <?= h((string)$entry['unidade']) ?></td>
                  <td>
                    <?php if ($updated): ?>
                      <span class="badge bg-success">Atualizado</span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark">Pendente no cadastro</span>
                    <?php endif; ?>
                  </td>
                  <td><?= !empty($entry['created_at']) ? h(date('d/m/Y H:i', strtotime((string)$entry['created_at']))) : '-' ?></td>
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