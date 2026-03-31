<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$pdo = getPDO();

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION['csrf_token'];

// Verifica se a coluna 'avatar' existe para evitar erro em bancos sem migração
$hasAvatarCol = (bool)$pdo->query("SHOW COLUMNS FROM usuarios LIKE 'avatar'")->fetch();
if ($hasAvatarCol) {
  $user = $pdo->prepare('SELECT id,nome,email,avatar,senha_hash,role,must_change_password FROM usuarios WHERE id = ? LIMIT 1');
} else {
    $user = $pdo->prepare('SELECT id,nome,email,senha_hash,role,must_change_password FROM usuarios WHERE id = ? LIMIT 1');
}
$user->execute([$_SESSION['usuario_id']]);
$me = $user->fetch();
if ($me && !$hasAvatarCol) { $me['avatar'] = null; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedToken = (string)($_POST['csrf_token'] ?? '');
  if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
    flash('error', 'Sessão inválida. Atualize a página e tente novamente.');
    header('Location: perfil.php');
    exit;
  }

    $nome = trim($_POST['nome'] ?? '');
  $currentPassword = $_POST['current_password'] ?? '';
    $senha = $_POST['password'] ?? '';
  $senhaConfirm = $_POST['password_confirm'] ?? '';
  $removeAvatar = isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1';
    $errors = [];
  $avatarFilename = $me['avatar'] ?? null;
  $oldAvatarFilename = (string)($me['avatar'] ?? '');
  $newAvatarUploaded = false;

  if ($hasAvatarCol && $removeAvatar) {
    $avatarFilename = null;
  }

  if ($hasAvatarCol && !empty($_FILES['avatar']['name'])) {
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
                if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
                $avatarFilename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (move_uploaded_file($f['tmp_name'], $uploadsDir . '/' . $avatarFilename)) {
          $newAvatarUploaded = true;
        } else {
          $errors[] = 'Não foi possível salvar o avatar enviado.';
        }
            } else {
        $errors[] = 'Arquivo inválido. Use PNG/JPG/WEBP e menor que 2MB.';
            }
        }
  } elseif (!$hasAvatarCol && ($removeAvatar || !empty($_FILES['avatar']['name']))) {
    $errors[] = 'Upload/remoção de avatar indisponível. Aplique as migrações do banco.';
    }

    if ($nome === '') $errors[] = 'Nome obrigatório.';

  $isChangingPassword = ($senha !== '' || $senhaConfirm !== '');
  if ($isChangingPassword) {
    if ($currentPassword === '') {
      $errors[] = 'Informe a senha atual para definir uma nova senha.';
    } elseif (!password_verify($currentPassword, (string)($me['senha_hash'] ?? ''))) {
      $errors[] = 'Senha atual incorreta.';
    }

    if (strlen($senha) < 8) {
      $errors[] = 'Nova senha deve ter ao menos 8 caracteres.';
    }
    if ($senha !== $senhaConfirm) {
      $errors[] = 'A confirmação da nova senha não confere.';
    }
  }

    if (!$errors) {
    if ($isChangingPassword) {
      $hash = password_hash($senha, PASSWORD_DEFAULT);
            if ($hasAvatarCol) {
        $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, senha_hash = ?, avatar = ?, must_change_password = 0, temp_password_expires_at = NULL WHERE id = ?');
        $stmt->execute([$nome, $hash, $avatarFilename, $_SESSION['usuario_id']]);
            } else {
        $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, senha_hash = ?, must_change_password = 0, temp_password_expires_at = NULL WHERE id = ?');
        $stmt->execute([$nome, $hash, $_SESSION['usuario_id']]);
            }
      $_SESSION['usuario_must_change_password'] = 0;
        } else {
      if ($hasAvatarCol) {
        $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, avatar = ? WHERE id = ?');
        $stmt->execute([$nome, $avatarFilename, $_SESSION['usuario_id']]);
      } else {
        $stmt = $pdo->prepare('UPDATE usuarios SET nome = ? WHERE id = ?');
        $stmt->execute([$nome, $_SESSION['usuario_id']]);
      }
    }

    if ($hasAvatarCol && $oldAvatarFilename !== '') {
      $shouldDeleteOld = false;
      if ($removeAvatar) {
        $shouldDeleteOld = true;
      }
      if ($newAvatarUploaded && $avatarFilename !== null && $avatarFilename !== $oldAvatarFilename) {
        $shouldDeleteOld = true;
      }
      if ($shouldDeleteOld) {
        $oldPath = __DIR__ . '/assets/uploads/' . basename($oldAvatarFilename);
        if (is_file($oldPath)) {
          @unlink($oldPath);
        }
      }
        }

        $msg = 'Perfil atualizado.';
    if ($removeAvatar) {
      $msg .= ' Avatar removido.';
    } elseif ($newAvatarUploaded) {
      $msg .= ' Avatar atualizado.';
    }
    if (!$hasAvatarCol && ($removeAvatar || !empty($_FILES['avatar']['name']))) {
      $msg .= ' Observação: para exibir a foto, aplique a migração executando "php database/apply_migrations.php".';
        }
    flash('success', $msg);
        header('Location: perfil.php');
        exit;
    } else {
        flash('error', implode(' ', $errors));
    }
}

