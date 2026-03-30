<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';

requireAdmin();
$pdo = getPDO();

$totalMateriais = (int)($pdo->query('SELECT COUNT(*) AS c FROM insumos_jnj')->fetch()['c'] ?? 0);
$totalQuantidade = (int)($pdo->query('SELECT COALESCE(SUM(quantidade), 0) AS c FROM insumos_jnj')->fetch()['c'] ?? 0);
$contadosHoje = (int)($pdo->query('SELECT COUNT(*) AS c FROM insumos_jnj WHERE data_contagem = CURDATE()')->fetch()['c'] ?? 0);
$semContagem = (int)($pdo->query('SELECT COUNT(*) AS c FROM insumos_jnj WHERE data_contagem IS NULL')->fetch()['c'] ?? 0);

$validadeSql = "
SELECT
  SUM(CASE WHEN validade < CURDATE() THEN 1 ELSE 0 END) AS expirados,
  SUM(CASE WHEN validade >= CURDATE() AND validade <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS v7,
  SUM(CASE WHEN validade > DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS v30,
  SUM(CASE WHEN validade > DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS ok_count
FROM insumos_jnj
";
$validadeRow = $pdo->query($validadeSql)->fetch() ?: [];
$validadeData = [
  'labels' => ['Expirados', 'Vencem em 7 dias', 'Vencem em 30 dias', 'Acima de 30 dias'],
  'values' => [
    (int)($validadeRow['expirados'] ?? 0),
    (int)($validadeRow['v7'] ?? 0),
    (int)($validadeRow['v30'] ?? 0),
    (int)($validadeRow['ok_count'] ?? 0),
  ],
];

$unidadesRows = $pdo->query("SELECT COALESCE(NULLIF(unidade, ''), 'Sem unidade') AS unidade_label, COUNT(*) AS total FROM insumos_jnj GROUP BY unidade_label ORDER BY total DESC LIMIT 8")->fetchAll();
$unidadesData = [
  'labels' => array_map(fn($r) => (string)$r['unidade_label'], $unidadesRows),
  'values' => array_map(fn($r) => (int)$r['total'], $unidadesRows),
];

$inicio = new DateTimeImmutable('today -6 days');
$fim = new DateTimeImmutable('today');
$mapaDias = [];
for ($d = $inicio; $d <= $fim; $d = $d->modify('+1 day')) {
  $mapaDias[$d->format('Y-m-d')] = 0;
}
$trendRows = $pdo->query("SELECT data_contagem AS dia, COUNT(*) AS total FROM insumos_jnj WHERE data_contagem IS NOT NULL AND data_contagem >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY data_contagem ORDER BY data_contagem ASC")->fetchAll();
foreach ($trendRows as $row) {
  $dia = (string)($row['dia'] ?? '');
  if (isset($mapaDias[$dia])) {
    $mapaDias[$dia] = (int)$row['total'];
  }
}
$tendenciaData = [
  'labels' => array_map(static function ($k) {
    return date('d/m', strtotime($k));
  }, array_keys($mapaDias)),
  'values' => array_values($mapaDias),
];

$topItens = $pdo->query('SELECT id, nome, quantidade, unidade, data_contagem FROM insumos_jnj ORDER BY quantidade DESC, id ASC LIMIT 8')->fetchAll();

$dashboardData = [
  'validade' => $validadeData,
  'unidades' => $unidadesData,
  'tendencia' => $tendenciaData,
];

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h2 class="h4 mb-1"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Dashboard de Inventario Fisico</h2>
    <p class="text-muted mb-0">Visao geral da contagem fisica com indicadores e graficos.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="contagem.php" class="btn btn-primary"><i class="fa-solid fa-barcode me-1"></i>Ir para contagem fisica</a>
    <a href="index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-table-list me-1"></i>Ver lista completa</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12 col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small">Total de materiais</div>
        <div class="display-6 fw-semibold"><?= h(number_format($totalMateriais, 0, ',', '.')) ?></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small">Quantidade em estoque</div>
        <div class="display-6 fw-semibold"><?= h(number_format($totalQuantidade, 0, ',', '.')) ?></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small">Contados hoje</div>
        <div class="display-6 fw-semibold text-success"><?= h(number_format($contadosHoje, 0, ',', '.')) ?></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small">Sem contagem registrada</div>
        <div class="display-6 fw-semibold text-warning"><?= h(number_format($semContagem, 0, ',', '.')) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12 col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white border-0 pt-3">
        <h3 class="h6 mb-0">Status de validade</h3>
      </div>
      <div class="card-body" style="height: 320px;">
        <canvas id="chart-validade" aria-label="Grafico de validade"></canvas>
        <div id="legend-validade" class="mt-2"></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white border-0 pt-3">
        <h3 class="h6 mb-0">Materiais por unidade (top 8)</h3>
      </div>
      <div class="card-body" style="height: 320px;">
        <canvas id="chart-unidades" aria-label="Grafico por unidade"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white border-0 pt-3">
        <h3 class="h6 mb-0">Tendencia de contagem (ultimos 7 dias)</h3>
      </div>
      <div class="card-body" style="height: 320px;">
        <canvas id="chart-tendencia" aria-label="Tendencia de contagem"></canvas>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white border-0 pt-3">
        <h3 class="h6 mb-0">Top materiais por quantidade</h3>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th class="ps-3">#</th>
                <th>Material</th>
                <th>Unidade</th>
                <th class="text-end pe-3">Qtd</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topItens as $row): ?>
              <tr>
                <td class="ps-3"><?= h((string)$row['id']) ?></td>
                <td><strong><?= h($row['nome']) ?></strong></td>
                <td><?= h($row['unidade'] ?: '-') ?></td>
                <td class="text-end pe-3"><?= h(number_format((int)$row['quantidade'], 0, ',', '.')) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (count($topItens) === 0): ?>
              <tr>
                <td colspan="4" class="text-center text-muted py-4">Sem dados para exibir.</td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.dashboardData = <?= json_encode($dashboardData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="assets/js/dashboard.js" defer></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
