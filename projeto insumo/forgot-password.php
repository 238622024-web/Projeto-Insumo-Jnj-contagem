<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
if (currentUser()) { header('Location: index.php'); exit; }

// Placeholder simples - em produção implementar envio de e-mail
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    flash('success', 'Se o e-mail existir, instruções serão enviadas. (Funcionalidade não implementada)');
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Recuperar Senha - Controle de Insumos JNJ</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>body{font-family:'Inter',sans-serif}</style>
</head>
<body class="bg-gray-100 text-gray-800">
<div class="min-h-screen flex items-center justify-center px-4 py-12">
  <div class="w-full max-w-md space-y-8 bg-white p-8 rounded shadow">
    <h2 class="text-center text-2xl font-bold text-gray-900">Recuperar Senha</h2>
    <p class="text-sm text-gray-600 text-center">Digite seu e-mail para iniciar o processo.</p>
    <form method="post" class="space-y-5">
      <div>
        <label class="block text-sm font-medium text-gray-700">E-mail</label>
        <input type="email" name="email" required class="mt-1 w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500" />
      </div>
      <button type="submit" class="w-full py-2 px-4 rounded-md text-white bg-red-600 hover:bg-red-700 font-medium">Enviar</button>
    </form>
    <div class="text-center text-sm"><a href="login.php" class="text-red-600 hover:text-red-500">Voltar ao login</a></div>
  </div>
</div>
</body>
</html>