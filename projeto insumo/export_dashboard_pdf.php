<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
  require_once __DIR__ . '/vendor/autoload.php';
}

$pdo = getPDO();

function esc(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

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
$periodLabel = [
  'today' => 'Hoje',
  '7d' => 'Ultimos 7 dias',
  '30d' => 'Ultimos 30 dias',
  '90d' => 'Ultimos 90 dias',
  'custom' => 'Periodo personalizado',
][$period] ?? 'Ultimos 30 dias';

$metricsSql = "
  SELECT
    COUNT(*) AS total_materiais,
    COALESCE(SUM(quantidade), 0) AS total_quantidade,
    SUM(CASE WHEN DATE(COALESCE(contagem_em, data_contagem)) = CURDATE() THEN 1 ELSE 0 END) AS contados_hoje,
    SUM(CASE WHEN data_contagem IS NULL OR DATE(COALESCE(contagem_em, data_contagem)) NOT BETWEEN ? AND ? THEN 1 ELSE 0 END) AS sem_contagem
  FROM insumos_jnj
  WHERE DATE(COALESCE(contagem_em, data_contagem)) BETWEEN ? AND ?
     OR data_contagem IS NULL
";
$metricsStmt = $pdo->prepare($metricsSql);
$metricsStmt->execute([$periodStart, $periodEnd, $periodStart, $periodEnd]);
$metrics = $metricsStmt->fetch() ?: [];

$totalMateriais = (int)($metrics['total_materiais'] ?? 0);
$totalQuantidade = (int)($metrics['total_quantidade'] ?? 0);
$contadosHoje = (int)($metrics['contados_hoje'] ?? 0);
$semContagem = (int)($metrics['sem_contagem'] ?? 0);

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
$validade = $validadeStmt->fetch() ?: [];

$topStmt = $pdo->prepare("SELECT id, nome, unidade, quantidade, validade FROM insumos_jnj WHERE DATE(COALESCE(contagem_em, data_contagem)) BETWEEN :start_date AND :end_date OR data_contagem IS NULL ORDER BY quantidade DESC, id ASC LIMIT 12");
$topStmt->execute([
  ':start_date' => $periodStart,
  ':end_date' => $periodEnd,
]);
$topItens = $topStmt->fetchAll();

$unidadesStmt = $pdo->prepare("SELECT COALESCE(NULLIF(unidade, ''), 'Sem unidade') AS unidade_label, COUNT(*) AS total FROM insumos_jnj WHERE DATE(COALESCE(contagem_em, data_contagem)) BETWEEN :start_date AND :end_date OR data_contagem IS NULL GROUP BY unidade_label ORDER BY total DESC LIMIT 8");
$unidadesStmt->execute([
  ':start_date' => $periodStart,
  ':end_date' => $periodEnd,
]);
$unidades = $unidadesStmt->fetchAll();

$generatedAt = date('d/m/Y H:i');
$filenameBase = 'dashboard_executivo_' . date('Ymd_His');

$html = '';
$html .= '<!doctype html><html><head><meta charset="utf-8">';
$html .= '<style>'
  . 'body{font-family:DejaVu Sans,Arial,sans-serif;color:#1e293b;font-size:12px;}'
  . 'h1{font-size:20px;margin:0 0 4px 0;color:#0f172a;}'
  . '.sub{font-size:11px;color:#64748b;margin-bottom:14px;}'
  . '.grid{width:100%;margin-bottom:14px;}'
  . '.kpi{display:inline-block;width:24%;vertical-align:top;border:1px solid #e2e8f0;border-radius:8px;padding:8px;box-sizing:border-box;margin-right:1%;background:#f8fbff;}'
  . '.kpi:last-child{margin-right:0;}'
  . '.kpi-label{font-size:10px;color:#64748b;margin-bottom:6px;}'
  . '.kpi-value{font-size:18px;font-weight:bold;color:#0f172a;}'
  . '.section{margin-top:12px;}'
  . '.section-title{font-size:13px;font-weight:bold;color:#0f172a;margin:0 0 8px 0;}'
  . 'table{width:100%;border-collapse:collapse;}'
  . 'th,td{border:1px solid #e2e8f0;padding:6px;}'
  . 'th{background:#f1f5f9;color:#334155;text-align:left;}'
  . '.num{text-align:right;}'
  . '.badge{display:inline-block;padding:2px 6px;border-radius:999px;font-size:10px;border:1px solid #cbd5e1;background:#f8fafc;}'
  . '.alerts td{font-weight:bold;}'
  . '</style>';
$html .= '</head><body>';
$html .= '<h1>Dashboard Executivo de Inventario</h1>';
$html .= '<div class="sub">Periodo: ' . esc($periodLabel) . ' | Janela: ' . esc(date('d/m/Y', strtotime($periodStart))) . ' ate ' . esc(date('d/m/Y', strtotime($periodEnd))) . ' | Gerado em: ' . esc($generatedAt) . '</div>';

$html .= '<div class="grid">';
$html .= '<div class="kpi"><div class="kpi-label">Total de materiais</div><div class="kpi-value">' . number_format($totalMateriais, 0, ',', '.') . '</div></div>';
$html .= '<div class="kpi"><div class="kpi-label">Quantidade em estoque</div><div class="kpi-value">' . number_format($totalQuantidade, 0, ',', '.') . '</div></div>';
$html .= '<div class="kpi"><div class="kpi-label">Contados hoje</div><div class="kpi-value">' . number_format($contadosHoje, 0, ',', '.') . '</div></div>';
$html .= '<div class="kpi"><div class="kpi-label">Sem contagem</div><div class="kpi-value">' . number_format($semContagem, 0, ',', '.') . '</div></div>';
$html .= '</div>';

$html .= '<div class="section">';
$html .= '<div class="section-title">Alertas Prioritarios</div>';
$html .= '<table class="alerts">';
$html .= '<tr><th>Indicador</th><th class="num">Quantidade</th></tr>';
$html .= '<tr><td>Materiais expirados</td><td class="num">' . number_format((int)($validade['expirados'] ?? 0), 0, ',', '.') . '</td></tr>';
$html .= '<tr><td>Vencendo em 7 dias</td><td class="num">' . number_format((int)($validade['v7'] ?? 0), 0, ',', '.') . '</td></tr>';
$html .= '<tr><td>Vencendo em 30 dias</td><td class="num">' . number_format((int)($validade['v30'] ?? 0), 0, ',', '.') . '</td></tr>';
$html .= '<tr><td>Acima de 30 dias</td><td class="num">' . number_format((int)($validade['ok_count'] ?? 0), 0, ',', '.') . '</td></tr>';
$html .= '</table>';
$html .= '</div>';

$html .= '<div class="section">';
$html .= '<div class="section-title">Materiais por Unidade (Top 8)</div>';
$html .= '<table>';
$html .= '<tr><th>Unidade</th><th class="num">Total</th></tr>';
if (!empty($unidades)) {
  foreach ($unidades as $u) {
    $html .= '<tr><td>' . esc((string)$u['unidade_label']) . '</td><td class="num">' . number_format((int)$u['total'], 0, ',', '.') . '</td></tr>';
  }
} else {
  $html .= '<tr><td colspan="2">Sem dados para o periodo selecionado.</td></tr>';
}
$html .= '</table>';
$html .= '</div>';

$html .= '<div class="section">';
$html .= '<div class="section-title">Top Materiais por Quantidade</div>';
$html .= '<table>';
$html .= '<tr><th>ID</th><th>Material</th><th>Unidade</th><th class="num">Qtd</th><th>Validade</th></tr>';
if (!empty($topItens)) {
  foreach ($topItens as $item) {
    $validadeFmt = !empty($item['validade']) ? date('d/m/Y', strtotime($item['validade'])) : '-';
    $html .= '<tr>'
      . '<td>' . (int)$item['id'] . '</td>'
      . '<td>' . esc((string)$item['nome']) . '</td>'
      . '<td>' . esc((string)($item['unidade'] ?? '-')) . '</td>'
      . '<td class="num">' . number_format((int)$item['quantidade'], 0, ',', '.') . '</td>'
      . '<td>' . esc($validadeFmt) . '</td>'
      . '</tr>';
  }
} else {
  $html .= '<tr><td colspan="5">Sem dados para o periodo selecionado.</td></tr>';
}
$html .= '</table>';
$html .= '</div>';

$html .= '</body></html>';

if (class_exists('Dompdf\\Dompdf')) {
  $dompdf = new Dompdf\Dompdf();
  $dompdf->loadHtml($html);
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->render();
  $dompdf->stream($filenameBase . '.pdf');
  exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filenameBase . '_IMPRIMIR.html"');
echo $html;
exit;
