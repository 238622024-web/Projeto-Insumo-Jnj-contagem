<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pdo = getPDO();
ensureUserAuthSchema();

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION['csrf_token'];

$userStmt = $pdo->prepare('SELECT id,nome,email,avatar,senha_hash,role,aprovado,must_change_password,temp_password_expires_at,last_login_at,last_login_ip,criado_em FROM usuarios WHERE id = ? LIMIT 1');
$userStmt->execute([$_SESSION['usuario_id']]);
$me = $userStmt->fetch();

if (!$me) {
  flash('error', 'Não foi possível carregar seu perfil.');
  header('Location: logout.php');
  exit;
}

$activityStmt = $pdo->prepare('SELECT acao,titulo,detalhes,ip_address,created_at FROM usuario_atividades WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 8');
$activityStmt->execute([(int)$me['id']]);
$recentActivities = $activityStmt->fetchAll();

function profileFormatDate(?string $value, string $format = 'd/m/Y H:i'): string {
  $value = trim((string)$value);
  if ($value === '') {
    return '-';
  }
  $timestamp = strtotime($value);
  return $timestamp !== false ? date($format, $timestamp) : '-';
}

function profilePasswordState(array $me): array {
  $mustChange = (int)($me['must_change_password'] ?? 0) === 1;
  $expiresAt = trim((string)($me['temp_password_expires_at'] ?? ''));

  if ($mustChange && $expiresAt !== '') {
    $timestamp = strtotime($expiresAt);
    if ($timestamp !== false) {
      $hoursLeft = (int)ceil(($timestamp - time()) / 3600);
      if ($hoursLeft <= 0) {
        return ['danger', 'Senha temporária expirada', 'A senha temporária venceu. Solicite uma nova redefinição.'];
      }
      if ($hoursLeft <= 24) {
        return ['warning', 'Senha temporária quase vencendo', 'Troque a senha temporária nas próximas horas.'];
      }
      return ['info', 'Senha temporária ativa', 'Você precisa definir uma nova senha.'];
    }
  }

  if ($mustChange) {
    return ['warning', 'Senha temporária ativa', 'Troque sua senha para continuar usando o sistema.'];
  }

  return ['success', 'Senha normal', 'Conta sem exigência de troca imediata.'];
}

function profileActivityMeta(string $action): array {
  $map = [
    'login' => ['Novo login', 'fa-right-to-bracket', 'text-primary'],
    'profile_update' => ['Perfil atualizado', 'fa-user-pen', 'text-success'],
    'password_change' => ['Senha alterada', 'fa-key', 'text-warning'],
    'avatar_update' => ['Avatar atualizado', 'fa-image', 'text-info'],
    'avatar_remove' => ['Avatar removido', 'fa-trash-can', 'text-danger'],
  ];

  return $map[$action] ?? ['Atividade', 'fa-clock-rotate-left', 'text-muted'];
}

