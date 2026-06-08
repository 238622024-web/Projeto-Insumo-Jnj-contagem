<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireLogin();
$pdo = getPDO();

// Filtragem por data de entrada via GET
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');
$count_start_date = trim($_GET['count_start_date'] ?? '');
$count_end_date = trim($_GET['count_end_date'] ?? '');
$status_validade = trim($_GET['status_validade'] ?? '');
$unidade_filter = trim($_GET['unidade_filter'] ?? '');
$sem_contagem = trim($_GET['sem_contagem'] ?? '');
$where = [];
$params = [];

$exportParams = [];

if ($start_date !== '') {
  $where[] = 'data_entrada >= ?';
  $params[] = $start_date;
  $exportParams['start_date'] = $start_date;
}
if ($end_date !== '') {
  $where[] = 'data_entrada <= ?';
  $params[] = $end_date;
  $exportParams['end_date'] = $end_date;
}
if ($count_start_date !== '') {
  $where[] = 'data_contagem >= ?';
  $params[] = $count_start_date;
  $exportParams['count_start_date'] = $count_start_date;
}
if ($count_end_date !== '') {
  $where[] = 'data_contagem <= ?';
  $params[] = $count_end_date;
  $exportParams['count_end_date'] = $count_end_date;
}
if ($status_validade === 'expirado') {
  $where[] = 'validade < CURDATE()';
  $exportParams['status_validade'] = $status_validade;
} elseif ($status_validade === 'v7') {
  $where[] = 'validade >= CURDATE() AND validade <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
  $exportParams['status_validade'] = $status_validade;
} elseif ($status_validade === 'v30') {
  $where[] = 'validade > DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
  $exportParams['status_validade'] = $status_validade;
} elseif ($status_validade === 'ok') {
  $where[] = 'validade > DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
  $exportParams['status_validade'] = $status_validade;
}
if ($unidade_filter !== '') {
  if ($unidade_filter === 'Sem unidade') {
    $where[] = "(unidade IS NULL OR unidade = '')";
  } else {
    $where[] = 'unidade = ?';
    $params[] = $unidade_filter;
  }
  $exportParams['unidade_filter'] = $unidade_filter;
}
if ($sem_contagem === '1') {
  $where[] = 'data_contagem IS NULL';
  $exportParams['sem_contagem'] = '1';
}
$sql = 'SELECT * FROM insumos_jnj';
if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$insumos = $stmt->fetchAll();
$total = count($insumos);
$exportQuery = http_build_query($exportParams);
$exportPdfHref = 'export_pdf.php' . ($exportQuery !== '' ? '?' . $exportQuery : '');
$exportExcelHref = 'export_excel.php' . ($exportQuery !== '' ? '?' . $exportQuery : '');
function buildIndexStatsLink(array $extra = []): string {
  $params = [];

  foreach (['start_date', 'end_date', 'count_start_date', 'count_end_date', 'status_validade', 'unidade_filter', 'sem_contagem'] as $key) {
    $value = trim($_GET[$key] ?? '');
    if ($value !== '') {
      $params[$key] = $value;
    }
  }

  foreach ($extra as $key => $value) {
    if ($value === null || $value === '') {
      unset($params[$key]);
    } else {
      $params[$key] = $value;
    }
  }

  $query = http_build_query($params);
  return 'index.php' . ($query !== '' ? '?' . $query : '');
}
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
<?php if ($count_start_date !== '' || $count_end_date !== '' || $status_validade !== '' || $unidade_filter !== '' || $sem_contagem === '1'): ?>
<div class="alert alert-info border-0 shadow-sm d-flex flex-wrap align-items-center gap-2">
  <strong class="me-1"><i class="fa-solid fa-filter me-1"></i>Filtros do dashboard:</strong>
  <?php if ($count_start_date !== '' || $count_end_date !== ''): ?>
    <span class="badge text-bg-light border">Contagem: <?= h($count_start_date !== '' ? $count_start_date : '...') ?> ate <?= h($count_end_date !== '' ? $count_end_date : '...') ?></span>
  <?php endif; ?>
  <?php if ($status_validade !== ''): ?>
    <span class="badge text-bg-light border">Status: <?= h($status_validade) ?></span>
  <?php endif; ?>
  <?php if ($unidade_filter !== ''): ?>
    <span class="badge text-bg-light border">Unidade: <?= h($unidade_filter) ?></span>
  <?php endif; ?>
  <?php if ($sem_contagem === '1'): ?>
    <span class="badge text-bg-light border">Sem contagem</span>
  <?php endif; ?>
  <a class="btn btn-sm btn-outline-secondary ms-auto" href="index.php"><i class="fa-solid fa-xmark me-1"></i>Limpar filtros</a>
</div>
<?php endif; ?>

