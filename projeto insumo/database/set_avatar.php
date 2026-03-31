<?php
// Atualiza o campo avatar de um usuário pelo e-mail.
// Uso via terminal:
// php database/set_avatar.php --email=SEU_EMAIL --file=NOME_DO_ARQUIVO.png

require_once __DIR__ . '/../db.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acesso negado. Execute este script apenas via CLI.');
}

if (isProductionEnvironment()) {
    exit("Script de manutencao desabilitado em ambiente de producao.\n");
}

$opts = getopt('', ['email:', 'file:']);
$email = trim((string)($opts['email'] ?? ''));
$file  = trim((string)($opts['file'] ?? ''));

if ($email === '' || $file === '') {
    echo "Parametros ausentes. Informe --email=... --file=...\n";
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
