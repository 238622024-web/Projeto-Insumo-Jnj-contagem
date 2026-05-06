<?php
require_once __DIR__ . '/../auth.php';

header('Content-Type: text/plain; charset=utf-8');

try {
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

    echo "Conta principal atualizada com sucesso.\n";
    echo "E-mail: {$email}\n";
    echo "Senha: {$senha}\n";
    echo "Abra agora: /projeto-insumo/login.php\n";
    echo "Depois de confirmar, remova este arquivo por seguranca.\n";
} catch (Throwable $e) {
    echo "Erro ao atualizar conta principal: " . $e->getMessage() . "\n";
}
