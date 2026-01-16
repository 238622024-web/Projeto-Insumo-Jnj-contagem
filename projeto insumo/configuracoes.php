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
    $mostrar_lote = isset($_POST['mostrar_lote']) ? '1' : '0';

    setSetting('tema_padrao', $tema);
    setSetting('lang', in_array($lang, ['pt-br','en']) ? $lang : 'pt-br');
    if ($primary_color !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $primary_color)) {
      setSetting('primary_color', strtoupper($primary_color));
    }
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
$primaryColorAtual = getSetting('primary_color', '#b50000');
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
  <div class="mb-3">
    <label class="form-label">Cor Primária (hex)</label>
    <input type="color" name="primary_color" class="form-control" value="<?= h($primaryColorAtual) ?>">
    <div class="form-text">Escolha a cor principal (usa no cabeçalho, botões, destaques).</div>
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

<!-- Seção para limpar histórico de contagem -->
<div class="mt-5 p-4 bg-light border rounded">
  <h3 class="h5 mb-3"><i class="fa fa-trash me-2 text-danger"></i>Limpeza de Dados</h3>
  <p class="text-muted mb-3">Use esta opção com cuidado. As ações abaixo não podem ser desfeitas.</p>
  
  <div class="alert alert-warning mb-3" role="alert">
    <strong>⚠️ Aviso:</strong> Limpar o histórico de contagem removerá a data de contagem de todos os insumos no banco de dados.
  </div>

  <button type="button" class="btn btn-outline-danger btn-rounded" data-bs-toggle="modal" data-bs-target="#modalLimparHistorico">
    <i class="fa fa-trash-alt me-1"></i>Limpar Histórico de Contagem
  </button>

  <div class="alert alert-danger mt-4 mb-3" role="alert">
    <strong>⚠️ Ação Irreversível:</strong> Apagar todos os materiais removerá <u>todos os registros</u> da tabela e reiniciará a numeração.
  </div>
  <button type="button" class="btn btn-danger btn-rounded" data-bs-toggle="modal" data-bs-target="#modalApagarTodos">
    <i class="fa fa-ban me-1"></i>Apagar Todos os Materiais
  </button>

  <div class="alert alert-danger mt-4 mb-3" role="alert">
    <strong>⚠️ PERIGO: Apagar Banco Completo</strong> irá remover <u>todos os dados</u> (Materiais, Configurações e Usuários). Você terá que criar usuários novamente.
  </div>
  <button type="button" class="btn btn-danger btn-rounded" data-bs-toggle="modal" data-bs-target="#modalApagarBanco">
    <i class="fa fa-skull-crossbones me-1"></i>Apagar Banco Completo
  </button>
</div>

<!-- Modal de confirmação -->
<div class="modal fade" id="modalLimparHistorico" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="modalLabel"><i class="fa fa-exclamation-triangle me-2"></i>Confirmação</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p><strong>Você tem certeza que deseja apagar todo o histórico de contagem?</strong></p>
        <p>Esta ação irá remover a data de contagem de <strong>todos os insumos</strong> do banco de dados.</p>
        <p class="text-muted small">Nota: Os insumos em si não serão deletados, apenas as datas de contagem serão limpas.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <form method="post" action="limpar_historico.php" style="display: inline;">
          <input type="hidden" name="confirmar" value="sim">
          <button type="submit" class="btn btn-danger">
            <i class="fa fa-trash me-1"></i>Sim, apagar tudo
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="mt-4 small text-muted">Outras configurações poderão ser adicionadas futuramente.</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

  <!-- Modal de confirmação: Apagar Todos -->
  <div class="modal fade" id="modalApagarTodos" tabindex="-1" aria-labelledby="modalApagarTodosLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="modalApagarTodosLabel"><i class="fa fa-exclamation-triangle me-2"></i>Apagar Todos os Materiais</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p><strong>Tem certeza que deseja apagar TODOS os materiais?</strong></p>
          <p>Esta ação irá remover permanentemente todos os registros da tabela <code>insumos_jnj</code>.</p>
          <p class="text-muted small">Dica: Faça um backup antes se precisar manter histórico. Veja a pasta <code>db_backups</code>.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <form method="post" action="limpar_historico.php" style="display: inline;">
            <input type="hidden" name="confirmar" value="sim">
            <input type="hidden" name="tipo" value="todos">
            <button type="submit" class="btn btn-danger">
              <i class="fa fa-ban me-1"></i>Sim, apagar tudo
            </button>
          </form>
        </div>
      </div>
    </div>

      <!-- Modal de confirmação: Apagar Banco Completo -->
      <div class="modal fade" id="modalApagarBanco" tabindex="-1" aria-labelledby="modalApagarBancoLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header bg-danger text-white">
              <h5 class="modal-title" id="modalApagarBancoLabel"><i class="fa fa-exclamation-triangle me-2"></i>Apagar Banco Completo</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <p><strong>Tem certeza que deseja apagar TODO o banco de dados do sistema?</strong></p>
              <p>Serão removidos permanentemente todos os materiais, configurações e usuários. IDs serão reiniciados.</p>
              <p class="text-muted small">Dica: Faça backup antes (veja a pasta <code>db_backups</code>).</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <form method="post" action="limpar_historico.php" style="display: inline;">
                <input type="hidden" name="confirmar" value="sim">
                <input type="hidden" name="tipo" value="banco">
                <button type="submit" class="btn btn-danger">
                  <i class="fa fa-skull-crossbones me-1"></i>Sim, apagar banco completo
                </button>
              </form>
            </div>
          </div>
        </div>
        </div>
    </div>