$tabToActivate = 'dados';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedToken = (string)($_POST['csrf_token'] ?? '');
  if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
    flash('error', 'Sessão inválida. Atualize a página e tente novamente.');
    header('Location: perfil.php');
    exit;
  }

  $profileAction = (string)($_POST['profile_action'] ?? '');
  $errors = [];

  if ($profileAction === 'details') {
    $tabToActivate = 'dados';
    $nome = trim((string)($_POST['nome'] ?? ''));
    if ($nome === '') {
      $errors[] = 'Nome obrigatório.';
    }

    if (!$errors) {
      $stmt = $pdo->prepare('UPDATE usuarios SET nome = ? WHERE id = ?');
      $stmt->execute([$nome, (int)$me['id']]);
      $_SESSION['usuario_nome'] = $nome;
      logUserActivity((int)$me['id'], 'profile_update', 'Dados pessoais atualizados', 'Nome atualizado para ' . $nome . '.');
      flash('success', 'Seus dados pessoais foram atualizados.');
      header('Location: perfil.php#dados');
      exit;
    }
  }

  if ($profileAction === 'security') {
    $tabToActivate = 'seguranca';
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $senha = (string)($_POST['password'] ?? '');
    $senhaConfirm = (string)($_POST['password_confirm'] ?? '');

    if ($currentPassword === '') {
      $errors[] = 'Informe a senha atual para definir uma nova senha.';
    } elseif (!password_verify($currentPassword, (string)($me['senha_hash'] ?? ''))) {
      $errors[] = 'Senha atual incorreta.';
    }

    if (strlen($senha) < 8) {
      $errors[] = 'A nova senha deve ter ao menos 8 caracteres.';
    }
    if ($senha !== $senhaConfirm) {
      $errors[] = 'A confirmação da nova senha não confere.';
    }

    if (!$errors) {
      $hash = password_hash($senha, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare('UPDATE usuarios SET senha_hash = ?, must_change_password = 0, temp_password_expires_at = NULL WHERE id = ?');
      $stmt->execute([$hash, (int)$me['id']]);
      $_SESSION['usuario_must_change_password'] = 0;
      logUserActivity((int)$me['id'], 'password_change', 'Senha alterada', 'Senha atualizada com sucesso.');
      flash('success', 'Sua senha foi alterada com sucesso.');
      header('Location: perfil.php#seguranca');
      exit;
    }
  }

  if ($profileAction === 'avatar') {
    $tabToActivate = 'avatar';
    $removeAvatar = isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1';
    $avatarFilename = (string)($me['avatar'] ?? '');
    $newAvatarUploaded = false;

    if (!empty($_FILES['avatar']['name'])) {
      $f = $_FILES['avatar'];
      if ($f['error'] === UPLOAD_ERR_OK) {
        $allowedMimeToExt = [
          'image/png' => 'png',
          'image/jpeg' => 'jpg',
          'image/webp' => 'webp',
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($f['tmp_name']);
        $ext = $allowedMimeToExt[$mime] ?? null;

        if ($ext !== null && $f['size'] <= 2 * 1024 * 1024) {
          $uploadsDir = __DIR__ . '/assets/uploads';
          if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
          }
          $avatarFilename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
          if (move_uploaded_file($f['tmp_name'], $uploadsDir . '/' . $avatarFilename)) {
            $newAvatarUploaded = true;
          } else {
            $errors[] = 'Não foi possível salvar o avatar enviado.';
          }
        } else {
          $errors[] = 'Arquivo inválido. Use PNG, JPG ou WEBP e menor que 2MB.';
        }
      } else {
        $errors[] = 'Não foi possível processar o arquivo enviado.';
      }
    } elseif (!$removeAvatar) {
      $errors[] = 'Selecione uma imagem ou marque para remover o avatar.';
    }

    if (!$errors) {
      $oldAvatarFilename = (string)($me['avatar'] ?? '');
      $newAvatarValue = $removeAvatar && !$newAvatarUploaded ? null : $avatarFilename;
      $stmt = $pdo->prepare('UPDATE usuarios SET avatar = ? WHERE id = ?');
      $stmt->execute([$newAvatarValue, (int)$me['id']]);

      if ($oldAvatarFilename !== '' && $oldAvatarFilename !== $newAvatarValue) {
        $oldPath = __DIR__ . '/assets/uploads/' . basename($oldAvatarFilename);
        if (is_file($oldPath)) {
          @unlink($oldPath);
        }
      }

      if ($removeAvatar && !$newAvatarUploaded) {
        logUserActivity((int)$me['id'], 'avatar_remove', 'Avatar removido', 'Avatar atual removido do perfil.');
        flash('success', 'Avatar removido com sucesso.');
      } elseif ($newAvatarUploaded) {
        logUserActivity((int)$me['id'], 'avatar_update', 'Avatar atualizado', 'Novo avatar carregado no perfil.');
        flash('success', 'Avatar atualizado com sucesso.');
      }

      header('Location: perfil.php#avatar');
      exit;
    }
  }

  if ($errors) {
    flash('error', implode(' ', $errors));
  }
}

$passwordState = profilePasswordState($me);
$accountStatus = (int)($me['aprovado'] ?? 0) === 1 ? 'Conta ativa' : 'Conta pendente';
$accountStatusClass = (int)($me['aprovado'] ?? 0) === 1 ? 'success' : 'warning';
$roleLabel = (($me['role'] ?? 'user') === 'admin') ? 'Administrador' : 'Conta normal';
$roleBadgeClass = (($me['role'] ?? 'user') === 'admin') ? 'primary' : 'secondary';
$avatarUrl = !empty($me['avatar']) ? 'assets/uploads/' . h((string)$me['avatar']) : '';

