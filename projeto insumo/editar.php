<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$pdo = getPDO();
require_once __DIR__ . '/i18n.php';
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error','ID inválido.'); header('Location: index.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM insumos_jnj WHERE id = ?');
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { flash('error','Material não encontrado.'); header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $posicao = trim($_POST['posicao'] ?? '');
  $lote = trim($_POST['lote'] ?? '');
    $quantidade = (int)($_POST['quantidade'] ?? 0);
    $data_contagem = $_POST['data_contagem'] ?? '';
    $unidade_selected = trim($_POST['unidade'] ?? '');
    $unidade = $unidade_selected;
    $data_entrada = $_POST['data_entrada'] ?? '';
    $validade = $_POST['validade'] ?? '';
    $observacoes = trim($_POST['observacoes'] ?? '');
    $erros = [];
    if ($nome === '') $erros[] = 'Nome é obrigatório.';
    if ($posicao === '') $erros[] = 'Posição é obrigatória.';
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
      $up = $pdo->prepare('UPDATE insumos_jnj SET nome=?, posicao=?, lote=?, quantidade=?, data_contagem=?, data_entrada=?, validade=?, observacoes=?, unidade=? WHERE id=?');
      $up->execute([$nome,$posicao,$lote,$quantidade,$formatted_data_contagem,$data_entrada,$validade,$observacoes,$unidade,$id]);
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
<h2 class="h4 mb-3"><i class="fa fa-pen me-2"></i><?= h(t('form.edit.title')) ?> #<?= h($item['id']) ?></h2>
<form method="post" class="row g-3 shadow-sm bg-white p-4 rounded">
  <div class="col-md-3">
    <label class="form-label"><?= h(t('form.count.date')) ?></label>
    <input type="date" name="data_contagem" class="form-control" value="<?= h($item['data_contagem'] ?? ($_POST['data_contagem'] ?? '')) ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label"><?= h(t('form.unit')) ?></label>
    <select name="unidade" class="form-control">
      <?php $opts = ['UN','BX','CENT','KG','MILH','PAC','ROLO']; foreach ($opts as $op): $sel = '';
        if (isset($_POST['unidade'])) { $sel = $_POST['unidade']===$op ? 'selected' : ''; }
        else { $sel = (isset($item['unidade']) && $item['unidade']===$op) ? 'selected' : ''; }
      ?>
        <option value="<?= h($op) ?>" <?= $sel ?>><?= h($op) ?></option>
      <?php endforeach; ?>
    </select>
    
  </div>
  <div class="col-md-6">
    <label class="form-label"><?= h(t('form.name')) ?> *</label>
    <input type="text" name="nome" class="form-control" required value="<?= h($item['nome']) ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label"><?= h(t('form.position')) ?> *</label>
    <input type="text" name="posicao" class="form-control" required value="<?= h($item['posicao']) ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label"><?= h(t('form.lot')) ?></label>
    <input type="text" name="lote" class="form-control" value="<?= h($item['lote'] ?? '') ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label"><?= h(t('form.quantity')) ?> *</label>
    <input type="number" name="quantidade" min="0" class="form-control" required value="<?= h($item['quantidade']) ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label"><?= h(t('form.entry.date')) ?> *</label>
    <input type="date" name="data_entrada" class="form-control" required value="<?= h($item['data_entrada']) ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label"><?= h(t('form.expiry')) ?> *</label>
    <input type="date" name="validade" class="form-control" required value="<?= h($item['validade']) ?>">
  </div>
  <div class="col-12">
    <label class="form-label"><?= h(t('form.notes')) ?></label>
    <textarea name="observacoes" rows="3" class="form-control"><?= h($item['observacoes']) ?></textarea>
  </div>
  <div class="col-12 d-flex justify-content-between align-items-center">
    <a href="index.php" class="btn btn-outline-secondary btn-rounded"><i class="fa fa-arrow-left me-1"></i><?= h(t('btn.back')) ?></a>
    <button class="btn btn-primary btn-rounded" type="submit"><i class="fa fa-save me-1"></i><?= h(t('btn.save.changes')) ?></button>
  </div>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>