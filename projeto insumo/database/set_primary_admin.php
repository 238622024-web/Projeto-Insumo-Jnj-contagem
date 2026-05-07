<?php
require_once __DIR__ . '/../auth.php';

if (PHP_SAPI !== 'cli') {
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($remoteAddr, ['127.0.0.1', '::1', 'localhost'], true)) {
        http_response_code(403);
        exit('Acesso negado. Use este script apenas localmente.');
    }
}

ensureUserAuthSchema();
$pdo = getPDO();

$nome = 'WEDER MESSIAS DA SILVA PEREIRA';
$email = 'weder.messias@hotmail.com';
$senha = '64664158Weder@';
$hash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    "INSERT INTO usuarios (nome, email, senha_hash, role, aprovado, aprovado_em)
     VALUES (?, ?, ?, 'admin', 1, NOW())
     ON DUPLICATE KEY UPDATE
       nome = VALUES(nome),
       senha_hash = VALUES(senha_hash),
       role = 'admin',
       aprovado = 1,
       aprovado_em = NOW()"
);
$stmt->execute([$nome, $email, $hash]);

if (PHP_SAPI === 'cli') {
    echo "Conta principal pronta.\n";
    echo "E-mail: {$email}\n";
    echo "Senha: {$senha}\n";
    exit(0);
}

echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Conta principal pronta</title><style>body{font-family:Arial,sans-serif;background:#f6f7fb;margin:0;padding:40px;color:#1f2937} .card{max-width:640px;margin:0 auto;background:#fff;border-radius:16px;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,.08)} code{background:#eef2ff;padding:2px 6px;border-radius:6px}</style></head><body><div class="card"><h1>Conta principal pronta</h1><p>O acesso principal foi recriado com sucesso no banco local.</p><p><strong>E-mail:</strong> <code>' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</code></p><p><strong>Senha:</strong> <code>' . htmlspecialchars($senha, ENT_QUOTES, 'UTF-8') . '</code></p><p><a href="/Projeto-Insumo-Jnj-contagem/projeto%20insumo/login.php">Ir para o login</a></p></div></body></html>';
