<?php
// Script de suporte local (CLI) para redefinir senha de um usuario.
// Uso:
// php database/reset_password.php --email=usuario@dominio.com --senha=NovaSenha123
require_once __DIR__ . '/../db.php';

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('Acesso negado. Execute este script apenas via CLI.');
}

if (isProductionEnvironment()) {
	exit("Script de manutencao desabilitado em ambiente de producao.\n");
}

$opts = getopt('', ['email:', 'senha:']);
$email = trim((string)($opts['email'] ?? ''));
$novaSenha = (string)($opts['senha'] ?? '');

if ($email === '' || $novaSenha === '') {
	echo "Uso: php database/reset_password.php --email=usuario@dominio.com --senha=NovaSenha123\n";
	exit(1);
}

$pdo = getPDO();
$hash = password_hash($novaSenha, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE email = ?');
$stmt->execute([$hash, $email]);

if ($stmt->rowCount() > 0) {
	echo "Senha atualizada para: {$email}\n";
} else {
	echo "Nenhum usuario atualizado. Verifique o e-mail informado.\n";
}
echo "Suporte local concluido.\n";
