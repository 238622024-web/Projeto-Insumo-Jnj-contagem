<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireLogin();
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
$pdo = getPDO();

$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');
$count_start_date = trim($_GET['count_start_date'] ?? '');
$count_end_date = trim($_GET['count_end_date'] ?? '');
$status_validade = trim($_GET['status_validade'] ?? '');
$unidade_filter = trim($_GET['unidade_filter'] ?? '');
$sem_contagem = trim($_GET['sem_contagem'] ?? '');

$where = [];
$params = [];

if ($start_date !== '') {
    $where[] = 'data_entrada >= ?';
    $params[] = $start_date;
}
if ($end_date !== '') {
    $where[] = 'data_entrada <= ?';
    $params[] = $end_date;
}
if ($count_start_date !== '') {
    $where[] = 'data_contagem >= ?';
    $params[] = $count_start_date;
}
if ($count_end_date !== '') {
    $where[] = 'data_contagem <= ?';
    $params[] = $count_end_date;
}
if ($status_validade === 'expirado') {
    $where[] = 'validade < CURDATE()';
} elseif ($status_validade === 'v7') {
    $where[] = 'validade >= CURDATE() AND validade <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
} elseif ($status_validade === 'v30') {
    $where[] = 'validade > DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
} elseif ($status_validade === 'ok') {
    $where[] = 'validade > DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
}
if ($unidade_filter !== '') {
    if ($unidade_filter === 'Sem unidade') {
        $where[] = "(unidade IS NULL OR unidade = '')";
    } else {
        $where[] = 'unidade = ?';
        $params[] = $unidade_filter;
    }
}
if ($sem_contagem === '1') {
    $where[] = 'data_contagem IS NULL';
}

$sql = 'SELECT * FROM insumos_jnj';
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY validade ASC, id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$dataAtual = date('Y-m-d');
$filenameBase = 'relatorio_insumos_JNJ_' . $dataAtual;
$diasCurta = (int) getSetting('alerta_validade_curta', 7);
$diasMedia = (int) getSetting('alerta_validade_media', 30);

$today = new DateTime('today');
$countTotal=0;$countExp=0;$countCurta=0;$countMedia=0;$countOk=0;

$html = '<div style="font-family:Arial">';
$html .= '<h2 style="color:#C00000;margin:0 0 8px 0;">Relatório de Insumos - JNJ</h2>';

$filtros = [];
if ($start_date !== '' || $end_date !== '') {
    $filtros[] = 'Entrada: ' . ($start_date !== '' ? $start_date : '...') . ' ate ' . ($end_date !== '' ? $end_date : '...');
}
if ($count_start_date !== '' || $count_end_date !== '') {
    $filtros[] = 'Contagem: ' . ($count_start_date !== '' ? $count_start_date : '...') . ' ate ' . ($count_end_date !== '' ? $count_end_date : '...');
}
if ($status_validade !== '') {
    $filtros[] = 'Status validade: ' . $status_validade;
}
if ($unidade_filter !== '') {
    $filtros[] = 'Unidade: ' . $unidade_filter;
}
if ($sem_contagem === '1') {
    $filtros[] = 'Somente sem contagem';
}
if (!empty($filtros)) {
    $html .= '<p style="margin:0 0 10px 0;font-size:11px;color:#555;"><strong>Filtros aplicados:</strong> ' . h(implode(' | ', $filtros)) . '</p>';
}

// Resumo
foreach ($rows as $r) {
    $countTotal++;
    $dv = !empty($r['validade']) ? DateTime::createFromFormat('Y-m-d', $r['validade']) : null;
    if ($dv) {
        $diff = (int)$today->diff($dv)->format('%r%a');
        if ($diff < 0) $countExp++;
        elseif ($diff <= $diasCurta) $countCurta++;
        elseif ($diff <= $diasMedia) $countMedia++;
        else $countOk++;
    } else {
        $countOk++;
    }
}
$html .= '<table cellspacing="0" cellpadding="6" style="font-size:11px;margin:0 0 14px 0;">'
             . '<tr><td><strong>Total:</strong></td><td>'.$countTotal.'</td>'
             . '<td><strong>Expirados:</strong></td><td>'.$countExp.'</td></tr>'
             . '<tr><td><strong>Val. curta (≤ '.$diasCurta.'d):</strong></td><td>'.$countCurta.'</td>'
             . '<td><strong>Val. média (≤ '.$diasMedia.'d):</strong></td><td>'.$countMedia.'</td></tr>'
             . '<tr><td><strong>OK:</strong></td><td>'.$countOk.'</td><td></td><td></td></tr>'
             . '</table>';

// Tabela de dados
$html .= '<table width="100%" border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;font-size:12px;">';
$html .= '<thead><tr style="background:#C00000;color:#fff"><th>Data de Contagem</th><th>Unidade</th><th>ID</th><th>Nome</th><th>Posição</th><th>Lote</th><th>Qtd</th><th>Entrada</th><th>Validade</th><th>Observações</th></tr></thead><tbody>';
if ($rows) {
    $i=0;
    foreach ($rows as $r) {
        $i++;
        $bg = ($i % 2 == 0) ? ' style="background:#FAFAFA"' : '';
        $validadeCellStyle = '';
        if (!empty($r['validade'])) {
            $dv = DateTime::createFromFormat('Y-m-d', $r['validade']);
            if ($dv) {
                $diff = (int)$today->diff($dv)->format('%r%a');
                if ($diff < 0) $validadeCellStyle = ' style="background:#FDEAEA"';
                elseif ($diff <= $diasCurta) $validadeCellStyle = ' style="background:#FFF7CC"';
                elseif ($diff <= $diasMedia) $validadeCellStyle = ' style="background:#FFF0E0"';
            }
        }
        $html .= '<tr'.$bg.'>'
            .'<td>'.(!empty($r['data_contagem'])?h(date('d/m/Y', strtotime($r['data_contagem']))):'').'</td>'
            .'<td>'.h($r['unidade'] ?? '').'</td>'
            .'<td>'.$r['id'].'</td>'
            .'<td>'.h($r['nome']).'</td>'
            .'<td>'.h($r['posicao']).'</td>'
            .'<td>'.h($r['lote'] ?? '').'</td>'
            .'<td>'.h($r['quantidade']).'</td>'
            .'<td>'.(!empty($r['data_entrada']) ? date('d/m/Y', strtotime($r['data_entrada'])) : '').'</td>'
            .'<td'.$validadeCellStyle.'>'.(!empty($r['validade']) ? date('d/m/Y', strtotime($r['validade'])) : '').'</td>'
            .'<td>'.nl2br(h($r['observacoes'] ?? '')).'</td>'
            .'</tr>';
    }
} else {
    $html .= '<tr><td colspan="10">Sem dados.</td></tr>';
}
$html .= '</tbody></table>';
$html .= '</div>';

if (class_exists('Dompdf\\Dompdf')) {
    $dompdf = new Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4','portrait');
    $dompdf->render();
    $dompdf->stream($filenameBase.'.pdf');
    exit;
}

// Fallback: força download HTML para impressão
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filenameBase.'_IMPRIMIR.html"');
echo $html;
exit;
?>