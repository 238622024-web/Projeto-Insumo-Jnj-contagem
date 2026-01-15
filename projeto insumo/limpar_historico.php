<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$pdo = getPDO();
require_once __DIR__ . '/i18n.php';

// Verificar se é admin (ou restrição adicional, se implementada)
// Por enquanto, qualquer usuário autenticado pode limpar o histórico
// Você pode adicionar uma verificação de permissão aqui

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Proteção contra CSRF simples
    if (!isset($_POST['confirmar']) || $_POST['confirmar'] !== 'sim') {
        flash('error', 'Confirmação necessária para limpar o histórico.');
        header('Location: configuracoes.php');
        exit;
    }

    try {
        // Atualizar todos os insumos, setando data_contagem como NULL
        $stmt = $pdo->prepare("UPDATE insumos_jnj SET data_contagem = NULL");
        $stmt->execute();
        
        $linhas_afetadas = $stmt->rowCount();
        
        flash('success', "Histórico de contagem apagado com sucesso! ({$linhas_afetadas} registros atualizados)");
        header('Location: configuracoes.php');
        exit;
    } catch (Exception $e) {
        flash('error', 'Erro ao limpar histórico: ' . $e->getMessage());
        header('Location: configuracoes.php');
        exit;
    }
}

// Se for GET, redirecionar para configurações
header('Location: configuracoes.php');
exit;
?>
