<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireAdmin();

$pdo = getPDO();
ensureUserAuthSchema();
$current = currentUser();
$adminId = (int)($current['id'] ?? 0);
$primaryAdminId = getPrimaryAdminId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($userId > 0) {
        if ($action === 'approve') {
        $targetStmt = $pdo->prepare('SELECT id, role FROM usuarios WHERE id = ? AND aprovado = 0 LIMIT 1');
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
                flash('success', 'Solicitação aprovada com sucesso.');
            } else {
                flash('error', 'Não foi possível aprovar esta solicitação.');
            }
        } elseif ($action === 'reject') {
        $targetStmt = $pdo->prepare('SELECT id, role FROM usuarios WHERE id = ? AND aprovado = 0 LIMIT 1');
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
                flash('success', 'Solicitação rejeitada e removida.');
            } else {
                flash('error', 'Não foi possível rejeitar esta solicitação.');
            }
        }
    }

    header('Location: solicitacoes.php');
    exit;
}

$pendingStmt = $pdo->query("SELECT id, nome, email, role, criado_em FROM usuarios WHERE aprovado = 0 ORDER BY criado_em ASC, id ASC");
$pendingUsers = $pendingStmt->fetchAll();

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

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Solicitações de cadastro</h1>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white border-0 pt-3 pb-0">
    <h2 class="h5 mb-3"><i class="fa fa-users me-2 text-primary"></i>Quem está na contagem</h2>
  </div>
  <div class="card-body">
    <?php if (!$hasContagemTracking): ?>
      <div class="alert alert-info mb-0">A área de rastreio de contagem será exibida após a primeira contagem no novo formato.</div>
    <?php elseif (empty($quemConta)): ?>
      <div class="text-muted">Nenhuma contagem registrada com usuário ainda.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
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
  <div class="table-responsive">
    <table class="table table-hover align-middle">
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
          <td><?= h($u['criado_em'] ?? '-') ?></td>
          <td class="text-end">
            <div class="d-inline-flex gap-2">
              <form method="post" class="m-0">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn btn-sm btn-success" <?= $adminRequestBlocked ? 'disabled title="Apenas o administrador principal pode aprovar este pedido"' : '' ?>>Aprovar</button>
              </form>
              <form method="post" class="m-0" onsubmit="return confirm('Deseja rejeitar esta solicitação?');">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <button type="submit" class="btn btn-sm btn-outline-danger" <?= $adminRequestBlocked ? 'disabled title="Apenas o administrador principal pode rejeitar este pedido"' : '' ?>>Rejeitar</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
