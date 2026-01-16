<?php
// Página de verificação rápida de saúde da aplicação
// Acesse após configurar Apache+MySQL (XAMPP):
//   http://localhost/projeto-insumo/health.php

header('Content-Type: application/json; charset=utf-8');

$status = [
    'app' => 'ok',
    'db' => 'unknown',
    'details' => []
];

try {
    require_once __DIR__ . '/db.php';
    $pdo = getPDO();
    $status['db'] = 'connected';

    // Verificar tabelas essenciais
    $tables = ['usuarios', 'insumos_jnj', 'configuracoes'];
    $existing = [];
    foreach ($tables as $t) {
        $stmt = $pdo->query("SHOW TABLES LIKE '" . str_replace("'", "''", $t) . "'");
        $existing[$t] = $stmt && $stmt->fetch() ? true : false;
    }
    $status['details']['tables'] = $existing;

    // Verificar contagem de usuários
    if (!empty($existing['usuarios'])) {
        $count = (int)($pdo->query('SELECT COUNT(*) AS c FROM usuarios')->fetch()['c'] ?? 0);
        $status['details']['usuarios_count'] = $count;
    }
} catch (Throwable $e) {
    $status['db'] = 'error';
    $status['details']['error'] = $e->getMessage();
}

echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

?>
