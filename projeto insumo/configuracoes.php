<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tema = $_POST['tema'] ?? 'claro';
  $lang = $_POST['lang'] ?? 'pt-br';
    $itens = (int)($_POST['itens_pagina'] ?? 25);
    $val_curta = (int)($_POST['alerta_validade_curta'] ?? 7);
    $val_media = (int)($_POST['alerta_validade_media'] ?? 30);
    $mostrar_lote = isset($_POST['mostrar_lote']) ? '1' : '0';

    setSetting('tema_padrao', $tema);
    setSetting('lang', in_array($lang, ['pt-br','en']) ? $lang : 'pt-br');
    setSetting('itens_pagina', max(5, min(200, $itens)));
    setSetting('alerta_validade_curta', max(0, $val_curta));
    setSetting('alerta_validade_media', max(0, $val_media));
    setSetting('mostrar_lote', $mostrar_lote);

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
                setSetting('logo_path', $destRel);
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
    if ($logo_url_input !== '') setSetting('logo_url', $logo_url_input);
    else setSetting('logo_url', '');
    flash('success','Configurações salvas.');
    header('Location: configuracoes.php');
    exit;
}
$temaAtual = getSetting('tema_padrao', $_SESSION['tema_jnj'] ?? 'claro');
$langAtual = getSetting('lang', $_SESSION['lang_jnj'] ?? 'pt-br');
$itensAtual = (int)getSetting('itens_pagina', 25);
$valCurta = (int)getSetting('alerta_validade_curta', 7);
$valMedia = (int)getSetting('alerta_validade_media', 30);
$mostrarLote = getSetting('mostrar_lote', '1') === '1';
// agora inclui o header (após o processamento POST e possíveis redirecionamentos)
include __DIR__ . '/includes/header.php';
?>
<h2 class="h4 mb-3"><i class="fa fa-gear me-2"></i>Configurações</h2>
<form method="post" enctype="multipart/form-data" class="shadow-sm bg-white p-4 rounded">
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
    <div class="form-text">Define o idioma preferido. (Textos principais ainda estão em Português)</div>
  </div>
  <div class="row g-3">
    <div class="col-md-4">
      <label class="form-label">Itens por página</label>
      <select name="itens_pagina" class="form-select">
        <?php foreach ([10,25,50,100] as $opt): ?>
          <option value="<?= $opt ?>" <?= $itensAtual==$opt?'selected':'' ?>><?= $opt ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Aviso validade curta (dias)</label>
      <input type="number" name="alerta_validade_curta" class="form-control" min="0" value="<?= $valCurta ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Aviso validade média (dias)</label>
      <input type="number" name="alerta_validade_media" class="form-control" min="0" value="<?= $valMedia ?>">
    </div>
  </div>

  <div class="form-check form-switch my-3">
    <input class="form-check-input" type="checkbox" id="mostrarLote" name="mostrar_lote" <?= $mostrarLote?'checked':'' ?>>
    <label class="form-check-label" for="mostrarLote">Mostrar coluna "Lote" na listagem</label>
  </div>

  <div class="mb-3">
    <label class="form-label">Logo do sistema (SVG/PNG/JPG)</label>
    <input type="file" name="logo" accept=".svg,.png,.jpg,.jpeg" class="form-control">
    <div class="form-text">O arquivo será salvo em assets/uploads/.</div>
  </div>
  <div class="mb-3">
    <label class="form-label">Ou URL do logo (http(s)://...)</label>
    <input type="url" name="logo_url" class="form-control" placeholder="https://example.com/logo.png" value="<?= h(getSetting('logo_url','')) ?>">
    <div class="form-text">Se preencher uma URL, a imagem será usada diretamente (prioridade menor que upload).</div>
  </div>
  <button class="btn btn-primary btn-rounded" type="submit"><i class="fa fa-save me-1"></i>Salvar</button>
</form>
<div class="mt-4 small text-muted">Outras configurações poderão ser adicionadas futuramente.</div>
<?php include __DIR__ . '/includes/footer.php'; ?>