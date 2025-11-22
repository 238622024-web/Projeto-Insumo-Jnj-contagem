<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
logout();
flash('success','Sessão encerrada.');
header('Location: login.php');
exit;
?>