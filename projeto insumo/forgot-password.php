<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
if (currentUser()) { header('Location: index.php'); exit; }

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

ensureUserAuthSchema();
$pdo = getPDO();
ensurePasswordResetRequestsSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
  $motivo = trim($_POST['motivo'] ?? '');

  if ($email !== '') {
    $userStmt = $pdo->prepare('SELECT id, email, aprovado FROM usuarios WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $userStmt->execute([$email]);
    $user = $userStmt->fetch();

    if ($user && (int)($user['aprovado'] ?? 0) === 1) {
      $pendingStmt = $pdo->prepare("SELECT id FROM password_reset_requests WHERE user_id = ? AND status = 'pending' LIMIT 1");
      $pendingStmt->execute([(int)$user['id']]);
      $pending = $pendingStmt->fetch();

      if (!$pending) {
        $ins = $pdo->prepare('INSERT INTO password_reset_requests (user_id, email, motivo_usuario, status) VALUES (?, ?, ?, ?)');
        $ins->execute([(int)$user['id'], (string)$user['email'], $motivo, 'pending']);
      }
    }
  }

  flash('success', 'Se o e-mail existir e estiver aprovado, sua solicitação de redefinição foi enviada ao administrador.');
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Recuperar Senha - Controle de Insumos JNJ</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/forgot-password.css" />
</head>
<body class="bg-gray-100 text-gray-800">
<div class="min-h-screen flex items-center justify-center px-4 py-12">
  <div class="w-full max-w-md space-y-8 bg-white p-8 rounded shadow">
    <h2 class="text-center text-2xl font-bold text-gray-900">Recuperar Senha</h2>
    <p class="text-sm text-gray-600 text-center">Digite seu e-mail para iniciar o processo.</p>
    <form method="post" class="space-y-5">
      <div>
        <label class="block text-sm font-medium text-gray-700">E-mail</label>
        <input type="email" name="email" required class="mt-1 w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">Motivo (opcional)</label>
        <textarea name="motivo" rows="3" class="mt-1 w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500" placeholder="Ex.: Perdi acesso ao e-mail antigo / esqueci a senha"></textarea>
      </div>
      <button type="submit" class="w-full py-2 px-4 rounded-md text-white bg-red-600 hover:bg-red-700 font-medium">Enviar</button>
    </form>
    <div class="text-center text-sm"><a href="login.php" class="text-red-600 hover:text-red-500">Voltar ao login</a></div>
  </div>
</div>
</body>
</html>