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
$currentPage = basename($scriptName ?: ($_SERVER['PHP_SELF'] ?? ''));

function buildAssetUrl(string $base, string $rel): string {
  $rel = ltrim($rel, '/');
  $parts = array_map('rawurlencode', explode('/', $rel));
  return $base . '/' . implode('/', $parts);
}

function assetVersion(string $relativePath): string {
  $absolutePath = __DIR__ . '/../' . ltrim($relativePath, '/');
  return file_exists($absolutePath) ? (string)filemtime($absolutePath) : (string)time();
}

$logoUrl = '';
if (!empty($logoPath)) {
  // Só usa logo_path se o arquivo realmente existir localmente.
  $logoAbs = realpath(__DIR__ . '/../' . ltrim($logoPath, '/'));
  if ($logoAbs && file_exists($logoAbs)) {
    $logoUrl = buildAssetUrl($projectBase, $logoPath);
  }
}

if ($logoUrl === '' && !empty($logoUrlSetting)) {
  // Aceita apenas URL absoluta válida.
  if (preg_match('/^https?:\/\//i', $logoUrlSetting)) {
    $logoUrl = $logoUrlSetting;
  }
}

if ($logoUrl === '') {
  // Tentativas automáticas: procurar logo enviado/local
  // Priorizar especificamente LOGO.JNJ.PNJ.png se existir
  $candidates = [
    // novas variações priorizadas
    'logo_manserv.png',
    'logo_msv_horizontal_trans 2.png',
    'logo_msv_horizontal_trans.png',
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
    $logoUrl = buildAssetUrl($projectBase, $found);
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
    <link rel="icon" type="image/png" href="<?= h(buildAssetUrl($projectBase, 'assets/uploads/logo_favicon.png')) ?>">
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/v4-shims.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/vendor/datatables/css/jquery.dataTables.min.css" />
    <link rel="stylesheet" href="style.css?v=<?= h(assetVersion('style.css')) ?>" />
    <link rel="stylesheet" href="assets/css/header-footer.css?v=<?= h(assetVersion('assets/css/header-footer.css')) ?>" />
    <?php if ($currentPage === 'login.php'): ?>
      <link rel="stylesheet" href="assets/css/login.css?v=<?= h(assetVersion('assets/css/login.css')) ?>" />
    <?php endif; ?>
    <?php if ($currentPage === 'create-account.php'): ?>
      <link rel="stylesheet" href="assets/css/create-account.css?v=<?= h(assetVersion('assets/css/create-account.css')) ?>" />
    <?php endif; ?>
    <?php if ($currentPage === 'forgot-password.php'): ?>
      <link rel="stylesheet" href="assets/css/forgot-password.css?v=<?= h(assetVersion('assets/css/forgot-password.css')) ?>" />
    <?php endif; ?>
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
<body class="<?= trim(($temaAtual === 'escuro' ? 'theme-dark ' : '') . ($user ? 'app-shell' : '')) ?>">
<?php if ($user): ?>
<div class="app-shell" data-app-shell>
  <aside class="app-sidebar" data-sidebar>
    <div class="sidebar-brand">
      <a class="sidebar-logo-link" href="index.php" aria-label="Ir para a página inicial">
        <img src="<?= h($logoUrl) ?>" alt="Manserv" class="site-logo sidebar-logo" />
      </a>
      <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-label="Recolher menu">
        <i class="fa-solid fa-bars"></i>
      </button>
    </div>

    <div class="sidebar-user">
      <?php if (!empty($user['avatar'])): ?>
        <img src="assets/uploads/<?= h($user['avatar']) ?>" alt="avatar" class="sidebar-avatar" />
      <?php else: ?>
        <div class="sidebar-avatar sidebar-avatar-fallback"><?= h(strtoupper(substr($user['email'], 0, 1))) ?></div>
      <?php endif; ?>
      <div class="sidebar-user-meta">
        <strong><?= h($user['name'] ?? 'Usuário') ?></strong>
        <span><?= h($user['email']) ?></span>
      </div>
    </div>

    <nav class="sidebar-nav" aria-label="Menu lateral">
      <a class="sidebar-link" href="index.php"><i class="fa-solid fa-house"></i><span><?= h(t('nav.home')) ?></span></a>
      <?php if (isAdmin()): ?>
        <a class="sidebar-link" href="dashboard.php"><i class="fa-solid fa-chart-column"></i><span><?= h(t('nav.dashboard')) ?></span></a>
      <?php endif; ?>
      <a class="sidebar-link" href="perfil.php"><i class="fa-solid fa-user"></i><span><?= h(t('nav.profile')) ?></span></a>
      <?php if (isAdmin()): ?>
        <a class="sidebar-link" href="solicitacoes.php"><i class="fa-solid fa-clipboard-list"></i><span>Solicitações</span></a>
      <?php endif; ?>
      <a class="sidebar-link" href="configuracoes.php"><i class="fa-solid fa-gear"></i><span><?= h(t('nav.settings')) ?></span></a>
    </nav>

    <div class="sidebar-footer">
      <a class="sidebar-link sidebar-link-logout" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i><span><?= h(t('nav.logout')) ?></span></a>
    </div>
  </aside>
  <div class="app-content">
    <header class="app-topbar">
      <button class="sidebar-toggle sidebar-toggle-inline" type="button" data-sidebar-toggle aria-label="Recolher menu">
        <i class="fa-solid fa-bars"></i>
      </button>
      <div class="app-topbar-title">
        <span><?= h(t('app.title')) ?></span>
      </div>
    </header>
    <main class="app-main container-fluid">
<?php else: ?>
<header class="auth-topbar shadow-sm" style="background: var(--color-primary) !important;">
  <div class="container-fluid d-flex align-items-center justify-content-between py-2">
    <a href="login.php" class="d-inline-flex align-items-center text-decoration-none">
      <img src="<?= h($logoUrl) ?>" alt="Manserv" class="site-logo" />
    </a>
    <nav class="d-flex gap-2">
      <?php if (empty($hideAuthButtons)): ?>
        <a class="btn btn-sm btn-light" href="login.php"><i class="fa-solid fa-right-to-bracket me-1"></i><?= h(t('nav.login')) ?></a>
        <a class="btn btn-sm btn-outline-light" href="create-account.php"><i class="fa-solid fa-user-plus me-1"></i><?= h(t('nav.register')) ?></a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="container auth-main">
<?php endif; ?>
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