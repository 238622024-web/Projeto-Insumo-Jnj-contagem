<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

$pdo = getPDO();
ensureUserAuthSchema();

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

function ensureSolicitacoesAuditSchema(PDO $pdo): void {
  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS solicitacoes_auditoria (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NULL,
      user_nome VARCHAR(150) NULL,
      user_email VARCHAR(190) NULL,
      user_role VARCHAR(20) NULL,
      acao VARCHAR(40) NOT NULL,
      motivo TEXT NULL,
      executado_por INT NULL,
      executado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_aud_user (user_id),
      INDEX idx_aud_exec (executado_por),
      INDEX idx_aud_data (executado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );
}

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

function logSolicitacaoAudit(PDO $pdo, array $target, string $acao, int $executadoPor, string $motivo = ''): void {
  $ins = $pdo->prepare(
    'INSERT INTO solicitacoes_auditoria (user_id, user_nome, user_email, user_role, acao, motivo, executado_por) VALUES (?, ?, ?, ?, ?, ?, ?)'
  );
  $ins->execute([
    (int)($target['id'] ?? 0),
    (string)($target['nome'] ?? ''),
    (string)($target['email'] ?? ''),
    (string)($target['role'] ?? 'user'),
    $acao,
    $motivo,
    $executadoPor > 0 ? $executadoPor : null,
  ]);
}

function getTempPasswordExpiryHours(): int {
  $hours = (int)getSetting('temp_password_expiry_hours', 24);
  return max(1, min(168, $hours));
}

ensureSolicitacoesAuditSchema($pdo);
ensurePasswordResetRequestsSchema($pdo);
ensureInsumoRequestsSchema($pdo);

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = (string)$_SESSION['csrf_token'];
$current = currentUser();
$adminId = (int)($current['id'] ?? 0);
$primaryAdminId = getPrimaryAdminId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedToken = (string)($_POST['csrf_token'] ?? '');
  if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
    flash('error', 'Sessão inválida. Atualize a página e tente novamente.');
    header('Location: solicitacoes.php');
    exit;
  }

    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    $requestId = (int)($_POST['request_id'] ?? 0);
  $reason = trim((string)($_POST['reason'] ?? ''));
    $tempPassword = trim((string)($_POST['temp_password'] ?? ''));

    if ($userId > 0) {
        if ($action === 'approve') {
    $targetStmt = $pdo->prepare('SELECT id, nome, email, role FROM usuarios WHERE id = ? AND aprovado = 0 LIMIT 1');
        $targetStmt->execute([$userId]);
        $target = $targetStmt->fetch();

        if (!$target) {
          flash('error', 'Solicitação não encontrada ou já processada.');
          header('Location: solicitacoes.php');
          exit;
        }

        if (($target['role'] ?? 'user') === 'admin' && $adminId !== $primaryAdminId) {
          flash('error', 'Apenas o administrador principal pode aprovar novas contas de administrador.');
          header('Location: solicitacoes.php');
          exit;
        }

            $stmt = $pdo->prepare("UPDATE usuarios SET aprovado = 1, aprovado_em = NOW(), aprovado_por = ? WHERE id = ? AND aprovado = 0");
            $stmt->execute([$adminId, $userId]);
            if ($stmt->rowCount() > 0) {
            logSolicitacaoAudit($pdo, $target, 'approve_request', $adminId);
                flash('success', 'Solicitação aprovada com sucesso.');
            } else {
                flash('error', 'Não foi possível aprovar esta solicitação.');
            }
        } elseif ($action === 'reject') {
        if ($reason === '') {
          flash('error', 'Informe o motivo da rejeição.');
          header('Location: solicitacoes.php');
          exit;
        }

        $targetStmt = $pdo->prepare('SELECT id, nome, email, role FROM usuarios WHERE id = ? AND aprovado = 0 LIMIT 1');
        $targetStmt->execute([$userId]);
        $target = $targetStmt->fetch();

        if (!$target) {
          flash('error', 'Solicitação não encontrada ou já processada.');
          header('Location: solicitacoes.php');
          exit;
        }

        if (($target['role'] ?? 'user') === 'admin' && $adminId !== $primaryAdminId) {
          flash('error', 'Apenas o administrador principal pode rejeitar solicitações de administrador.');
          header('Location: solicitacoes.php');
          exit;
        }

        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND aprovado = 0");
            $stmt->execute([$userId]);
            if ($stmt->rowCount() > 0) {
        logSolicitacaoAudit($pdo, $target, 'reject_request', $adminId, $reason);
                flash('success', 'Solicitação rejeitada e removida.');
            } else {
                flash('error', 'Não foi possível rejeitar esta solicitação.');
            }
        } elseif ($action === 'delete_account') {
        $targetStmt = $pdo->prepare('SELECT id, role, aprovado, nome, email FROM usuarios WHERE id = ? LIMIT 1');
        $targetStmt->execute([$userId]);
        $target = $targetStmt->fetch();

        if (!$target || (int)($target['aprovado'] ?? 0) !== 1) {
          flash('error', 'Conta não encontrada ou ainda não aprovada.');
          header('Location: solicitacoes.php');
          exit;
        }

        if ($userId === $adminId) {
          flash('error', 'Você não pode apagar sua própria conta por esta tela.');
          header('Location: solicitacoes.php');
          exit;
        }

        if ($userId === $primaryAdminId) {
          flash('error', 'A conta do administrador principal não pode ser apagada.');
          header('Location: solicitacoes.php');
          exit;
        }

        if (($target['role'] ?? 'user') === 'admin' && $adminId !== $primaryAdminId) {
          flash('error', 'Apenas o administrador principal pode apagar contas de administrador.');
          header('Location: solicitacoes.php');
          exit;
        }

        if ($reason === '') {
          flash('error', 'Informe o motivo para apagar a conta.');
          header('Location: solicitacoes.php');
          exit;
        }

        if (($target['role'] ?? 'user') === 'admin') {
          $adminCountStmt = $pdo->query("SELECT COUNT(*) AS c FROM usuarios WHERE role = 'admin' AND aprovado = 1");
          $adminCount = (int)($adminCountStmt->fetch()['c'] ?? 0);
          if ($adminCount <= 1) {
            flash('error', 'Não é possível apagar o último administrador aprovado do sistema.');
            header('Location: solicitacoes.php');
            exit;
          }
        }

        $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = ? AND aprovado = 1');
        $stmt->execute([$userId]);
        if ($stmt->rowCount() > 0) {
          logSolicitacaoAudit($pdo, $target, 'delete_account', $adminId, $reason);
          flash('success', 'Conta apagada com sucesso.');
        } else {
          flash('error', 'Não foi possível apagar esta conta.');
        }
        }
    } elseif ($requestId > 0) {
      if ($action === 'reset_approve') {
        if ($tempPassword === '' || strlen($tempPassword) < 6) {
          flash('error', 'A senha temporária deve ter pelo menos 6 caracteres.');
          header('Location: solicitacoes.php');
          exit;
        }

        $reqStmt = $pdo->prepare(
          "SELECT pr.id, pr.user_id, pr.email, pr.status, pr.motivo_usuario, u.nome, u.role, u.aprovado
           FROM password_reset_requests pr
           LEFT JOIN usuarios u ON u.id = pr.user_id
           WHERE pr.id = ? AND pr.status = 'pending' LIMIT 1"
        );
        $reqStmt->execute([$requestId]);
        $req = $reqStmt->fetch();

        if (!$req) {
          flash('error', 'Solicitação de redefinição não encontrada ou já processada.');
          header('Location: solicitacoes.php');
          exit;
        }

        if ((int)($req['user_id'] ?? 0) <= 0 || (int)($req['aprovado'] ?? 0) !== 1) {
          flash('error', 'Usuário desta solicitação não está ativo/aprovado.');
          header('Location: solicitacoes.php');
          exit;
        }

        if (($req['role'] ?? 'user') === 'admin' && $adminId !== $primaryAdminId) {
          flash('error', 'Apenas o administrador principal pode redefinir senha de contas admin.');
          header('Location: solicitacoes.php');
          exit;
        }

        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

        $expiryHours = getTempPasswordExpiryHours();
        $expiryAt = date('Y-m-d H:i:s', time() + ($expiryHours * 3600));

        $pdo->beginTransaction();
        try {
          $upUser = $pdo->prepare('UPDATE usuarios SET senha_hash = ?, must_change_password = 1, temp_password_expires_at = ? WHERE id = ? AND aprovado = 1');
          $upUser->execute([$hash, $expiryAt, (int)$req['user_id']]);

          $upReq = $pdo->prepare("UPDATE password_reset_requests SET status = 'completed', admin_note = ?, processed_at = NOW(), processed_by = ? WHERE id = ? AND status = 'pending'");
          $upReq->execute([$reason, $adminId, $requestId]);

          $pdo->commit();

          logSolicitacaoAudit($pdo, [
            'id' => (int)$req['user_id'],
            'nome' => (string)($req['nome'] ?? ''),
            'email' => (string)($req['email'] ?? ''),
            'role' => (string)($req['role'] ?? 'user'),
          ], 'password_reset_completed', $adminId, $reason !== '' ? $reason : 'Senha temporária definida pelo administrador.');

          flash('success', 'Senha redefinida com sucesso. Passe a senha temporária ao usuário (validade: ' . $expiryHours . 'h).');
        } catch (Throwable $e) {
          if ($pdo->inTransaction()) {
            $pdo->rollBack();
          }
          flash('error', 'Não foi possível concluir a redefinição de senha.');
        }
      } elseif ($action === 'reset_reject') {
        if ($reason === '') {
          flash('error', 'Informe o motivo da rejeição da solicitação de senha.');
          header('Location: solicitacoes.php');
          exit;
        }

        $reqStmt = $pdo->prepare(
          "SELECT pr.id, pr.user_id, pr.email, pr.status, u.nome, u.role
           FROM password_reset_requests pr
           LEFT JOIN usuarios u ON u.id = pr.user_id
           WHERE pr.id = ? AND pr.status = 'pending' LIMIT 1"
        );
        $reqStmt->execute([$requestId]);
        $req = $reqStmt->fetch();

        if (!$req) {
          flash('error', 'Solicitação de redefinição não encontrada ou já processada.');
          header('Location: solicitacoes.php');
          exit;
        }

        if (($req['role'] ?? 'user') === 'admin' && $adminId !== $primaryAdminId) {
          flash('error', 'Apenas o administrador principal pode rejeitar pedido de redefinição para admin.');
          header('Location: solicitacoes.php');
          exit;
        }

        $upReq = $pdo->prepare("UPDATE password_reset_requests SET status = 'rejected', admin_note = ?, processed_at = NOW(), processed_by = ? WHERE id = ? AND status = 'pending'");
        $upReq->execute([$reason, $adminId, $requestId]);

        if ($upReq->rowCount() > 0) {
          logSolicitacaoAudit($pdo, [
            'id' => (int)($req['user_id'] ?? 0),
            'nome' => (string)($req['nome'] ?? ''),
            'email' => (string)($req['email'] ?? ''),
            'role' => (string)($req['role'] ?? 'user'),
          ], 'password_reset_rejected', $adminId, $reason);

          flash('success', 'Solicitação de redefinição rejeitada.');
        } else {
          flash('error', 'Não foi possível rejeitar esta solicitação de senha.');
        }
      }
    }

    header('Location: solicitacoes.php');
    exit;
}

  $q = trim((string)($_GET['q'] ?? ''));
  $roleFilter = (string)($_GET['role'] ?? 'all');
  if (!in_array($roleFilter, ['all', 'admin', 'user'], true)) {
    $roleFilter = 'all';
  }

  $pendingPage = max(1, (int)($_GET['ppage'] ?? 1));
  $approvedPage = max(1, (int)($_GET['apage'] ?? 1));
  $perPage = 10;
  $approvedPerPage = 6;

  $pendingWhere = ['aprovado = 0'];
  $pendingParams = [];
  $approvedWhere = ['aprovado = 1'];
  $approvedParams = [];

  if ($q !== '') {
    $pendingWhere[] = '(nome LIKE ? OR email LIKE ?)';
    $pendingParams[] = '%' . $q . '%';
    $pendingParams[] = '%' . $q . '%';

    $approvedWhere[] = '(nome LIKE ? OR email LIKE ?)';
    $approvedParams[] = '%' . $q . '%';
    $approvedParams[] = '%' . $q . '%';
  }

  if ($roleFilter !== 'all') {
    $pendingWhere[] = 'role = ?';
    $pendingParams[] = $roleFilter;

    $approvedWhere[] = 'role = ?';
    $approvedParams[] = $roleFilter;
  }

  $pendingWhereSql = implode(' AND ', $pendingWhere);
  $approvedWhereSql = implode(' AND ', $approvedWhere);

  $pendingCountStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM usuarios WHERE ' . $pendingWhereSql);
  $pendingCountStmt->execute($pendingParams);
  $pendingTotal = (int)($pendingCountStmt->fetch()['c'] ?? 0);
  $pendingPages = max(1, (int)ceil($pendingTotal / $perPage));
  $pendingPage = min($pendingPage, $pendingPages);
  $pendingOffset = ($pendingPage - 1) * $perPage;

  $pendingSql = 'SELECT id, nome, email, role, criado_em FROM usuarios WHERE ' . $pendingWhereSql . ' ORDER BY criado_em ASC, id ASC LIMIT ? OFFSET ?';
  $pendingStmt = $pdo->prepare($pendingSql);
  $pendingExecParams = array_merge($pendingParams, [$perPage, $pendingOffset]);
  $pendingStmt->execute($pendingExecParams);
  $pendingUsers = $pendingStmt->fetchAll();

  $approvedCountStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM usuarios WHERE ' . $approvedWhereSql);
  $approvedCountStmt->execute($approvedParams);
  $approvedTotal = (int)($approvedCountStmt->fetch()['c'] ?? 0);
  $approvedPages = max(1, (int)ceil($approvedTotal / $approvedPerPage));
  $approvedPage = min($approvedPage, $approvedPages);
  $approvedOffset = ($approvedPage - 1) * $approvedPerPage;

  $approvedSql = 'SELECT id, nome, email, role, aprovado_em FROM usuarios WHERE ' . $approvedWhereSql . ' ORDER BY role DESC, nome ASC LIMIT ? OFFSET ?';
  $approvedStmt = $pdo->prepare($approvedSql);
  $approvedExecParams = array_merge($approvedParams, [$approvedPerPage, $approvedOffset]);
  $approvedStmt->execute($approvedExecParams);
  $approvedUsers = $approvedStmt->fetchAll();
  $approvedShowingStart = $approvedTotal > 0 ? (($approvedPage - 1) * $approvedPerPage) + 1 : 0;
  $approvedShowingEnd = min($approvedTotal, $approvedPage * $approvedPerPage);

  $resetRequestsStmt = $pdo->query(
    "SELECT
      pr.id,
      pr.user_id,
      pr.email,
      pr.motivo_usuario,
      pr.requested_at,
      u.nome,
      u.role,
      u.aprovado
    FROM password_reset_requests pr
    LEFT JOIN usuarios u ON u.id = pr.user_id
    WHERE pr.status = 'pending'
    ORDER BY pr.requested_at ASC, pr.id ASC"
  );
  $pendingResetRequests = $resetRequestsStmt->fetchAll();
  $pendingResetCount = count($pendingResetRequests);
  $pendingCadastroCount = count($pendingUsers);

  $buildQuery = static function (array $overrides = []) use ($q, $roleFilter, $pendingPage, $approvedPage): string {
    $base = [
      'q' => $q,
      'role' => $roleFilter,
      'ppage' => $pendingPage,
      'apage' => $approvedPage,
    ];
    $params = array_merge($base, $overrides);
    foreach ($params as $k => $v) {
      if ($v === '' || $v === null) {
        unset($params[$k]);
      }
    }
    return 'solicitacoes.php' . (!empty($params) ? '?' . http_build_query($params) : '');
  };

