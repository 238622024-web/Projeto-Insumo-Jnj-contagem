<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireLogin();
$pdo = getPDO();

// Filtragem por data de entrada via GET
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');
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
$sql = 'SELECT * FROM insumos_jnj';
if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$insumos = $stmt->fetchAll();
$total = count($insumos);
function diasPara($data) {
  $hoje = new DateTime();
  $d = DateTime::createFromFormat('Y-m-d', $data);
  if (!$d) return null;
  return (int)$hoje->diff($d)->format('%r%a');
}
include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="h4 m-0"><i class="fa fa-table me-2"></i>Insumos</h2>
  <div class="btn-group">
    <a class="btn btn-success btn-rounded" href="cadastrar.php"><i class="fa fa-plus me-1"></i>Adicionar</a>
    <a class="btn btn-outline-secondary btn-rounded" href="export_excel.php"><i class="fa fa-file-excel me-1"></i>Excel</a>
    <a class="btn btn-outline-secondary btn-rounded" href="export_pdf.php"><i class="fa fa-file-pdf me-1"></i>PDF</a>
  </div>
</div>
<p class="text-muted">Total de materiais: <strong><?= $total ?></strong></p>

<!-- Formulário de filtragem por data de entrada -->
<form method="get" class="row g-2 align-items-end mb-3">
  <div class="col-auto">
    <label class="form-label small mb-0">Data entrada (de)</label>
    <input type="date" name="start_date" class="form-control" value="<?= h($start_date) ?>">
  </div>
  <div class="col-auto">
    <label class="form-label small mb-0">Data entrada (até)</label>
    <input type="date" name="end_date" class="form-control" value="<?= h($end_date) ?>">
  </div>
  <div class="col-auto">
    <button class="btn btn-primary" type="submit">Filtrar</button>
    <a class="btn btn-link text-decoration-none" href="index.php">Limpar</a>
  </div>
</form>

<div class="table-responsive shadow-sm bg-white rounded p-2">
  <table class="table table-hover align-middle">
    <thead>
      <tr>
        <th>ID</th>
        <th>Data de Contagem</th>
        <th>Unidade</th>
        <th>Nome</th>
        <th>Posição</th>
        <?php $mostrarLote = getSetting('mostrar_lote','1')==='1'; if ($mostrarLote): ?>
        <th>Lote</th>
        <?php endif; ?>
        <th>Qtd</th>
        <th>Entrada</th>
        <th>Validade</th>
        <th>Observações</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($insumos as $row): 
        $dias = diasPara($row['validade']);
        $classeLinha = '';
        $badge = '';
        if ($dias !== null) {
          if ($dias < 0) { $classeLinha='row-expirada'; $badge='<span class="badge bg-danger status-validade">Expirado</span>'; }
          elseif ($dias <= 7) { $badge='<span class="badge badge-validade-curta status-validade">'. $dias .'d</span>'; }
          elseif ($dias <= 30) { $badge='<span class="badge badge-validade-media status-validade">'. $dias .'d</span>'; }
        }
    ?>
      <tr class="<?= $classeLinha ?>">
        <td><?= h($row['id']) ?></td>
        <td><?= !empty($row['data_contagem']) ? h(date('d/m/Y', strtotime($row['data_contagem'])) ) : '' ?></td>
        <td><?= h($row['unidade'] ?? '') ?></td>
        <td><?= h($row['nome']) ?></td>
        <td><?= h($row['posicao']) ?></td>
        <?php if ($mostrarLote): ?>
        <td><?= h($row['lote'] ?? '') ?></td>
        <?php endif; ?>
        <td><?= h($row['quantidade']) ?></td>
        <td><?= h(date('d/m/Y', strtotime($row['data_entrada']))) ?></td>
        <td><?= h(date('d/m/Y', strtotime($row['validade']))) ?> <?= $badge ?></td>
        <td class="small"><?= nl2br(h($row['observacoes'] ?? '')) ?></td>
        <td class="text-nowrap">
          <a class="btn btn-sm btn-primary" href="editar.php?id=<?= h($row['id']) ?>" title="Editar"><i class="fa fa-pen"></i></a>
          <a class="btn btn-sm btn-danger" href="excluir.php?id=<?= h($row['id']) ?>" title="Excluir"><i class="fa fa-trash"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($total === 0): ?>
      <tr><td colspan="<?= 10 + ($mostrarLote?1:0) ?>" class="text-center text-muted py-4">Nenhum material cadastrado.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>