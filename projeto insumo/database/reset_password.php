<?php
// Uso (somente CLI):
// php database/reset_password.php --email=usuario@jnj.com --senha="NovaSenhaForte"

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('Este script so pode ser executado via CLI.' . PHP_EOL);
}

require_once __DIR__ . '/../db.php';

$opts = getopt('', ['email:', 'senha:']);
$email = isset($opts['email']) ? trim((string)$opts['email']) : '';
$novaSenha = isset($opts['senha']) ? (string)$opts['senha'] : '';

if ($email === '' || $novaSenha === '') {
	fwrite(STDERR, 'Uso: php database/reset_password.php --email=usuario@jnj.com --senha="NovaSenhaForte"' . PHP_EOL);
	exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
	fwrite(STDERR, 'Email invalido.' . PHP_EOL);
	exit(1);
}

if (strlen($novaSenha) < 8) {
	fwrite(STDERR, 'Senha invalida: minimo de 8 caracteres.' . PHP_EOL);
	exit(1);
}

$pdo = getPDO();
$hash = password_hash($novaSenha, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE email = ?');
$stmt->execute([$hash, $email]);

if ($stmt->rowCount() === 0) {
	fwrite(STDOUT, 'Nenhum usuario encontrado para o email informado.' . PHP_EOL);
	exit(2);
}

fwrite(STDOUT, 'Senha atualizada com sucesso para: ' . $email . PHP_EOL);
