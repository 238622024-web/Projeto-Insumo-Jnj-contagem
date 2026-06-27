<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/../i18n.php';
$user = currentUser();
// Tema do usuário (padrão claro)
$temaAtual = getSetting('tema_padrao', $_SESSION['tema_jnj'] ?? 'claro');
if (!empty($user['preferred_theme']) && in_array((string)$user['preferred_theme'], ['claro', 'escuro'], true)) {
  $temaAtual = (string)$user['preferred_theme'];
}
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
$adminMenuPages = [
  'usuarios_pendentes.php',
  'usuarios_aprovados.php',
  'solicitacoes_senha.php',
  'perfis.php',
  'permissoes.php',
  'setores.php',
];
$adminMenuOpen = in_array($currentPage, $adminMenuPages, true);
$stockMenuPages = [
  'produtos.php',
  'entrada_nota_fiscal.php',
  'saida_consumo.php',
  'estoque_atual.php',
  'atualizar_estoque_pela_contagem.php',
  'picking_qrcode.php',
];
$stockMenuOpen = in_array($currentPage, $stockMenuPages, true);
$reportMenuPages = [
  'relatorio_consumo_setor.php',
  'relatorio_consumo_produto.php',
  'pedidos_insumos_pendentes.php',
  'historico_insumos.php',
  'historico-pedidos-insumos.php',
  'estoque_baixo.php',
];
$reportMenuOpen = in_array($currentPage, $reportMenuPages, true);

$pendingInsumoSolicitationsCount = 0;
$pendingInsumoNotifications = [];
if ($user && isAdmin()) {
  try {
    $pdo = getPDO();
    ensureInsumoRequestsSchema($pdo);
    $pendingStmt = $pdo->query(
      "SELECT COUNT(*) AS c
       FROM (
         SELECT COALESCE(NULLIF(batch_id, ''), CONCAT('legacy:', id)) AS solicitation_key
         FROM insumo_requests
         WHERE status = 'pending'
         GROUP BY solicitation_key
       ) pending_groups"
    );
    $pendingInsumoSolicitationsCount = (int)($pendingStmt->fetch()['c'] ?? 0);

    $notificationsStmt = $pdo->query(
      "SELECT
          g.group_key,
          MAX(g.batch_id) AS batch_id,
          MAX(g.sector) AS sector,
          MAX(g.requested_at) AS requested_at,
          MAX(g.data_solicitada_entrega) AS data_solicitada_entrega,
          MAX(g.user_nome) AS user_nome,
          MAX(g.user_email) AS user_email,
          COUNT(*) AS items_count
       FROM (
          SELECT
            COALESCE(NULLIF(batch_id, ''), CONCAT('legacy:', id)) AS group_key,
            batch_id,
            setor AS sector,
            requested_at,
            data_solicitada_entrega,
            user_nome,
            user_email
          FROM insumo_requests
          WHERE status = 'pending'
       ) g
       GROUP BY g.group_key
       ORDER BY MAX(g.requested_at) DESC, g.group_key DESC
       LIMIT 5"
    );
    $pendingInsumoNotifications = $notificationsStmt->fetchAll() ?: [];
  } catch (Throwable $e) {
    $pendingInsumoSolicitationsCount = 0;
    $pendingInsumoNotifications = [];
  }
}

