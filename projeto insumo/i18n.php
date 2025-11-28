<?php
require_once __DIR__ . '/settings.php';

function getLang(): string {
  return getSetting('lang', $_SESSION['lang_jnj'] ?? 'pt-br');
}

function t(string $key): string {
  static $dict = null; static $lang = null;
  if ($dict === null) {
    $lang = getLang();
    $dict = [
      'pt-br' => [
        'app.title' => 'JNJ',
        'nav.home' => 'Inicio',
        'nav.new' => 'Novo',
        'nav.profile' => 'Perfil',
        'nav.settings' => 'Configurações',
        'nav.login' => 'Login',
        'nav.register' => 'Registrar',
        'nav.logout' => 'Sair',
        'list.title' => 'Insumos',
        'list.add' => 'Adicionar',
        'list.export.excel' => 'Excel',
        'list.export.pdf' => 'PDF',
      ],
      'en' => [
        'app.title' => 'JNJ',
        'nav.home' => 'Home',
        'nav.new' => 'New',
        'nav.profile' => 'Profile',
        'nav.settings' => 'Settings',
        'nav.login' => 'Login',
        'nav.register' => 'Register',
        'nav.logout' => 'Logout',
        'list.title' => 'Supplies',
        'list.add' => 'Add',
        'list.export.excel' => 'Excel',
        'list.export.pdf' => 'PDF',
      ],
    ];
  }
  $lang = $lang ?? getLang();
  return $dict[$lang][$key] ?? $key;
}
