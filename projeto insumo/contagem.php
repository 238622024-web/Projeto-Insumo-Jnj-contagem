<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$pdo = getPDO();

ensureContagemTrackingSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $termo = trim($_POST['termo'] ?? '');
  $incrementoRaw = trim((string)($_POST['incremento'] ?? '1'));
  $incrementoDigits = preg_replace('/\D+/', '', $incrementoRaw);
  $incremento = ($incrementoDigits === '') ? 1 : (int)$incrementoDigits;

    if ($incremento <= 0) {
        $incremento = 1;
    }

    if ($termo === '') {
        flash('error', 'Informe ou escaneie um código de barras (ou nome).');
        header('Location: contagem.php');
        exit;
    }

    $item = null;

    $byBarcode = $pdo->prepare('SELECT * FROM insumos_jnj WHERE codigo_barra = ? LIMIT 1');
    $byBarcode->execute([$termo]);
    $item = $byBarcode->fetch();

    if (!$item) {
        $byExactName = $pdo->prepare('SELECT * FROM insumos_jnj WHERE nome = ? LIMIT 1');
        $byExactName->execute([$termo]);
        $item = $byExactName->fetch();
    }

    if (!$item) {
        $byLikeName = $pdo->prepare('SELECT * FROM insumos_jnj WHERE nome LIKE ? ORDER BY id DESC LIMIT 2');
        $byLikeName->execute(['%' . $termo . '%']);
        $matches = $byLikeName->fetchAll();

        if (count($matches) === 1) {
            $item = $matches[0];
        } elseif (count($matches) > 1) {
            flash('error', 'Mais de um item encontrado. Escaneie o código de barras ou informe o nome completo.');
            header('Location: contagem.php');
            exit;
        }
    }

    if (!$item) {
        flash('error', 'Item não encontrado para contagem. Cadastre o material primeiro.');
        header('Location: contagem.php');
        exit;
    }

    $user = currentUser();
    $contadorId = (int)($user['id'] ?? 0);
    $contadorNome = (string)($user['nome'] ?? $user['email'] ?? 'Usuario');

    $update = $pdo->prepare('UPDATE insumos_jnj SET quantidade = quantidade + ?, data_contagem = CURDATE(), contagem_por_id = ?, contagem_por_nome = ?, contagem_em = NOW() WHERE id = ?');
    $update->execute([$incremento, $contadorId > 0 ? $contadorId : null, $contadorNome, (int)$item['id']]);

    $refresh = $pdo->prepare('SELECT * FROM insumos_jnj WHERE id = ?');
    $refresh->execute([(int)$item['id']]);
    $updatedItem = $refresh->fetch();

    flash('success', 'Contagem registrada: ' . $updatedItem['nome'] . ' (+' . number_format($incremento, 0, ',', '.') . '). Quantidade atual: ' . number_format((int)$updatedItem['quantidade'], 0, ',', '.'));
    header('Location: contagem.php');
    exit;
}

$ultimos = $pdo->query('SELECT id, nome, codigo_barra, quantidade, data_contagem, contagem_por_nome, contagem_em FROM insumos_jnj ORDER BY COALESCE(contagem_em, data_contagem) DESC, id DESC LIMIT 12')->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white border-0 pt-4 pb-0">
    <h2 class="h4 mb-0"><i class="fa-solid fa-barcode me-2 text-primary"></i>Contagem física de inventário</h2>
    <p class="text-muted small mt-2 mb-3">Escaneie o código de barras ou digite o nome do produto para somar na contagem.</p>
  </div>
  <div class="card-body">
    <form method="post" class="row g-3 align-items-end form-responsive">
      <div class="col-12 col-md-8">
        <label class="form-label fw-600">Código de barras ou nome</label>
        <input id="scan-input" type="text" name="termo" class="form-control form-control-lg" placeholder="Escaneie aqui..." autocomplete="off" required>
        <div id="scan-feedback" class="alert alert-success py-2 mt-2 mb-0" style="display:none;">
          <i class="fa-solid fa-circle-check me-1"></i><span id="scan-feedback-text"></span>
        </div>
        <div class="d-flex gap-2 mt-2 flex-wrap">
          <button type="button" id="btn-start-scan-contagem" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-camera me-1"></i>Escanear câmera</button>
          <button type="button" id="btn-stop-scan-contagem" class="btn btn-outline-secondary btn-sm" style="display:none;"><i class="fa-solid fa-stop me-1"></i>Parar</button>
        </div>
        <small class="text-muted d-block mt-1">Leitor físico também funciona: escaneie com foco neste campo.</small>
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label fw-600">Somar</label>
        <input type="text" name="incremento" class="form-control form-control-lg" inputmode="numeric" pattern="[0-9. ]+" value="1" placeholder="Ex.: 1.200" required>
      </div>
      <div class="col-12 col-md-2 d-grid">
        <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-check me-1"></i>Registrar</button>
      </div>
      <div class="col-12">
        <div id="reader-contagem" class="border rounded p-2" style="display:none; max-width:420px;"></div>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white border-0 pt-4 pb-0">
    <h3 class="h5 mb-3"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Últimas contagens</h3>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Código de barras</th>
            <th>Contado por</th>
            <th>Quantidade</th>
            <th>Data contagem</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ultimos as $row): ?>
            <tr>
              <td><?= h((string)$row['id']) ?></td>
              <td><strong><?= h($row['nome']) ?></strong></td>
              <td><?= h($row['codigo_barra'] ?? '') ?></td>
              <td><?= h($row['contagem_por_nome'] ?? '-') ?></td>
              <td><?= h(number_format((int)$row['quantidade'], 0, ',', '.')) ?></td>
              <td><?= !empty($row['contagem_em']) ? h(date('d/m/Y H:i', strtotime($row['contagem_em']))) : (!empty($row['data_contagem']) ? h(date('d/m/Y', strtotime($row['data_contagem']))) : '<span class="text-muted">-</span>') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (count($ultimos) === 0): ?>
            <tr>
              <td colspan="6" class="text-center text-muted py-4">Nenhuma contagem registrada ainda.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="assets/vendor/html5-qrcode/html5-qrcode.min.js" defer></script>
<script src="assets/js/contagem.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
