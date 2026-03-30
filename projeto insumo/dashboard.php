<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';

requireAdmin();
$pdo = getPDO();

function parseDateYmd(string $value): ?string {
  $d = DateTime::createFromFormat('Y-m-d', $value);
  if (!$d) return null;
  return $d->format('Y-m-d') === $value ? $value : null;
}

$period = $_GET['period'] ?? '30d';
$allowedPeriods = ['today', '7d', '30d', '90d', 'custom'];
if (!in_array($period, $allowedPeriods, true)) {
  $period = '30d';
}

$customStart = parseDateYmd(trim($_GET['custom_start'] ?? ''));
$customEnd = parseDateYmd(trim($_GET['custom_end'] ?? ''));

$today = new DateTimeImmutable('today');
$periodStartObj = null;
$periodEndObj = null;

if ($period === 'today') {
  $periodStartObj = $today;
  $periodEndObj = $today;
} elseif ($period === '7d') {
  $periodStartObj = $today->modify('-6 days');
  $periodEndObj = $today;
} elseif ($period === '30d') {
  $periodStartObj = $today->modify('-29 days');
  $periodEndObj = $today;
} elseif ($period === '90d') {
  $periodStartObj = $today->modify('-89 days');
  $periodEndObj = $today;
} elseif ($period === 'custom' && $customStart !== null && $customEnd !== null && $customStart <= $customEnd) {
  $periodStartObj = new DateTimeImmutable($customStart);
  $periodEndObj = new DateTimeImmutable($customEnd);
} else {
  $period = '30d';
  $periodStartObj = $today->modify('-29 days');
  $periodEndObj = $today;
}

$periodStart = $periodStartObj->format('Y-m-d');
$periodEnd = $periodEndObj->format('Y-m-d');
$periodDays = (int)$periodStartObj->diff($periodEndObj)->format('%a') + 1;

$previousEndObj = $periodStartObj->modify('-1 day');
$previousStartObj = $previousEndObj->modify('-' . ($periodDays - 1) . ' days');
$previousStart = $previousStartObj->format('Y-m-d');
$previousEnd = $previousEndObj->format('Y-m-d');

function getPeriodMetrics(PDO $pdo, string $startDate, string $endDate): array {
  $sql = "
    SELECT
      COUNT(*) AS total_materiais,
      COALESCE(SUM(quantidade), 0) AS total_quantidade,
      SUM(CASE WHEN DATE(COALESCE(contagem_em, data_contagem)) = CURDATE() THEN 1 ELSE 0 END) AS contados_hoje,
      SUM(CASE WHEN data_contagem IS NULL OR DATE(COALESCE(contagem_em, data_contagem)) NOT BETWEEN ? AND ? THEN 1 ELSE 0 END) AS sem_contagem
    FROM insumos_jnj
    WHERE DATE(COALESCE(contagem_em, data_contagem)) BETWEEN ? AND ?
      OR data_contagem IS NULL
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$startDate, $endDate, $startDate, $endDate]);
  $row = $stmt->fetch() ?: [];

  return [
    'total_materiais' => (int)($row['total_materiais'] ?? 0),
    'total_quantidade' => (int)($row['total_quantidade'] ?? 0),
    'contados_hoje' => (int)($row['contados_hoje'] ?? 0),
    'sem_contagem' => (int)($row['sem_contagem'] ?? 0),
  ];
}

$currentMetrics = getPeriodMetrics($pdo, $periodStart, $periodEnd);
$previousMetrics = getPeriodMetrics($pdo, $previousStart, $previousEnd);

$totalMateriais = $currentMetrics['total_materiais'];
$totalQuantidade = $currentMetrics['total_quantidade'];
$contadosHoje = $currentMetrics['contados_hoje'];
$semContagem = $currentMetrics['sem_contagem'];

function calcDeltaPercent(int $current, int $previous): float {
  if ($previous <= 0) {
    return $current > 0 ? 100.0 : 0.0;
  }
  return (($current - $previous) / $previous) * 100.0;
}

$deltaMateriais = calcDeltaPercent($totalMateriais, $previousMetrics['total_materiais']);
$deltaQuantidade = calcDeltaPercent($totalQuantidade, $previousMetrics['total_quantidade']);
$deltaContados = calcDeltaPercent($contadosHoje, $previousMetrics['contados_hoje']);
$deltaSemContagem = calcDeltaPercent($semContagem, $previousMetrics['sem_contagem']);

