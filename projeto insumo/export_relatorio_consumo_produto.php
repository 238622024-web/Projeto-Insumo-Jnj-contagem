<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
  require_once __DIR__ . '/vendor/autoload.php';
}

$pdo = getPDO();
$nomesInsumos = require __DIR__ . '/materiais-lista.php';
sort($nomesInsumos, SORT_NATURAL | SORT_FLAG_CASE);

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$produto = trim((string)($_GET['produto'] ?? ''));

if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
  $from = '';
}
if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
  $to = '';
}
if ($from !== '' && $to === '') {
  $to = date('Y-m-d');
}

$productStmt = $pdo->query(
  "SELECT DISTINCT insumo_nome AS produto_nome
   FROM insumo_requests
   WHERE status = 'approved' AND insumo_nome IS NOT NULL AND insumo_nome <> ''
   ORDER BY insumo_nome ASC"
);
$recentProductOptions = $productStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
$productOptions = array_values(array_unique(array_filter(array_merge($nomesInsumos, $recentProductOptions))));
sort($productOptions, SORT_NATURAL | SORT_FLAG_CASE);

if ($produto !== '' && !in_array($produto, $productOptions, true)) {
  $produto = '';
}

$quantityExpr = 'COALESCE(ir.quantidade_entregue, ir.quantidade)';
$unitExpr = "COALESCE(NULLIF(ir.unidade_entregue, ''), ir.unidade)";

$where = ["ir.status = 'approved'", 'ir.processed_at IS NOT NULL', "ir.processed_at <> ''", 'ir.insumo_nome IS NOT NULL', "ir.insumo_nome <> ''"];
$params = [];
if ($from !== '') {
  $where[] = 'DATE(ir.processed_at) >= ?';
  $params[] = $from;
}
if ($to !== '') {
  $where[] = 'DATE(ir.processed_at) <= ?';
  $params[] = $to;
}
if ($produto !== '') {
  $where[] = 'ir.insumo_nome = ?';
  $params[] = $produto;
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare(
  "SELECT ir.setor, ir.insumo_nome AS produto_nome, $unitExpr AS unidade, COUNT(*) AS movimentos, SUM($quantityExpr) AS quantidade_total
   FROM insumo_requests ir
   WHERE $whereSql
   GROUP BY ir.setor, produto_nome, $unitExpr
   ORDER BY quantidade_total DESC, movimentos DESC, setor ASC, produto_nome ASC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll() ?: [];

$summaryStmt = $pdo->prepare(
  "SELECT COUNT(*) AS movimentos, COALESCE(SUM($quantityExpr), 0) AS quantidade_total
   FROM insumo_requests ir
   WHERE $whereSql"
);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch() ?: [];

$filenameBase = 'relatorio_consumo_produto_' . date('Y-m-d');
$headers = ['Setor', 'Produto', 'Unidade', 'Movimentos', 'Quantidade total'];

if (class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
  $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
  $sheet = $spreadsheet->getActiveSheet();
  $sheet->setTitle('Consumo');

  foreach ($headers as $index => $header) {
    $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
  }

  $rowNumber = 2;
  foreach ($rows as $row) {
    $sheet->setCellValueByColumnAndRow(1, $rowNumber, (string)($row['setor'] ?? 'Sem setor'));
    $sheet->setCellValueByColumnAndRow(2, $rowNumber, (string)($row['produto_nome'] ?? ''));
    $sheet->setCellValueByColumnAndRow(3, $rowNumber, (string)($row['unidade'] ?? ''));
    $sheet->setCellValueByColumnAndRow(4, $rowNumber, (int)($row['movimentos'] ?? 0));
    $sheet->setCellValueByColumnAndRow(5, $rowNumber, (float)($row['quantidade_total'] ?? 0));
    $rowNumber++;
  }

  $lastRow = max(1, $rowNumber - 1);
  $sheet->getStyle('A1:E1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2F5597']],
  ]);
  $sheet->getStyle('A1:E' . $lastRow)->applyFromArray([
    'borders' => [
      'allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'D9D9D9']]
    ]
  ]);
  $sheet->getStyle('D2:D' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
  $sheet->getStyle('E2:E' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
  foreach (range('A', 'E') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
  }
  $sheet->freezePane('A2');

  $summarySheet = $spreadsheet->createSheet(0);
  $summarySheet->setTitle('Resumo');
  $summarySheet->setCellValue('A1', 'Resumo do consumo por produto');
  $summarySheet->mergeCells('A1:B1');
  $summarySheet->setCellValue('A3', 'Período inicial');
  $summarySheet->setCellValue('B3', $from !== '' ? date('d/m/Y', strtotime($from)) : 'Todos');
  $summarySheet->setCellValue('A4', 'Período final');
  $summarySheet->setCellValue('B4', $to !== '' ? date('d/m/Y', strtotime($to)) : 'Todos');
  $summarySheet->setCellValue('A5', 'Produto');
  $summarySheet->setCellValue('B5', $produto !== '' ? $produto : 'Todos os produtos');
  $summarySheet->setCellValue('A7', 'Movimentos');
  $summarySheet->setCellValue('B7', (int)($summary['movimentos'] ?? 0));
  $summarySheet->setCellValue('A8', 'Quantidade total');
  $summarySheet->setCellValue('B8', (float)($summary['quantidade_total'] ?? 0));
  $summarySheet->getStyle('A1:B1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2F5597']],
  ]);
  $summarySheet->getStyle('A3:A8')->getFont()->setBold(true);
  $summarySheet->getColumnDimension('A')->setAutoSize(true);
  $summarySheet->getColumnDimension('B')->setAutoSize(true);
  $spreadsheet->setActiveSheetIndex(0);

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="' . $filenameBase . '.xlsx"');
  header('Cache-Control: max-age=0');

  if (class_exists('PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx')) {
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
  }
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, $headers, ';');
foreach ($rows as $row) {
  fputcsv($out, [
    (string)($row['setor'] ?? 'Sem setor'),
    (string)($row['produto_nome'] ?? ''),
    (string)($row['unidade'] ?? ''),
    (int)($row['movimentos'] ?? 0),
    (float)($row['quantidade_total'] ?? 0),
  ], ';');
}
fclose($out);
exit;
