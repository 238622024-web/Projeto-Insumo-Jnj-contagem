<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
  require_once __DIR__ . '/vendor/autoload.php';
}

$pdo = getPDO();
ensureInsumoRequestsSchema($pdo);

function esc(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$today = new DateTimeImmutable('today');
$generatedAt = date('d/m/Y H:i');
$filenameBase = 'pedidos_insumos_pendentes_' . date('Ymd_His');

$stmt = $pdo->prepare(
  "SELECT
      ir.id,
      ir.user_nome,
      ir.user_email,
      ir.user_role,
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
      ir.requested_at
    FROM insumo_requests ir
    WHERE ir.status = 'pending'
    ORDER BY ir.requested_at ASC, ir.id ASC"
);
$stmt->execute();
$rows = $stmt->fetchAll();

$html = '';
$html .= '<!doctype html><html><head><meta charset="utf-8">';
$html .= '<style>'
  . 'body{font-family:DejaVu Sans,Arial,sans-serif;color:#1e293b;font-size:11px;} '
  . 'h1{font-size:20px;margin:0 0 4px 0;color:#0f172a;} '
  . '.sub{font-size:10px;color:#64748b;margin-bottom:12px;} '
  . '.kpi{display:inline-block;width:31%;vertical-align:top;border:1px solid #e2e8f0;border-radius:8px;padding:8px;box-sizing:border-box;margin-right:1%;background:#f8fbff;} '
  . '.kpi:last-child{margin-right:0;} '
  . '.kpi-label{font-size:10px;color:#64748b;margin-bottom:4px;} '
  . '.kpi-value{font-size:18px;font-weight:bold;color:#0f172a;} '
  . '.section{margin-top:12px;} '
  . '.section-title{font-size:13px;font-weight:bold;color:#0f172a;margin:0 0 8px 0;} '
  . 'table{width:100%;border-collapse:collapse;} '
  . 'th,td{border:1px solid #e2e8f0;padding:5px;vertical-align:top;} '
  . 'th{background:#f1f5f9;color:#334155;text-align:left;} '
  . '.num{text-align:right;} '
  . '.badge{display:inline-block;padding:2px 6px;border-radius:999px;font-size:10px;border:1px solid #cbd5e1;background:#f8fafc;} '
  . '</style>';
$html .= '</head><body>';
$html .= '<h1>Pedidos de insumo pendentes</h1>';
$html .= '<div class="sub">Gerado em: ' . esc($generatedAt) . ' | Total de pedidos pendentes: ' . number_format(count($rows), 0, ',', '.') . '</div>';

$html .= '<div class="section">';
$html .= '<div class="kpi"><div class="kpi-label">Pedidos pendentes</div><div class="kpi-value">' . number_format(count($rows), 0, ',', '.') . '</div></div>';
$html .= '<div class="kpi"><div class="kpi-label">Data do relatório</div><div class="kpi-value">' . esc($today->format('d/m/Y')) . '</div></div>';
$html .= '<div class="kpi"><div class="kpi-label">Situação</div><div class="kpi-value">Fila ativa</div></div>';
$html .= '</div>';

$html .= '<div class="section">';
$html .= '<div class="section-title">Lista detalhada</div>';
$html .= '<table>';
$html .= '<tr><th>ID</th><th>Usuário</th><th>E-mail</th><th>Setor</th><th>Entrega desejada</th><th>Insumo</th><th class="num">Qtd.</th><th>Lote</th><th>Fabricação</th><th>Validade</th><th>Motivo</th><th>Solicitado em</th></tr>';

if (!empty($rows)) {
  foreach ($rows as $row) {
    $userLabel = esc((string)($row['user_nome'] ?? ''));
    if (($row['user_role'] ?? 'user') === 'admin') {
      $userLabel .= ' <span class="badge">Administrador</span>';
    }
    $html .= '<tr>'
      . '<td>' . (int)$row['id'] . '</td>'
      . '<td>' . $userLabel . '</td>'
      . '<td>' . esc((string)($row['user_email'] ?? '')) . '</td>'
      . '<td>' . esc((string)($row['setor'] ?? '-')) . '</td>'
      . '<td>' . (!empty($row['data_solicitada_entrega']) ? esc(date('d/m/Y', strtotime((string)$row['data_solicitada_entrega']))) : '-') . '</td>'
      . '<td>' . esc((string)($row['insumo_nome'] ?? '-')) . '</td>'
      . '<td class="num">' . esc(number_format((float)($row['quantidade'] ?? 0), 2, ',', '.')) . ' ' . esc((string)($row['unidade'] ?? '')) . '</td>'
      . '<td>' . esc((string)($row['lote'] ?? '-')) . '</td>'
      . '<td>' . (!empty($row['fabricacao']) ? esc(date('d/m/Y', strtotime((string)$row['fabricacao']))) : '-') . '</td>'
      . '<td>' . (!empty($row['validade']) ? esc(date('d/m/Y', strtotime((string)$row['validade']))) : '-') . '</td>'
      . '<td>' . esc((string)($row['motivo_usuario'] ?? '-')) . '</td>'
      . '<td>' . (!empty($row['requested_at']) ? esc(date('d/m/Y H:i', strtotime((string)$row['requested_at']))) : '-') . '</td>'
      . '</tr>';
  }
} else {
  $html .= '<tr><td colspan="12">Não há pedidos pendentes no momento.</td></tr>';
}

$html .= '</table>';
$html .= '</div>';
$html .= '</body></html>';

if (class_exists('Dompdf\\Dompdf')) {
  $dompdf = new Dompdf\Dompdf();
  $dompdf->loadHtml($html);
  $dompdf->setPaper('A4', 'landscape');
  $dompdf->render();
  $dompdf->stream($filenameBase . '.pdf');
  exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filenameBase . '_IMPRIMIR.html"');
echo $html;
exit;
