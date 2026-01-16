/**
 * CREATE ACCOUNT PAGE - JAVASCRIPT
 * Gerencia o toggle de visibilidade de senhas na página de criação de conta
 */

(function() {
  'use strict';

  /**
   * Configurar toggle para um campo de senha específico
   */
  function setupToggle(btnId, inputId, openId, closedId) {
    const btn = document.getElementById(btnId);
    const input = document.getElementById(inputId);
    const eyeOpen = document.getElementById(openId);
    const eyeClosed = document.getElementById(closedId);

    if (!btn || !input) return;

    btn.addEventListener('click', function() {
      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';

      if (eyeOpen && eyeClosed) {
        eyeOpen.classList.toggle('d-none', !showing);
        eyeClosed.classList.toggle('d-none', showing);
      }

      btn.setAttribute('aria-label', showing ? 'Mostrar senha' : 'Ocultar senha');
    });
  }

  // Inicializar quando o documento está pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      setupToggle('togglePasswordCA', 'password', 'eyeOpenCA', 'eyeClosedCA');
      setupToggle('toggleConfirmCA', 'confirm_password', 'eyeOpenConfirmCA', 'eyeClosedConfirmCA');
    });
  } else {
    setupToggle('togglePasswordCA', 'password', 'eyeOpenCA', 'eyeClosedCA');
    setupToggle('toggleConfirmCA', 'confirm_password', 'eyeOpenConfirmCA', 'eyeClosedConfirmCA');
  }
})();