$validadeSql = "
SELECT
  SUM(CASE WHEN validade < CURDATE() THEN 1 ELSE 0 END) AS expirados,
  SUM(CASE WHEN validade >= CURDATE() AND validade <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS v7,
  SUM(CASE WHEN validade > DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS v30,
  SUM(CASE WHEN validade > DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS ok_count
FROM insumos_jnj
WHERE DATE(COALESCE(contagem_em, data_contagem)) BETWEEN :start_date AND :end_date
   OR data_contagem IS NULL
";
$validadeStmt = $pdo->prepare($validadeSql);
$validadeStmt->execute([
  ':start_date' => $periodStart,
  ':end_date' => $periodEnd,
]);
$validadeRow = $validadeStmt->fetch() ?: [];
$validadeData = [
  'labels' => ['Expirados', 'Vencem em 7 dias', 'Vencem em 30 dias', 'Acima de 30 dias'],
  'values' => [
    (int)($validadeRow['expirados'] ?? 0),
    (int)($validadeRow['v7'] ?? 0),
    (int)($validadeRow['v30'] ?? 0),
    (int)($validadeRow['ok_count'] ?? 0),
  ],
];

$unidadesStmt = $pdo->prepare("SELECT COALESCE(NULLIF(unidade, ''), 'Sem unidade') AS unidade_label, COUNT(*) AS total FROM insumos_jnj WHERE DATE(COALESCE(contagem_em, data_contagem)) BETWEEN :start_date AND :end_date OR data_contagem IS NULL GROUP BY unidade_label ORDER BY total DESC LIMIT 8");
$unidadesStmt->execute([
  ':start_date' => $periodStart,
  ':end_date' => $periodEnd,
]);
$unidadesRows = $unidadesStmt->fetchAll();
$unidadesData = [
  'labels' => array_map(fn($r) => (string)$r['unidade_label'], $unidadesRows),
  'values' => array_map(fn($r) => (int)$r['total'], $unidadesRows),
];

$inicio = $periodStartObj;
$fim = $periodEndObj;
$mapaDias = [];
for ($d = $inicio; $d <= $fim; $d = $d->modify('+1 day')) {
  $mapaDias[$d->format('Y-m-d')] = 0;
}
$trendStmt = $pdo->prepare("SELECT DATE(COALESCE(contagem_em, data_contagem)) AS dia, COUNT(*) AS total FROM insumos_jnj WHERE DATE(COALESCE(contagem_em, data_contagem)) BETWEEN :start_date AND :end_date GROUP BY DATE(COALESCE(contagem_em, data_contagem)) ORDER BY dia ASC");
$trendStmt->execute([
  ':start_date' => $periodStart,
  ':end_date' => $periodEnd,
]);
$trendRows = $trendStmt->fetchAll();
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

$topStmt = $pdo->prepare('SELECT id, nome, quantidade, unidade, data_contagem FROM insumos_jnj WHERE DATE(COALESCE(contagem_em, data_contagem)) BETWEEN :start_date AND :end_date OR data_contagem IS NULL ORDER BY quantidade DESC, id ASC LIMIT 8');
$topStmt->execute([
  ':start_date' => $periodStart,
  ':end_date' => $periodEnd,
]);
$topItens = $topStmt->fetchAll();

$dashboardData = [
  'validade' => $validadeData,
  'unidades' => $unidadesData,
  'tendencia' => $tendenciaData,
];

$materiaisComContagem = max(0, $totalMateriais - $semContagem);
$coberturaInventario = $totalMateriais > 0 ? (($materiaisComContagem / $totalMateriais) * 100) : 0;
$mediaPorItem = $totalMateriais > 0 ? ($totalQuantidade / $totalMateriais) : 0;
$hojeLabel = date('d/m/Y');
$ultimaAtualizacao = date('d/m/Y H:i');

function deltaClass(float $delta): string {
  if ($delta > 0.01) return 'delta-up';
  if ($delta < -0.01) return 'delta-down';
  return 'delta-neutral';
}

function deltaIcon(float $delta): string {
  if ($delta > 0.01) return 'fa-arrow-trend-up';
  if ($delta < -0.01) return 'fa-arrow-trend-down';
  return 'fa-arrows-left-right';
}

function buildDashboardLink(array $params): string {
  return 'dashboard.php?' . http_build_query($params);
}

function buildDashboardPdfLink(string $period, string $periodStart, string $periodEnd): string {
  $params = ['period' => $period];
  if ($period === 'custom') {
    $params['custom_start'] = $periodStart;
    $params['custom_end'] = $periodEnd;
  }
  return 'export_dashboard_pdf.php?' . http_build_query($params);
}

function buildIndexDrillLink(array $extra, string $periodStart, string $periodEnd): string {
  $base = [
    'count_start_date' => $periodStart,
    'count_end_date' => $periodEnd,
  ];
  return 'index.php?' . http_build_query(array_merge($base, $extra));
}

$periodLabel = [
  'today' => 'Hoje',
  '7d' => 'Ultimos 7 dias',
  '30d' => 'Ultimos 30 dias',
  '90d' => 'Ultimos 90 dias',
  'custom' => 'Periodo personalizado',
][$period] ?? 'Ultimos 30 dias';

$alerts = [
  [
    'title' => 'Materiais expirados',
    'value' => (int)($validadeData['values'][0] ?? 0),
    'level' => 'danger',
    'link' => buildIndexDrillLink(['status_validade' => 'expirado'], $periodStart, $periodEnd),
    'action' => 'Ver itens',
  ],
  [
    'title' => 'Vencendo em 7 dias',
    'value' => (int)($validadeData['values'][1] ?? 0),
    'level' => 'warning',
    'link' => buildIndexDrillLink(['status_validade' => 'v7'], $periodStart, $periodEnd),
    'action' => 'Planejar reposicao',
  ],
  [
    'title' => 'Sem contagem no periodo',
    'value' => $semContagem,
    'level' => 'info',
    'link' => buildIndexDrillLink(['sem_contagem' => '1'], $periodStart, $periodEnd),
    'action' => 'Ir para listagem',
  ],
];

include __DIR__ . '/includes/header.php';
?>

<section class="dashboard-bi">
  <div class="dashboard-hero mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <div class="dashboard-overline">ANALYTICS BOARD</div>
        <h2 class="h3 mb-1"><i class="fa-solid fa-chart-line me-2"></i>Dashboard de Inventario Fisico</h2>
        <p class="mb-0 text-muted">Visao executiva de estoque, contagem e validade em tempo real.</p>
      </div>
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="badge rounded-pill text-bg-light border"><i class="fa-regular fa-calendar me-1"></i><?= h($hojeLabel) ?></span>
        <span class="badge rounded-pill text-bg-light border"><i class="fa-regular fa-clock me-1"></i>Atualizado: <?= h($ultimaAtualizacao) ?></span>
        <a href="<?= h(buildDashboardPdfLink($period, $periodStart, $periodEnd)) ?>" class="btn btn-outline-dark"><i class="fa-solid fa-file-pdf me-1"></i>Exportar PDF</a>
        <a href="contagem.php" class="btn btn-primary"><i class="fa-solid fa-barcode me-1"></i>Ir para contagem fisica</a>
        <a href="<?= h(buildIndexDrillLink([], $periodStart, $periodEnd)) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-table-list me-1"></i>Ver lista completa</a>
      </div>
    </div>

    <form method="get" class="dashboard-filter-bar mt-3">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4 col-lg-3">
          <label class="form-label small text-muted mb-1">Periodo</label>
          <select class="form-select" name="period" id="period-select">
            <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Hoje</option>
            <option value="7d" <?= $period === '7d' ? 'selected' : '' ?>>Ultimos 7 dias</option>
            <option value="30d" <?= $period === '30d' ? 'selected' : '' ?>>Ultimos 30 dias</option>
            <option value="90d" <?= $period === '90d' ? 'selected' : '' ?>>Ultimos 90 dias</option>
            <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Personalizado</option>
          </select>
        </div>
        <div class="col-6 col-md-3 col-lg-3 period-custom-field <?= $period === 'custom' ? '' : 'd-none' ?>">
          <label class="form-label small text-muted mb-1">Data inicial</label>
          <input type="date" class="form-control" name="custom_start" value="<?= h($periodStart) ?>">
        </div>
        <div class="col-6 col-md-3 col-lg-3 period-custom-field <?= $period === 'custom' ? '' : 'd-none' ?>">
          <label class="form-label small text-muted mb-1">Data final</label>
          <input type="date" class="form-control" name="custom_end" value="<?= h($periodEnd) ?>">
        </div>
        <div class="col-12 col-md-2 col-lg-2 d-flex gap-2">
          <button class="btn btn-primary flex-fill" type="submit"><i class="fa-solid fa-filter me-1"></i>Aplicar</button>
          <a class="btn btn-outline-secondary" href="dashboard.php"><i class="fa-solid fa-rotate-left"></i></a>
        </div>
        <div class="col-12 col-lg-1 d-flex align-items-center justify-content-lg-end">
          <span class="period-chip"><?= h($periodLabel) ?></span>
        </div>
      </div>
    </form>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
      <article class="dashboard-kpi-card kpi-blue h-100">
        <div class="kpi-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
        <div class="kpi-label">Total de materiais</div>
        <div class="kpi-value"><?= h(number_format($totalMateriais, 0, ',', '.')) ?></div>
        <div class="kpi-delta <?= h(deltaClass($deltaMateriais)) ?>"><i class="fa-solid <?= h(deltaIcon($deltaMateriais)) ?> me-1"></i><?= h(number_format(abs($deltaMateriais), 1, ',', '.')) ?>% vs periodo anterior</div>
        <div class="kpi-meta">Itens unicos cadastrados no inventario.</div>
        <a class="kpi-link" href="<?= h(buildIndexDrillLink([], $periodStart, $periodEnd)) ?>">Abrir detalhes</a>
      </article>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
      <article class="dashboard-kpi-card kpi-emerald h-100">
        <div class="kpi-icon"><i class="fa-solid fa-cubes"></i></div>
        <div class="kpi-label">Quantidade em estoque</div>
        <div class="kpi-value"><?= h(number_format($totalQuantidade, 0, ',', '.')) ?></div>
        <div class="kpi-delta <?= h(deltaClass($deltaQuantidade)) ?>"><i class="fa-solid <?= h(deltaIcon($deltaQuantidade)) ?> me-1"></i><?= h(number_format(abs($deltaQuantidade), 1, ',', '.')) ?>% vs periodo anterior</div>
        <div class="kpi-meta">Media por item: <?= h(number_format($mediaPorItem, 1, ',', '.')) ?></div>
        <a class="kpi-link" href="<?= h(buildIndexDrillLink([], $periodStart, $periodEnd)) ?>">Abrir detalhes</a>
      </article>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
      <article class="dashboard-kpi-card kpi-violet h-100">
        <div class="kpi-icon"><i class="fa-solid fa-clipboard-check"></i></div>
        <div class="kpi-label">Contados hoje</div>
        <div class="kpi-value"><?= h(number_format($contadosHoje, 0, ',', '.')) ?></div>
        <div class="kpi-delta <?= h(deltaClass($deltaContados)) ?>"><i class="fa-solid <?= h(deltaIcon($deltaContados)) ?> me-1"></i><?= h(number_format(abs($deltaContados), 1, ',', '.')) ?>% vs periodo anterior</div>
        <div class="kpi-progress">
          <div class="kpi-progress-bar" style="width: <?= h(number_format(min(100, max(0, $coberturaInventario)), 2, '.', '')) ?>%;"></div>
        </div>
        <div class="kpi-meta">Cobertura: <?= h(number_format($coberturaInventario, 1, ',', '.')) ?>%</div>
        <a class="kpi-link" href="contagem.php">Registrar agora</a>
      </article>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
      <article class="dashboard-kpi-card kpi-amber h-100">
        <div class="kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="kpi-label">Sem contagem registrada</div>
        <div class="kpi-value"><?= h(number_format($semContagem, 0, ',', '.')) ?></div>
        <div class="kpi-delta <?= h(deltaClass(-1 * $deltaSemContagem)) ?>"><i class="fa-solid <?= h(deltaIcon(-1 * $deltaSemContagem)) ?> me-1"></i><?= h(number_format(abs($deltaSemContagem), 1, ',', '.')) ?>% vs periodo anterior</div>
        <div class="kpi-meta">Materiais com oportunidade de revisao.</div>
        <a class="kpi-link" href="<?= h(buildIndexDrillLink(['sem_contagem' => '1'], $periodStart, $periodEnd)) ?>">Ver pendentes</a>
      </article>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-12">
      <section class="dashboard-panel">
        <header class="dashboard-panel-header">
          <h3 class="h6 mb-0">Alertas prioritarios</h3>
          <span class="badge text-bg-light border">Acoes rapidas</span>
        </header>
        <div class="dashboard-panel-body">
          <div class="row g-2">
            <?php foreach ($alerts as $alert): ?>
              <div class="col-12 col-md-4">
                <a href="<?= h($alert['link']) ?>" class="alert-tile alert-<?= h($alert['level']) ?> text-decoration-none">
                  <div>
                    <div class="alert-tile-title"><?= h($alert['title']) ?></div>
                    <div class="alert-tile-action"><?= h($alert['action']) ?> <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i></div>
                  </div>
                  <div class="alert-tile-value"><?= h(number_format($alert['value'], 0, ',', '.')) ?></div>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-12 col-lg-4">
      <section class="dashboard-panel h-100">
        <header class="dashboard-panel-header">
          <h3 class="h6 mb-0">Status de validade</h3>
          <span class="badge text-bg-light border">Risco</span>
        </header>
        <div class="dashboard-panel-body chart-panel">
          <canvas id="chart-validade" aria-label="Grafico de validade"></canvas>
          <div id="legend-validade" class="mt-2"></div>
        </div>
      </section>
    </div>
    <div class="col-12 col-lg-8">
      <section class="dashboard-panel h-100">
        <header class="dashboard-panel-header">
          <h3 class="h6 mb-0">Materiais por unidade (top 8)</h3>
          <span class="badge text-bg-light border">Distribuicao</span>
        </header>
        <div class="dashboard-panel-body chart-panel">
          <canvas id="chart-unidades" aria-label="Grafico por unidade"></canvas>
          <div class="unit-drill-list mt-2">
            <?php foreach ($unidadesRows as $urow): ?>
              <?php $label = (string)$urow['unidade_label']; ?>
              <a class="unit-chip" href="<?= h(buildIndexDrillLink(['unidade_filter' => $label], $periodStart, $periodEnd)) ?>">
                <?= h($label) ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-7">
      <section class="dashboard-panel h-100">
        <header class="dashboard-panel-header">
          <h3 class="h6 mb-0">Tendencia de contagem (ultimos 7 dias)</h3>
          <span class="badge text-bg-light border">Produtividade</span>
        </header>
        <div class="dashboard-panel-body chart-panel">
          <canvas id="chart-tendencia" aria-label="Tendencia de contagem"></canvas>
        </div>
      </section>
    </div>
    <div class="col-12 col-lg-5">
      <section class="dashboard-panel h-100">
        <header class="dashboard-panel-header">
          <h3 class="h6 mb-0">Top materiais por quantidade</h3>
          <span class="badge text-bg-light border">Ranking</span>
        </header>
        <div class="dashboard-panel-body p-0">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 dashboard-table">
              <thead>
                <tr>
                  <th class="ps-3">#</th>
                  <th>Material</th>
                  <th>Unidade</th>
                  <th class="text-end pe-3">Qtd</th>
                </tr>
              </thead>
              <tbody>
                <?php $rank = 1; foreach ($topItens as $row): ?>
                <tr>
                  <td class="ps-3"><span class="rank-pill"><?= h((string)$rank) ?></span></td>
                  <td><strong><?= h($row['nome']) ?></strong></td>
                  <td><?= h($row['unidade'] ?: '-') ?></td>
                  <td class="text-end pe-3 fw-semibold"><?= h(number_format((int)$row['quantidade'], 0, ',', '.')) ?></td>
                </tr>
                <?php $rank++; endforeach; ?>
                <?php if (count($topItens) === 0): ?>
                <tr>
                  <td colspan="4" class="text-center text-muted py-4">Sem dados para exibir.</td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </div>
</section>

<script>
window.dashboardData = <?= json_encode($dashboardData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
document.addEventListener('DOMContentLoaded', function () {
  var periodSelect = document.getElementById('period-select');
  if (!periodSelect) return;

  var toggleCustomFields = function () {
    var isCustom = periodSelect.value === 'custom';
    document.querySelectorAll('.period-custom-field').forEach(function (el) {
      if (isCustom) {
        el.classList.remove('d-none');
      } else {
        el.classList.add('d-none');
      }
    });
  };

  periodSelect.addEventListener('change', toggleCustomFields);
  toggleCustomFields();
});
</script>
<script src="assets/js/dashboard.js" defer></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
