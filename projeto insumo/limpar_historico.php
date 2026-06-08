<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireAdmin();
$pdo = getPDO();
require_once __DIR__ . '/i18n.php';

// Verificar se é admin (ou restrição adicional, se implementada)
// Por enquanto, qualquer usuário autenticado pode limpar o histórico
// Você pode adicionar uma verificação de permissão aqui

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['confirmar']) || $_POST['confirmar'] !== 'sim') {
        flash('error', 'Confirmação necessária para executar a ação.');
        header('Location: index.php');
        exit;
    }

    $tipo = $_POST['tipo'] ?? 'historico';

    if ($tipo === 'todos') {
        $senhaConfirmacao = (string)($_POST['senha_confirmacao'] ?? '');
        if ($senhaConfirmacao === '') {
            flash('error', 'Digite sua senha para confirmar a exclusão total.');
            header('Location: index.php');
            exit;
        }

        $user = currentUser();
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            flash('error', 'Nao foi possivel validar o usuario logado.');
            header('Location: index.php');
            exit;
        }

        $stmtUser = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = ? LIMIT 1');
        $stmtUser->execute([$userId]);
        $userRow = $stmtUser->fetch();
        $senhaHash = (string)($userRow['senha_hash'] ?? '');

        if ($senhaHash === '' || !password_verify($senhaConfirmacao, $senhaHash)) {
            flash('error', 'Senha incorreta. A exclusão total não foi executada.');
            header('Location: index.php');
            exit;
        }

        try {
            $pdo->beginTransaction();
            $deleted = $pdo->exec('DELETE FROM insumos_jnj');
            $pdo->commit();
            $pdo->exec('ALTER TABLE insumos_jnj AUTO_INCREMENT = 1');
            flash('success', 'Todos os materiais foram apagados (' . (int)$deleted . ' registros).');
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
            flash('error', 'Erro ao apagar todos os materiais: ' . $e->getMessage());
            header('Location: index.php');
            exit;
        }
    } elseif ($tipo === 'banco') {
        try {
            // Apagar todas as tabelas principais do sistema (materiais, configs e usuários)
            $pdo->beginTransaction();
            $c1 = $pdo->exec('DELETE FROM insumos_jnj');
            $c2 = $pdo->exec('DELETE FROM configuracoes');
            $c3 = $pdo->exec('DELETE FROM usuarios');
            $pdo->commit();
            // Reiniciar auto_increment (DDL fora de transação para evitar commit implícito)
            $pdo->exec('ALTER TABLE insumos_jnj AUTO_INCREMENT = 1');
            $pdo->exec('ALTER TABLE usuarios AUTO_INCREMENT = 1');
            $total = (int)$c1 + (int)$c2 + (int)$c3;
            flash('success', 'Banco limpo com sucesso. Registros removidos: Materiais='.(int)$c1.', Configurações='.(int)$c2.', Usuários='.(int)$c3.'. Total='.$total.'.');
            header('Location: configuracoes.php');
            exit;
        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
            flash('error', 'Erro ao apagar todo o banco: ' . $e->getMessage());
            header('Location: configuracoes.php');
            exit;
        }
    } else {
        try {
            $pdo->beginTransaction();
            $upd1 = $pdo->exec("UPDATE insumos_jnj SET data_contagem = NULL WHERE data_contagem IS NOT NULL");
            $rem = 0;
            $check = $pdo->query("SELECT COUNT(*) AS c FROM insumos_jnj WHERE data_contagem IS NOT NULL");
            if ($check) { $row = $check->fetch(); $rem = isset($row['c']) ? (int)$row['c'] : 0; }
            $pdo->commit();
            $totalAtualizados = (int)$upd1;
            $msg = "Histórico de contagem apagado com sucesso! (" . $totalAtualizados . " registros atualizados)";
            if ($rem > 0) { $msg .= " — Atenção: " . $rem . " registros ainda possuem data de contagem."; }
            flash('success', $msg);
            header('Location: configuracoes.php');
            exit;
        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
            flash('error', 'Erro ao limpar histórico: ' . $e->getMessage());
            header('Location: configuracoes.php');
            exit;
        }
    }
}

// Se for GET, redirecionar para configurações
header('Location: configuracoes.php');
exit;
?>