<!-- Cards de Estatísticas -->
<div class="row g-3 mb-4">
  <div class="col-12 col-md-3">
    <a class="dashboard-stat-card text-decoration-none d-block h-100" href="<?= h(buildIndexStatsLink()) ?>" aria-label="Abrir a lista completa de materiais">
      <div class="card border-0 shadow-sm h-100 dashboard-stat-card-inner" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
      <div class="card-body text-white">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-white-50 text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Total de Materiais</h6>
            <h2 class="mb-0 fw-bold"><?= $total ?></h2>
          </div>
          <div class="fs-1 opacity-50"><i class="fa fa-boxes"></i></div>
        </div>
        <div class="mt-2 small text-white-50">Abrir lista completa</div>
      </div>
      </div>
    </a>
  </div>
  <div class="col-12 col-md-3">
    <a class="dashboard-stat-card text-decoration-none d-block h-100" href="<?= h(buildIndexStatsLink(['status_validade' => 'expirado'])) ?>" aria-label="Ver materiais expirados">
      <div class="card border-0 shadow-sm h-100 dashboard-stat-card-inner" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
      <div class="card-body text-white">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-white-50 text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Expirados</h6>
            <h2 class="mb-0 fw-bold"><?= $expirados ?></h2>
          </div>
          <div class="fs-1 opacity-50"><i class="fa fa-times-circle"></i></div>
        </div>
        <div class="mt-2 small text-white-50">Clique para abrir os itens vencidos</div>
      </div>
      </div>
    </a>
  </div>
  <div class="col-12 col-md-3">
    <a class="dashboard-stat-card text-decoration-none d-block h-100" href="<?= h(buildIndexStatsLink(['status_validade' => 'v7'])) ?>" aria-label="Ver materiais vencendo em 7 dias">
      <div class="card border-0 shadow-sm h-100 dashboard-stat-card-inner" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
      <div class="card-body text-white">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-white-50 text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Vence em 7 dias</h6>
            <h2 class="mb-0 fw-bold"><?= $vencendo7dias ?></h2>
          </div>
          <div class="fs-1 opacity-50"><i class="fa fa-exclamation-triangle"></i></div>
        </div>
        <div class="mt-2 small text-white-50">Clique para planejar a reposição</div>
      </div>
      </div>
    </a>
  </div>
  <div class="col-12 col-md-3">
    <a class="dashboard-stat-card text-decoration-none d-block h-100" href="<?= h(buildIndexStatsLink(['status_validade' => 'v30'])) ?>" aria-label="Ver materiais vencendo em 30 dias">
      <div class="card border-0 shadow-sm h-100 dashboard-stat-card-inner" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
      <div class="card-body text-white">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-white-50 text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Vence em 30 dias</h6>
            <h2 class="mb-0 fw-bold"><?= $vencendo30dias ?></h2>
          </div>
          <div class="fs-1 opacity-50"><i class="fa fa-clock"></i></div>
        </div>
        <div class="mt-2 small text-white-50">Clique para ver a faixa de atenção</div>
      </div>
      </div>
    </a>
  </div>
</div>

<!-- Header com Ações -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h2 class="h4 m-0"><i class="fa fa-table me-2 text-primary"></i><?= h(t('list.title')) ?></h2>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-success" href="cadastrar.php"><i class="fa-solid fa-circle-plus me-2"></i><?= h(t('list.add')) ?></a>
        <a class="btn btn-outline-success" href="<?= h($exportExcelHref) ?>"><i class="fa-solid fa-file-excel me-2"></i><?= h(t('list.export.excel')) ?></a>
        <a class="btn btn-outline-danger" href="<?= h($exportPdfHref) ?>"><i class="fa-solid fa-file-pdf me-2"></i><?= h(t('list.export.pdf')) ?></a>
        <?php if (isAdmin()): ?>
          <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalApagarTodos">
            <i class="fa-solid fa-trash-can me-2"></i>Apagar Todos
          </button>
        <?php endif; ?>
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
    <table class="table table-hover align-middle mb-0 js-materials-search">
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
          <td style="padding: 1rem;"><strong><?= h(number_format((int)$row['quantidade'], 0, ',', '.')) ?></strong></td>
          <td style="padding: 1rem;"><?= h(date('d/m/Y', strtotime($row['data_entrada']))) ?></td>
          <td style="padding: 1rem;"><?= h(date('d/m/Y', strtotime($row['validade']))) ?><br><?= $badge ?></td>
          <td class="small" style="padding: 1rem; max-width: 200px;"><?= nl2br(h($row['observacoes'] ?? '')) ?></td>
          <td class="text-center" style="padding: 1rem;">
            <div class="materials-action-group" role="group" aria-label="Acoes do item <?= h($row['id']) ?>">
              <a class="btn btn-sm materials-action-btn materials-action-edit" href="editar.php?id=<?= h($row['id']) ?>" title="Editar item #<?= h($row['id']) ?>" aria-label="Editar item #<?= h($row['id']) ?>">
                <i class="fa-solid fa-pen-to-square"></i>
                <span>Editar</span>
              </a>
              <?php if (isAdmin()): ?>
                <a class="btn btn-sm materials-action-btn materials-action-delete" href="excluir.php?id=<?= h($row['id']) ?>" title="Excluir item #<?= h($row['id']) ?>" aria-label="Excluir item #<?= h($row['id']) ?>">
                  <i class="fa-solid fa-trash-can"></i>
                  <span>Excluir</span>
                </a>
              <?php endif; ?>
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
<?php if (isAdmin()): ?>
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
          <div class="alert alert-warning border-0 mb-0">
            <i class="fa-solid fa-lock me-2"></i>Para confirmar, digite sua senha de administrador.
          </div>
          <div class="mt-3">
            <label class="form-label fw-semibold" for="senhaApagarTodos">Senha</label>
            <input type="password" class="form-control" id="senhaApagarTodos" name="senha_confirmacao" form="formApagarTodos" autocomplete="current-password" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <form id="formApagarTodos" method="post" action="limpar_historico.php" style="display: inline;">
            <input type="hidden" name="confirmar" value="sim">
            <input type="hidden" name="tipo" value="todos">
            <button type="submit" class="btn btn-danger">
              <i class="fa-solid fa-trash-can me-1"></i>Sim, apagar tudo
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>