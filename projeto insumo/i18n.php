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
        'form.new.title' => 'Novo Material',
        'form.edit.title' => 'Editar Material',
        'form.count.date' => 'Data de Contagem',
        'form.unit' => 'Unidade',
        'form.name' => 'Nome do material',
        'form.position' => 'Posição',
        'form.lot' => 'Lote',
        'form.quantity' => 'Quantidade',
        'form.entry.date' => 'Data de Entrada',
        'form.expiry' => 'Validade',
        'form.notes' => 'Observações',
        'btn.back' => 'Voltar',
        'btn.save' => 'Salvar',
        'btn.save.changes' => 'Salvar alterações',
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
        'form.new.title' => 'New Item',
        'form.edit.title' => 'Edit Item',
        'form.count.date' => 'Count Date',
        'form.unit' => 'Unit',
        'form.name' => 'Item name',
        'form.position' => 'Position',
        'form.lot' => 'Lot',
        'form.quantity' => 'Quantity',
        'form.entry.date' => 'Entry Date',
        'form.expiry' => 'Expiry',
        'form.notes' => 'Notes',
        'btn.back' => 'Back',
        'btn.save' => 'Save',
        'btn.save.changes' => 'Save changes',
      ],
    ];
  }
  $lang = $lang ?? getLang();
  return $dict[$lang][$key] ?? $key;
}
