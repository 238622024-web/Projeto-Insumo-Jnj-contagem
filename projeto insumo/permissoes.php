<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

$pdo = getPDO();
ensureUserAuthSchema();

$permissionRules = [
  [
    'name' => 'Acesso administrativo',
    'description' => 'Somente contas com role admin podem abrir a área de administração e executar ações restritas.',
    'source' => 'auth.php: isAdmin() / requireAdmin()',
    'status' => 'active',
    'icon' => 'fa-shield-halved',
  ],
  [
    'name' => 'Conta aprovada',
    'description' => 'O campo aprovado define se a conta pode autenticar e ser tratada como ativa no sistema.',
    'source' => 'usuarios.aprovado',
    'status' => 'active',
    'icon' => 'fa-user-check',
  ],
  [
    'name' => 'Troca obrigatória de senha',
    'description' => 'Quando must_change_password está ativo, o usuário é redirecionado para concluir a alteração antes de seguir no sistema.',
    'source' => 'auth.php: requireLogin()',
    'status' => 'active',
    'icon' => 'fa-key',
  ],
  [
    'name' => 'Preferências por conta',
    'description' => 'Tema, idioma e notificações ficam armazenados por usuário e afetam a experiência individual.',
    'source' => 'usuarios.preferred_* / notifications',
    'status' => 'active',
    'icon' => 'fa-sliders',
  ],
  [
    'name' => 'Lembrar login',
    'description' => 'O sistema usa token persistente para sessão prolongada quando o recurso remember me é ativado.',
    'source' => 'auth.php: remember_token_*',
    'status' => 'active',
    'icon' => 'fa-cookie-bite',
  ],
];

$roleMatrix = [
  'admin' => [
    'label' => 'Administrador',
    'description' => 'Acesso à área administrativa e operações de manutenção.',
    'badge' => 'text-bg-warning',
    'permissions' => [
      ['label' => 'Abrir Administração', 'enabled' => true],
      ['label' => 'Aprovar usuários', 'enabled' => true],
      ['label' => 'Rejeitar usuários', 'enabled' => true],
      ['label' => 'Ver usuários aprovados', 'enabled' => true],
      ['label' => 'Ver solicitações de senha', 'enabled' => true],
      ['label' => 'Gerenciar insumos', 'enabled' => true],
    ],
  ],
  'user' => [
    'label' => 'Conta normal',
    'description' => 'Acesso operacional ao módulo de uso diário e ao próprio perfil.',
    'badge' => 'text-bg-secondary',
    'permissions' => [
      ['label' => 'Abrir Administração', 'enabled' => false],
      ['label' => 'Aprovar usuários', 'enabled' => false],
      ['label' => 'Rejeitar usuários', 'enabled' => false],
      ['label' => 'Ver usuários aprovados', 'enabled' => false],
      ['label' => 'Ver solicitações de senha', 'enabled' => false],
      ['label' => 'Gerenciar insumos', 'enabled' => false],
    ],
  ],
];

$summaryStmt = $pdo->query(
  "SELECT
      COUNT(*) AS total_users,
      SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS total_admins,
      SUM(CASE WHEN aprovado = 1 THEN 1 ELSE 0 END) AS total_approved,
      SUM(CASE WHEN aprovado = 0 THEN 1 ELSE 0 END) AS total_pending,
      SUM(CASE WHEN must_change_password = 1 THEN 1 ELSE 0 END) AS forced_password_changes,
      SUM(CASE WHEN email_notifications = 1 THEN 1 ELSE 0 END) AS email_notifications_on,
      SUM(CASE WHEN security_notifications = 1 THEN 1 ELSE 0 END) AS security_notifications_on
   FROM usuarios"
);
$summary = $summaryStmt->fetch() ?: [];

$rolesStmt = $pdo->query(
  "SELECT role, COUNT(*) AS total, SUM(CASE WHEN aprovado = 1 THEN 1 ELSE 0 END) AS approved
   FROM usuarios
   GROUP BY role"
);
$rolesRows = $rolesStmt->fetchAll() ?: [];
$rolesStats = [];
foreach ($rolesRows as $row) {
  $rolesStats[(string)($row['role'] ?? 'user')] = [
    'total' => (int)($row['total'] ?? 0),
    'approved' => (int)($row['approved'] ?? 0),
  ];
}

