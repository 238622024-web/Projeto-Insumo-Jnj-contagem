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

    if (input && btn) {
      const check = function() {
        btn.disabled = (input.value.trim().toUpperCase() !== 'EXCLUIR');
      };

      input.addEventListener('input', check);
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
