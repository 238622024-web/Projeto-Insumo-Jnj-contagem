<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
if (currentUser()) { header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['password'] ?? '';
    if (login($email, $senha)) {
        flash('success', 'Login efetuado com sucesso.');
        header('Location: index.php');
        exit;
    } else {
        flash('error', 'E-mail ou senha inválidos.');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - Controle de Insumos</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-RXf+QSDCUQs6Q0GqQmCtT9e7N1KleChX2NDVYqoQZnQEqplLWYw0EN0pZK0s8AjtKqJrY6QXTsE6YdZP+eT1Bw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="style.css" />
</head>
<body>
  <div class="min-vh-100 d-flex align-items-center justify-content-center">
    <div class="card shadow-sm" style="max-width:420px; width:100%; border-radius:12px;">
      <div class="card-body p-4">
        <div class="text-center mb-3">
          <img src="assets/logo.svg" alt="logo" style="height:42px;" />
        </div>
        <h3 class="h5 text-center mb-3">Login</h3>
        <p class="text-center text-muted small">Acesse para gerenciar os materiais</p>
        <?php if ($m = flash('error')): ?>
          <div class="alert alert-danger py-2"><?= h($m) ?></div>
        <?php endif; ?>
        <?php if ($m = flash('success')): ?>
          <div class="alert alert-success py-2"><?= h($m) ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
          <div class="mb-3">
            <label for="email" class="form-label small">E-mail</label>
            <div class="input-group">
              <span class="input-group-text" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20 21a8 8 0 1 0-16 0"/>
                  <circle cx="12" cy="7" r="4"/>
                </svg>
              </span>
              <input id="email" name="email" type="email" required class="form-control" placeholder="Seu e-mail" autofocus />
            </div>
          </div>
          <div class="mb-3">
            <label for="password" class="form-label small">Senha</label>
            <div class="input-group">
              <span class="input-group-text" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
              </span>
              <input id="password" name="password" type="password" required class="form-control" placeholder="Sua senha" />
              <button type="button" class="btn btn-outline-secondary" id="togglePassword" tabindex="-1" aria-label="Mostrar senha" title="Mostrar/ocultar senha">
                <!-- Ícones inline (sem depender de CDN) -->
                <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg id="eyeClosed" class="d-none" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.77 21.77 0 0 1 5.06-6.94"/>
                  <path d="M1 1l22 22"/>
                </svg>
              </button>
            </div>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-check">
              <input id="remember-me" name="remember-me" type="checkbox" class="form-check-input" />
              <label for="remember-me" class="form-check-label small">Lembrar-me</label>
            </div>
            <a href="forgot-password.php" class="small">Esqueceu a senha?</a>
          </div>
          <button type="submit" class="btn btn-primary w-100">Entrar</button>
        </form>
        <div class="text-center mt-3 small text-muted">Não tem uma conta? <a href="create-account.php">Criar agora</a></div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script>
    (function(){
      const toggleBtn = document.getElementById('togglePassword');
      const pwd = document.getElementById('password');
      const eyeOpen = document.getElementById('eyeOpen');
      const eyeClosed = document.getElementById('eyeClosed');
      if (toggleBtn && pwd){
        toggleBtn.addEventListener('click', function(){
          const showing = pwd.type === 'text';
          pwd.type = showing ? 'password' : 'text';
          if (eyeOpen && eyeClosed){
            eyeOpen.classList.toggle('d-none', !showing);
            eyeClosed.classList.toggle('d-none', showing);
          }
          toggleBtn.setAttribute('aria-label', showing ? 'Mostrar senha' : 'Ocultar senha');
        });
      }
    })();
  </script>
  <script>
    // Toggle .filled on .mb-3 when inputs have value so labels stay floated
    (function(){
      function updateFilled(el){
        var wrapper = el.closest('.mb-3');
        if (!wrapper) return;
        if (el.value && el.value.trim() !== '') wrapper.classList.add('filled');
        else wrapper.classList.remove('filled');
      }
      document.addEventListener('DOMContentLoaded', function(){
        var inputs = document.querySelectorAll('.mb-3 input, .mb-3 textarea');
        inputs.forEach(function(inp){
          // initialize
          updateFilled(inp);
          // update on input/change
          inp.addEventListener('input', function(){ updateFilled(inp); });
          inp.addEventListener('change', function(){ updateFilled(inp); });
        });
      });
    })();
  </script>
</body>
</html>