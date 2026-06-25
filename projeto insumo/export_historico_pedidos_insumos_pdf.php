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

function escHistory(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function buildInsumoRequestGroupKey(array $request): string {
  $batchId = trim((string)($request['batch_id'] ?? ''));
  if ($batchId !== '') {
    return 'batch:' . $batchId;
  }

  return 'legacy:' . implode('|', [
    (string)($request['user_id'] ?? ''),
    trim((string)($request['user_email'] ?? '')),
    trim((string)($request['setor'] ?? '')),
    trim((string)($request['data_solicitada_entrega'] ?? '')),
    trim((string)($request['motivo_usuario'] ?? '')),
    trim((string)($request['requested_at'] ?? '')),
  ]);
}

function normalizeRequestIds($value): array {
  $raw = is_array($value) ? $value : (is_string($value) ? explode(',', $value) : []);
  return array_values(array_filter(array_map('intval', $raw), static function (int $item): bool {
    return $item > 0;
  }));
}

function formatHistoryPdfDate(?string $value, string $format = 'd/m/Y H:i'): string {
  if ($value === null || trim($value) === '') {
    return '-';
  }

  $timestamp = strtotime((string)$value);
  return $timestamp ? date($format, $timestamp) : '-';
}

$selectedIds = normalizeRequestIds($_GET['ids'] ?? []);

$sql = 'SELECT
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
    ir.unidade_entregue,
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
  WHERE ir.status = \'approved\'';

$params = [];
if (!empty($selectedIds)) {
  $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
  $sql .= ' AND ir.id IN (' . $placeholders . ')';
  $params = $selectedIds;
}

$sql .= ' ORDER BY COALESCE(ir.processed_at, ir.requested_at) DESC, ir.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$groups = [];
foreach ($rows as $row) {
  $groupKey = buildInsumoRequestGroupKey($row);

  if (!isset($groups[$groupKey])) {
    $groups[$groupKey] = [
      'batch_id' => trim((string)($row['batch_id'] ?? '')),
      'sector' => trim((string)($row['setor'] ?? '')) !== '' ? trim((string)$row['setor']) : 'Sem setor',
      'requested_at' => $row['requested_at'] ?? null,
      'processed_at' => $row['processed_at'] ?? null,
      'user_nome' => $row['user_nome'] ?? '',
      'user_email' => $row['user_email'] ?? '',
      'processed_by_nome' => $row['processed_by_nome'] ?? '',
      'motivo_usuario' => $row['motivo_usuario'] ?? '',
      'admin_note' => $row['admin_note'] ?? '',
      'items' => [],
    ];
  }

  $groups[$groupKey]['items'][] = $row;
}

$generatedAt = date('d/m/Y H:i');
$filenameBase = 'historico_pedidos_insumos_' . date('Ymd_His');
$totalDocs = count($groups);
$totalItems = count($rows);

$html = '<!doctype html><html><head><meta charset="utf-8">';
$html .= '<style>'
  . 'body{font-family:DejaVu Sans,Arial,sans-serif;color:#1e293b;font-size:11px;} '
  . 'h1{font-size:20px;margin:0 0 4px 0;color:#0f172a;} '
  . '.sub{font-size:10px;color:#64748b;margin-bottom:12px;} '
  . '.kpi{display:inline-block;width:31%;vertical-align:top;border:1px solid #e2e8f0;border-radius:8px;padding:8px;box-sizing:border-box;margin-right:1%;background:#f8fbff;} '
  . '.kpi:last-child{margin-right:0;} '
  . '.kpi-label{font-size:10px;color:#64748b;margin-bottom:4px;} '
  . '.kpi-value{font-size:18px;font-weight:bold;color:#0f172a;} '
  . '.document{margin-top:14px;padding-top:12px;border-top:2px solid #e2e8f0;} '
  . '.document-title{font-size:13px;font-weight:bold;color:#0f172a;margin:0 0 8px 0;} '
  . '.meta{font-size:10px;color:#475569;margin-bottom:6px;} '
  . 'table{width:100%;border-collapse:collapse;margin-top:8px;} '
  . 'th,td{border:1px solid #e2e8f0;padding:5px;vertical-align:top;} '
  . 'th{background:#f1f5f9;color:#334155;text-align:left;} '
  . '.num{text-align:right;} '
  . '.section{margin-top:12px;} '
  . '</style>';
$html .= '</head><body>';
$html .= '<h1>Histórico de pedidos de insumo</h1>';
$html .= '<div class="sub">Gerado em: ' . escHistory($generatedAt) . ' | Documentos aprovados: ' . number_format($totalDocs, 0, ',', '.') . ' | Itens: ' . number_format($totalItems, 0, ',', '.') . '</div>';

$html .= '<div class="section">';
$html .= '<div class="kpi"><div class="kpi-label">Documentos aprovados</div><div class="kpi-value">' . number_format($totalDocs, 0, ',', '.') . '</div></div>';
$html .= '<div class="kpi"><div class="kpi-label">Itens aprovados</div><div class="kpi-value">' . number_format($totalItems, 0, ',', '.') . '</div></div>';
$html .= '<div class="kpi"><div class="kpi-label">Situação</div><div class="kpi-value">Histórico</div></div>';
$html .= '</div>';

if (!empty($groups)) {
  foreach ($groups as $group) {
    $html .= '<div class="document">';
    $html .= '<div class="document-title">' . escHistory((string)$group['sector']) . '</div>';
    $html .= '<div class="meta"><strong>Solicitante:</strong> ' . escHistory((string)$group['user_nome']) . ' | <strong>E-mail:</strong> ' . escHistory((string)$group['user_email']) . ' | <strong>Solicitado em:</strong> ' . escHistory(formatHistoryPdfDate($group['requested_at'] ?? null)) . ' | <strong>Aprovado em:</strong> ' . escHistory(formatHistoryPdfDate($group['processed_at'] ?? null)) . '</div>';
    $html .= '<div class="meta"><strong>Aprovado por:</strong> ' . escHistory((string)($group['processed_by_nome'] !== '' ? $group['processed_by_nome'] : 'Administrador')) . ' | <strong>Origem:</strong> ' . escHistory($group['batch_id'] !== '' ? 'Documento em lote' : 'Documento avulso') . '</div>';
    $html .= '<table>';
    $html .= '<tr><th>ID</th><th>Insumo</th><th class="num">Qtd. solicitada</th><th>Unid. solicitada</th><th class="num">Qtd. entregue</th><th>Unid. entregue</th><th>Lote</th><th>Fabricação</th><th>Validade</th></tr>';

    foreach ($group['items'] as $item) {
      $html .= '<tr>'
        . '<td>' . (int)$item['id'] . '</td>'
        . '<td>' . escHistory((string)($item['insumo_nome'] ?? '-')) . '</td>'
        . '<td class="num">' . escHistory(number_format((float)($item['quantidade'] ?? 0), 2, ',', '.')) . '</td>'
        . '<td>' . escHistory((string)($item['unidade'] ?? '')) . '</td>'
        . '<td class="num">' . escHistory(number_format((float)($item['quantidade_entregue'] ?? $item['quantidade'] ?? 0), 2, ',', '.')) . '</td>'
        . '<td>' . escHistory((string)($item['unidade_entregue'] ?? $item['unidade'] ?? '')) . '</td>'
        . '<td>' . escHistory((string)($item['lote'] ?? '-')) . '</td>'
        . '<td>' . escHistory(formatHistoryPdfDate($item['fabricacao'] ?? null, 'd/m/Y')) . '</td>'
        . '<td>' . escHistory(formatHistoryPdfDate($item['validade'] ?? null, 'd/m/Y')) . '</td>'
        . '</tr>';
    }

    $html .= '</table>';
    $html .= '<div class="section"><strong>Motivo:</strong> ' . escHistory((string)($group['motivo_usuario'] !== '' ? $group['motivo_usuario'] : '-')) . '</div>';
    $html .= '<div class="section"><strong>Observação da aprovação:</strong> ' . escHistory((string)($group['admin_note'] !== '' ? $group['admin_note'] : '-')) . '</div>';
    $html .= '</div>';
  }
} else {
  $html .= '<div class="section">Nenhum documento encontrado para exportação.</div>';
}

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