function buildAssetUrl(string $base, string $rel): string {
  $base = str_replace('\\', '/', $base);
  $base = trim($base);
  $base = trim($base, '/');
  $rel = str_replace('\\', '/', $rel);
  $rel = ltrim($rel, '/');
  $parts = array_map('rawurlencode', array_filter(explode('/', $rel), static fn($part) => $part !== ''));
  $path = implode('/', $parts);

  if ($path === '') {
    return '/';
  }

  return $base === '' ? '/' . $path : '/' . implode('/', array_map('rawurlencode', array_filter(explode('/', $base), static fn($part) => $part !== ''))) . '/' . $path;
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
<?php
  $lang = getSetting('lang', $_SESSION['lang_jnj'] ?? 'pt-br');
  if (!empty($user['preferred_language']) && in_array((string)$user['preferred_language'], ['pt-br', 'en'], true)) {
    $lang = (string)$user['preferred_language'];
  }
?>
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
<input type="checkbox" id="mobile-sidebar-toggle" class="sidebar-toggle-state" aria-hidden="true" tabindex="-1">
<div class="app-shell" data-app-shell>
  <aside class="app-sidebar" data-sidebar>
    <div class="sidebar-mobile-actions d-lg-none">
      <button type="button" class="sidebar-toggle sidebar-close-mobile" data-sidebar-close-mobile aria-label="Fechar menu">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="sidebar-brand">
      <a class="sidebar-logo-link" href="index.php" aria-label="Ir para a página inicial">
        <img src="<?= h($logoUrl) ?>" alt="Manserv" class="site-logo sidebar-logo" />
      </a>
    </div>

    <div class="sidebar-user">
      <?php if (!empty($user['avatar'])): ?>
        <img src="assets/uploads/<?= h($user['avatar']) ?>" alt="avatar" class="sidebar-avatar" />
      <?php else: ?>
        <div class="sidebar-avatar sidebar-avatar-fallback"><?= h(strtoupper(substr($user['email'], 0, 1))) ?></div>
      <?php endif; ?>
      <div class="sidebar-user-meta">
        <span class="sidebar-user-label">Usuário</span>
        <strong class="sidebar-user-name <?= isAdmin() ? 'is-admin' : '' ?>"><?= h($user['email']) ?></strong>
        <small class="sidebar-user-role <?= isAdmin() ? 'role-admin' : 'role-user' ?>">
          <i class="fa-solid <?= isAdmin() ? 'fa-shield-halved' : 'fa-user' ?> me-1"></i>
          <?= isAdmin() ? 'Administrador' : 'Conta normal' ?>
        </small>
      </div>
    </div>

    <nav class="sidebar-nav" aria-label="Menu lateral">
      <a class="sidebar-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="index.php"><i class="fa-solid fa-house"></i><span><?= h(t('nav.home')) ?></span></a>
      <?php if (isAdmin()): ?>
        <a class="sidebar-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><i class="fa-solid fa-chart-column"></i><span><?= h(t('nav.dashboard')) ?></span></a>
        <div class="sidebar-admin-menu" data-admin-menu data-admin-menu-open="<?= $adminMenuOpen ? 'true' : 'false' ?>">
          <button
            class="sidebar-link sidebar-menu-toggle <?= $adminMenuOpen ? 'active is-open' : '' ?>"
            type="button"
            data-admin-menu-toggle
            aria-expanded="<?= $adminMenuOpen ? 'true' : 'false' ?>"
            aria-controls="admin-submenu"
          >
            <i class="fa-solid fa-clipboard-list"></i>
            <span class="sidebar-link-text">Administração</span>
            <i class="fa-solid fa-chevron-down sidebar-menu-caret" aria-hidden="true"></i>
          </button>
          <div class="sidebar-submenu <?= $adminMenuOpen ? 'is-open' : '' ?>" id="admin-submenu" data-admin-submenu>
            <a class="sidebar-submenu-link <?= $currentPage === 'usuarios_pendentes.php' ? 'active' : '' ?>" href="usuarios_pendentes.php"><i class="fa-solid fa-user-clock me-2" aria-hidden="true"></i>Usuários Pendentes</a>
            <a class="sidebar-submenu-link <?= $currentPage === 'usuarios_aprovados.php' ? 'active' : '' ?>" href="usuarios_aprovados.php"><i class="fa-solid fa-user-check me-2" aria-hidden="true"></i>Usuários Aprovados</a>
            <a class="sidebar-submenu-link <?= $currentPage === 'solicitacoes_senha.php' ? 'active' : '' ?>" href="solicitacoes_senha.php"><i class="fa-solid fa-key me-2" aria-hidden="true"></i>Solicitações de Senha</a>
            <a class="sidebar-submenu-link <?= $currentPage === 'perfis.php' ? 'active' : '' ?>" href="perfis.php"><i class="fa-solid fa-id-badge me-2" aria-hidden="true"></i>Perfis</a>
            <a class="sidebar-submenu-link <?= $currentPage === 'permissoes.php' ? 'active' : '' ?>" href="permissoes.php"><i class="fa-solid fa-user-shield me-2" aria-hidden="true"></i>Permissões</a>
            <a class="sidebar-submenu-link <?= $currentPage === 'setores.php' ? 'active' : '' ?>" href="setores.php"><i class="fa-solid fa-layer-group me-2" aria-hidden="true"></i>Setores</a>
          </div>
        </div>
        <div class="sidebar-stock-menu" data-stock-menu data-stock-menu-open="<?= $stockMenuOpen ? 'true' : 'false' ?>">
          <button
            class="sidebar-link sidebar-menu-toggle <?= $stockMenuOpen ? 'active is-open' : '' ?>"
            type="button"
            data-stock-menu-toggle
            aria-expanded="<?= $stockMenuOpen ? 'true' : 'false' ?>"
            aria-controls="stock-submenu"
          >
            <i class="fa-solid fa-warehouse"></i>
            <span class="sidebar-link-text">Estoque</span>
            <i class="fa-solid fa-chevron-down sidebar-menu-caret" aria-hidden="true"></i>
          </button>
          <div class="sidebar-submenu <?= $stockMenuOpen ? 'is-open' : '' ?>" id="stock-submenu" data-stock-submenu>
            <a class="sidebar-submenu-link <?= $currentPage === 'produtos.php' ? 'active' : '' ?>" href="produtos.php"><i class="fa-solid fa-box me-2" aria-hidden="true"></i>Produtos</a>
            <a class="sidebar-submenu-link <?= $currentPage === 'entrada_nota_fiscal.php' ? 'active' : '' ?>" href="entrada_nota_fiscal.php"><i class="fa-solid fa-file-invoice me-2" aria-hidden="true"></i>Entrada por Nota Fiscal</a>
            <a class="sidebar-submenu-link <?= $currentPage === 'saida_consumo.php' ? 'active' : '' ?>" href="saida_consumo.php"><i class="fa-solid fa-arrow-right-from-bracket me-2" aria-hidden="true"></i>Saída / Consumo</a>
            <a class="sidebar-submenu-link <?= $currentPage === 'estoque_atual.php' ? 'active' : '' ?>" href="estoque_atual.php"><i class="fa-solid fa-clipboard-list me-2" aria-hidden="true"></i>Estoque Atual</a>
            <a class="sidebar-submenu-link <?= $currentPage === 'atualizar_estoque_pela_contagem.php' ? 'active' : '' ?>" href="atualizar_estoque_pela_contagem.php"><i class="fa-solid fa-file-arrow-up me-2" aria-hidden="true"></i>Atualizar estoque pela contagem</a>
            <a class="sidebar-submenu-link <?= $currentPage === 'picking_qrcode.php' ? 'active' : '' ?>" href="picking_qrcode.php"><i class="fa-solid fa-qrcode me-2" aria-hidden="true"></i>Baixa por QR</a>
          </div>
        </div>
        <div class="sidebar-report-menu" data-report-menu data-report-menu-open="<?= $reportMenuOpen ? 'true' : 'false' ?>">
          <button
            class="sidebar-link sidebar-menu-toggle <?= $reportMenuOpen ? 'active is-open' : '' ?>"
            type="button"
            data-report-menu-toggle
            aria-expanded="<?= $reportMenuOpen ? 'true' : 'false' ?>"
            aria-controls="report-submenu"
          >
            <i class="fa-solid fa-chart-line"></i>
            <span class="sidebar-link-text">Relatório de Insumos</span>
            <i class="fa-solid fa-chevron-down sidebar-menu-caret" aria-hidden="true"></i>
          </button>
          <div class="sidebar-submenu <?= $reportMenuOpen ? 'is-open' : '' ?>" id="report-submenu" data-report-submenu>
            <a class="sidebar-submenu-link <?= $currentPage === 'relatorio_consumo_setor.php' ? 'active' : '' ?>" href="relatorio_consumo_setor.php"><i class="fa-solid fa-people-group me-2" aria-hidden="true"></i>Consumo por Setor</a>
            <a class="sidebar-submenu-link <?= $currentPage === 'relatorio_consumo_produto.php' ? 'active' : '' ?>" href="relatorio_consumo_produto.php"><i class="fa-solid fa-boxes-stacked me-2" aria-hidden="true"></i>Consumo por Produto</a>
            <a class="sidebar-submenu-link <?= $currentPage === 'pedidos_insumos_pendentes.php' ? 'active' : '' ?>" href="pedidos_insumos_pendentes.php"><i class="fa-solid fa-hourglass-half me-2" aria-hidden="true"></i>Pedidos de Insumos Pendentes</a>
            <a class="sidebar-submenu-link <?= $currentPage === 'historico-pedidos-insumos.php' ? 'active' : '' ?>" href="historico-pedidos-insumos.php"><i class="fa-solid fa-box-archive me-2" aria-hidden="true"></i>Histórico de Pedidos de Insumos</a>
            <a class="sidebar-submenu-link <?= $currentPage === 'historico_insumos.php' ? 'active' : '' ?>" href="historico_insumos.php"><i class="fa-solid fa-clock-rotate-left me-2" aria-hidden="true"></i>Histórico de Insumos</a>
            <a class="sidebar-submenu-link <?= $currentPage === 'estoque_baixo.php' ? 'active' : '' ?>" href="estoque_baixo.php"><i class="fa-solid fa-triangle-exclamation me-2" aria-hidden="true"></i>Estoque Baixo</a>
          </div>
        </div>
      <?php endif; ?>
      <?php if (!isAdmin()): ?>
        <a class="sidebar-link <?= $currentPage === 'solicitar-insumo.php' ? 'active' : '' ?>" href="solicitar-insumo.php"><i class="fa-solid fa-box-open"></i><span>Solicitar insumo</span></a>
        <a class="sidebar-link <?= $currentPage === 'meus-pedidos-insumos.php' ? 'active' : '' ?>" href="meus-pedidos-insumos.php"><i class="fa-solid fa-box-archive"></i><span>Meus pedidos</span></a>
      <?php endif; ?>
      <a class="sidebar-link <?= $currentPage === 'perfil.php' ? 'active' : '' ?>" href="perfil.php"><i class="fa-solid fa-user"></i><span><?= h(t('nav.profile')) ?></span></a>
      <a class="sidebar-link <?= $currentPage === 'configuracoes.php' ? 'active' : '' ?>" href="configuracoes.php"><i class="fa-solid fa-gear"></i><span><?= h(t('nav.settings')) ?></span></a>
    </nav>
  </aside>
  <div class="app-content">
    <header class="app-topbar">
      <button class="sidebar-toggle sidebar-toggle-inline" type="button" data-sidebar-toggle aria-label="Recolher menu">
        <i class="fa-solid fa-bars"></i>
      </button>
      <label class="sidebar-toggle sidebar-toggle-mobile" for="mobile-sidebar-toggle" aria-label="Abrir menu">
        <i class="fa-solid fa-bars"></i>
      </label>
      <div class="app-topbar-title">
        <span><?= h(t('app.title')) ?></span>
      </div>
      <div class="app-topbar-actions">
        <?php if (isAdmin() && $pendingInsumoSolicitationsCount > 0): ?>
          <button class="btn btn-sm btn-outline-warning app-notification-btn" type="button" data-bs-toggle="modal" data-bs-target="#insumoNotificationsModal" aria-label="Abrir mensagens de solicitações de insumo pendentes" title="<?= h(number_format($pendingInsumoSolicitationsCount, 0, ',', '.')) ?> solicitações pendentes">
            <i class="fa-solid fa-bell"></i>
            <span class="app-notification-count"><?= h(number_format($pendingInsumoSolicitationsCount, 0, ',', '.')) ?></span>
          </button>
        <?php endif; ?>
        <a class="btn btn-sm btn-outline-secondary app-theme-btn" href="toggletheme.php" aria-label="Alternar tema" title="<?= h($temaAtual === 'escuro' ? 'Mudar para tema claro' : 'Mudar para tema escuro') ?>">
          <i class="fa-solid <?= $temaAtual === 'escuro' ? 'fa-sun' : 'fa-moon' ?>"></i>
        </a>
        <a class="btn btn-sm btn-outline-danger app-logout-btn" href="logout.php">
          <i class="fa-solid fa-right-from-bracket me-1"></i><?= h(t('nav.logout')) ?>
        </a>
      </div>
    </header>
    <label class="sidebar-backdrop" for="mobile-sidebar-toggle" data-sidebar-backdrop aria-hidden="true"></label>

    <?php if (isAdmin()): ?>
      <div class="modal fade" id="insumoNotificationsModal" tabindex="-1" aria-labelledby="insumoNotificationsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content insumo-notification-modal">
            <div class="modal-header border-0 pb-0">
              <div>
                <p class="insumo-notification-kicker mb-1">Central de mensagens</p>
                <h5 class="modal-title" id="insumoNotificationsModalLabel">Solicitações de insumo por setor</h5>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body pt-3">
              <?php if (!empty($pendingInsumoNotifications)): ?>
                <div class="insumo-notification-list">
                  <?php foreach ($pendingInsumoNotifications as $notification): ?>
                    <div class="insumo-notification-item">
                      <div class="insumo-notification-icon">
                        <i class="fa-solid fa-layer-group"></i>
                      </div>
                      <div class="insumo-notification-content">
                        <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                          <div>
                            <h6 class="mb-1"><?= h((string)($notification['sector'] ?? 'Sem setor')) ?></h6>
                            <p class="mb-1 text-muted small">
                              <?= h(number_format((int)($notification['items_count'] ?? 0), 0, ',', '.')) ?> item<?= (int)($notification['items_count'] ?? 0) === 1 ? '' : 's' ?> aguardando atendimento
                            </p>
                          </div>
                          <span class="insumo-notification-badge badge rounded-pill text-bg-light border align-self-start">
                            <?= h(!empty($notification['batch_id']) ? 'Em lote' : 'Avulsa') ?>
                          </span>
                        </div>
                        <div class="insumo-notification-message">
                          Chegou uma solicitação do setor <?= h((string)($notification['sector'] ?? 'Sem setor')) ?>.
                          <?= !empty($notification['user_nome']) ? ' Solicitante: ' . h((string)$notification['user_nome']) . '.' : '' ?>
                          <?= !empty($notification['requested_at']) ? ' Recebida em ' . h(date('d/m/Y H:i', strtotime((string)$notification['requested_at']))) . '.' : '' ?>
                        </div>
                        <?php if (!empty($notification['data_solicitada_entrega'])): ?>
                          <div class="insumo-notification-meta small text-muted">
                            Entrega desejada: <?= h(date('d/m/Y', strtotime((string)$notification['data_solicitada_entrega']))) ?>
                          </div>
                        <?php endif; ?>
                        <div class="mt-2">
                          <a class="btn btn-sm btn-outline-primary" href="pedidos-insumos-pendentes.php?sector=<?= h(rawurlencode((string)($notification['sector'] ?? ''))) ?>">
                            <i class="fa-solid fa-arrow-right me-1"></i>Abrir fila deste setor
                          </a>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="insumo-notification-empty">
                  <div class="insumo-notification-icon">
                    <i class="fa-regular fa-circle-check"></i>
                  </div>
                  <div>
                    <h6 class="mb-1">Nenhuma solicitação pendente no momento</h6>
                    <p class="mb-0 text-muted">Quando um setor enviar um novo pedido, ele aparecerá aqui com o resumo da mensagem.</p>
                  </div>
                </div>
              <?php endif; ?>
            </div>
            <div class="modal-footer border-0 pt-0">
              <a href="pedidos-insumos-pendentes.php" class="btn btn-primary">
                <i class="fa-solid fa-arrow-right me-1"></i>Ver fila completa
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
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