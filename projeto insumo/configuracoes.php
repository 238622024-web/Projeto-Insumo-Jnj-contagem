<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tema = $_POST['tema'] ?? 'claro';
  $lang = $_POST['lang'] ?? 'pt-br';
  $primary_color = trim($_POST['primary_color'] ?? '');
    $itens = (int)($_POST['itens_pagina'] ?? 25);
    $val_curta = (int)($_POST['alerta_validade_curta'] ?? 7);
    $val_media = (int)($_POST['alerta_validade_media'] ?? 30);
    $temp_expiry_hours = (int)($_POST['temp_password_expiry_hours'] ?? 24);
    $mostrar_lote = isset($_POST['mostrar_lote']) ? '1' : '0';

    $saveErrors = [];

    if (!setSetting('tema_padrao', $tema)) { $saveErrors[] = 'tema_padrao'; }
    if (!setSetting('lang', in_array($lang, ['pt-br','en']) ? $lang : 'pt-br')) { $saveErrors[] = 'lang'; }
    if ($primary_color !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $primary_color)) {
      if (!setSetting('primary_color', strtoupper($primary_color))) { $saveErrors[] = 'primary_color'; }
    }
    if (!setSetting('itens_pagina', max(5, min(200, $itens)))) { $saveErrors[] = 'itens_pagina'; }
    if (!setSetting('alerta_validade_curta', max(0, $val_curta))) { $saveErrors[] = 'alerta_validade_curta'; }
    if (!setSetting('alerta_validade_media', max(0, $val_media))) { $saveErrors[] = 'alerta_validade_media'; }
    if (!setSetting('temp_password_expiry_hours', max(1, min(168, $temp_expiry_hours)))) { $saveErrors[] = 'temp_password_expiry_hours'; }
    if (!setSetting('mostrar_lote', $mostrar_lote)) { $saveErrors[] = 'mostrar_lote'; }

    // Upload de logo opcional
    if (!empty($_FILES['logo']['name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $permitidas = ['svg','png','jpg','jpeg'];
        if (in_array($ext, $permitidas)) {
            $dir = __DIR__ . '/assets/uploads';
            if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
            $destRel = 'assets/uploads/logo_custom.' . $ext;
            $destAbs = __DIR__ . '/' . $destRel;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $destAbs)) {
              if (!setSetting('logo_path', $destRel)) { $saveErrors[] = 'logo_path'; }
            } else {
                flash('error', 'Falha ao enviar o logo.');
            }
        } else {
            flash('error', 'Formato de logo não suportado. Use SVG, PNG ou JPG.');
        }
    }

    // também refletir o tema atual na sessão
    $_SESSION['tema_jnj'] = $tema;
    $_SESSION['lang_jnj'] = $lang;
    // salvar URL do logo (se informado)
    $logo_url_input = trim($_POST['logo_url'] ?? '');
    if ($logo_url_input !== '') {
      if (!setSetting('logo_url', $logo_url_input)) { $saveErrors[] = 'logo_url'; }
    } else {
      if (!setSetting('logo_url', '')) { $saveErrors[] = 'logo_url'; }
    }

    if (empty($saveErrors)) {
      flash('success','Configurações salvas.');
    } else {
      flash('error','Não foi possível salvar algumas configurações. Verifique conexão/permissões do banco de dados.');
    }
    header('Location: configuracoes.php');
    exit;
}
$temaAtual = getSetting('tema_padrao', $_SESSION['tema_jnj'] ?? 'claro');
$langAtual = getSetting('lang', $_SESSION['lang_jnj'] ?? 'pt-br');
$primaryColorAtual = getSetting('primary_color', '#b50000');
$itensAtual = (int)getSetting('itens_pagina', 25);
$valCurta = (int)getSetting('alerta_validade_curta', 7);
$valMedia = (int)getSetting('alerta_validade_media', 30);
$tempPasswordExpiryHours = (int)getSetting('temp_password_expiry_hours', 24);
$tempPasswordExpiryHours = max(1, min(168, $tempPasswordExpiryHours));
$mostrarLote = getSetting('mostrar_lote', '1') === '1';
$zipDisponivel = class_exists('ZipArchive');
$logoPathAtual = getSetting('logo_path', '');
$logoUrlAtual = getSetting('logo_url', '');
$logoPreviewUrl = '';
if (!empty($logoPathAtual)) {
  $logoAbs = realpath(__DIR__ . '/' . ltrim($logoPathAtual, '/'));
  if ($logoAbs && file_exists($logoAbs)) {
    $logoPreviewUrl = $logoPathAtual;
  }
}
if ($logoPreviewUrl === '' && !empty($logoUrlAtual) && preg_match('/^https?:\/\//i', $logoUrlAtual)) {
  $logoPreviewUrl = $logoUrlAtual;
}
// agora inclui o header (após o processamento POST e possíveis redirecionamentos)
include __DIR__ . '/includes/header.php';
?>
<div class="settings-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="solicitacoes-kicker">Painel administrativo</span>
          <h1 class="display-6 fw-semibold mb-2">Configurações</h1>
          <p class="solicitacoes-subtitle mb-0">Ajuste aparência, paginação, alertas e identidade visual do sistema em um só lugar.</p>
        </div>
        <div class="text-lg-end d-flex flex-column gap-2 align-items-lg-end">
          <span class="solicitacoes-pill"><i class="fa-solid fa-wand-magic-sparkles"></i>Configuração global</span>
          <small class="text-muted d-block">Tema, idioma e marca afetam toda a aplicação.</small>
        </div>
      </div>
    </div>
  </section>

  <form method="post" enctype="multipart/form-data" class="settings-form">
    <div class="row g-4">
      <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100 profile-section-card">
          <div class="card-body">
            <h2 class="h5 mb-3"><i class="fa-solid fa-palette me-2 text-primary"></i>Aparência</h2>
            <div class="mb-3">
              <label class="form-label">Tema</label>
              <select name="tema" class="form-select">
                <option value="claro" <?= $temaAtual==='claro'?'selected':'' ?>>Claro</option>
                <option value="escuro" <?= $temaAtual==='escuro'?'selected':'' ?>>Escuro</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Idioma</label>
              <select name="lang" class="form-select">
                <option value="pt-br" <?= $langAtual==='pt-br'?'selected':'' ?>>Português (Brasil)</option>
                <option value="en" <?= $langAtual==='en'?'selected':'' ?>>English</option>
              </select>
              <div class="form-text">O sistema já usa esse idioma no shell e nas preferências do usuário.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Cor primária</label>
              <div class="d-flex gap-3 align-items-center flex-wrap">
                <input type="color" name="primary_color" class="form-control form-control-color settings-color-input" value="<?= h($primaryColorAtual) ?>" aria-label="Cor primária">
                <div class="settings-color-preview" style="background: <?= h($primaryColorAtual) ?>;">
                  <strong><?= h($primaryColorAtual) ?></strong>
                </div>
              </div>
              <div class="form-text">Usada em botões, destaques e cabeçalho.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100 profile-section-card">
          <div class="card-body">
            <h2 class="h5 mb-3"><i class="fa-solid fa-sliders me-2 text-primary"></i>Listagem e alertas</h2>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Itens por página</label>
                <select name="itens_pagina" class="form-select">
                  <?php foreach ([10,25,50,100] as $opt): ?>
                    <option value="<?= $opt ?>" <?= $itensAtual==$opt?'selected':'' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-md-6 col-lg-12">
                <label class="form-label">Aviso validade curta (dias)</label>
                <input type="number" name="alerta_validade_curta" class="form-control" min="0" value="<?= $valCurta ?>">
              </div>
              <div class="col-12 col-md-6 col-lg-12">
                <label class="form-label">Aviso validade média (dias)</label>
                <input type="number" name="alerta_validade_media" class="form-control" min="0" value="<?= $valMedia ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Validade senha temporária (horas)</label>
                <input type="number" name="temp_password_expiry_hours" class="form-control" min="1" max="168" value="<?= $tempPasswordExpiryHours ?>">
                <div class="form-text">Define por quantas horas a senha temporária liberada pelo admin permanece válida.</div>
              </div>
            </div>
            <div class="form-check form-switch mt-3">
              <input class="form-check-input" type="checkbox" id="mostrarLote" name="mostrar_lote" <?= $mostrarLote?'checked':'' ?>>
              <label class="form-check-label" for="mostrarLote">Mostrar coluna "Lote" na listagem</label>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100 profile-section-card">
          <div class="card-body">
            <h2 class="h5 mb-3"><i class="fa-solid fa-badge-check me-2 text-primary"></i>Marca</h2>
            <div class="mb-3">
              <label class="form-label">Logo da aplicação</label>
              <input type="file" name="logo" class="form-control" accept=".svg,.png,.jpg,.jpeg">
              <div class="form-text">Envie um arquivo novo para substituir o logo usado no sistema.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">URL do logo</label>
              <input type="url" name="logo_url" class="form-control" value="<?= h($logoUrlAtual) ?>" placeholder="https://...">
              <div class="form-text">Útil para apontar para um logo hospedado externamente.</div>
            </div>
            <div class="settings-logo-preview card border-0 shadow-sm">
              <div class="card-body text-center">
                <?php if ($logoPreviewUrl !== ''): ?>
                  <img src="<?= h($logoPreviewUrl) ?>" alt="Prévia do logo" class="settings-logo-image mb-3">
                  <div class="small text-muted">Prévia do logo atual</div>
                <?php else: ?>
                  <div class="settings-logo-placeholder mb-3">
                    <i class="fa-solid fa-image"></i>
                  </div>
                  <div class="small text-muted">Nenhum logo personalizado definido</div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="d-flex flex-column flex-md-row justify-content-end gap-2">
          <a href="index.php" class="btn btn-outline-secondary btn-rounded">
            <i class="fa-solid fa-arrow-left me-1"></i>Voltar
          </a>
          <button class="btn btn-primary btn-rounded" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Salvar configurações</button>
        </div>
      </div>
    </div>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>