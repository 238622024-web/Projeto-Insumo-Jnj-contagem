<?php
require_once __DIR__ . '/auth.php';
requireLogin();

flash('info', 'O histórico de insumos foi desativado. Para baixar material, use Baixa por QR.');
header('Location: picking_qrcode.php');
exit;