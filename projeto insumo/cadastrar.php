<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$pdo = getPDO();
require_once __DIR__ . '/i18n.php';
$nomesInsumos = require __DIR__ . '/materiais-lista.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $posicao = trim($_POST['posicao'] ?? '');
  $lote = trim($_POST['lote'] ?? '');
    $codigo_barra = trim($_POST['codigo_barra'] ?? '');
    $quantidadeRaw = trim((string)($_POST['quantidade'] ?? ''));
    $quantidadeDigits = preg_replace('/\D+/', '', $quantidadeRaw);
    $quantidade = ($quantidadeDigits === '') ? -1 : (int)$quantidadeDigits;
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
    if ($quantidade < 0) $erros[] = 'Quantidade inválida. Use apenas números (ex.: 1.000).';
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
      $stmt = $pdo->prepare("INSERT INTO insumos_jnj (nome,posicao,lote,codigo_barra,quantidade,data_contagem,data_entrada,validade,observacoes,unidade) VALUES (?,?,?,?,?,?,?,?,?,?)");
      $stmt->execute([$nome, $posicao, $lote, $codigo_barra, $quantidade, $formatted_data_contagem, $formatted_data_entrada, $formatted_validade, $observacoes, $unidade]);
      flash('success', 'Material cadastrado com sucesso!');
      header('Location: cadastrar.php');
      exit;
    } else {
      flash('error', implode(' ', $erros));
    }
}
include __DIR__ . '/includes/header.php';
?>
<div class="card shadow-lg border-0">
  <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2" style="background: linear-gradient(135deg, var(--color-primary) 0%, #7f0f16 100%); color: #fff; padding: 1rem 1.25rem;">
    <h2 class="h5 mb-0"><i class="fa-solid fa-circle-plus me-2"></i><?= h(t('form.new.title')) ?></h2>
  </div>
  <div class="card-body p-3 p-md-4">
    <form id="form-cadastro-insumo" method="post" class="row g-3 form-responsive">
      <div class="col-12">
        <label class="form-label fw-semibold"><i class="fa-solid fa-box me-2 text-primary"></i><?= h(t('form.name')) ?></label>
        <select name="nome" class="form-select form-select-lg" required>
          <option value="">Selecione o material</option>
          <?php foreach ($nomesInsumos as $nomeItem): ?>
            <option value="<?= h($nomeItem) ?>" <?= (($_POST['nome'] ?? '') === $nomeItem) ? 'selected' : '' ?>><?= h($nomeItem) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-4 col-lg-2">
        <label class="form-label fw-semibold"><i class="fa-solid fa-calendar-days me-2 text-primary"></i><?= h(t('form.count.date')) ?></label>
        <input type="date" name="data_contagem" class="form-control" value="<?= h($_POST['data_contagem'] ?? '') ?>">
      </div>
      <div class="col-12 col-md-4 col-lg-2">
        <label class="form-label fw-semibold"><i class="fa-solid fa-ruler-vertical me-2 text-primary"></i><?= h(t('form.unit')) ?></label>
        <select name="unidade" class="form-select">
          <?php $opts = ['UN','BX','CENT','KG','MILH','PAC','ROLO']; foreach ($opts as $op): ?>
            <option value="<?= h($op) ?>" <?= (isset($_POST['unidade']) && $_POST['unidade']===$op)?'selected':'' ?>><?= h($op) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-4 col-lg-2">
        <label class="form-label fw-semibold"><i class="fa-solid fa-location-dot me-2 text-primary"></i><?= h(t('form.position')) ?></label>
        <input type="text" name="posicao" class="form-control" required value="<?= h($_POST['posicao'] ?? '') ?>">
      </div>
      <div class="col-12 col-md-4 col-lg-2">
        <label class="form-label fw-semibold"><i class="fa-solid fa-calculator me-2 text-primary"></i><?= h(t('form.quantity')) ?></label>
        <input type="text" name="quantidade" inputmode="numeric" pattern="[0-9. ]+" class="form-control" required value="<?= h($_POST['quantidade'] ?? '') ?>" placeholder="Ex.: 1.000">
      </div>

      <div class="col-12 col-md-4 col-lg-3">
        <label class="form-label fw-semibold"><i class="fa-solid fa-boxes-stacked me-2 text-primary"></i><?= h(t('form.lot')) ?></label>
        <input type="text" name="lote" class="form-control" value="<?= h($_POST['lote'] ?? '') ?>">
      </div>
      <div class="col-12 col-md-8 col-lg-3">
        <label class="form-label fw-semibold"><i class="fa-solid fa-qrcode me-2 text-primary"></i>Código de barras</label>
        <input id="codigo_barra" type="text" name="codigo_barra" class="form-control" value="<?= h($_POST['codigo_barra'] ?? '') ?>" placeholder="Escaneie ou digite">
        <div class="d-flex gap-2 mt-2 flex-wrap">
          <button type="button" id="btn-start-scan" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-camera me-1"></i>Escanear câmera</button>
          <button type="button" id="btn-stop-scan" class="btn btn-outline-secondary btn-sm" style="display:none;"><i class="fa-solid fa-stop me-1"></i>Parar</button>
        </div>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label fw-semibold"><i class="fa-solid fa-right-to-bracket me-2 text-primary"></i><?= h(t('form.entry.date')) ?></label>
        <input type="date" name="data_entrada" class="form-control" required value="<?= h($_POST['data_entrada'] ?? date('Y-m-d')) ?>">
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label fw-semibold"><i class="fa-solid fa-hourglass-end me-2 text-primary"></i><?= h(t('form.expiry')) ?></label>
        <input type="date" name="validade" class="form-control" value="<?= h($_POST['validade'] ?? '') ?>">
      </div>

      <div class="col-12">
        <div id="reader" class="border rounded p-2" style="display:none; max-width:420px;"></div>
      </div>

      <div class="col-12">
        <label class="form-label fw-semibold"><i class="fa-solid fa-note-sticky me-2 text-primary"></i><?= h(t('form.notes')) ?></label>
        <textarea name="observacoes" rows="3" class="form-control" style="resize: vertical;"><?= h($_POST['observacoes'] ?? '') ?></textarea>
      </div>

      <div class="col-12 d-flex flex-column flex-md-row justify-content-between align-items-stretch gap-2 pt-1 form-actions-responsive">
        <a href="index.php" class="btn btn-outline-secondary btn-rounded w-100 w-md-auto"><i class="fa-solid fa-arrow-left me-1"></i><?= h(t('btn.back')) ?></a>
        <button class="btn btn-primary btn-rounded w-100 w-md-auto" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i><?= h(t('btn.save')) ?></button>
      </div>
    </form>
  </div>
</div>
<script src="assets/vendor/html5-qrcode/html5-qrcode.min.js" defer></script>
<script src="assets/js/cadastro.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>