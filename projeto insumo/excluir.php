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
<div class="delete-screen-wrap">
    <div class="delete-card shadow-lg">
        <div class="delete-card-header">
            <div class="delete-header-icon"><i class="fa-solid fa-shield-halved"></i></div>
            <div>
                <h2 class="h5 mb-1">Confirmacao de exclusao</h2>
                <p class="mb-0 text-muted">Esta operacao remove o material de forma permanente.</p>
            </div>
        </div>

        <div class="delete-risk-banner" role="alert">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong>Acao critica:</strong> apos confirmar, este registro nao podera ser recuperado pela tela do sistema.
            </div>
        </div>

        <div class="delete-item-grid">
            <div class="delete-item-cell"><span>ID</span><strong>#<?= h($item['id']) ?></strong></div>
            <div class="delete-item-cell"><span>Nome</span><strong><?= h($item['nome']) ?></strong></div>
            <div class="delete-item-cell"><span>Posicao</span><strong><?= h($item['posicao']) ?></strong></div>
            <div class="delete-item-cell"><span>Lote</span><strong><?= h($item['lote'] ?? '-') ?></strong></div>
            <div class="delete-item-cell"><span>Quantidade</span><strong><?= h(number_format((int)$item['quantidade'], 0, ',', '.')) ?></strong></div>
            <div class="delete-item-cell"><span>Validade</span><strong><?= isset($item['validade']) ? h(date('d/m/Y', strtotime($item['validade']))) : '-' ?></strong></div>
        </div>

        <form method="post" class="delete-confirm-form" novalidate>
            <label for="confirm_text" class="form-label fw-semibold">Digite EXCLUIR para continuar</label>
            <input type="text" name="confirm_text" id="confirm_text" class="form-control" placeholder="EXCLUIR" autocomplete="off" maxlength="20">

            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" value="1" id="ack_delete">
                <label class="form-check-label" for="ack_delete">
                    Confirmo que revisei os dados e desejo excluir este material.
                </label>
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4">
                <a href="index.php" class="btn btn-outline-secondary btn-rounded"><i class="fa fa-arrow-left me-1"></i>Voltar sem excluir</a>
                <button id="btnDelete" name="confirm" value="SIM" class="btn btn-danger btn-rounded" disabled>
                    <i class="fa-solid fa-trash-can me-1"></i>Confirmar exclusao
                </button>
            </div>
        </form>
    </div>
</div>
<script src="assets/js/delete-confirmation.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>