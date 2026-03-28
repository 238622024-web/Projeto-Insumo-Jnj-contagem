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
    <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/vendor/datatables/css/jquery.dataTables.min.css" />
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="assets/css/header-footer.css" />
    <?php if ($currentPage === 'login.php'): ?>
      <link rel="stylesheet" href="assets/css/login.css" />
    <?php endif; ?>
    <?php if ($currentPage === 'create-account.php'): ?>
      <link rel="stylesheet" href="assets/css/create-account.css" />
    <?php endif; ?>
    <?php if ($currentPage === 'forgot-password.php'): ?>
      <link rel="stylesheet" href="assets/css/forgot-password.css" />
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
<body class="<?= $temaAtual === 'escuro' ? 'theme-dark' : '' ?>">
<header class="navbar navbar-dark fixed-top shadow" style="background: var(--color-primary) !important;">
  <div class="container-fluid">
    <span class="navbar-brand mb-0 h1 d-flex align-items-center">
      <img src="<?= h($logoUrl) ?>" alt="Manserv" class="site-logo" />
    </span>

    <nav class="d-flex gap-2">
      <?php if ($user): ?>
        <a class="btn btn-sm btn-light" href="index.php"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1" style="display:inline;vertical-align:middle;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg><?= h(t('nav.home')) ?></a>
        <a class="btn btn-sm btn-light" href="perfil.php"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1" style="display:inline;vertical-align:middle;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg><?= h(t('nav.profile')) ?></a>
        <?php if (isAdmin()): ?>
          <a class="btn btn-sm btn-light" href="solicitacoes.php"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1" style="display:inline;vertical-align:middle;"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>Solicitações</a>
        <?php endif; ?>
        <a class="btn btn-sm btn-light" href="configuracoes.php"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1" style="display:inline;vertical-align:middle;"><circle cx="12" cy="12" r="3"></circle><path d="M12 1v6m0 6v6M4.22 4.22l4.24 4.24m2.96 2.96l4.24 4.24M1 12h6m6 0h6m-17.78 7.78l4.24-4.24m2.96-2.96l4.24-4.24"></path></svg><?= h(t('nav.settings')) ?></a>
        <!-- Theme toggle removed per request -->
        <?php if (!empty($user['avatar'])): ?>
          <a href="perfil.php" class="d-inline-block"><img src="assets/uploads/<?= h($user['avatar']) ?>" alt="avatar" style="height:30px;width:30px;border-radius:6px;object-fit:cover;margin-right:6px;" /></a>
        <?php else: ?>
          <a href="perfil.php" class="badge bg-light text-dark align-self-center text-decoration-none"><?= h($user['email']) ?></a>
        <?php endif; ?>
          <a class="btn btn-sm btn-outline-light" href="logout.php"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1" style="display:inline;vertical-align:middle;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg><?= h(t('nav.logout')) ?></a>
      <?php else: ?>
        <?php if (empty($hideAuthButtons)): ?>
          <a class="btn btn-sm btn-light" href="login.php"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1" style="display:inline;vertical-align:middle;"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg><?= h(t('nav.login')) ?></a>
          <a class="btn btn-sm btn-outline-light" href="create-account.php"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1" style="display:inline;vertical-align:middle;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="16" y1="11" x2="16" y2="17"></line><line x1="19" y1="14" x2="13" y2="14"></line></svg><?= h(t('nav.register')) ?></a>
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