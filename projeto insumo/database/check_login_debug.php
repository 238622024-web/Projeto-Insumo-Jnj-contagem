<?php
// DEBUG temporário - verificar por que o login falha
// Uso (navegador):
// http://localhost/dashboard/projeto%20insumo/database/check_login_debug.php?email=WMESSIA&senha=SENHA
// Substitua WMESSIA e SENHA por valores reais. Apague este arquivo depois de usar.
require_once __DIR__ . '/../db.php';

$email = $_GET['email'] ?? '';
$senha = $_GET['senha'] ?? '';

header('Content-Type: text/plain; charset=utf-8');
echo "DEBUG - Verificação de login\n";
if ($email === '' || $senha === '') {
    echo "Uso: passe ?email=seu_email&senha=sua_senha\n";
    exit;
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id,email,senha_hash FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        echo "Usuário não encontrado para email: $email\n";
        exit;
    }
    echo "ID: " . $user['id'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Senha hash (do banco): " . $user['senha_hash'] . "\n";
    $ok = password_verify($senha, $user['senha_hash']);
    echo "password_verify(\"$senha\", db_hash) = " . ($ok ? 'TRUE' : 'FALSE') . "\n";
    // Mostrar se hash é o placeholder conhecido
    $placeholder = '$2y$10$abcdefghijklmnopqrstuvC1u7mF1XyLqjvZQXw2fQY9xH0g7Yk3NqW2';
    echo "É placeholder seed? " . ($user['senha_hash'] === $placeholder ? 'SIM' : 'NAO') . "\n";
    echo "\nNotas:\n- Se password_verify retornar FALSE, o hash no banco não corresponde à senha enviada.\n";
    echo "- Se for placeholder, atualize o hash (ou use \"reset_password.php\").\n";
} catch (Exception $e) {
    echo "Erro ao conectar/consultar: " . $e->getMessage() . "\n";
}

echo "\nApague este arquivo por segurança quando terminar.\n";

?>
