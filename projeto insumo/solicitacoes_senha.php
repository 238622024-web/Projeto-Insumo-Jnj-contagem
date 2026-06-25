<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

function ensurePasswordResetRequestsSchema(PDO $pdo): void {
  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS password_reset_requests (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NULL,
      email VARCHAR(190) NOT NULL,
      motivo_usuario TEXT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'pending',
      admin_note TEXT NULL,
      requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      processed_at DATETIME NULL,
      processed_by INT NULL,
      INDEX idx_prr_status (status),
      INDEX idx_prr_user (user_id),
      INDEX idx_prr_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );
}

$pdo = getPDO();
ensureUserAuthSchema();
ensurePasswordResetRequestsSchema($pdo);

$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? 'pending');
if (!in_array($statusFilter, ['pending', 'completed', 'rejected', 'all'], true)) {
  $statusFilter = 'pending';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$where = ['1 = 1'];
$params = [];

if ($statusFilter !== 'all') {
  $where[] = 'pr.status = ?';
  $params[] = $statusFilter;
}

if ($q !== '') {
  $where[] = '(pr.email LIKE ? OR pr.motivo_usuario LIKE ? OR u.nome LIKE ?)';
  $params[] = '%' . $q . '%';
  $params[] = '%' . $q . '%';
  $params[] = '%' . $q . '%';
}

$whereSql = implode(' AND ', $where);

$totalStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM password_reset_requests pr LEFT JOIN usuarios u ON u.id = pr.user_id WHERE $whereSql");
$totalStmt->execute($params);
$totalRequests = (int)($totalStmt->fetch()['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalRequests / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT pr.id, pr.user_id, pr.email, pr.motivo_usuario, pr.status, pr.admin_note, pr.requested_at, pr.processed_at, pr.processed_by, u.nome, u.role, u.aprovado
        FROM password_reset_requests pr
        LEFT JOIN usuarios u ON u.id = pr.user_id
        WHERE $whereSql
        ORDER BY pr.requested_at DESC, pr.id DESC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$requests = $stmt->fetchAll();

$countsStmt = $pdo->query(
  "SELECT
      SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
      SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
      SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count
   FROM password_reset_requests"
);
$counts = $countsStmt->fetch() ?: [];
$pendingCount = (int)($counts['pending_count'] ?? 0);
$completedCount = (int)($counts['completed_count'] ?? 0);
$rejectedCount = (int)($counts['rejected_count'] ?? 0);

$showingStart = $totalRequests > 0 ? (($page - 1) * $perPage) + 1 : 0;
$showingEnd = min($totalRequests, $page * $perPage);

$buildQuery = static function (array $overrides = []) use ($q, $statusFilter, $page): string {
  $base = [
    'q' => $q,
    'status' => $statusFilter,
    'page' => $page,
  ];
  $params = array_merge($base, $overrides);
  foreach ($params as $key => $value) {
    if ($value === '' || $value === null) {
      unset($params[$key]);
    }
  }
  return 'solicitacoes_senha.php' . (!empty($params) ? '?' . http_build_query($params) : '');
};

require_once __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="solicitacoes-kicker">Central de acesso</span>
          <h1 class="display-6 fw-semibold mb-2">Solicitações de senha</h1>
          <p class="solicitacoes-subtitle mb-0">Acompanhe os pedidos de redefinição enviados pelos usuários.</p>
        </div>
        <div class="text-lg-end">
          <div class="solicitacoes-pill">Consulta somente leitura</div>
          <small class="text-muted d-block mt-2">As decisões de aprovação e rejeição ficam na Administração central.</small>
        </div>
      </div>

      <div class="row g-3 mt-4">
        <div class="col-12 col-md-6 col-xl-4">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-key"></i></div>
            <div>
              <div class="metric-label">Pendentes</div>
              <div class="metric-value"><?= h(number_format($pendingCount, 0, ',', '.')) ?></div>
              <div class="metric-help">Aguardando análise do administrador.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-success"><i class="fa-solid fa-circle-check"></i></div>
            <div>
              <div class="metric-label">Concluídas</div>
              <div class="metric-value"><?= h(number_format($completedCount, 0, ',', '.')) ?></div>
              <div class="metric-help">Pedidos com senha temporária definida.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-danger"><i class="fa-solid fa-xmark"></i></div>
            <div>
              <div class="metric-label">Rejeitadas</div>
              <div class="metric-value"><?= h(number_format($rejectedCount, 0, ',', '.')) ?></div>
              <div class="metric-help">Pedidos recusados com motivo registrado.</div>
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
          <label class="form-label small text-muted mb-1">Buscar pedido</label>
          <input type="text" class="form-control" name="q" value="<?= h($q) ?>" placeholder="Ex.: nome, e-mail ou motivo...">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label small text-muted mb-1">Status</label>
          <select class="form-select" name="status">
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pendentes</option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Concluídas</option>
            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejeitadas</option>
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Todas</option>
          </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
          <a href="solicitacoes_senha.php" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i></a>
        </div>
      </form>
    </div>
  </div>

  <div class="section-card card border-0 shadow-sm mb-4 approved-accounts-card">
    <div class="section-card-header card-header bg-white border-0 pt-3 pb-0 approved-accounts-header">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="section-badge mb-2"><i class="fa-solid fa-shield-halved"></i>Solicitações</span>
          <h2 class="h5 mb-2"><i class="fa-solid fa-clipboard-list me-2 text-primary"></i>Fila de redefinição</h2>
          <p class="section-card-subtitle mb-0">Consulta rápida dos pedidos enviados pelo formulário de recuperação.</p>
        </div>
        <div class="approved-summary">
          <div class="approved-summary-item">
            <span>Por página</span>
            <strong><?= h(number_format($perPage, 0, ',', '.')) ?></strong>
          </div>
          <div class="approved-summary-item">
            <span>Exibindo</span>
            <strong><?= $totalRequests > 0 ? h(number_format($showingStart, 0, ',', '.')) . ' - ' . h(number_format($showingEnd, 0, ',', '.')) : '0' ?></strong>
          </div>
        </div>
      </div>
    </div>
    <div class="card-body pt-0">
      <?php if (empty($requests)): ?>
        <div class="alert alert-info mb-0">Não há solicitações de senha para exibir.</div>
      <?php else: ?>
        <div class="approved-table-toolbar d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
          <div class="approved-table-note">
            <i class="fa-solid fa-circle-info me-1 text-primary"></i>
            Os pedidos pendentes aparecem aqui e são tratados na Administração central.
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
                <th>Usuário</th>
                <th>E-mail</th>
                <th>Motivo informado</th>
                <th>Solicitado em</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($requests as $request): ?>
                <?php
                  $status = (string)($request['status'] ?? 'pending');
                  $statusBadge = [
                    'pending' => 'warning text-dark',
                    'completed' => 'success',
                    'rejected' => 'danger',
                  ][$status] ?? 'secondary';
                  $statusLabel = [
                    'pending' => 'Pendente',
                    'completed' => 'Concluída',
                    'rejected' => 'Rejeitada',
                  ][$status] ?? 'Desconhecido';
                  $userName = (string)($request['nome'] ?? 'Usuário não localizado');
                  $userRole = (string)($request['role'] ?? 'user');
                ?>
                <tr>
                  <td><?= (int)$request['id'] ?></td>
                  <td>
                    <?= h($userName) ?>
                    <?php if (($request['user_id'] ?? null) === null): ?>
                      <span class="badge bg-light text-dark border ms-1">Sem vínculo</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?= h((string)$request['email']) ?>
                    <?php if ($userRole === 'admin'): ?>
                      <span class="badge bg-warning text-dark ms-1">Admin</span>
                    <?php endif; ?>
                  </td>
                  <td><?= h((string)($request['motivo_usuario'] !== '' ? $request['motivo_usuario'] : '-')) ?></td>
                  <td><?= !empty($request['requested_at']) ? h(date('d/m/Y H:i', strtotime((string)$request['requested_at']))) : '-' ?></td>
                  <td><span class="badge bg-<?= h($statusBadge) ?>"><?= h($statusLabel) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($totalPages > 1): ?>
          <nav class="mt-3" aria-label="Paginação de solicitações de senha">
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