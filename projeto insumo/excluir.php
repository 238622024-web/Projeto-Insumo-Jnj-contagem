<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$pdo = getPDO();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error','ID inválido.'); header('Location: index.php'); exit; }
$stmt = $pdo->prepare('SELECT * FROM insumos_jnj WHERE id=?');
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { flash('error','Material não encontrado.'); header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typed = strtoupper(trim($_POST['confirm_text'] ?? ''));
    if (isset($_POST['confirm']) && $_POST['confirm'] === 'SIM' && $typed === 'EXCLUIR') {
        $del = $pdo->prepare('DELETE FROM insumos_jnj WHERE id=?');
        $del->execute([$id]);
        flash('success','Material excluído com sucesso!');
    } else {
        flash('error','Exclusão cancelada.');
    }
    header('Location: index.php');
    exit;
}
include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-center align-items-start" style="min-height:50vh;">
    <div class="card shadow-lg" style="max-width: 600px; width:100%;">
        <div class="card-header bg-danger text-white d-flex align-items-center">
            <i class="fa fa-triangle-exclamation me-2"></i>
            <strong>Confirmar exclusão</strong>
        </div>
        <div class="card-body">
            <p class="mb-3">Tem certeza que deseja excluir este material? Esta ação não pode ser desfeita.</p>
            <div class="border rounded p-3 bg-light">
                <div class="row g-2 small">
                    <div class="col-12"><strong>ID:</strong> <?= h($item['id']) ?></div>
                    <div class="col-12"><strong>Nome:</strong> <?= h($item['nome']) ?></div>
                    <div class="col-md-6"><strong>Posição:</strong> <?= h($item['posicao']) ?></div>
                    <div class="col-md-6"><strong>Lote:</strong> <?= h($item['lote'] ?? '') ?></div>
                    <div class="col-md-6"><strong>Qtd:</strong> <?= h($item['quantidade']) ?></div>
                    <div class="col-md-6"><strong>Validade:</strong> <?= isset($item['validade']) ? h(date('d/m/Y', strtotime($item['validade']))) : '' ?></div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <a href="index.php" class="btn btn-outline-secondary btn-rounded"><i class="fa fa-arrow-left me-1"></i>Cancelar</a>
            <form method="post" class="m-0 d-flex align-items-center gap-2">
                <input type="text" name="confirm_text" id="confirm_text" class="form-control form-control-sm" placeholder="Digite EXCLUIR para confirmar" style="max-width:260px;" autocomplete="off">
                <button id="btnDelete" name="confirm" value="SIM" class="btn btn-danger btn-rounded" disabled><i class="fa fa-trash me-1"></i>Sim, excluir</button>
            </form>
        </div>
    </div>
    </div>
<script src="assets/js/delete-confirmation.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>