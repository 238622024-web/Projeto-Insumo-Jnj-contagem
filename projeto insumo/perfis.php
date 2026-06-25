<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

$pdo = getPDO();
ensureUserAuthSchema();

$profileDefinitions = [
  'admin' => [
    'label' => 'Administrador',
    'icon' => 'fa-shield-halved',
    'description' => 'Conta com privilégios de gestão, aprovação e manutenção.',
    'badge' => 'text-bg-warning',
  ],
  'user' => [
    'label' => 'Conta normal',
    'icon' => 'fa-user',
    'description' => 'Conta operacional padrão para uso diário do sistema.',
    'badge' => 'text-bg-secondary',
  ],
];

$roleStatsStmt = $pdo->query(
  "SELECT
      role,
      COUNT(*) AS total_users,
      SUM(CASE WHEN aprovado = 1 THEN 1 ELSE 0 END) AS approved_users,
      SUM(CASE WHEN aprovado = 0 THEN 1 ELSE 0 END) AS pending_users,
      MIN(criado_em) AS first_created,
      MAX(COALESCE(aprovado_em, criado_em)) AS last_activity
   FROM usuarios
   GROUP BY role"
);
$roleStatsRows = $roleStatsStmt->fetchAll() ?: [];
$roleStats = [];
foreach ($roleStatsRows as $row) {
  $roleKey = (string)($row['role'] ?? 'user');
  $roleStats[$roleKey] = [
    'total_users' => (int)($row['total_users'] ?? 0),
    'approved_users' => (int)($row['approved_users'] ?? 0),
    'pending_users' => (int)($row['pending_users'] ?? 0),
    'first_created' => $row['first_created'] ?? null,
    'last_activity' => $row['last_activity'] ?? null,
  ];
}

foreach ($profileDefinitions as $roleKey => $definition) {
  if (!isset($roleStats[$roleKey])) {
    $roleStats[$roleKey] = [
      'total_users' => 0,
      'approved_users' => 0,
      'pending_users' => 0,
      'first_created' => null,
      'last_activity' => null,
    ];
  }
}

$summaryStmt = $pdo->query(
  "SELECT
      COUNT(*) AS total_users,
      SUM(CASE WHEN role = 'admin' AND aprovado = 1 THEN 1 ELSE 0 END) AS total_admins,
      SUM(CASE WHEN aprovado = 1 THEN 1 ELSE 0 END) AS total_approved,
      SUM(CASE WHEN aprovado = 0 THEN 1 ELSE 0 END) AS total_pending
   FROM usuarios"
);
$summary = $summaryStmt->fetch() ?: [];

$recentUsersStmt = $pdo->query(
  'SELECT id, nome, email, role, aprovado, aprovado_em, criado_em
   FROM usuarios
   ORDER BY role DESC, nome ASC
   LIMIT 8'
);
$recentUsers = $recentUsersStmt->fetchAll() ?: [];

