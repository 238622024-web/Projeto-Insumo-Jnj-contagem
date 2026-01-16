<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$pdo = getPDO();
// Verifica se a coluna 'avatar' existe para evitar erro em bancos sem migração
$hasAvatarCol = (bool)$pdo->query("SHOW COLUMNS FROM usuarios LIKE 'avatar'")->fetch();
if ($hasAvatarCol) {
  $user = $pdo->prepare('SELECT id,nome,email,avatar FROM usuarios WHERE id = ? LIMIT 1');
} else {
  $user = $pdo->prepare('SELECT id,nome,email FROM usuarios WHERE id = ? LIMIT 1');
}
$user->execute([$_SESSION['usuario_id']]);
$me = $user->fetch();
if ($me && !$hasAvatarCol) { $me['avatar'] = null; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $senha = $_POST['password'] ?? '';
    $errors = [];
    $avatarFilename = $me['avatar'];
    if (!empty($_FILES['avatar']['name'])) {
        $f = $_FILES['avatar'];
        if ($f['error'] === UPLOAD_ERR_OK) {
            $allowed = ['png','jpg','jpeg','svg'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed) && $f['size'] <= 2 * 1024 * 1024) {
                $uploadsDir = __DIR__ . '/assets/uploads';
                if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
                $avatarFilename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                move_uploaded_file($f['tmp_name'], $uploadsDir . '/' . $avatarFilename);
            } else {
                $errors[] = 'Arquivo inválido. Use png/jpg/svg e menor que 2MB.';
            }
        }
    }

    if ($nome === '') $errors[] = 'Nome obrigatório.';
    if (!$errors) {
        if ($senha !== '') {
            if (strlen($senha) < 6) { $errors[] = 'Senha deve ter ao menos 6 caracteres.'; }
            else {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
            if ($hasAvatarCol) {
              $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, senha_hash = ?, avatar = ? WHERE id = ?');
              $stmt->execute([$nome,$hash,$avatarFilename,$_SESSION['usuario_id']]);
            } else {
              $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, senha_hash = ? WHERE id = ?');
              $stmt->execute([$nome,$hash,$_SESSION['usuario_id']]);
            }
            }
        } else {
          if ($hasAvatarCol) {
            $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, avatar = ? WHERE id = ?');
            $stmt->execute([$nome,$avatarFilename,$_SESSION['usuario_id']]);
          } else {
            $stmt = $pdo->prepare('UPDATE usuarios SET nome = ? WHERE id = ?');
            $stmt->execute([$nome,$_SESSION['usuario_id']]);
          }
        }
        $msg = 'Perfil atualizado.';
        if (!$hasAvatarCol && !empty($_FILES['avatar']['name'])) {
          $msg .= ' Observação: para exibir a foto, aplique a migração executando "php database/apply_migrations.php".';
        }
        flash('success',$msg);
        header('Location: perfil.php');
        exit;
    } else {
        flash('error', implode(' ', $errors));
    }
}

include __DIR__ . '/includes/header.php';
?>
<h2 class="h4 mb-3"><i class="fa fa-user me-2"></i>Meu Perfil</h2>
<?php if ($m = flash('error')): ?>
  <div class="alert alert-danger"><?= h($m) ?></div>
<?php endif; ?>
<?php if ($m = flash('success')): ?>
  <div class="alert alert-success"><?= h($m) ?></div>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" class="shadow-sm bg-white p-4 rounded">
  <div class="mb-3">
    <label class="form-label">Nome</label>
    <input class="form-control" name="nome" value="<?= h($me['nome']) ?>" required />
  </div>
  <div class="mb-3">
    <label class="form-label">Avatar (png/jpg/svg, max 2MB)</label>
    <input type="file" name="avatar" class="form-control" />
    <?php if ($hasAvatarCol && !empty($me['avatar'])): ?>
      <div class="mt-2"><img src="assets/uploads/<?= h($me['avatar']) ?>" style="height:60px;border-radius:6px;" /></div>
    <?php endif; ?>
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
  <button class="btn btn-primary">Salvar</button>
</form>

<script src="assets/js/profile.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