$contagemCols = $pdo->query('SHOW COLUMNS FROM insumos_jnj')->fetchAll();
$hasContagemTracking = false;
foreach ($contagemCols as $col) {
    if (($col['Field'] ?? '') === 'contagem_por_nome') {
        $hasContagemTracking = true;
        break;
    }
}

$quemConta = [];
if ($hasContagemTracking) {
    $sqlQuemConta = "
      SELECT
        COALESCE(NULLIF(contagem_por_nome, ''), 'Usuario') AS contador,
        MAX(contagem_em) AS ultima_contagem,
        SUM(CASE WHEN DATE(contagem_em) = CURDATE() THEN 1 ELSE 0 END) AS contagens_hoje,
        COUNT(*) AS total_registros
      FROM insumos_jnj
      WHERE contagem_em IS NOT NULL
      GROUP BY contagem_por_id, contagem_por_nome
      ORDER BY ultima_contagem DESC
      LIMIT 20
    ";
    $quemConta = $pdo->query($sqlQuemConta)->fetchAll();
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="solicitacoes-kicker">Administração central</span>
          <h1 class="display-6 fw-semibold mb-2">Administração</h1>
          <p class="solicitacoes-subtitle mb-0">Centralize aprovações, redefinições de senha e contas já liberadas em uma única visão.</p>
        </div>
        <div class="text-lg-end">
          <div class="solicitacoes-pill">Atualizado com ações seguras e rastreáveis</div>
          <small class="text-muted d-block mt-2">Use os filtros para priorizar contas por nome, e-mail ou perfil.</small>
        </div>
      </div>

      <div class="row g-3 mt-4">
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-user-clock"></i></div>
            <div>
              <div class="metric-label">Cadastro pendente</div>
              <div class="metric-value"><?= h(number_format($pendingCadastroCount, 0, ',', '.')) ?></div>
              <div class="metric-help">Aguardando aprovação do time administrativo.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-info"><i class="fa-solid fa-key"></i></div>
            <div>
              <div class="metric-label">Redefinições pendentes</div>
              <div class="metric-value"><?= h(number_format($pendingResetCount, 0, ',', '.')) ?></div>
              <div class="metric-help">Pedidos que exigem senha temporária ou recusa motivada.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-success"><i class="fa-solid fa-user-check"></i></div>
            <div>
              <div class="metric-label">Contas aprovadas</div>
              <div class="metric-value"><?= h(number_format($approvedTotal, 0, ',', '.')) ?></div>
              <div class="metric-help">Base ativa disponível para manutenção e auditoria.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

<div class="section-card card border-0 shadow-sm mb-4">
  <div class="section-card-header card-header bg-white border-0 pt-3 pb-0">
    <h2 class="h5 mb-3"><i class="fa-solid fa-key me-2 text-primary"></i>Solicitações de redefinição de senha</h2>
  </div>
  <div class="card-body pt-0">
    <?php if (empty($pendingResetRequests)): ?>
      <div class="alert alert-info mb-0">Não há solicitações de redefinição de senha pendentes.</div>
    <?php else: ?>
      <div class="table-responsive request-table-wrap">
        <table class="table table-hover align-middle mb-0 request-table">
          <thead>
            <tr>
              <th>ID Req.</th>
              <th>Usuário</th>
              <th>E-mail</th>
              <th>Perfil</th>
              <th>Motivo informado</th>
              <th>Solicitado em</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($pendingResetRequests as $r): ?>
            <?php
              $isAdminReset = (($r['role'] ?? 'user') === 'admin');
              $resetBlocked = $isAdminReset && ($adminId !== $primaryAdminId);
              $resetTitle = $resetBlocked ? 'Apenas o administrador principal pode processar redefinição de senha para admins' : '';
            ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= h((string)($r['nome'] ?? 'Usuário não encontrado')) ?></td>
              <td><?= h((string)$r['email']) ?></td>
              <td>
                <?php if ($isAdminReset): ?>
                  <span class="badge bg-warning text-dark">Administrador</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Conta normal</span>
                <?php endif; ?>
              </td>
              <td><?= h((string)($r['motivo_usuario'] ?? '-')) ?></td>
              <td><?= !empty($r['requested_at']) ? h(date('d/m/Y H:i', strtotime((string)$r['requested_at']))) : '-' ?></td>
              <td class="text-end">
                <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                  <form method="post" class="m-0" onsubmit="return requestTempPassword(this);">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="reset_approve">
                    <input type="hidden" name="temp_password" value="">
                    <input type="hidden" name="reason" value="">
                    <button type="submit" class="btn btn-sm btn-success" <?= $resetBlocked ? 'disabled' : '' ?> <?= $resetTitle !== '' ? 'title="'.h($resetTitle).'"' : '' ?>><i class="fa-solid fa-key me-1"></i>Definir senha temporária</button>
                  </form>
                  <form method="post" class="m-0" onsubmit="return requestActionReason(this, 'rejeitar esta solicitação de redefinição');">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="reset_reject">
                    <input type="hidden" name="reason" value="">
                    <button type="submit" class="btn btn-sm btn-outline-danger" <?= $resetBlocked ? 'disabled' : '' ?> <?= $resetTitle !== '' ? 'title="'.h($resetTitle).'"' : '' ?>><i class="fa-solid fa-xmark me-1"></i>Rejeitar</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<div class="section-card card border-0 shadow-sm mb-4">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end solicitacoes-filter">
      <div class="col-12 col-md-5">
        <label class="form-label small text-muted mb-1">Buscar por nome ou e-mail</label>
        <input type="text" class="form-control" name="q" value="<?= h($q) ?>" placeholder="Ex.: joao@empresa.com">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label small text-muted mb-1">Perfil</label>
        <select class="form-select" name="role">
          <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>Todos</option>
          <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Administrador</option>
          <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>Conta normal</option>
        </select>
      </div>
      <div class="col-12 col-md-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
        <a href="solicitacoes.php" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i>Limpar</a>
      </div>
    </form>
  </div>
</div>

<div class="section-card card border-0 shadow-sm mb-4">
  <div class="section-card-header card-header bg-white border-0 pt-3 pb-0">
    <h2 class="h5 mb-3"><i class="fa fa-users me-2 text-primary"></i>Quem está na contagem</h2>
  </div>
  <div class="card-body">
    <?php if (!$hasContagemTracking): ?>
      <div class="alert alert-info mb-0">A área de rastreio de contagem será exibida após a primeira contagem no novo formato.</div>
    <?php elseif (empty($quemConta)): ?>
      <div class="text-muted">Nenhuma contagem registrada com usuário ainda.</div>
    <?php else: ?>
      <div class="table-responsive request-table-wrap">
        <table class="table table-sm align-middle mb-0 request-table">
          <thead>
            <tr>
              <th>Colaborador</th>
              <th>Última contagem</th>
              <th>Contagens hoje</th>
              <th>Total de registros</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($quemConta as $q): ?>
            <tr>
              <td><strong><?= h((string)$q['contador']) ?></strong></td>
              <td><?= !empty($q['ultima_contagem']) ? h(date('d/m/Y H:i', strtotime((string)$q['ultima_contagem']))) : '-' ?></td>
              <td><?= h(number_format((int)$q['contagens_hoje'], 0, ',', '.')) ?></td>
              <td><?= h(number_format((int)$q['total_registros'], 0, ',', '.')) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if (empty($pendingUsers)): ?>
  <div class="alert alert-info">Não há solicitações pendentes no momento.</div>
<?php else: ?>
  <div class="table-responsive request-table-wrap">
    <table class="table table-hover align-middle request-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nome</th>
          <th>E-mail</th>
          <th>Tipo solicitado</th>
          <th>Solicitado em</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($pendingUsers as $u): ?>
        <?php
          $isAdminRequest = (($u['role'] ?? 'user') === 'admin');
          $adminRequestBlocked = $isAdminRequest && ($adminId !== $primaryAdminId);
        ?>
        <tr>
          <td><?= (int)$u['id'] ?></td>
          <td><?= h($u['nome']) ?></td>
          <td><?= h($u['email']) ?></td>
          <td>
            <?php if ($isAdminRequest): ?>
              <span class="badge bg-warning text-dark">Administrador</span>
            <?php else: ?>
              <span class="badge bg-secondary">Conta normal</span>
            <?php endif; ?>
          </td>
          <td>
            <?php
              $criadoRaw = (string)($u['criado_em'] ?? '');
              $criadoFmt = $criadoRaw !== '' ? date('d/m/Y H:i', strtotime($criadoRaw)) : '-';
              $isAtrasada = $criadoRaw !== '' && (time() - strtotime($criadoRaw)) > (48 * 3600);
            ?>
            <?= h($criadoFmt) ?>
            <?php if ($isAtrasada): ?>
              <span class="badge bg-danger ms-1">+48h</span>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
              <form method="post" class="m-0" onsubmit="return confirm('Deseja aprovar esta solicitação?');">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn btn-sm btn-success" <?= $adminRequestBlocked ? 'disabled title="Apenas o administrador principal pode aprovar este pedido"' : '' ?>>Aprovar</button>
              </form>
              <form method="post" class="m-0" onsubmit="return requestActionReason(this, 'rejeitar esta solicitação');">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="reason" value="">
                <button type="submit" class="btn btn-sm btn-outline-danger" <?= $adminRequestBlocked ? 'disabled title="Apenas o administrador principal pode rejeitar este pedido"' : '' ?>>Rejeitar</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pendingPages > 1): ?>
    <nav class="mt-3" aria-label="Paginação de solicitações pendentes">
      <ul class="pagination pagination-sm mb-0">
        <?php for ($p = 1; $p <= $pendingPages; $p++): ?>
          <li class="page-item <?= $p === $pendingPage ? 'active' : '' ?>">
            <a class="page-link" href="<?= h($buildQuery(['ppage' => $p])) ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
