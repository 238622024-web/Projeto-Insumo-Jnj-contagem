<?php
// Alterna a sessão de tema e volta para a página anterior
if (session_status() === PHP_SESSION_NONE) session_start();
$ref = $_SERVER['HTTP_REFERER'] ?? '/';
$current = $_SESSION['tema_jnj'] ?? 'claro';
$_SESSION['tema_jnj'] = $current === 'escuro' ? 'claro' : 'escuro';
header('Location: ' . $ref);
exit;
