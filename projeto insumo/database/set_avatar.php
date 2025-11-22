<?php
// Atualiza o campo avatar de um usuário pelo e-mail.
// Uso no navegador (ajuste os parâmetros):
// http://localhost/dashboard/projeto%20insumo/database/set_avatar.php?email=SEU_EMAIL&file=NOME_DO_ARQUIVO.png

require_once __DIR__ . '/../db.php';

header('Content-Type: text/plain; charset=utf-8');

$email = isset($_GET['email']) ? trim($_GET['email']) : '';
$file  = isset($_GET['file']) ? trim($_GET['file']) : '';

if ($email === '' || $file === '') {
    echo "Parâmetros ausentes. Informe ?email=...&file=...\n";
    exit(1);
}

try {
    $pdo = getPDO();
    $col = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'avatar'")->fetch();
    if (!$col) {
        echo "Coluna 'avatar' não existe. Rode: php database/apply_migrations.php\n";
        exit(1);
    }

    $path = __DIR__ . '/../assets/uploads/' . $file;
    if (!is_file($path)) {
        echo "Arquivo não encontrado em assets/uploads: {$file}\n";
        exit(1);
    }

    $stmt = $pdo->prepare('UPDATE usuarios SET avatar = ? WHERE email = ?');
    $stmt->execute([$file, $email]);
    if ($stmt->rowCount() > 0) {
        echo "Avatar atualizado para {$email} => {$file}\n";
    } else {
        echo "Nenhum usuário atualizado. Verifique o e-mail informado.\n";
    }
} catch (Exception $e) {
    echo 'Erro: ' . $e->getMessage() . "\n";
    exit(1);
}
