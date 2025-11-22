<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../settings.php';
$user = currentUser();
// Tema do usuário (padrão claro)
$temaAtual = getSetting('tema_padrao', $_SESSION['tema_jnj'] ?? 'claro');
// Preferência: 1) logo carregado (logo_path) 2) logo externo (logo_url) 3) logo padrão interno
$logoPath = getSetting('logo_path', '');
$logoUrlSetting = getSetting('logo_url', '');

// Construir base do projeto a partir do document root
$docRoot = rtrim(str_replace('\\','/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
$projectDir = str_replace('\\','/', realpath(__DIR__ . '/..'));
$projectBase = '';
if (strpos($projectDir, $docRoot) === 0) {
  $projectBase = substr($projectDir, strlen($docRoot));
  $projectBase = '/' . trim($projectBase, '/');
}

$logoUrl = '';
if (!empty($logoPath)) {
  $logoUrl = $projectBase . '/' . ltrim($logoPath, '/');
} elseif (!empty($logoUrlSetting)) {
  // aceita URL absoluto (http/https)
  $logoUrl = $logoUrlSetting;
} else {
  $logoUrl = $projectBase . '/assets/logo.svg';
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JNJ</title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="assets/logo.svg">
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css" />
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-RXf+QSDCUQs6Q0GqQmCtT9e7N1KleChX2NDVYqoQZnQEqplLWYw0EN0pZK0s8AjtKqJrY6QXTsE6YdZP+eT1Bw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="<?= $temaAtual === 'escuro' ? 'theme-dark' : '' ?>">
<header class="navbar navbar-dark fixed-top shadow" style="background: var(--color-primary) !important;">
  <div class="container-fluid">
    <span class="navbar-brand mb-0 h1 d-flex align-items-center">
      <img src="<?= h($logoUrl) ?>" alt="JNJ" class="site-logo" />
    </span>

    <nav class="d-flex gap-2">
      <?php if ($user): ?>
        <a class="btn btn-sm btn-light" href="index.php"><i class="fa-solid fa-table me-1"></i>Insumos</a>
        <a class="btn btn-sm btn-light" href="cadastrar.php"><i class="fa-solid fa-plus me-1"></i>Novo</a>
        <a class="btn btn-sm btn-light" href="perfil.php"><i class="fa-solid fa-user me-1"></i>Perfil</a>
        <a class="btn btn-sm btn-light" href="configuracoes.php"><i class="fa-solid fa-gear me-1"></i>Configurações</a>
        <a class="btn btn-sm btn-light" href="toggletheme.php" title="Alternar tema"><i class="fa-solid fa-moon me-1"></i></a>
        <?php if (!empty($user['avatar'])): ?>
          <a href="perfil.php" class="d-inline-block"><img src="assets/uploads/<?= h($user['avatar']) ?>" alt="avatar" style="height:30px;width:30px;border-radius:6px;object-fit:cover;margin-right:6px;" /></a>
        <?php else: ?>
          <a href="perfil.php" class="badge bg-light text-dark align-self-center text-decoration-none"><?= h($user['email']) ?></a>
        <?php endif; ?>
        <a class="btn btn-sm btn-outline-light" href="logout.php"><i class="fa-solid fa-right-from-bracket me-1"></i>Sair</a>
      <?php else: ?>
        <a class="btn btn-sm btn-light" href="login.php"><i class="fa-solid fa-right-to-bracket me-1"></i>Login</a>
        <a class="btn btn-sm btn-outline-light" href="create-account.php"><i class="fa-solid fa-user-plus me-1"></i>Registrar</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="container" style="padding-top:90px;">
  <?php if ($m = flash('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="fa fa-check-circle me-1"></i><?= h($m) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <?php if ($m = flash('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="fa fa-triangle-exclamation me-1"></i><?= h($m) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>