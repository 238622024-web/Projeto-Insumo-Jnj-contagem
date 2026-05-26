<?php
// Alterna a sessão de tema e volta para a página anterior
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/settings.php';
$ref = $_SERVER['HTTP_REFERER'] ?? '/';
$current = $_SESSION['tema_jnj'] ?? 'claro';
$next = $current === 'escuro' ? 'claro' : 'escuro';
$_SESSION['tema_jnj'] = $next;
setSetting('tema_padrao', $next);
header('Location: ' . $ref);
exit;
