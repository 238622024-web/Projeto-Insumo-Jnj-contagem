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

// Calcular estatísticas
$expirados = 0;
$vencendo7dias = 0;
$vencendo30dias = 0;
foreach ($insumos as $item) {
  $dias = diasPara($item['validade']);
  if ($dias !== null) {
    if ($dias < 0) $expirados++;
    elseif ($dias <= 7) $vencendo7dias++;
    elseif ($dias <= 30) $vencendo30dias++;
  }
}

include __DIR__ . '/includes/header.php';
?>
<!-- Cards de Estatísticas -->
<div class="row g-3 mb-4">
  <div class="col-12 col-md-3">
    <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
      <div class="card-body text-white">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-white-50 text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Total de Materiais</h6>
            <h2 class="mb-0 fw-bold"><?= $total ?></h2>
          </div>
          <div class="fs-1 opacity-50"><i class="fa fa-boxes"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-3">
    <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
      <div class="card-body text-white">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-white-50 text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Expirados</h6>
            <h2 class="mb-0 fw-bold"><?= $expirados ?></h2>
          </div>
          <div class="fs-1 opacity-50"><i class="fa fa-times-circle"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-3">
    <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
      <div class="card-body text-white">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-white-50 text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Vence em 7 dias</h6>
            <h2 class="mb-0 fw-bold"><?= $vencendo7dias ?></h2>
          </div>
          <div class="fs-1 opacity-50"><i class="fa fa-exclamation-triangle"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-3">
    <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
      <div class="card-body text-white">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-white-50 text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Vence em 30 dias</h6>
            <h2 class="mb-0 fw-bold"><?= $vencendo30dias ?></h2>
          </div>
          <div class="fs-1 opacity-50"><i class="fa fa-clock"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Header com Ações -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h2 class="h4 m-0"><i class="fa fa-table me-2 text-primary"></i><?= h(t('list.title')) ?></h2>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-success" href="cadastrar.php"><i class="fa fa-plus me-2"></i><?= h(t('list.add')) ?></a>
        <a class="btn btn-outline-success" href="export_excel.php"><i class="fa fa-file-excel me-2"></i><?= h(t('list.export.excel')) ?></a>
        <a class="btn btn-outline-danger" href="export_pdf.php"><i class="fa fa-file-pdf me-2"></i><?= h(t('list.export.pdf')) ?></a>
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalApagarTodos">
          <i class="fa fa-ban me-2"></i>Apagar Todos
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Filtro Moderno -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <form method="get" class="row g-3 align-items-end">
      <div class="col-12 col-md-4">
        <label class="form-label fw-600"><i class="fa fa-calendar-alt text-primary me-2"></i>Data entrada (de)</label>
        <input type="date" name="start_date" class="form-control form-control-lg" value="<?= h($start_date) ?>">
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label fw-600"><i class="fa fa-calendar-alt text-primary me-2"></i>Data entrada (até)</label>
        <input type="date" name="end_date" class="form-control form-control-lg" value="<?= h($end_date) ?>">
      </div>
      <div class="col-12 col-md-4 d-flex gap-2">
        <button class="btn btn-primary btn-lg flex-fill" type="submit"><i class="fa fa-filter me-2"></i>Filtrar</button>
        <a class="btn btn-outline-secondary btn-lg" href="index.php"><i class="fa fa-redo me-2"></i>Limpar</a>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
        <tr>
          <th class="fw-600" style="padding: 1rem;">ID</th>
          <th class="fw-600" style="padding: 1rem;">Data de Contagem</th>
          <th class="fw-600" style="padding: 1rem;">Unidade</th>
          <th class="fw-600" style="padding: 1rem;">Nome</th>
          <th class="fw-600" style="padding: 1rem;">Posição</th>
          <?php $mostrarLote = getSetting('mostrar_lote','1')==='1'; if ($mostrarLote): ?>
          <th class="fw-600" style="padding: 1rem;">Lote</th>
          <?php endif; ?>
          <th class="fw-600" style="padding: 1rem;">Qtd</th>
          <th class="fw-600" style="padding: 1rem;">Entrada</th>
          <th class="fw-600" style="padding: 1rem;">Validade</th>
          <th class="fw-600" style="padding: 1rem;">Observações</th>
          <th class="fw-600 text-center" style="padding: 1rem;">Ações</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($insumos as $row): 
          $dias = diasPara($row['validade']);
          $classeLinha = '';
          $badge = '';
          if ($dias !== null) {
            if ($dias < 0) { $classeLinha='row-expirada'; $badge='<span class="badge bg-danger fs-6 px-3 py-2"><i class="fa fa-times-circle me-1"></i>Expirado</span>'; }
            elseif ($dias <= 7) { $badge='<span class="badge bg-warning text-dark fs-6 px-3 py-2"><i class="fa fa-exclamation-triangle me-1"></i>'. $dias .' dias</span>'; }
            elseif ($dias <= 30) { $badge='<span class="badge bg-info text-dark fs-6 px-3 py-2"><i class="fa fa-clock me-1"></i>'. $dias .' dias</span>'; }
          }
      ?>
        <tr class="<?= $classeLinha ?>" style="border-bottom: 1px solid #e9ecef;">
          <td style="padding: 1rem;"><strong><?= h($row['id']) ?></strong></td>
          <td style="padding: 1rem;"><?= !empty($row['data_contagem']) ? h(date('d/m/Y', strtotime($row['data_contagem'])) ) : '<span class="text-muted">-</span>' ?></td>
          <td style="padding: 1rem;"><span class="badge bg-light text-dark"><?= h($row['unidade'] ?? '') ?></span></td>
          <td style="padding: 1rem;"><strong><?= h($row['nome']) ?></strong></td>
          <td style="padding: 1rem;"><span class="badge bg-secondary"><?= h($row['posicao']) ?></span></td>
          <?php if ($mostrarLote): ?>
          <td style="padding: 1rem;"><?= h($row['lote'] ?? '') ?></td>
          <?php endif; ?>
          <td style="padding: 1rem;"><strong><?= h($row['quantidade']) ?></strong></td>
          <td style="padding: 1rem;"><?= h(date('d/m/Y', strtotime($row['data_entrada']))) ?></td>
          <td style="padding: 1rem;"><?= h(date('d/m/Y', strtotime($row['validade']))) ?><br><?= $badge ?></td>
          <td class="small" style="padding: 1rem; max-width: 200px;"><?= nl2br(h($row['observacoes'] ?? '')) ?></td>
          <td class="text-center" style="padding: 1rem;">
            <div class="btn-group" role="group">
              <a class="btn btn-sm btn-outline-primary" href="editar.php?id=<?= h($row['id']) ?>" title="Editar"><i class="fa fa-pen me-1"></i>Editar</a>
              <a class="btn btn-sm btn-outline-danger" href="excluir.php?id=<?= h($row['id']) ?>" title="Excluir"><i class="fa fa-trash me-1"></i>Excluir</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($total === 0): ?>
        <tr>
          <td colspan="<?= 10 + ($mostrarLote?1:0) ?>" class="text-center py-5">
            <div class="text-muted">
              <i class="fa fa-inbox fa-3x mb-3 opacity-50"></i>
              <h5>Nenhum material cadastrado</h5>
              <p class="mb-0">Adicione seu primeiro material para começar.</p>
            </div>
          </td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<!-- Modal de confirmação: Apagar Todos na listagem -->
<div class="modal fade" id="modalApagarTodos" tabindex="-1" aria-labelledby="modalApagarTodosLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="modalApagarTodosLabel"><i class="fa fa-exclamation-triangle me-2"></i>Apagar Todos os Materiais</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p><strong>Tem certeza que deseja apagar TODOS os materiais desta lista?</strong></p>
        <p>Esta ação irá remover permanentemente todos os registros da tabela <code>insumos_jnj</code> e reiniciar a numeração.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <form method="post" action="limpar_historico.php" style="display: inline;">
          <input type="hidden" name="confirmar" value="sim">
          <input type="hidden" name="tipo" value="todos">
          <button type="submit" class="btn btn-danger">
            <i class="fa fa-ban me-1"></i>Sim, apagar tudo
          </button>
        </form>
      </div>
    </div>
  </div>
  </div>
<?php include __DIR__ . '/includes/footer.php'; ?>