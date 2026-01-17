<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$pdo = getPDO();
require_once __DIR__ . '/i18n.php';

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
    // Validade: se não informada, vamos calcular depois (2 anos após data_entrada)

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
      if ($formatted_data_entrada === false) $erros[] = 'Data de entrada inválida.';
      // Calcular/validar validade: se vazio, define +2 anos da data_entrada
      if (!$erros) {
        if (trim($validade) === '') {
          $d = DateTime::createFromFormat('Y-m-d', $formatted_data_entrada);
          if ($d) { $d->modify('+2 years'); $formatted_validade = $d->format('Y-m-d'); }
          else { $erros[] = 'Data de entrada inválida.'; }
        } else {
          $formatted_validade = parseDateToYmd($validade);
          if ($formatted_validade === false) $erros[] = 'Validade inválida.';
        }
      }
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
  <div class="card shadow-lg border-0">
    <div class="card-header d-flex align-items-center" style="background: #ffffff; color: #000000; padding: 1.5rem; border-bottom: 2px solid #e9ecef;">
      <h2 class="h5 mb-0" style="color: #000000;"><i class="fa fa-plus me-2"></i><?= h(t('form.new.title')) ?></h2>
    </div>
    <div class="card-body p-4">
      <form method="post" class="row g-4">
        <!-- Nome do Material -->
        <div class="col-12">
          <label class="form-label fw-600"><i class="fa fa-box text-primary me-2"></i><?= h(t('form.name')) ?> <span class="text-danger">*</span></label>
          <input type="text" name="nome" class="form-control form-control-lg" required value="<?= h($_POST['nome'] ?? '') ?>">
        </div>

        <!-- Linha 1: Unidade, Quantidade, Posição -->
        <div class="col-12 col-md-4">
          <label class="form-label fw-600"><i class="fa fa-ruler text-primary me-2"></i><?= h(t('form.unit')) ?></label>
          <select name="unidade" class="form-select form-select-lg">
            <?php $opts = ['UN','BX','CENT','KG','MILH','PAC','ROLO']; foreach ($opts as $op): ?>
              <option value="<?= h($op) ?>" <?= (isset($_POST['unidade']) && $_POST['unidade']===$op)?'selected':'' ?>><?= h($op) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label fw-600"><i class="fa fa-calculator text-primary me-2"></i><?= h(t('form.quantity')) ?> <span class="text-danger">*</span></label>
          <input type="number" name="quantidade" min="0" class="form-control form-control-lg" required value="<?= h($_POST['quantidade'] ?? '') ?>">
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label fw-600"><i class="fa fa-map-marker text-primary me-2"></i><?= h(t('form.position')) ?> <span class="text-danger">*</span></label>
          <input type="text" name="posicao" class="form-control form-control-lg" required value="<?= h($_POST['posicao'] ?? '') ?>">
        </div>

        <!-- Linha 2: Lote, Data Contagem, Data Entrada, Validade -->
        <div class="col-12 col-md-3">
          <label class="form-label fw-600"><i class="fa fa-barcode text-primary me-2"></i><?= h(t('form.lot')) ?></label>
          <input type="text" name="lote" class="form-control form-control-lg" value="<?= h($_POST['lote'] ?? '') ?>">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label fw-600"><i class="fa fa-calendar text-primary me-2"></i><?= h(t('form.count.date')) ?></label>
          <input type="date" name="data_contagem" class="form-control form-control-lg" value="<?= h($_POST['data_contagem'] ?? '') ?>">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label fw-600"><i class="fa fa-sign-in text-primary me-2"></i><?= h(t('form.entry.date')) ?> <span class="text-danger">*</span></label>
          <input type="date" name="data_entrada" class="form-control form-control-lg" required value="<?= h($_POST['data_entrada'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label fw-600"><i class="fa fa-hourglass-end text-primary me-2"></i><?= h(t('form.expiry')) ?> <span class="text-danger">*</span></label>
          <input type="date" name="validade" class="form-control form-control-lg" value="<?= h($_POST['validade'] ?? '') ?>">
        </div>

        <!-- Observações -->
        <div class="col-12">
          <label class="form-label fw-600"><i class="fa fa-sticky-note text-primary me-2"></i><?= h(t('form.notes')) ?></label>
          <textarea name="observacoes" rows="4" class="form-control form-control-lg" style="resize: vertical;"><?= h($_POST['observacoes'] ?? '') ?></textarea>
        </div>

        <!-- Botões de Ação -->
        <div class="col-12 d-flex justify-content-between gap-2 mt-4">
          <a href="index.php" class="btn btn-outline-secondary btn-lg" style="transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.15)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
            <i class="fa fa-arrow-left me-2"></i><?= h(t('btn.back')) ?>
          </a>
          <button class="btn btn-primary btn-lg" type="submit" style="transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 12px rgba(102,126,234,0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
            <i class="fa fa-save me-2"></i><?= h(t('btn.save')) ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<script src="assets/js/cadastro.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>