include __DIR__ . '/includes/header.php';
?>
<div class="solicitacoes-page profile-page">
  <div class="profile-hero card shadow-sm mb-4">
    <div class="card-body profile-hero-body">
      <div class="profile-hero-avatar-wrap">
        <?php if (!empty($me['avatar'])): ?>
          <img src="assets/uploads/<?= h((string)$me['avatar']) ?>" alt="Avatar de <?= h((string)$me['nome']) ?>" class="profile-hero-avatar" data-avatar-preview>
        <?php else: ?>
          <div class="profile-hero-avatar profile-hero-avatar-fallback" data-avatar-preview><?= h(strtoupper(substr((string)$me['nome'], 0, 1))) ?></div>
        <?php endif; ?>
      </div>
      <div class="profile-hero-content">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
          <span class="badge rounded-pill text-bg-<?= h($roleBadgeClass) ?> profile-role-badge"><?= h($roleLabel) ?></span>
          <span class="badge rounded-pill text-bg-<?= h($accountStatusClass) ?> profile-role-badge"><?= h($accountStatus) ?></span>
          <span class="badge rounded-pill text-bg-<?= h($passwordState[0]) ?> profile-role-badge"><?= h($passwordState[1]) ?></span>
        </div>
        <h2 class="profile-hero-name mb-1"><?= h((string)$me['nome']) ?></h2>
        <div class="profile-hero-email mb-3"><?= h((string)$me['email']) ?></div>
        <div class="profile-summary-grid">
          <div class="profile-summary-item">
            <span>Último login</span>
            <strong><?= profileFormatDate($me['last_login_at'] ?? null) ?></strong>
          </div>
          <div class="profile-summary-item">
            <span>IP do último login</span>
            <strong><?= h((string)($me['last_login_ip'] ?: '-')) ?></strong>
          </div>
          <div class="profile-summary-item">
            <span>Conta criada em</span>
            <strong><?= profileFormatDate($me['criado_em'] ?? null) ?></strong>
          </div>
          <div class="profile-summary-item">
            <span>Senha temporária</span>
            <strong><?= !empty($me['temp_password_expires_at']) ? profileFormatDate($me['temp_password_expires_at'], 'd/m/Y H:i') : 'Sem expiração' ?></strong>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php if ((int)($me['must_change_password'] ?? 0) === 1): ?>
    <div class="alert alert-warning profile-alert">
      Você está usando uma senha temporária. Troque-a na aba Segurança para continuar com acesso normal.
    </div>
  <?php elseif (!empty($me['temp_password_expires_at'])): ?>
    <?php
      $expires = strtotime((string)$me['temp_password_expires_at']);
      $hoursLeft = $expires !== false ? (int)ceil(($expires - time()) / 3600) : null;
    ?>
    <?php if ($hoursLeft !== null && $hoursLeft <= 24): ?>
      <div class="alert alert-danger profile-alert">
        Sua senha temporária vence em menos de 24 horas. Troque-a o quanto antes.
      </div>
    <?php elseif ($hoursLeft !== null && $hoursLeft <= 72): ?>
      <div class="alert alert-warning profile-alert">
        Sua senha temporária está perto de vencer. Troque-a para evitar bloqueio.
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($m = flash('error')): ?>
    <div class="alert alert-danger profile-alert"><?= h($m) ?></div>
  <?php endif; ?>
  <?php if ($m = flash('success')): ?>
    <div class="alert alert-success profile-alert"><?= h($m) ?></div>
  <?php endif; ?>

  <ul class="nav nav-tabs profile-tabs mb-3" id="profileTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= $tabToActivate === 'dados' ? 'active' : '' ?>" id="dados-tab" data-bs-toggle="tab" data-bs-target="#dados" type="button" role="tab" aria-controls="dados" aria-selected="<?= $tabToActivate === 'dados' ? 'true' : 'false' ?>">Dados pessoais</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= $tabToActivate === 'seguranca' ? 'active' : '' ?>" id="seguranca-tab" data-bs-toggle="tab" data-bs-target="#seguranca" type="button" role="tab" aria-controls="seguranca" aria-selected="<?= $tabToActivate === 'seguranca' ? 'true' : 'false' ?>">Segurança</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= $tabToActivate === 'avatar' ? 'active' : '' ?>" id="avatar-tab" data-bs-toggle="tab" data-bs-target="#avatar" type="button" role="tab" aria-controls="avatar" aria-selected="<?= $tabToActivate === 'avatar' ? 'true' : 'false' ?>">Avatar</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= $tabToActivate === 'atividades' ? 'active' : '' ?>" id="atividades-tab" data-bs-toggle="tab" data-bs-target="#atividades" type="button" role="tab" aria-controls="atividades" aria-selected="<?= $tabToActivate === 'atividades' ? 'true' : 'false' ?>">Atividade recente</button>
    </li>
  </ul>

  <div class="tab-content profile-tab-content">
    <div class="tab-pane fade <?= $tabToActivate === 'dados' ? 'show active' : '' ?>" id="dados" role="tabpanel" aria-labelledby="dados-tab">
      <div class="card profile-section-card shadow-sm">
        <div class="card-body">
          <form method="post" class="profile-form">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>" />
            <input type="hidden" name="profile_action" value="details" />
            <div class="row g-3">
              <div class="col-12 col-lg-6">
                <label class="form-label">Nome</label>
                <input class="form-control form-control-lg" name="nome" value="<?= h((string)$me['nome']) ?>" required />
              </div>
              <div class="col-12 col-lg-6">
                <label class="form-label">E-mail</label>
                <input class="form-control form-control-lg" value="<?= h((string)$me['email']) ?>" readonly />
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label">Função</label>
                <input class="form-control" value="<?= h($roleLabel) ?>" readonly />
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label">Status da conta</label>
                <input class="form-control" value="<?= h($accountStatus) ?>" readonly />
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label">Criado em</label>
                <input class="form-control" value="<?= h(profileFormatDate($me['criado_em'] ?? null)) ?>" readonly />
              </div>
            </div>
            <div class="profile-form-actions mt-3 d-flex flex-wrap gap-2">
              <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Salvar dados</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="tab-pane fade <?= $tabToActivate === 'seguranca' ? 'show active' : '' ?>" id="seguranca" role="tabpanel" aria-labelledby="seguranca-tab">
      <div class="card profile-section-card shadow-sm">
        <div class="card-body">
          <form method="post" class="profile-form" id="profileSecurityForm">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>" />
            <input type="hidden" name="profile_action" value="security" />
            <div class="row g-3">
              <div class="col-12 col-lg-4">
                <label class="form-label">Senha atual</label>
                <div class="input-group">
                  <input id="current_password" type="password" name="current_password" class="form-control" autocomplete="current-password" />
                  <button type="button" class="btn btn-outline-secondary" id="toggleCurrentPwd" aria-label="Mostrar senha atual" title="Mostrar/ocultar senha atual">
                    <i class="fa-regular fa-eye"></i>
                  </button>
                </div>
              </div>
              <div class="col-12 col-lg-4">
                <label class="form-label">Nova senha</label>
                <div class="input-group">
                  <span class="input-group-text" aria-hidden="true">
                    <i class="fa-solid fa-lock"></i>
                  </span>
                  <input id="new_password" type="password" name="password" class="form-control" autocomplete="new-password" />
                  <button type="button" class="btn btn-outline-secondary" id="toggleNewPwd" tabindex="-1" aria-label="Mostrar senha" title="Mostrar/ocultar senha">
                    <i class="fa-regular fa-eye"></i>
                  </button>
                </div>
                <div class="password-strength mt-2" data-password-strength>
                  <div class="password-strength-bar"><span data-password-strength-bar></span></div>
                  <div class="password-strength-meta">
                    <span data-password-strength-label>Digite uma senha</span>
                    <span data-password-strength-hint>Use 8+ caracteres, letras maiúsculas, números e símbolos.</span>
                  </div>
                </div>
              </div>
              <div class="col-12 col-lg-4">
                <label class="form-label">Confirmar nova senha</label>
                <div class="input-group">
                  <input id="confirm_password" type="password" name="password_confirm" class="form-control" autocomplete="new-password" />
                  <button type="button" class="btn btn-outline-secondary" id="toggleConfirmPwd" aria-label="Mostrar confirmação" title="Mostrar/ocultar confirmação">
                    <i class="fa-regular fa-eye"></i>
                  </button>
                </div>
                <div class="form-text mt-2">A confirmação deve coincidir com a nova senha.</div>
              </div>
            </div>
            <div class="profile-form-actions mt-3 d-flex flex-wrap gap-2">
              <button class="btn btn-primary"><i class="fa-solid fa-key me-1"></i>Atualizar senha</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="tab-pane fade <?= $tabToActivate === 'avatar' ? 'show active' : '' ?>" id="avatar" role="tabpanel" aria-labelledby="avatar-tab">
      <div class="card profile-section-card shadow-sm">
        <div class="card-body">
          <form method="post" enctype="multipart/form-data" class="profile-form" id="profileAvatarForm">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>" />
            <input type="hidden" name="profile_action" value="avatar" />
            <div class="row g-4 align-items-start">
              <div class="col-12 col-lg-4">
                <div class="avatar-preview-card">
                  <?php if (!empty($me['avatar'])): ?>
                    <img src="assets/uploads/<?= h((string)$me['avatar']) ?>" alt="Avatar atual" class="avatar-preview-image" data-avatar-preview />
                  <?php else: ?>
                    <div class="avatar-preview-image avatar-preview-fallback" data-avatar-preview><?= h(strtoupper(substr((string)$me['nome'], 0, 1))) ?></div>
                  <?php endif; ?>
                  <div class="avatar-preview-caption">Pré-visualização com corte automático centralizado.</div>
                </div>
              </div>
              <div class="col-12 col-lg-8">
                <div class="mb-3">
                  <label class="form-label">Enviar novo avatar</label>
                  <input id="avatarInput" type="file" name="avatar" class="form-control form-control-lg" accept="image/png,image/jpeg,image/webp" />
                  <div class="form-text">PNG, JPG ou WEBP até 2MB. A imagem será ajustada automaticamente para formato quadrado.</div>
                </div>
                <div class="avatar-actions-box">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" value="1" id="removeAvatar" name="remove_avatar">
                    <label class="form-check-label fw-semibold" for="removeAvatar">Remover avatar atual</label>
                  </div>
                  <div class="form-text">Se enviar uma nova imagem, o sistema substitui o avatar atual.</div>
                </div>
                <div class="profile-form-actions mt-3 d-flex flex-wrap gap-2">
                  <button class="btn btn-primary"><i class="fa-solid fa-image me-1"></i>Salvar avatar</button>
                  <?php if (!empty($me['avatar'])): ?>
                    <button type="button" class="btn btn-outline-danger" data-remove-avatar-button>
                      <i class="fa-solid fa-trash-can me-1"></i>Remover avatar
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="tab-pane fade <?= $tabToActivate === 'atividades' ? 'show active' : '' ?>" id="atividades" role="tabpanel" aria-labelledby="atividades-tab">
      <div class="card profile-section-card shadow-sm">
        <div class="card-body">
          <?php if (!empty($recentActivities)): ?>
            <div class="profile-activity-list">
              <?php foreach ($recentActivities as $activity): ?>
                <?php [$activityLabel, $activityIcon, $activityClass] = profileActivityMeta((string)($activity['acao'] ?? '')); ?>
                <div class="profile-activity-item">
                  <div class="profile-activity-icon <?= h($activityClass) ?>"><i class="fa-solid <?= h($activityIcon) ?>"></i></div>
                  <div class="profile-activity-content">
                    <div class="profile-activity-header">
                      <strong><?= h($activityLabel) ?></strong>
                      <span><?= profileFormatDate($activity['created_at'] ?? null) ?></span>
                    </div>
                    <div class="profile-activity-title"><?= h((string)($activity['titulo'] ?? 'Atividade')) ?></div>
                    <?php if (!empty($activity['detalhes'])): ?>
                      <div class="profile-activity-details"><?= h((string)$activity['detalhes']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($activity['ip_address'])): ?>
                      <div class="profile-activity-meta">IP <?= h((string)$activity['ip_address']) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="profile-empty-state">
              <div class="profile-empty-icon"><i class="fa-regular fa-clock"></i></div>
              <h3 class="h6 mb-2">Sem atividade recente</h3>
              <p class="text-muted mb-0">As ações mais recentes do seu perfil aparecerão aqui, como login, troca de senha e atualização de avatar.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/profile.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>