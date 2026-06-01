<?php
// Alterna a sessão de tema e volta para a página anterior
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
$ref = $_SERVER['HTTP_REFERER'] ?? '/';
$current = $_SESSION['tema_jnj'] ?? 'claro';
$next = $current === 'escuro' ? 'claro' : 'escuro';
$_SESSION['tema_jnj'] = $next;

$user = currentUser();
if (!empty($user['id'])) {
	try {
		$pdo = getPDO();
		ensureUserAuthSchema();
		$stmt = $pdo->prepare('UPDATE usuarios SET preferred_theme = ? WHERE id = ?');
		$stmt->execute([$next, (int)$user['id']]);
	} catch (Throwable $e) {
		// Mantém a sessão local mesmo se não conseguir persistir no banco.
	}
}

header('Location: ' . $ref);
exit;
