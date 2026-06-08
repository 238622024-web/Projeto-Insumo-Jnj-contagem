<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireAdmin();
$pdo = getPDO();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error','ID inválido.'); header('Location: index.php'); exit; }
$stmt = $pdo->prepare('SELECT * FROM insumos_jnj WHERE id=?');
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { flash('error','Material não encontrado.'); header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senhaConfirmacao = (string)($_POST['senha_confirmacao'] ?? '');
    $typed = strtoupper(trim($_POST['confirm_text'] ?? ''));

    if ($senhaConfirmacao === '') {
        flash('error', 'Digite a senha do administrador para excluir este material.');
        header('Location: excluir.php?id=' . $id);
        exit;
    }

    $user = currentUser();
    $userId = (int)($user['id'] ?? 0);
    if ($userId <= 0) {
        flash('error', 'Nao foi possivel validar o usuario logado.');
        header('Location: excluir.php?id=' . $id);
        exit;
    }

    $stmtUser = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = ? LIMIT 1');
    $stmtUser->execute([$userId]);
    $userRow = $stmtUser->fetch();
    $senhaHash = (string)($userRow['senha_hash'] ?? '');

    if ($senhaHash === '' || !password_verify($senhaConfirmacao, $senhaHash)) {
        flash('error', 'Senha incorreta. A exclusão não foi executada.');
        header('Location: excluir.php?id=' . $id);
        exit;
    }

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
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-start gap-3">
                    <div class="delete-header-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <div>
                        <h2 class="h5 mb-1">Confirmacao de exclusao</h2>
                        <p class="mb-0 text-muted">Esta operacao remove o material de forma permanente.</p>
                    </div>
                </div>
                <a href="index.php" class="btn-close" aria-label="Fechar"></a>
            </div>

            <div class="modal-body pt-3">
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

                <form method="post" class="delete-confirm-form" id="deleteConfirmForm" novalidate>
                    <label for="confirm_text" class="form-label fw-semibold">Digite EXCLUIR para continuar</label>
                    <input type="text" name="confirm_text" id="confirm_text" class="form-control" placeholder="EXCLUIR" autocomplete="off" maxlength="20">

                    <div class="mt-3">
                        <label for="senha_confirmacao" class="form-label fw-semibold">Senha do administrador</label>
                        <input type="password" name="senha_confirmacao" id="senha_confirmacao" class="form-control" autocomplete="current-password" required>
                    </div>

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
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('deleteConfirmModal');
    if (modalEl && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
});
</script>
<script src="assets/js/delete-confirmation.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>