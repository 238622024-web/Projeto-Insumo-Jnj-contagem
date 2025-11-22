<?php
// Script de diagnóstico: lista usuários e o valor do campo `avatar`, e lista arquivos em assets/uploads
require_once __DIR__ . '/../db.php';
try {
    $pdo = getPDO();
    $col = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'avatar'")->fetch();
    if (!$col) {
        echo "Coluna 'avatar' NÃO existe na tabela 'usuarios'.\n";
        echo "Rode: php database/apply_migrations.php\n\n";
    } else {
        echo "Coluna 'avatar' existe na tabela 'usuarios'.\n\n";
    }

    $stmt = $pdo->query('SELECT id,nome,email,avatar FROM usuarios');
    $users = $stmt->fetchAll();
    if (!$users) {
        echo "Nenhum usuário encontrado.\n";
        exit;
    }

    echo str_pad('ID',6) . str_pad('EMAIL',36) . "AVATAR\n";
    echo str_repeat('-', 80) . "\n";
    foreach ($users as $u) {
        printf("%-6s %-36s %s\n", $u['id'], $u['email'], $u['avatar'] ?? '(null)');
    }

    echo "\nArquivos em assets/uploads:\n";
    $upDir = __DIR__ . '/../assets/uploads';
    if (is_dir($upDir)) {
        $files = array_values(array_diff(scandir($upDir), ['.','..']));
        foreach ($files as $f) echo " - $f\n";
    } else {
        echo "Pasta assets/uploads não existe.\n";
    }

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

// Uso: executar no terminal dentro da pasta do projeto
// php database/check_avatars.php
