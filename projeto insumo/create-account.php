<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
if (currentUser()) { header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nome = trim($_POST['nome'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $senha = $_POST['password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';
  $erros = [];
  if ($nome === '') $erros[] = 'Nome obrigatório.';
  if ($email === '') $erros[] = 'E-mail obrigatório.';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';
  if ($senha === '' || strlen($senha) < 6) $erros[] = 'Senha deve ter pelo menos 6 caracteres.';
  if ($senha !== $confirm) $erros[] = 'As senhas não conferem.';
  if (!$erros) {
     $pdo = getPDO();
     $check = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
     $check->execute([$email]);
     if ($check->fetch()) {
        $erros[] = 'E-mail já registrado.';
     } else {
      $hash = password_hash($senha, PASSWORD_DEFAULT);
      // Handle optional avatar upload if the column exists
      $avatarFilename = null;
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
          }
        }
      }

      // Check if avatar column exists
      $col = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'avatar'")->fetch();
      if ($col) {
        $ins = $pdo->prepare('INSERT INTO usuarios (nome,email,senha_hash,avatar) VALUES (?,?,?,?)');
        $ins->execute([$nome,$email,$hash,$avatarFilename]);
      } else {
        $ins = $pdo->prepare('INSERT INTO usuarios (nome,email,senha_hash) VALUES (?,?,?)');
        $ins->execute([$nome,$email,$hash]);
      }
        flash('success','Conta criada. Faça login.');
        header('Location: login.php');
        exit;
     }
  }
  if ($erros) { flash('error', implode(' ', $erros)); }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="d-flex align-items-center justify-content-center" style="min-height:70vh;">
  <div class="w-100" style="max-width:420px;">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h4 text-center mb-3">Criar Conta</h2>
        <form method="post" enctype="multipart/form-data">
          <div class="mb-3">
            <label class="form-label">Nome</label>
            <input type="text" name="nome" required value="<?= h($_POST['nome'] ?? '') ?>" class="form-control" />
          </div>
          <div class="mb-3">
            <label class="form-label">E-mail</label>
            <input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>" class="form-control" />
          </div>
          <div class="mb-3">
            <label class="form-label">Senha</label>
            <div class="input-group">
              <span class="input-group-text" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
              </span>
              <input id="password" type="password" name="password" required class="form-control" />
              <button type="button" class="btn btn-outline-secondary" id="togglePasswordCA" tabindex="-1" aria-label="Mostrar senha" title="Mostrar/ocultar senha">
                <svg id="eyeOpenCA" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg id="eyeClosedCA" class="d-none" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.77 21.77 0 0 1 5.06-6.94"/>
                  <path d="M1 1l22 22"/>
                </svg>
              </button>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Confirmar Senha</label>
            <div class="input-group">
              <span class="input-group-text" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
              </span>
              <input id="confirm_password" type="password" name="confirm_password" required class="form-control" />
              <button type="button" class="btn btn-outline-secondary" id="toggleConfirmCA" tabindex="-1" aria-label="Mostrar senha" title="Mostrar/ocultar senha">
                <svg id="eyeOpenConfirmCA" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg id="eyeClosedConfirmCA" class="d-none" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.77 21.77 0 0 1 5.06-6.94"/>
                  <path d="M1 1l22 22"/>
                </svg>
              </button>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Avatar (opcional)</label>
            <input type="file" name="avatar" accept="image/*" class="form-control" />
          </div>
          <button type="submit" class="btn btn-primary w-100">Criar</button>
        </form>
        <div class="text-center mt-3"><a href="login.php">Já tenho conta</a></div>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/create-account.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>