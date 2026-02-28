<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $termo = trim($_POST['termo'] ?? '');
    $incremento = (int)($_POST['incremento'] ?? 1);

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

    $update = $pdo->prepare('UPDATE insumos_jnj SET quantidade = quantidade + ?, data_contagem = CURDATE() WHERE id = ?');
    $update->execute([$incremento, (int)$item['id']]);

    $refresh = $pdo->prepare('SELECT * FROM insumos_jnj WHERE id = ?');
    $refresh->execute([(int)$item['id']]);
    $updatedItem = $refresh->fetch();

    flash('success', 'Contagem registrada: ' . $updatedItem['nome'] . ' (+'.$incremento.'). Quantidade atual: ' . $updatedItem['quantidade']);
    header('Location: contagem.php');
    exit;
}

$ultimos = $pdo->query('SELECT id, nome, codigo_barra, quantidade, data_contagem FROM insumos_jnj ORDER BY data_contagem DESC, id DESC LIMIT 12')->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white border-0 pt-4 pb-0">
    <h2 class="h4 mb-0"><i class="fa fa-barcode me-2 text-primary"></i>Contagem física de inventário</h2>
    <p class="text-muted small mt-2 mb-3">Escaneie o código de barras ou digite o nome do produto para somar na contagem.</p>
  </div>
  <div class="card-body">
    <form method="post" class="row g-3 align-items-end form-responsive">
      <div class="col-12 col-md-8">
        <label class="form-label fw-600">Código de barras ou nome</label>
        <input id="scan-input" type="text" name="termo" class="form-control form-control-lg" placeholder="Escaneie aqui..." autocomplete="off" required>
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label fw-600">Somar</label>
        <input type="number" name="incremento" class="form-control form-control-lg" min="1" value="1" required>
      </div>
      <div class="col-12 col-md-2 d-grid">
        <button type="submit" class="btn btn-primary btn-lg"><i class="fa fa-check me-1"></i>Registrar</button>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white border-0 pt-4 pb-0">
    <h3 class="h5 mb-3"><i class="fa fa-clock-rotate-left me-2 text-primary"></i>Últimas contagens</h3>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Código de barras</th>
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
              <td><?= h((string)$row['quantidade']) ?></td>
              <td><?= !empty($row['data_contagem']) ? h(date('d/m/Y', strtotime($row['data_contagem']))) : '<span class="text-muted">-</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (count($ultimos) === 0): ?>
            <tr>
              <td colspan="5" class="text-center text-muted py-4">Nenhuma contagem registrada ainda.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  (function(){
    const input = document.getElementById('scan-input');
    if (input) {
      input.focus();
      setInterval(() => {
        if (document.activeElement !== input) {
          input.focus();
        }
      }, 1500);
    }
  })();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
