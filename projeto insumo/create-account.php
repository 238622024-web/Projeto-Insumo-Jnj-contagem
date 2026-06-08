<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
if (currentUser()) { header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nome = trim($_POST['nome'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $tipoConta = trim($_POST['tipo_conta'] ?? 'user');
  if (!in_array($tipoConta, ['user', 'admin'], true)) {
    $tipoConta = 'user';
  }
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
      ensureUserAuthSchema();
     $check = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
     $check->execute([$email]);
     if ($check->fetch()) {
        $erros[] = 'E-mail já registrado.';
     } else {
      $hash = password_hash($senha, PASSWORD_DEFAULT);
      $hasApprovedAdmin = (int)($pdo->query("SELECT COUNT(*) AS c FROM usuarios WHERE role = 'admin' AND aprovado = 1")->fetch()['c'] ?? 0) > 0;
      $newRole = $hasApprovedAdmin ? $tipoConta : 'admin';
      $isApproved = $hasApprovedAdmin ? 0 : 1;
        $ins = $pdo->prepare('INSERT INTO usuarios (nome,email,senha_hash,role,aprovado,aprovado_em) VALUES (?,?,?,?,?,?)');
        $ins->execute([$nome,$email,$hash,$newRole,$isApproved,$isApproved ? date('Y-m-d H:i:s') : null]);
        if ($hasApprovedAdmin) {
          if ($newRole === 'admin') {
            flash('success','Solicitação de administrador enviada. Aguarde aprovação do administrador principal.');
          } else {
            flash('success','Solicitação enviada com sucesso. Aguarde a aprovação do administrador para acessar.');
          }
        } else {
          flash('success','Conta de administrador criada com sucesso. Faça login.');
        }
        header('Location: login.php');
        exit;
     }
  }
  if ($erros) { flash('error', implode(' ', $erros)); }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Solicitar Acesso</title>
  <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/create-account.css" />
</head>
<body class="create-account-page">
  <div class="min-vh-100 d-flex align-items-center justify-content-center">
    <div class="create-account-shell">
      <div class="card create-account-card shadow-sm">
      <div class="card-body p-4 p-md-4">
        <h2 class="h4 text-center mb-2">Solicitar Acesso</h2>
        <form method="post" autocomplete="off" novalidate>
          <div class="mb-3">
            <label class="form-label small" for="nome">Nome</label>
            <input id="nome" type="text" name="nome" required value="<?= h($_POST['nome'] ?? '') ?>" class="form-control" placeholder="Seu nome" />
          </div>
          <div class="mb-3">
            <label class="form-label small" for="email">E-mail</label>
            <input id="email" type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>" class="form-control" placeholder="Seu e-mail" />
          </div>
          <div class="mb-3">
            <label class="form-label small" for="tipo_conta">Tipo de conta</label>
            <select id="tipo_conta" name="tipo_conta" class="form-select" required>
              <option value="user" <?= (($_POST['tipo_conta'] ?? 'user') === 'user') ? 'selected' : '' ?>>Conta normal</option>
              <option value="admin" <?= (($_POST['tipo_conta'] ?? '') === 'admin') ? 'selected' : '' ?>>Administrador</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small" for="password">Senha</label>
            <div class="input-group">
              <span class="input-group-text" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
              </span>
              <input id="password" type="password" name="password" required class="form-control" placeholder="Crie sua senha" />
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
            <label class="form-label small" for="confirm_password">Confirmar Senha</label>
            <div class="input-group">
              <span class="input-group-text" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
              </span>
              <input id="confirm_password" type="password" name="confirm_password" required class="form-control" placeholder="Repita a senha" />
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
          <button type="submit" class="btn btn-primary btn-register w-100">Enviar solicitação</button>
        </form>
        <div class="text-center mt-3 small text-muted"><a href="login.php">Já tenho conta</a></div>
      </div>
    </div>
    </div>
  </div>

  <script src="assets/js/create-account.js"></script>
</body>
</html>