include __DIR__ . '/includes/header.php';
?>
<h2 class="h4 mb-3"><i class="fa fa-user me-2"></i>Meu Perfil</h2>
<?php if ((int)($me['must_change_password'] ?? 0) === 1): ?>
  <div class="alert alert-warning">
    Você está usando uma senha temporária. Para continuar no sistema, defina uma nova senha agora.
  </div>
<?php endif; ?>
<?php if ($m = flash('error')): ?>
  <div class="alert alert-danger"><?= h($m) ?></div>
<?php endif; ?>
<?php if ($m = flash('success')): ?>
  <div class="alert alert-success"><?= h($m) ?></div>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" class="shadow-sm bg-white p-4 rounded">
  <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>" />
  <div class="mb-3">
    <label class="form-label">Nome</label>
    <input class="form-control" name="nome" value="<?= h($me['nome']) ?>" required />
  </div>
  <div class="mb-3">
    <label class="form-label">E-mail</label>
    <input class="form-control" value="<?= h($me['email']) ?>" readonly />
  </div>
  <div class="mb-3">
    <label class="form-label">Perfil</label>
    <input class="form-control" value="<?= h(($me['role'] ?? 'user') === 'admin' ? 'Administrador' : 'Conta normal') ?>" readonly />
  </div>
  <div class="mb-3">
    <label class="form-label">Avatar (png/jpg/webp, max 2MB)</label>
    <input type="file" name="avatar" class="form-control" />
    <?php if ($hasAvatarCol && !empty($me['avatar'])): ?>
      <div class="mt-2"><img src="assets/uploads/<?= h($me['avatar']) ?>" style="height:60px;border-radius:6px;" /></div>
      <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" value="1" id="removeAvatar" name="remove_avatar">
        <label class="form-check-label" for="removeAvatar">Remover avatar atual</label>
      </div>
    <?php endif; ?>
  </div>
  <div class="mb-3">
    <label class="form-label">Senha atual (obrigatória para trocar senha)</label>
    <div class="input-group">
      <input id="current_password" type="password" name="current_password" class="form-control" />
      <button type="button" class="btn btn-outline-secondary" id="toggleCurrentPwd" aria-label="Mostrar senha atual" title="Mostrar/ocultar senha atual">
        <i class="fa-regular fa-eye"></i>
      </button>
    </div>
  </div>
  <div class="mb-3">
    <label class="form-label">Nova senha (deixe em branco para manter)</label>
    <div class="input-group">
      <span class="input-group-text" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
      </span>
      <input id="new_password" type="password" name="password" class="form-control" />
      <button type="button" class="btn btn-outline-secondary" id="toggleNewPwd" tabindex="-1" aria-label="Mostrar senha" title="Mostrar/ocultar senha">
        <svg id="eyeOpenNP" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/>
          <circle cx="12" cy="12" r="3"/>
        </svg>
        <svg id="eyeClosedNP" class="d-none" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.77 21.77 0 0 1 5.06-6.94"/>
          <path d="M1 1l22 22"/>
        </svg>
      </button>
    </div>
  </div>
  <div class="mb-3">
    <label class="form-label">Confirmar nova senha</label>
    <div class="input-group">
      <input id="confirm_password" type="password" name="password_confirm" class="form-control" />
      <button type="button" class="btn btn-outline-secondary" id="toggleConfirmPwd" aria-label="Mostrar confirmação" title="Mostrar/ocultar confirmação">
        <i class="fa-regular fa-eye"></i>
      </button>
    </div>
    <small class="text-muted">Para trocar senha, use ao menos 8 caracteres e confirme corretamente.</small>
  </div>
  <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Salvar</button>
</form>

<script src="assets/js/profile.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
