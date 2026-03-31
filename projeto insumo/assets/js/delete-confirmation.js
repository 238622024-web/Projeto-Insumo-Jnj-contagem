/**
 * DELETE CONFIRMATION PAGE - JAVASCRIPT
 * Gerencia a validação de texto de confirmação de exclusão
 */

(function() {
  'use strict';

  /**
   * Verificar se o texto de confirmação está correto
   */
  function initDeleteConfirmation() {
    const input = document.getElementById('confirm_text');
    const btn = document.getElementById('btnDelete');
    const ack = document.getElementById('ack_delete');

    if (input && btn) {
      const check = function() {
        const validText = input.value.trim().toUpperCase() === 'EXCLUIR';
        const validAck = !!(ack && ack.checked);
        btn.disabled = !(validText && validAck);
      };

      input.addEventListener('input', check);
      if (ack) {
        ack.addEventListener('change', check);
      }
      check(); // Initialize on page load
    }
  }

  // Inicializar quando o documento está pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDeleteConfirmation);
  } else {
    initDeleteConfirmation();
  }
})();