include __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="solicitacoes-kicker">Administração</span>
          <h1 class="display-6 fw-semibold mb-2">Perfis de acesso</h1>
          <p class="solicitacoes-subtitle mb-0">Veja como as contas do sistema estão distribuídas entre administradores e usuários comuns.</p>
        </div>
        <div class="text-lg-end">
          <div class="solicitacoes-pill">Visão somente leitura</div>
          <small class="text-muted d-block mt-2">As ações de aprovação continuam concentradas em Solicitações.</small>
        </div>
      </div>

      <div class="row g-3 mt-4">
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-info"><i class="fa-solid fa-users"></i></div>
            <div>
              <div class="metric-label">Total de contas</div>
              <div class="metric-value"><?= h(number_format((int)($summary['total_users'] ?? 0), 0, ',', '.')) ?></div>
              <div class="metric-help">Contas cadastradas no banco.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-success"><i class="fa-solid fa-user-check"></i></div>
            <div>
              <div class="metric-label">Contas aprovadas</div>
              <div class="metric-value"><?= h(number_format((int)($summary['total_approved'] ?? 0), 0, ',', '.')) ?></div>
              <div class="metric-help">Usuários com acesso liberado.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-user-clock"></i></div>
            <div>
              <div class="metric-label">Pendentes</div>
              <div class="metric-value"><?= h(number_format((int)($summary['total_pending'] ?? 0), 0, ',', '.')) ?></div>
              <div class="metric-help">Contas aguardando liberação.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-primary"><i class="fa-solid fa-shield-halved"></i></div>
            <div>
              <div class="metric-label">Administradores</div>
              <div class="metric-value"><?= h(number_format((int)($summary['total_admins'] ?? 0), 0, ',', '.')) ?></div>
              <div class="metric-help">Contas com privilégios administrativos.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="row g-4 mb-4">
    <?php foreach ($profileDefinitions as $roleKey => $definition): ?>
      <?php $stats = $roleStats[$roleKey]; ?>
      <div class="col-12 col-lg-6">
        <div class="section-card card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
              <div class="d-flex align-items-center gap-3">
                <div class="metric-icon <?= $roleKey === 'admin' ? 'metric-icon-warning' : 'metric-icon-info' ?>">
                  <i class="fa-solid <?= h($definition['icon']) ?>"></i>
                </div>
                <div>
                  <span class="section-badge mb-2"><i class="fa-solid fa-id-badge"></i><?= h($definition['label']) ?></span>
                  <h2 class="h5 mb-1"><?= h($definition['label']) ?></h2>
                  <p class="section-card-subtitle mb-0"><?= h($definition['description']) ?></p>
                </div>
              </div>
              <span class="badge <?= h($definition['badge']) ?> align-self-start"><?= h(number_format($stats['total_users'], 0, ',', '.')) ?> contas</span>
            </div>

            <div class="row g-3">
              <div class="col-6">
                <div class="metric-card h-100">
                  <div>
                    <div class="metric-label">Aprovadas</div>
                    <div class="metric-value fs-3"><?= h(number_format($stats['approved_users'], 0, ',', '.')) ?></div>
                    <div class="metric-help">Contas liberadas neste perfil.</div>
                  </div>
                </div>
              </div>
              <div class="col-6">
                <div class="metric-card h-100">
                  <div>
                    <div class="metric-label">Pendentes</div>
                    <div class="metric-value fs-3"><?= h(number_format($stats['pending_users'], 0, ',', '.')) ?></div>
                    <div class="metric-help">Contas ainda aguardando análise.</div>
                  </div>
                </div>
              </div>
            </div>

            <div class="d-flex flex-column gap-2 mt-3 text-muted small">
              <div><i class="fa-regular fa-calendar-plus me-2 text-primary"></i>Primeiro cadastro: <?= !empty($stats['first_created']) ? h(date('d/m/Y H:i', strtotime((string)$stats['first_created']))) : '-' ?></div>
              <div><i class="fa-regular fa-clock me-2 text-primary"></i>Última atividade: <?= !empty($stats['last_activity']) ? h(date('d/m/Y H:i', strtotime((string)$stats['last_activity']))) : '-' ?></div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="section-card card border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-3">
        <div>
          <span class="section-badge mb-2"><i class="fa-solid fa-list"></i>Base atual</span>
          <h2 class="h5 mb-2">Contas por perfil</h2>
          <p class="section-card-subtitle mb-0">Lista resumida das contas mais recentes para inspeção rápida.</p>
        </div>
        <a href="usuarios_aprovados.php" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Abrir aprovados</a>
      </div>

      <?php if (empty($recentUsers)): ?>
        <div class="alert alert-info mb-0">Não há usuários cadastrados para exibir.</div>
      <?php else: ?>
        <div class="table-responsive request-table-wrap">
          <table class="table table-hover align-middle mb-0 request-table js-no-datatable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Perfil</th>
                <th>Status</th>
                <th>Criado em</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentUsers as $user): ?>
                <?php
                  $isAdmin = (($user['role'] ?? 'user') === 'admin');
                  $isApproved = (int)($user['aprovado'] ?? 0) === 1;
                ?>
                <tr>
                  <td><?= (int)$user['id'] ?></td>
                  <td><?= h((string)$user['nome']) ?></td>
                  <td><?= h((string)$user['email']) ?></td>
                  <td>
                    <?php if ($isAdmin): ?>
                      <span class="badge bg-warning text-dark">Administrador</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">Conta normal</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($isApproved): ?>
                      <span class="badge bg-success">Aprovada</span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark">Pendente</span>
                    <?php endif; ?>
                  </td>
                  <td><?= !empty($user['criado_em']) ? h(date('d/m/Y H:i', strtotime((string)$user['criado_em']))) : '-' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>