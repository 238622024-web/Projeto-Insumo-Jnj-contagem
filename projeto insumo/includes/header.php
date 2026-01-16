<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/../i18n.php';
$user = currentUser();
// Tema do usuário (padrão claro)
$temaAtual = getSetting('tema_padrao', $_SESSION['tema_jnj'] ?? 'claro');
// Preferência: 1) logo carregado (logo_path) 2) logo externo (logo_url) 3) logo padrão interno
$logoPath = getSetting('logo_path', '');
$logoUrlSetting = getSetting('logo_url', '');

// Construir base do projeto de forma robusta (funciona em Docker/Windows)
// Usa SCRIPT_NAME para obter a base do subdiretório atual
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\','/', $_SERVER['SCRIPT_NAME']) : '';
$scriptDir = '/' . trim(dirname($scriptName), '/');
// Se scriptDir for apenas '/', não prefixar
$projectBase = $scriptDir === '/' ? '' : $scriptDir;

$logoUrl = '';
if (!empty($logoPath)) {
  // Primeiro tenta caminho relativo simples; se não existir, mantém mesmo assim (para servidores que atendem o arquivo)
  $logoUrl = $projectBase . '/' . ltrim($logoPath, '/');
} elseif (!empty($logoUrlSetting)) {
  // aceita URL absoluto (http/https)
  $logoUrl = $logoUrlSetting;
} else {
  // Tentativas automáticas: procurar logo enviado/local
  // Priorizar especificamente LOGO.JNJ.PNJ.png se existir
  $candidates = [
    // novas variações priorizadas
    'logo_msv_horizontal_trans.png',
    'logo_msv_horizontal_trans 2.png',
    // anteriores
    'LOGO.JNJ.PNJ.png',
    'assets/uploads/logo_custom.svg',
    'assets/uploads/logo_custom.png',
    'assets/uploads/logo_custom.jpg',
    'assets/uploads/logo_custom.jpeg',
  ];
  $found = '';
  foreach ($candidates as $rel) {
    $abs = realpath(__DIR__ . '/../' . $rel);
    if ($abs && file_exists($abs)) { $found = $rel; break; }
  }
  if ($found !== '') {
    $logoUrl = $projectBase . '/' . $found;
  } else {
    $logoUrl = $projectBase . '/assets/logo.svg';
  }
}
?>

<!DOCTYPE html>
<?php $lang = getSetting('lang', $_SESSION['lang_jnj'] ?? 'pt-br'); ?>
<html lang="<?= h($lang) ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= h(t('app.title')) ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="LOGO.JNJ.PNJ.png">
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap via CDN (primário) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Fallback local básico se o CDN falhar -->
    <link rel="stylesheet" href="assets/fallbacks/bootstrap-lite.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css" />
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-RXf+QSDCUQs6Q0GqQmCtT9e7N1KleChX2NDVYqoQZnQEqplLWYw0EN0pZK0s8AjtKqJrY6QXTsE6YdZP+eT1Bw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
      /* Fallback mínimo inline para elementos principais (quando CDN indisponível) */
      .btn{display:inline-block;line-height:1.5;color:#fff;text-align:center;text-decoration:none;cursor:pointer;background-color:#0d6efd;border:1px solid #0d6efd;padding:.375rem .75rem;font-size:1rem;border-radius:.375rem}
      .btn-light{background:#f8f9fa;color:#000;border-color:#f8f9fa}
      .btn-outline-light{color:#fff;background-color:transparent;border-color:#f8f9fa}
      .btn-outline-light:hover{background:#f8f9fa;color:#000}
      .btn-close{float:right;width:1em;height:1em;background:transparent;border:0}
      .badge{display:inline-block;padding:.35em .65em;font-size:.75em;border-radius:.25rem}
      .navbar{display:flex;align-items:center;padding:.5rem 1rem}
      .container, .container-fluid{width:100%;padding-right:12px;padding-left:12px;margin-right:auto;margin-left:auto}
      .alert{position:relative;padding:.5rem .75rem;margin-bottom:1rem;border:1px solid transparent;border-radius:.375rem}
      .alert-success{color:#0f5132;background-color:#d1e7dd;border-color:#badbcc}
      .alert-danger{color:#842029;background-color:#f8d7da;border-color:#f5c2c7}
      .site-logo{height:28px;width:auto;vertical-align:middle}
    </style>
<?php
  // Aplicar cor primária dinâmica se configurada
  $primaryColor = getSetting('primary_color', '');
  if (preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) {
    // Calcular contraste simples (luminância) para texto
    $r = hexdec(substr($primaryColor,1,2));
    $g = hexdec(substr($primaryColor,3,2));
    $b = hexdec(substr($primaryColor,5,2));
    $luminance = (0.2126*$r + 0.7152*$g + 0.0722*$b);
    $contrast = $luminance > 140 ? '#000' : '#FFF';
    echo '<style>:root{--color-primary:'.h(strtoupper($primaryColor)).';--color-primary-contrast:'.h($contrast).';}</style>';
  }
?>
</head>
<body class="<?= $temaAtual === 'escuro' ? 'theme-dark' : '' ?>">
<header class="navbar navbar-dark fixed-top shadow" style="background: var(--color-primary) !important;">
  <div class="container-fluid">
    <span class="navbar-brand mb-0 h1 d-flex align-items-center">
      <img src="<?= h($logoUrl) ?>" alt="JNJ" class="site-logo" />
    </span>

    <nav class="d-flex gap-2">
      <?php if ($user): ?>
        <a class="btn btn-sm btn-light" href="index.php"><i class="fa-solid fa-table me-1"></i><?= h(t('nav.home')) ?></a>
        <a class="btn btn-sm btn-light" href="cadastrar.php"><i class="fa-solid fa-plus me-1"></i><?= h(t('nav.new')) ?></a>
        <a class="btn btn-sm btn-light" href="perfil.php"><i class="fa-solid fa-user me-1"></i><?= h(t('nav.profile')) ?></a>
        <a class="btn btn-sm btn-light" href="configuracoes.php"><i class="fa-solid fa-gear me-1"></i><?= h(t('nav.settings')) ?></a>
        <!-- Theme toggle removed per request -->
        <?php if (!empty($user['avatar'])): ?>
          <a href="perfil.php" class="d-inline-block"><img src="assets/uploads/<?= h($user['avatar']) ?>" alt="avatar" style="height:30px;width:30px;border-radius:6px;object-fit:cover;margin-right:6px;" /></a>
        <?php else: ?>
          <a href="perfil.php" class="badge bg-light text-dark align-self-center text-decoration-none"><?= h($user['email']) ?></a>
        <?php endif; ?>
          <a class="btn btn-sm btn-outline-light" href="logout.php"><i class="fa-solid fa-right-from-bracket me-1"></i><?= h(t('nav.logout')) ?></a>
      <?php else: ?>
        <?php if (empty($hideAuthButtons)): ?>
          <a class="btn btn-sm btn-light" href="login.php"><i class="fa-solid fa-right-to-bracket me-1"></i><?= h(t('nav.login')) ?></a>
          <a class="btn btn-sm btn-outline-light" href="create-account.php"><i class="fa-solid fa-user-plus me-1"></i><?= h(t('nav.register')) ?></a>
        <?php endif; ?>
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