<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

$pdo = getPDO();
$current = currentUser();
$primaryAdminId = getPrimaryAdminId();

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION['csrf_token'];

$q = trim((string)($_GET['q'] ?? ''));
$roleFilter = (string)($_GET['role'] ?? 'all');
if (!in_array($roleFilter, ['all', 'admin', 'user'], true)) {
  $roleFilter = 'all';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$buildQuery = static function (array $overrides = []) use ($q, $roleFilter, $page): string {
  $base = [
    'q' => $q,
    'role' => $roleFilter,
    'page' => $page,
  ];
  $params = array_merge($base, $overrides);
  foreach ($params as $key => $value) {
    if ($value === '' || $value === null) {
      unset($params[$key]);
    }
  }
  return 'usuarios_pendentes.php' . (!empty($params) ? '?' . http_build_query($params) : '');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedToken = (string)($_POST['csrf_token'] ?? '');
  if ($postedToken === '' || !hash_equals((string)$_SESSION['csrf_token'], $postedToken)) {
    flash('error', 'Sessão inválida. Atualize a página e tente novamente.');
    header('Location: ' . $buildQuery());
    exit;
  }

  $action = (string)($_POST['action'] ?? '');
  $userId = (int)($_POST['user_id'] ?? 0);
  $reason = trim((string)($_POST['reason'] ?? ''));

  if ($userId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    flash('error', 'Ação inválida.');
    header('Location: ' . $buildQuery());
    exit;
  }

  $targetStmt = $pdo->prepare('SELECT id, nome, email, role FROM usuarios WHERE id = ? AND aprovado = 0 LIMIT 1');
  $targetStmt->execute([$userId]);
  $target = $targetStmt->fetch();

  if (!$target) {
    flash('error', 'Solicitação não encontrada ou já processada.');
    header('Location: ' . $buildQuery());
    exit;
  }

  if (($target['role'] ?? 'user') === 'admin' && (int)($current['id'] ?? 0) !== $primaryAdminId) {
    flash('error', $action === 'approve' ? 'Apenas o administrador principal pode aprovar novas contas de administrador.' : 'Apenas o administrador principal pode rejeitar solicitações de administrador.');
    header('Location: ' . $buildQuery());
    exit;
  }

  if ($action === 'approve') {
    $stmt = $pdo->prepare('UPDATE usuarios SET aprovado = 1, aprovado_em = NOW(), aprovado_por = ? WHERE id = ? AND aprovado = 0');
    $stmt->execute([(int)($current['id'] ?? 0), $userId]);
    flash($stmt->rowCount() > 0 ? 'success' : 'error', $stmt->rowCount() > 0 ? 'Solicitação aprovada com sucesso.' : 'Não foi possível aprovar esta solicitação.');
  } elseif ($action === 'reject') {
    if ($reason === '') {
      flash('error', 'Informe o motivo da rejeição.');
      header('Location: ' . $buildQuery());
      exit;
    }

    $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = ? AND aprovado = 0');
    $stmt->execute([$userId]);
    flash($stmt->rowCount() > 0 ? 'success' : 'error', $stmt->rowCount() > 0 ? 'Solicitação rejeitada e removida.' : 'Não foi possível rejeitar esta solicitação.');
  }

  header('Location: ' . $buildQuery());
  exit;
}

$where = ['aprovado = 0'];
$params = [];

if ($q !== '') {
  $where[] = '(nome LIKE ? OR email LIKE ?)';
  $params[] = '%' . $q . '%';
  $params[] = '%' . $q . '%';
}

if ($roleFilter !== 'all') {
  $where[] = 'role = ?';
  $params[] = $roleFilter;
}

$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM usuarios WHERE ' . $whereSql);
$countStmt->execute($params);
$totalUsers = (int)($countStmt->fetch()['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalUsers / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare('SELECT id, nome, email, role, criado_em FROM usuarios WHERE ' . $whereSql . ' ORDER BY criado_em ASC, id ASC LIMIT ? OFFSET ?');
$stmt->execute(array_merge($params, [$perPage, $offset]));
$users = $stmt->fetchAll();

$showingStart = $totalUsers > 0 ? (($page - 1) * $perPage) + 1 : 0;
$showingEnd = min($totalUsers, $page * $perPage);

require_once __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="solicitacoes-kicker">Base em análise</span>
          <h1 class="display-6 fw-semibold mb-2">Usuários pendentes</h1>
          <p class="solicitacoes-subtitle mb-0">Veja as contas que ainda aguardam aprovação no sistema e aprove direto por aqui.</p>
        </div>
        <div class="text-lg-end">
          <div class="solicitacoes-pill">Aprovação por linha</div>
          <small class="text-muted d-block mt-2">Use os botões da tabela para liberar cada conta.</small>
        </div>
      </div>

      <div class="row g-3 mt-4">
        <div class="col-12 col-md-6 col-xl-4">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-user-clock"></i></div>
            <div>
              <div class="metric-label">Contas pendentes</div>
              <div class="metric-value"><?= h(number_format($totalUsers, 0, ',', '.')) ?></div>
              <div class="metric-help">Total de usuários aguardando análise.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-info"><i class="fa-solid fa-layer-group"></i></div>
            <div>
              <div class="metric-label">Exibindo</div>
              <div class="metric-value"><?= $totalUsers > 0 ? h(number_format($showingStart, 0, ',', '.')) . ' - ' . h(number_format($showingEnd, 0, ',', '.')) : '0' ?></div>
              <div class="metric-help">Página atual da fila de aprovação.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-success"><i class="fa-solid fa-shield-halved"></i></div>
            <div>
              <div class="metric-label">Ação</div>
              <div class="metric-value">Admin</div>
              <div class="metric-help">Aprovação e rejeição ficam na Administração central.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="section-card card border-0 shadow-sm mb-4">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md-7">
          <label class="form-label small text-muted mb-1">Buscar usuário</label>
          <input type="text" class="form-control" name="q" value="<?= h($q) ?>" placeholder="Ex.: nome ou e-mail...">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label small text-muted mb-1">Perfil</label>
          <select class="form-select" name="role">
            <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>Todos</option>
            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Administrador</option>
            <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>Conta normal</option>
          </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          <a href="usuarios_pendentes.php" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i></a>
        </div>
      </form>
    </div>
  </div>

  <div class="section-card card border-0 shadow-sm mb-4 approved-accounts-card">
    <div class="section-card-header card-header bg-white border-0 pt-3 pb-0 approved-accounts-header">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="section-badge mb-2"><i class="fa-solid fa-user-clock"></i>Fila de aprovação</span>
          <h2 class="h5 mb-2"><i class="fa-solid fa-users me-2 text-primary"></i>Lista de pendentes</h2>
          <p class="section-card-subtitle mb-0">Consulta rápida das contas ainda não liberadas.</p>
        </div>
        <div class="approved-summary">
          <div class="approved-summary-item">
            <span>Por página</span>
            <strong><?= h(number_format($perPage, 0, ',', '.')) ?></strong>
          </div>
          <div class="approved-summary-item">
            <span>Usuários</span>
            <strong><?= h(number_format($totalUsers, 0, ',', '.')) ?></strong>
          </div>
        </div>
      </div>
    </div>
    <div class="card-body pt-0">
      <?php if (empty($users)): ?>
        <div class="alert alert-info mb-0">Não há contas pendentes para exibir.</div>
      <?php else: ?>
        <div class="approved-table-toolbar d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
          <div class="approved-table-note">
            <i class="fa-solid fa-circle-info me-1 text-primary"></i>
            As ações de aprovação e rejeição funcionam nesta própria tela.
          </div>
          <div class="approved-table-chip">
            <i class="fa-solid fa-layer-group"></i>
            <?= h(number_format($perPage, 0, ',', '.')) ?> por página
          </div>
        </div>
        <div class="table-responsive request-table-wrap approved-table-wrap">
          <table class="table table-hover align-middle mb-0 request-table js-no-datatable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Perfil</th>
                <th>Criado em</th>
                <th class="text-end">Ações</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $user): ?>
                <?php
                  $isAdminRequest = (($user['role'] ?? 'user') === 'admin');
                  $adminRequestBlocked = $isAdminRequest && ((int)$current['id'] !== $primaryAdminId);
                ?>
                <tr>
                  <td><?= (int)$user['id'] ?></td>
                  <td><?= h((string)$user['nome']) ?></td>
                  <td><?= h((string)$user['email']) ?></td>
                  <td>
                    <?php if (($user['role'] ?? 'user') === 'admin'): ?>
                      <span class="badge bg-warning text-dark">Administrador</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">Conta normal</span>
                    <?php endif; ?>
                  </td>
                  <td><?= !empty($user['criado_em']) ? h(date('d/m/Y H:i', strtotime((string)$user['criado_em']))) : '-' ?></td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                      <form method="post" action="<?= h($buildQuery()) ?>" class="m-0 d-inline">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-sm btn-success" <?= $adminRequestBlocked ? 'disabled title="Apenas o administrador principal pode aprovar este pedido"' : '' ?>>Aprovar</button>
                      </form>
                      <form method="post" action="<?= h($buildQuery()) ?>" class="m-0 d-inline" onsubmit="return requestActionReason(this, 'rejeitar esta solicitação');">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="reason" value="">
                        <button type="submit" class="btn btn-sm btn-outline-danger" <?= $adminRequestBlocked ? 'disabled title="Apenas o administrador principal pode rejeitar este pedido"' : '' ?>>Rejeitar</button>
                      </form>
                    </div>
                  </td>
                  <td><span class="badge bg-warning text-dark">Pendente</span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($totalPages > 1): ?>
          <nav class="mt-3" aria-label="Paginação de usuários pendentes">
            <ul class="pagination pagination-sm mb-0">
              <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                  <a class="page-link" href="<?= h($buildQuery(['page' => $p])) ?>"><?= $p ?></a>
                </li>
              <?php endfor; ?>
            </ul>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function requestActionReason(form, actionLabel) {
  var promptText = 'Digite o motivo para ' + actionLabel + ':';
  var reason = window.prompt(promptText, '');
  if (reason === null) {
    return false;
  }
  reason = reason.trim();
  if (reason.length === 0) {
    alert('Motivo é obrigatório.');
    return false;
  }
  var input = form.querySelector('input[name="reason"]');
  if (input) {
    input.value = reason;
  }
  return true;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>