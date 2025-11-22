<?php
// Uso: acesse via navegador http://localhost/dashboard/projeto%20insumo/database/reset_password.php
// Altere $email e $novaSenha conforme necessário. APAGUE este arquivo depois de usar.
require_once __DIR__ . '/../db.php';

$email = 'wmessia1@its.jnj.com';
$novaSenha = 'senha165';

$pdo = getPDO();
$hash = password_hash($novaSenha, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE email = ?');
$stmt->execute([$hash, $email]);

echo "Senha atualizada para: " . htmlspecialchars($email) . "<br>";
echo "Novo hash: " . htmlspecialchars($hash) . "<br>";
echo "Apague este arquivo por segurança assim que terminar.";
