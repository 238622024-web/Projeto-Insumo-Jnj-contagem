<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

$pdo = getPDO();
$current = currentUser();
$adminId = (int)($current['id'] ?? 0);
$primaryAdminId = getPrimaryAdminId();

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$q = trim((string)($_GET['q'] ?? ''));
$roleFilter = (string)($_GET['role'] ?? 'all');
if (!in_array($roleFilter, ['all', 'admin', 'user'], true)) {
  $roleFilter = 'all';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$where = ['aprovado = 1'];
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

$sql = 'SELECT id, nome, email, role, aprovado_em, criado_em FROM usuarios WHERE ' . $whereSql . ' ORDER BY role DESC, nome ASC LIMIT ? OFFSET ?';
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$users = $stmt->fetchAll();

$showingStart = $totalUsers > 0 ? (($page - 1) * $perPage) + 1 : 0;
$showingEnd = min($totalUsers, $page * $perPage);

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
  return 'usuarios_aprovados.php' . (!empty($params) ? '?' . http_build_query($params) : '');
};
?>

<?php
require_once __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="solicitacoes-kicker">Base ativa</span>
          <h1 class="display-6 fw-semibold mb-2">Usuários aprovados</h1>
          <p class="solicitacoes-subtitle mb-0">Consulte as contas já liberadas no sistema e mantenha a base de acesso organizada.</p>
        </div>
        <div class="text-lg-end">
          <div class="solicitacoes-pill">Listagem somente leitura</div>
          <small class="text-muted d-block mt-2">Use os filtros para localizar por nome, e-mail ou perfil.</small>
        </div>
      </div>

      <div class="row g-3 mt-4">
        <div class="col-12 col-md-6 col-xl-4">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-success"><i class="fa-solid fa-user-check"></i></div>
            <div>
              <div class="metric-label">Contas aprovadas</div>
              <div class="metric-value"><?= h(number_format($totalUsers, 0, ',', '.')) ?></div>
              <div class="metric-help">Total de usuários com acesso liberado.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-info"><i class="fa-solid fa-layer-group"></i></div>
            <div>
              <div class="metric-label">Exibindo</div>
              <div class="metric-value"><?= $totalUsers > 0 ? h(number_format($showingStart, 0, ',', '.')) . ' - ' . h(number_format($showingEnd, 0, ',', '.')) : '0' ?></div>
              <div class="metric-help">Página atual da base aprovada.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-shield-halved"></i></div>
            <div>
              <div class="metric-label">Administrador principal</div>
              <div class="metric-value"><?= $primaryAdminId > 0 ? '#' . h((string)$primaryAdminId) : '-' ?></div>
              <div class="metric-help">Referência para contas com privilégios especiais.</div>
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
          <a href="usuarios_aprovados.php" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i></a>
        </div>
      </form>
    </div>
  </div>

  <div class="section-card card border-0 shadow-sm mb-4 approved-accounts-card">
    <div class="section-card-header card-header bg-white border-0 pt-3 pb-0 approved-accounts-header">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="section-badge mb-2"><i class="fa-solid fa-user-shield"></i>Base ativa</span>
          <h2 class="h5 mb-2"><i class="fa-solid fa-users me-2 text-primary"></i>Lista de aprovados</h2>
          <p class="section-card-subtitle mb-0">Visual limpo e focado em consulta rápida da base liberada.</p>
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
        <div class="alert alert-info mb-0">Não há contas aprovadas para exibir.</div>
      <?php else: ?>
        <div class="approved-table-toolbar d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
          <div class="approved-table-note">
            <i class="fa-solid fa-circle-info me-1 text-primary"></i>
            Esta listagem é somente para consulta.
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
                <th>Aprovado em</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $user): ?>
                <?php
                  $isPrimaryAdminRow = ((int)$user['id'] === $primaryAdminId);
                  $isCurrentUserRow = ((int)$user['id'] === $adminId);
                  $isAdminRow = (($user['role'] ?? 'user') === 'admin');
                ?>
                <tr>
                  <td><?= (int)$user['id'] ?></td>
                  <td>
                    <?= h((string)$user['nome']) ?>
                    <?php if ($isPrimaryAdminRow): ?>
                      <span class="badge bg-primary ms-1 approved-row-badge">Principal</span>
                    <?php endif; ?>
                    <?php if ($isCurrentUserRow): ?>
                      <span class="badge bg-light text-dark border ms-1 approved-row-badge">Você</span>
                    <?php endif; ?>
                  </td>
                  <td><?= h((string)$user['email']) ?></td>
                  <td>
                    <?php if ($isAdminRow): ?>
                      <span class="badge bg-warning text-dark">Administrador</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">Conta normal</span>
                    <?php endif; ?>
                  </td>
                  <td><?= !empty($user['aprovado_em']) ? h(date('d/m/Y H:i', strtotime((string)$user['aprovado_em']))) : '-' ?></td>
                  <td>
                    <?php if ($isPrimaryAdminRow): ?>
                      <span class="badge bg-primary">Principal</span>
                    <?php elseif ($isCurrentUserRow): ?>
                      <span class="badge bg-light text-dark border">Ativa</span>
                    <?php else: ?>
                      <span class="badge bg-success">Ativa</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($totalPages > 1): ?>
          <nav class="mt-3" aria-label="Paginação de usuários aprovados">
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>