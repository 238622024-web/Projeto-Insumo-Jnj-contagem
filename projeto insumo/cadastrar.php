<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$pdo = getPDO();
require_once __DIR__ . '/i18n.php';

$nomesInsumos = [
  'ABRACADEIRA NYLON',
  'BUBBLE A (P)',
  'BUBBLE B (M)',
  'BUBBLE C (G)',
  'BUBBLE DPS D',
  'BUBBLE DPS F',
  'BUBBLE DPS G',
  'BUBBLE DPS H',
  'BUBBLE DPS I',
  'CABOS',
  'CAIXA 10',
  'CAIXA 24',
  'CAIXA 24 (KLABIN)',
  'CAIXA 25',
  'CAIXA 25 (KLABIN)',
  'CAIXA 25A',
  'CAIXA 30',
  'CAIXA 30 (KLABIN)',
  'CAIXA 32',
  'CAIXA 41A',
  'CAIXA 41A (KLABIN)',
  'CAIXA 9',
  'CAIXA ARQUIVO',
  'CAIXA BAN (KLABIN)',
  'CAIXA BANHEIRA G',
  'CAIXA BANHEIRA P',
  'CAIXA BRANCA',
  'CAIXA G',
  'CAIXA G (KLABIN)',
  'CAIXA KIT G',
  'CAIXA KIT P',
  'CAIXA LL',
  'CAIXA LL (KLABIN)',
  'CAIXA NR49',
  'CAIXA NR49 (KLABIN)',
  'CANTONEIRA 114',
  'CANTONEIRA 116',
  'CANTONEIRA 119',
  'CANTONEIRA 29',
  'CANTONEIRA 49',
  'CANTONEIRA 59',
  'CANTONEIRA 91',
  'CANTONEIRA 97',
  'CANTONEIRA 99',
  'CINTA VERDE',
  'ELASTICO MEDIO',
  'ELASTICO PEQUENO',
  'ENVELOPE EXPORTACAO',
  'ENVELOPE PARA NF',
  'ETIQ 100X146',
  'ETIQ 100X50',
  'ETIQ 102X85',
  'ETIQ 20X60',
  'ETIQ 20X60 PEROLADA',
  'ETIQ 65X45',
  'ETIQ 80X40 AMARELA',
  'ETIQ 80X40 BRANCA',
  'ETIQ AGULHAS',
  'ETIQ AMARELA',
  'ETIQ AZUL',
  'ETIQ CAIXA INCOMPLETA',
  'ETIQ CLIENTE RETIRA',
  'ETIQ INDELEVEL',
  'ETIQ LARANJA',
  'ETIQ PARE ADEQUACAO',
  'ETIQ PIMACO - 6182',
  'ETIQ PRIORIDADE',
  'ETIQ REPROVADO',
  'ETIQ ROSA',
  'ETIQ URGENCIA',
  'ETIQUETA BOLHA VERDE',
  'ETIQUETA ENVELOPE DE NOTA FISCAL',
  'ETIQUETAS RFID',
  'ETQ IMPLANTE',
  'FITA BROTHER M-231',
  'FITA BROTHER TZE-231',
  'FITA GOMADA C/ TIMBRE',
  'FITA TRANSPARENTE',
  'FORMULARIO DE CONTINGENCIA',
  'GELO SECO',
  'INMETRO OPC 0004 TUV',
  'INMETRO OPC 0029 UL',
  'LACRE EXPORTACAO',
  'LACRE EXPRESS',
  'LACRE G J&I',
  'LACRE MALOTE',
  'LACRE P J&I',
  'LUVA SEM PO G',
  'LUVA SEM PO M',
  'LUVA SEM PO P',
  'NOTA FISCAL',
  'PALETE NOVO',
  'PAPEL CARTA',
  'PAPEL PIMACO ADQ',
  'PLASTICO BOLHA',
  'PRESILHAS',
  'RIBON CERA',
  'RIBON RESINA',
  'SACO 30CM X 45CM',
  'SACO G',
  'SACO GG',
  'SACO IMPLANTE C/ FITA 10X16X3X0,015',
  'SACO IMPLANTE C/ FITA 7,5 X 10 X 3',
  'SACO IMPLANTE C/ FITA 7,5 X 16 X 3',
  'SACO IMPLANTE C/ FITA 7,5 X 70,0 X 3 X 0,015',
  'SACO P',
  'SACO PARA KIT G',
  'SACO PARA KIT M',
  'SACO PARA KIT P',
  'SACO PEBD 18X178',
  'SACO PEBD 25X133',
  'SACO PEBD TRANSPARENTE',
  'SACO PLASTICO 11X20',
  'SACO PLASTICO 20X30X0,015 - DPS',
  'SACO PLASTICO 25X35 - DPS VLM',
  'SACO PLASTICO 30X50 - DPS VLM',
  'SACO PLASTICO TRANSP',
  'SACO ZIP G',
  'SACO ZIP M',
  'SACO ZIP P',
  'SACO PLASTICO 40X80 - DPS VLM',
  'STRETCH',
  'SULFITE AMARELO',
  'SULFITE AZUL',
  'SULFITE MARFIM',
  'SULFITE ROSA',
  'SULFITE VERDE',
  'TAG ALERT'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $posicao = trim($_POST['posicao'] ?? '');
  $lote = trim($_POST['lote'] ?? '');
    $codigo_barra = trim($_POST['codigo_barra'] ?? '');
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
      $stmt = $pdo->prepare("INSERT INTO insumos_jnj (nome,posicao,lote,codigo_barra,quantidade,data_contagem,data_entrada,validade,observacoes,unidade) VALUES (?,?,?,?,?,?,?,?,?,?)");
      $stmt->execute([$nome, $posicao, $lote, $codigo_barra, $quantidade, $formatted_data_contagem, $formatted_data_entrada, $formatted_validade, $observacoes, $unidade]);
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
      <form id="form-cadastro-insumo" method="post" class="row g-4 form-responsive">
        <!-- Nome do Material -->
        <div class="col-12">
          <label class="form-label fw-600"><i class="fa fa-box text-primary me-2"></i><?= h(t('form.name')) ?> <span class="text-danger">*</span></label>
          <select name="nome" class="form-select form-select-lg" required>
            <option value="">Selecione o material</option>
            <?php foreach ($nomesInsumos as $nomeItem): ?>
              <option value="<?= h($nomeItem) ?>" <?= (($_POST['nome'] ?? '') === $nomeItem) ? 'selected' : '' ?>><?= h($nomeItem) ?></option>
            <?php endforeach; ?>
          </select>
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
          <label class="form-label fw-600"><i class="fa fa-qrcode text-primary me-2"></i>Código de barras</label>
          <input id="codigo_barra" type="text" name="codigo_barra" class="form-control form-control-lg" value="<?= h($_POST['codigo_barra'] ?? '') ?>" placeholder="Escaneie ou digite">
          <div class="d-flex gap-2 mt-2 flex-wrap">
            <button type="button" id="btn-start-scan" class="btn btn-outline-primary btn-sm"><i class="fa fa-camera me-1"></i>Escanear câmera</button>
            <button type="button" id="btn-stop-scan" class="btn btn-outline-secondary btn-sm" style="display:none;"><i class="fa fa-stop me-1"></i>Parar</button>
          </div>
          <small class="text-muted d-block mt-1">Leitor físico também funciona: escaneie com foco neste campo.</small>
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

        <div class="col-12">
          <div id="reader" class="border rounded p-2" style="display:none; max-width:420px;"></div>
        </div>

        <!-- Observações -->
        <div class="col-12">
          <label class="form-label fw-600"><i class="fa fa-sticky-note text-primary me-2"></i><?= h(t('form.notes')) ?></label>
          <textarea name="observacoes" rows="4" class="form-control form-control-lg" style="resize: vertical;"><?= h($_POST['observacoes'] ?? '') ?></textarea>
        </div>

        <!-- Botões de Ação -->
        <div class="col-12 d-flex flex-column flex-md-row justify-content-between align-items-stretch gap-2 mt-4 form-actions-responsive">
          <a href="index.php" class="btn btn-outline-secondary btn-lg w-100 w-md-auto" style="transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.15)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
            <i class="fa fa-arrow-left me-2"></i><?= h(t('btn.back')) ?>
          </a>
          <button class="btn btn-primary btn-lg w-100 w-md-auto" type="submit" style="transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 12px rgba(102,126,234,0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
            <i class="fa fa-save me-2"></i><?= h(t('btn.save')) ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<script src="assets/vendor/html5-qrcode/html5-qrcode.min.js" defer></script>
<script src="assets/js/cadastro.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>