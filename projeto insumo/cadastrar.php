<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $posicao = trim($_POST['posicao'] ?? '');
  $lote = trim($_POST['lote'] ?? '');
    $quantidade = (int)($_POST['quantidade'] ?? 0);
    $data_contagem = $_POST['data_contagem'] ?? '';
    // unidade: select with options; allow optional custom override
    $unidade_selected = trim($_POST['unidade'] ?? '');
    $unidade = $unidade_selected;
    $data_entrada = $_POST['data_entrada'] ?? '';
    $validade = $_POST['validade'] ?? '';
    $observacoes = trim($_POST['observacoes'] ?? '');
    $erros = [];
    if ($nome === '') $erros[] = 'Nome é obrigatório.';
    if ($posicao === '') $erros[] = 'Posição é obrigatória.';
    if ($quantidade < 0) $erros[] = 'Quantidade inválida.';
    if ($data_entrada === '') $erros[] = 'Data de entrada obrigatória.';
    if ($validade === '') $erros[] = 'Validade obrigatória.';

    // Validação/normalização de datas: aceita Y-m-d e d/m/Y e converte para Y-m-d
    function parseDateToYmd($input) {
      $s = trim((string)$input);
      if ($s === '') return false;
      $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'];
      foreach ($formats as $fmt) {
        $d = DateTime::createFromFormat($fmt, $s);
        if ($d && $d->format($fmt) === $s) {
          $year = (int)$d->format('Y');
          if ($year < 1900 || $year > 3000) return false;
          return $d->format('Y-m-d');
        }
      }
      // fallback para strings strtotime-like
      $ts = strtotime($s);
      if ($ts !== false) {
        $d = new DateTime("@".$ts);
        $d->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $year = (int)$d->format('Y');
        if ($year >= 1900 && $year <= 3000) return $d->format('Y-m-d');
      }
      return false;
    }

    if (!$erros) {
      $formatted_data_contagem = $data_contagem !== '' ? parseDateToYmd($data_contagem) : null;
      $formatted_data_entrada = parseDateToYmd($data_entrada);
      $formatted_validade = parseDateToYmd($validade);
      if ($formatted_data_entrada === false) $erros[] = 'Data de entrada inválida.';
      if ($formatted_validade === false) $erros[] = 'Validade inválida.';
      if ($data_contagem !== '' && $formatted_data_contagem === false) $erros[] = 'Data de contagem inválida.';
    }

    if (!$erros) {
      $stmt = $pdo->prepare("INSERT INTO insumos_jnj (nome,posicao,lote,quantidade,data_contagem,data_entrada,validade,observacoes,unidade) VALUES (?,?,?,?,?,?,?,?,?)");
      $stmt->execute([$nome, $posicao, $lote, $quantidade, $formatted_data_contagem, $formatted_data_entrada, $formatted_validade, $observacoes, $unidade]);
      flash('success', 'Material cadastrado com sucesso!');
      header('Location: index.php');
      exit;
    } else {
      flash('error', implode(' ', $erros));
    }
}
include __DIR__ . '/includes/header.php';
?>
<div class="cadastro-insumo">
<h2 class="h4 mb-3"><i class="fa fa-plus me-2"></i>Novo Material</h2>
<form method="post" class="row g-3 shadow-sm bg-white p-4 rounded">
  <div class="col-md-3">
    <label class="form-label">Data de Contagem</label>
    <input type="date" name="data_contagem" class="form-control" value="<?= h($_POST['data_contagem'] ?? '') ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label">Unidade</label>
    <select name="unidade" class="form-control">
      <?php $opts = ['UN','BX','CENT','KG','MILH','PAC','ROLO']; foreach ($opts as $op): ?>
        <option value="<?= h($op) ?>" <?= (isset($_POST['unidade']) && $_POST['unidade']===$op)?'selected':'' ?>><?= h($op) ?></option>
      <?php endforeach; ?>
    </select>
    
  </div>
  <div class="col-md-6">
    <label class="form-label">Nome do material *</label>
    <input type="text" name="nome" class="form-control" required value="<?= h($_POST['nome'] ?? '') ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label">Posição *</label>
    <input type="text" name="posicao" class="form-control" placeholder="P01" required value="<?= h($_POST['posicao'] ?? '') ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label">Lote</label>
    <input type="text" name="lote" class="form-control" placeholder="L1234" value="<?= h($_POST['lote'] ?? '') ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label">Quantidade *</label>
    <input type="number" name="quantidade" min="0" class="form-control" required value="<?= h($_POST['quantidade'] ?? 0) ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label">Data de Entrada *</label>
    <input type="date" name="data_entrada" class="form-control" required value="<?= h($_POST['data_entrada'] ?? date('Y-m-d')) ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label">Validade *</label>
    <input type="date" name="validade" class="form-control" required value="<?= h($_POST['validade'] ?? '') ?>">
  </div>
  <div class="col-12">
    <label class="form-label">Observações</label>
    <textarea name="observacoes" rows="3" class="form-control"><?= h($_POST['observacoes'] ?? '') ?></textarea>
  </div>
  <div class="col-12 d-flex justify-content-between align-items-center">
    <a href="index.php" class="btn btn-outline-secondary btn-rounded"><i class="fa fa-arrow-left me-1"></i>Voltar</a>
    <button class="btn btn-primary btn-rounded" type="submit"><i class="fa fa-save me-1"></i>Salvar</button>
  </div>
</form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>