<?php endif; ?>

<div class="section-card card border-0 shadow-sm mt-4 approved-accounts-card">
  <div class="section-card-header card-header bg-white border-0 pt-3 pb-0 approved-accounts-header">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
      <div>
        <span class="section-badge mb-2"><i class="fa-solid fa-user-shield"></i>Base ativa</span>
        <h2 class="h5 mb-2"><i class="fa-solid fa-user-check me-2 text-primary"></i>Contas aprovadas</h2>
        <p class="section-card-subtitle mb-0">Acompanhe quem já está liberado no sistema, com foco em manutenção, auditoria e controle de acesso.</p>
      </div>
      <div class="approved-summary">
        <div class="approved-summary-item">
          <span>Total aprovado</span>
          <strong><?= h(number_format($approvedTotal, 0, ',', '.')) ?></strong>
        </div>
        <div class="approved-summary-item">
          <span>Exibindo</span>
          <strong><?= $approvedTotal > 0 ? h(number_format($approvedShowingStart, 0, ',', '.')) . ' - ' . h(number_format($approvedShowingEnd, 0, ',', '.')) : '0' ?></strong>
        </div>
      </div>
    </div>
  </div>
  <div class="card-body pt-0">
    <?php if (empty($approvedUsers)): ?>
      <div class="alert alert-info mb-0">Não há contas aprovadas.</div>
    <?php else: ?>
      <div class="approved-table-toolbar d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div class="approved-table-note">
          <i class="fa-solid fa-wand-magic-sparkles me-1 text-primary"></i>
          Visual limpo, paginação compacta e leitura rápida para a equipe administrativa.
        </div>
        <div class="approved-table-chip">
          <i class="fa-solid fa-layer-group"></i>
          <?= h(number_format($approvedPerPage, 0, ',', '.')) ?> por página
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
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($approvedUsers as $u): ?>
            <?php
              $isPrimaryAdminRow = ((int)$u['id'] === $primaryAdminId);
              $isCurrentUserRow = ((int)$u['id'] === $adminId);
              $isAdminRow = (($u['role'] ?? 'user') === 'admin');
              $deleteBlocked = $isPrimaryAdminRow || $isCurrentUserRow || ($isAdminRow && $adminId !== $primaryAdminId);
              $deleteTitle = '';
              if ($isPrimaryAdminRow) $deleteTitle = 'Administrador principal não pode ser apagado';
              elseif ($isCurrentUserRow) $deleteTitle = 'Você não pode apagar sua própria conta';
              elseif ($isAdminRow && $adminId !== $primaryAdminId) $deleteTitle = 'Apenas o administrador principal pode apagar contas admin';
            ?>
            <tr>
              <td><?= (int)$u['id'] ?></td>
              <td>
                <?= h((string)$u['nome']) ?>
                <?php if ($isPrimaryAdminRow): ?>
                  <span class="badge bg-primary ms-1 approved-row-badge">Principal</span>
                <?php endif; ?>
                <?php if ($isCurrentUserRow): ?>
                  <span class="badge bg-light text-dark border ms-1 approved-row-badge">Você</span>
                <?php endif; ?>
              </td>
              <td><?= h((string)$u['email']) ?></td>
              <td>
                <?php if ($isAdminRow): ?>
                  <span class="badge bg-warning text-dark">Administrador</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Conta normal</span>
                <?php endif; ?>
              </td>
              <td><?= !empty($u['aprovado_em']) ? h(date('d/m/Y H:i', strtotime((string)$u['aprovado_em']))) : '-' ?></td>
              <td class="text-end">
                <form method="post" class="m-0 d-inline" onsubmit="return requestActionReason(this, 'apagar esta conta de usuário');">
                  <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <input type="hidden" name="action" value="delete_account">
                  <input type="hidden" name="reason" value="">
                  <button type="submit" class="btn btn-sm btn-outline-danger" <?= $deleteBlocked ? 'disabled' : '' ?> <?= $deleteTitle !== '' ? 'title="'.h($deleteTitle).'"' : '' ?>><i class="fa-solid fa-user-xmark me-1"></i>Apagar conta</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($approvedPages > 1): ?>
        <nav class="mt-3" aria-label="Paginação de contas aprovadas">
          <ul class="pagination pagination-sm mb-0">
            <?php for ($p = 1; $p <= $approvedPages; $p++): ?>
              <li class="page-item <?= $p === $approvedPage ? 'active' : '' ?>">
                <a class="page-link" href="<?= h($buildQuery(['apage' => $p])) ?>"><?= $p ?></a>
              </li>
            <?php endfor; ?>
          </ul>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
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

function requestTempPassword(form) {
  var tempPassword = window.prompt('Digite a senha temporária (mínimo 6 caracteres):', '');
  if (tempPassword === null) {
    return false;
  }
  tempPassword = tempPassword.trim();
  if (tempPassword.length < 6) {
    alert('A senha temporária deve ter pelo menos 6 caracteres.');
    return false;
  }

  var note = window.prompt('Observação para auditoria (opcional):', '');
  if (note === null) {
    note = '';
  }

  var passInput = form.querySelector('input[name="temp_password"]');
  var reasonInput = form.querySelector('input[name="reason"]');
  if (passInput) {
    passInput.value = tempPassword;
  }
  if (reasonInput) {
    reasonInput.value = note.trim();
  }
  return true;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
