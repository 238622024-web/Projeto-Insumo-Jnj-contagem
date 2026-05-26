<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$pdo = getPDO();
require_once __DIR__ . '/i18n.php';
$nomesInsumos = require __DIR__ . '/materiais-lista.php';
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error','ID inválido.'); header('Location: index.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM insumos_jnj WHERE id = ?');
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { flash('error','Material não encontrado.'); header('Location: index.php'); exit; }
$nomeAtual = (string)($item['nome'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $posicao = trim($_POST['posicao'] ?? '');
  $lote = trim($_POST['lote'] ?? '');
    $codigo_barra = trim($_POST['codigo_barra'] ?? '');
    $quantidadeRaw = trim((string)($_POST['quantidade'] ?? ''));
    $quantidadeDigits = preg_replace('/\D+/', '', $quantidadeRaw);
    $quantidade = ($quantidadeDigits === '') ? -1 : (int)$quantidadeDigits;
    $data_contagem = $_POST['data_contagem'] ?? '';
    $unidade_selected = trim($_POST['unidade'] ?? '');
    $unidade = $unidade_selected;
    $data_entrada = $_POST['data_entrada'] ?? '';
    $validade = $_POST['validade'] ?? '';
    $observacoes = trim($_POST['observacoes'] ?? '');
    $erros = [];
    if ($nome === '') $erros[] = 'Nome é obrigatório.';
    if ($posicao === '') $erros[] = 'Posição é obrigatória.';
    if ($quantidade < 0) $erros[] = 'Quantidade inválida. Use apenas números (ex.: 1.000).';
    if ($data_entrada === '') $erros[] = 'Data de entrada obrigatória.';
    if ($validade === '') $erros[] = 'Validade obrigatória.';
    if (!$erros) {
      // valida e formata data de contagem se informada
      $formatted_data_contagem = $data_contagem !== '' ? (function($s){
        $d = DateTime::createFromFormat('Y-m-d', $s);
        return $d ? $d->format('Y-m-d') : false;
      })($data_contagem) : null;
      if ($data_contagem !== '' && $formatted_data_contagem === false) { $erros[] = 'Data de contagem inválida.'; }
    }

    if (!$erros) {
      $up = $pdo->prepare('UPDATE insumos_jnj SET nome=?, posicao=?, lote=?, codigo_barra=?, quantidade=?, data_contagem=?, data_entrada=?, validade=?, observacoes=?, unidade=? WHERE id=?');
      $up->execute([$nome,$posicao,$lote,$codigo_barra,$quantidade,$formatted_data_contagem,$data_entrada,$validade,$observacoes,$unidade,$id]);
        flash('success','Material atualizado com sucesso!');
        header('Location: index.php');
        exit;
    } else {
        flash('error', implode(' ', $erros));
        $item = array_merge($item, $_POST); // repopula
    }
}
include __DIR__ . '/includes/header.php';
?>
<div class="card shadow-lg border-0">
  <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2" style="background: linear-gradient(135deg, var(--color-primary) 0%, #7f0f16 100%); color: #fff; padding: 1rem 1.25rem;">
    <h2 class="h5 mb-0"><i class="fa-solid fa-pen-to-square me-2"></i><?= h(t('form.edit.title')) ?> #<?= h($item['id']) ?></h2>
  </div>
  <div class="card-body p-3 p-md-4">
    <form method="post" class="row g-3 form-responsive">
      <div class="col-12">
        <label class="form-label fw-semibold"><i class="fa-solid fa-box me-2 text-primary"></i><?= h(t('form.name')) ?></label>
        <select name="nome" class="form-select form-select-lg" required>
          <option value="">Selecione o material</option>
          <?php foreach ($nomesInsumos as $nomeItem): ?>
            <option value="<?= h($nomeItem) ?>" <?= (($_POST['nome'] ?? $nomeAtual) === $nomeItem) ? 'selected' : '' ?>><?= h($nomeItem) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-4 col-lg-2">
        <label class="form-label fw-semibold"><i class="fa-solid fa-calendar-days me-2 text-primary"></i><?= h(t('form.count.date')) ?></label>
        <input type="date" name="data_contagem" class="form-control" value="<?= h($item['data_contagem'] ?? ($_POST['data_contagem'] ?? '')) ?>">
      </div>
      <div class="col-12 col-md-4 col-lg-2">
        <label class="form-label fw-semibold"><i class="fa-solid fa-ruler-vertical me-2 text-primary"></i><?= h(t('form.unit')) ?></label>
        <select name="unidade" class="form-select">
      <?php $opts = ['UN','BX','CENT','KG','MILH','PAC','ROLO']; foreach ($opts as $op): $sel = '';
        if (isset($_POST['unidade'])) { $sel = $_POST['unidade']===$op ? 'selected' : ''; }
        else { $sel = (isset($item['unidade']) && $item['unidade']===$op) ? 'selected' : ''; }
      ?>
        <option value="<?= h($op) ?>" <?= $sel ?>><?= h($op) ?></option>
      <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-4 col-lg-2">
        <label class="form-label fw-semibold"><i class="fa-solid fa-location-dot me-2 text-primary"></i><?= h(t('form.position')) ?></label>
        <input type="text" name="posicao" class="form-control" required value="<?= h($item['posicao']) ?>">
      </div>
      <div class="col-12 col-md-4 col-lg-2">
        <label class="form-label fw-semibold"><i class="fa-solid fa-calculator me-2 text-primary"></i><?= h(t('form.quantity')) ?></label>
        <input type="text" name="quantidade" inputmode="numeric" pattern="[0-9. ]+" class="form-control" required value="<?= h(isset($_POST['quantidade']) ? (string)$_POST['quantidade'] : number_format((int)($item['quantidade'] ?? 0), 0, ',', '.')) ?>" placeholder="Ex.: 1.000">
      </div>

      <div class="col-12 col-md-4 col-lg-3">
        <label class="form-label fw-semibold"><i class="fa-solid fa-boxes-stacked me-2 text-primary"></i><?= h(t('form.lot')) ?></label>
        <input type="text" name="lote" class="form-control" value="<?= h($item['lote'] ?? '') ?>">
      </div>
      <div class="col-12 col-md-8 col-lg-3">
        <label class="form-label fw-semibold"><i class="fa-solid fa-qrcode me-2 text-primary"></i>Código de barras</label>
        <input type="text" name="codigo_barra" class="form-control" value="<?= h($item['codigo_barra'] ?? '') ?>" placeholder="Escaneie ou digite">
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label fw-semibold"><i class="fa-solid fa-right-to-bracket me-2 text-primary"></i><?= h(t('form.entry.date')) ?></label>
        <input type="date" name="data_entrada" class="form-control" required value="<?= h($item['data_entrada']) ?>">
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label fw-semibold"><i class="fa-solid fa-circle-exclamation me-2 text-primary"></i><?= h(t('form.expiry')) ?></label>
        <input type="date" name="validade" class="form-control" required value="<?= h($item['validade']) ?>">
      </div>

      <div class="col-12">
        <label class="form-label fw-semibold"><i class="fa-solid fa-pen-to-square me-2 text-primary"></i><?= h(t('form.notes')) ?></label>
        <textarea name="observacoes" rows="3" class="form-control"><?= h($item['observacoes']) ?></textarea>
      </div>

      <div class="col-12 d-flex flex-column flex-md-row justify-content-between align-items-stretch gap-2 form-actions-responsive pt-1">
        <a href="index.php" class="btn btn-outline-secondary btn-rounded w-100 w-md-auto"><i class="fa-solid fa-arrow-left me-1"></i><?= h(t('btn.back')) ?></a>
        <button class="btn btn-primary btn-rounded w-100 w-md-auto" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i><?= h(t('btn.save.changes')) ?></button>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>