foreach (array_keys($roleMatrix) as $roleKey) {
  if (!isset($rolesStats[$roleKey])) {
    $rolesStats[$roleKey] = ['total' => 0, 'approved' => 0];
  }
}

$flagStats = [
  ['label' => 'Exigir troca de senha', 'value' => (int)($summary['forced_password_changes'] ?? 0), 'help' => 'Contas que ainda precisam concluir a troca de senha.'],
  ['label' => 'Notificações por e-mail ativas', 'value' => (int)($summary['email_notifications_on'] ?? 0), 'help' => 'Usuários com recebimento de e-mails habilitado.'],
  ['label' => 'Notificações de segurança ativas', 'value' => (int)($summary['security_notifications_on'] ?? 0), 'help' => 'Usuários com alertas de segurança habilitados.'],
];

$recentAdminsStmt = $pdo->query(
  "SELECT id, nome, email, aprovado, must_change_password, last_login_at, criado_em
   FROM usuarios
   WHERE role = 'admin'
   ORDER BY aprovado DESC, COALESCE(last_login_at, criado_em) DESC
   LIMIT 6"
);
$recentAdmins = $recentAdminsStmt->fetchAll() ?: [];

include __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="solicitacoes-kicker">Administração</span>
          <h1 class="display-6 fw-semibold mb-2">Permissões do sistema</h1>
          <p class="solicitacoes-subtitle mb-0">Resumo das regras que o sistema aplica hoje para acesso, aprovação e comportamento da conta.</p>
        </div>
        <div class="text-lg-end">
          <div class="solicitacoes-pill">Mapa de acesso atual</div>
          <small class="text-muted d-block mt-2">As ações continuam concentradas em Solicitações e nos módulos operacionais.</small>
        </div>
      </div>

      <div class="row g-3 mt-4">
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-info"><i class="fa-solid fa-users"></i></div>
            <div>
              <div class="metric-label">Total de contas</div>
              <div class="metric-value"><?= h(number_format((int)($summary['total_users'] ?? 0), 0, ',', '.')) ?></div>
              <div class="metric-help">Base registrada no sistema.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-shield-halved"></i></div>
            <div>
              <div class="metric-label">Administradores</div>
              <div class="metric-value"><?= h(number_format((int)($summary['total_admins'] ?? 0), 0, ',', '.')) ?></div>
              <div class="metric-help">Contas com acesso restrito ampliado.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-success"><i class="fa-solid fa-user-check"></i></div>
            <div>
              <div class="metric-label">Contas aprovadas</div>
              <div class="metric-value"><?= h(number_format((int)($summary['total_approved'] ?? 0), 0, ',', '.')) ?></div>
              <div class="metric-help">Usuários atualmente ativos.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-danger"><i class="fa-solid fa-user-clock"></i></div>
            <div>
              <div class="metric-label">Pendentes</div>
              <div class="metric-value"><?= h(number_format((int)($summary['total_pending'] ?? 0), 0, ',', '.')) ?></div>
              <div class="metric-help">Contas aguardando validação.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="row g-4 mb-4">
    <div class="col-12 col-xl-7">
      <div class="section-card card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-3">
            <div>
              <span class="section-badge mb-2"><i class="fa-solid fa-list-check"></i>Regras ativas</span>
              <h2 class="h5 mb-2">O que controla o acesso hoje</h2>
              <p class="section-card-subtitle mb-0">Essas são as condições que o código já aplica no fluxo atual do sistema.</p>
            </div>
          </div>

          <div class="list-group list-group-flush">
            <?php foreach ($permissionRules as $rule): ?>
              <div class="list-group-item px-0 py-3 d-flex gap-3 align-items-start">
                <div class="metric-icon metric-icon-info flex-shrink-0">
                  <i class="fa-solid <?= h($rule['icon']) ?>"></i>
                </div>
                <div class="flex-grow-1">
                  <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                    <div>
                      <h3 class="h6 mb-1"><?= h($rule['name']) ?></h3>
                      <p class="mb-1 text-muted"><?= h($rule['description']) ?></p>
                    </div>
                    <span class="badge <?= $rule['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?> align-self-start"><?= $rule['status'] === 'active' ? 'Ativa' : 'Inativa' ?></span>
                  </div>
                  <small class="text-muted">Fonte: <?= h($rule['source']) ?></small>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-5">
      <div class="section-card card border-0 shadow-sm h-100">
        <div class="card-body">
          <span class="section-badge mb-2"><i class="fa-solid fa-screwdriver-wrench"></i>Flags de conta</span>
          <h2 class="h5 mb-3">Estados que afetam a sessão e a experiência</h2>

          <div class="row g-3">
            <?php foreach ($flagStats as $flag): ?>
              <div class="col-12">
                <div class="metric-card h-100">
                  <div>
                    <div class="metric-label"><?= h($flag['label']) ?></div>
                    <div class="metric-value fs-3"><?= h(number_format($flag['value'], 0, ',', '.')) ?></div>
                    <div class="metric-help"><?= h($flag['help']) ?></div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="alert alert-info mt-3 mb-0">
            Esta página é apenas de referência. A edição de permissões ainda não foi implementada como módulo separado.
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <?php foreach ($roleMatrix as $roleKey => $role): ?>
      <?php $stats = $rolesStats[$roleKey]; ?>
      <div class="col-12 col-lg-6">
        <div class="section-card card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-3">
              <div>
                <span class="section-badge mb-2"><i class="fa-solid fa-user-gear"></i><?= h($role['label']) ?></span>
                <h2 class="h5 mb-1"><?= h($role['label']) ?></h2>
                <p class="section-card-subtitle mb-0"><?= h($role['description']) ?></p>
              </div>
              <span class="badge <?= h($role['badge']) ?> align-self-start"><?= h(number_format($stats['total'], 0, ',', '.')) ?> contas</span>
            </div>

            <div class="table-responsive request-table-wrap">
              <table class="table table-sm align-middle mb-0 request-table js-no-datatable">
                <thead>
                  <tr>
                    <th>Permissão</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($role['permissions'] as $permission): ?>
                    <tr>
                      <td><?= h($permission['label']) ?></td>
                      <td>
                        <?php if ($permission['enabled']): ?>
                          <span class="badge bg-success">Permitida</span>
                        <?php else: ?>
                          <span class="badge bg-secondary">Bloqueada</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="d-flex flex-column gap-2 mt-3 text-muted small">
              <div><i class="fa-solid fa-circle-info me-2 text-primary"></i>Contas aprovadas neste perfil: <?= h(number_format($stats['approved'], 0, ',', '.')) ?></div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="section-card card border-0 shadow-sm mt-4">
    <div class="card-body">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-3">
        <div>
          <span class="section-badge mb-2"><i class="fa-solid fa-user-shield"></i>Últimos administradores</span>
          <h2 class="h5 mb-2">Contas administrativas recentes</h2>
          <p class="section-card-subtitle mb-0">Referência rápida para auditoria e conferência de acesso.</p>
        </div>
      </div>

      <?php if (empty($recentAdmins)): ?>
        <div class="alert alert-info mb-0">Não há administradores cadastrados para exibir.</div>
      <?php else: ?>
        <div class="table-responsive request-table-wrap">
          <table class="table table-hover align-middle mb-0 request-table js-no-datatable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Status</th>
                <th>Senha</th>
                <th>Último login</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentAdmins as $admin): ?>
                <?php
                  $isApproved = (int)($admin['aprovado'] ?? 0) === 1;
                  $mustChange = (int)($admin['must_change_password'] ?? 0) === 1;
                ?>
                <tr>
                  <td><?= (int)$admin['id'] ?></td>
                  <td><?= h((string)$admin['nome']) ?></td>
                  <td><?= h((string)$admin['email']) ?></td>
                  <td>
                    <?php if ($isApproved): ?>
                      <span class="badge bg-success">Aprovada</span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark">Pendente</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($mustChange): ?>
                      <span class="badge bg-warning text-dark">Troca obrigatória</span>
                    <?php else: ?>
                      <span class="badge bg-light text-dark border">Normal</span>
                    <?php endif; ?>
                  </td>
                  <td><?= !empty($admin['last_login_at']) ? h(date('d/m/Y H:i', strtotime((string)$admin['last_login_at']))) : 'Nunca' ?></td>
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