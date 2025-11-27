<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireLogin();
// Carrega autoload do Composer se disponível
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
  require_once __DIR__ . '/vendor/autoload.php';
}
$pdo = getPDO();
$stmt = $pdo->query('SELECT * FROM insumos_jnj ORDER BY validade ASC');
$rows = $stmt->fetchAll();
$dataAtual = date('Y-m-d');
$filenameBase = 'relatorio_insumos_JNJ_' . $dataAtual;
// Configurações para alertas de validade
$diasCurta = (int) getSetting('alerta_validade_curta', 7);
$diasMedia = (int) getSetting('alerta_validade_media', 30);

// Tenta usar PhpSpreadsheet
if (class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Insumos');

    // Cabeçalhos (Data de Contagem primeiro)
    // Cabeçalhos (Data de Contagem, Unidade)
    $headers = ['Data de Contagem','Unidade','ID','Nome','Posição','Lote','Quantidade','Data Entrada','Validade','Observações'];
    $col = 1;
    foreach ($headers as $h) { $sheet->setCellValueByColumnAndRow($col,1,$h); $col++; }

    // Estilo do cabeçalho
    $headerRange = 'A1:' . $sheet->getCellByColumnAndRow(count($headers),1)->getCoordinate();
      $sheet->getStyle($headerRange)->applyFromArray([
          'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
          'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'C00000']],
          'alignment' => ['horizontal' => 'center'],
          'borders' => ['bottom' => ['borderStyle' => 'thin', 'color' => ['rgb' => '900000']]]
    ]);

    // Linhas de dados
    $rowNumber = 2;
    $today = new DateTime('today');
    $countTotal = 0; $countExp = 0; $countCurta = 0; $countMedia = 0; $countOk = 0;
    foreach ($rows as $r) {
      // Data de contagem (col 1)
      if (!empty($r['data_contagem'])) {
        $dc = DateTime::createFromFormat('Y-m-d', $r['data_contagem']);
        if ($dc) $sheet->setCellValueByColumnAndRow(1,$rowNumber, $dc->format('d/m/Y'));
      }
        $sheet->setCellValueByColumnAndRow(2,$rowNumber,$r['unidade'] ?? '');
        $sheet->setCellValueByColumnAndRow(3,$rowNumber,$r['id']);
        $sheet->setCellValueByColumnAndRow(4,$rowNumber,$r['nome']);
        $sheet->setCellValueByColumnAndRow(5,$rowNumber,$r['posicao']);
        $sheet->setCellValueByColumnAndRow(6,$rowNumber,$r['lote'] ?? '');
        $sheet->setCellValueByColumnAndRow(7,$rowNumber,(int)$r['quantidade']);
      // Datas como objetos para formatar dd/mm/yyyy (Data Entrada col 7)
      if (!empty($r['data_entrada'])) {
        $de = DateTime::createFromFormat('Y-m-d', $r['data_entrada']);
          if ($de) { $sheet->setCellValueByColumnAndRow(7,$rowNumber, $de->format('d/m/Y')); }
      }
      if (!empty($r['validade'])) {
        $dv = DateTime::createFromFormat('Y-m-d', $r['validade']);
          if ($dv) { $sheet->setCellValueByColumnAndRow(8,$rowNumber, $dv->format('d/m/Y')); }
      }
      $sheet->setCellValueByColumnAndRow(9,$rowNumber,$r['observacoes']);

      // Zebra striping
      if ($rowNumber % 2 === 0) {
            $sheet->getStyle('A'.$rowNumber.':J'.$rowNumber)->applyFromArray([
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F9F9F9']]
          ]);
      }

      // Destaques de validade + contadores
      if (!empty($r['validade'])) {
        $dv = DateTime::createFromFormat('Y-m-d', $r['validade']);
        if ($dv) {
          $diff = (int)$today->diff($dv)->format('%r%a');
          $style = null;
          if ($diff < 0) { // expirado
            $style = ['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FDEAEA']]];
            $countExp++;
          } elseif ($diff <= $diasCurta) {
            $style = ['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFF7CC']]];
            $countCurta++;
          } elseif ($diff <= $diasMedia) {
            $style = ['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFF0E0']]];
            $countMedia++;
          } else {
            $countOk++;
          }
          if ($style) { $sheet->getStyle('G'.$rowNumber)->applyFromArray($style); }
        }
      } else {
        $countOk++;
      }

      $rowNumber++;
      $countTotal++;
    }

    // Formatação de colunas: auto width e datas
    foreach (range('A','I') as $colL) { $sheet->getColumnDimension($colL)->setAutoSize(true); }
     foreach (range('A','J') as $colL) { $sheet->getColumnDimension($colL)->setAutoSize(true); }
      $sheet->getStyle('H2:H'.$rowNumber)->getNumberFormat()->setFormatCode('dd/mm/yyyy'); // Formatação de datas (Data Entrada)
      $sheet->getStyle('I2:I'.$rowNumber)->getNumberFormat()->setFormatCode('dd/mm/yyyy'); // Formatação de datas (Validade)

    // Bordas e alinhamentos no corpo
    $lastRow = max(1, $rowNumber - 1);
      $dataRange = 'A1:J'.$lastRow;
    $sheet->getStyle($dataRange)->applyFromArray([
      'borders' => [
        'allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'DDDDDD']]
      ]
    ]);
    // Alinhar números e quantidades
    $sheet->getStyle('A2:A'.$lastRow)->applyFromArray(['alignment' => ['horizontal' => 'right']]);
      $sheet->getStyle('G2:G'.$lastRow)->applyFromArray(['alignment' => ['horizontal' => 'right']]);
    // Observações com quebra de linha
      $sheet->getStyle('J2:J'.$lastRow)->applyFromArray(['alignment' => ['wrapText' => true]]);
    $sheet->freezePane('A2');
    $sheet->setAutoFilter($headerRange);

    // Aba de Resumo
    $summary = $spreadsheet->createSheet(0);
    $summary->setTitle('Resumo');
    $summary->setCellValue('A1', 'Resumo - Controle de Insumos JNJ');
    $summary->setCellValue('A2', 'Data:');
    $summary->setCellValue('B2', date('d/m/Y'));
    $summary->mergeCells('A1:D1');
    $summary->getStyle('A1')->applyFromArray([
      'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'C00000']]
    ]);
    $summary->fromArray([
      ['Métrica','Quantidade'],
      ['Total', $countTotal],
      ['Expirados', $countExp],
      ['Validade curta (≤ '.$diasCurta.'d)', $countCurta],
      ['Validade média (≤ '.$diasMedia.'d)', $countMedia],
      ['OK', $countOk]
    ], NULL, 'A4');
    $summary->getStyle('A4:B4')->applyFromArray([
      'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
      'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'C00000']]
    ]);
    $summary->getColumnDimension('A')->setAutoSize(true);
    $summary->getColumnDimension('B')->setAutoSize(true);
    $spreadsheet->setActiveSheetIndex(0);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filenameBase.'.xlsx"');
    header('Cache-Control: max-age=0');
  if (class_exists('PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx')) {
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
  }
  // Se Writer não existir cai para CSV abaixo
}

// Fallback simples CSV se biblioteca não instalada
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filenameBase.'.csv"');
$out = fopen('php://output','w');
fputcsv($out,['Data de Contagem','Unidade','ID','Nome','Posição','Lote','Quantidade','Data Entrada','Validade','Observações']);
foreach ($rows as $r) {
  fputcsv($out,[(!empty($r['data_contagem'])?date('d/m/Y',strtotime($r['data_contagem'])):''),($r['unidade'] ?? ''),$r['id'],$r['nome'],$r['posicao'],$r['lote'] ?? '',$r['quantidade'],$r['data_entrada'],$r['validade'],$r['observacoes']]);
}
fclose($out);
exit;
?>