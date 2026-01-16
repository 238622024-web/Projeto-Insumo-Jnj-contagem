/**
 * PROFILE PAGE - JAVASCRIPT
 * Gerencia o toggle de visibilidade de senha na página de perfil
 */

(function() {
  'use strict';

  /**
   * Toggle de visibilidade da nova senha
   */
  function initNewPasswordToggle() {
    const btn = document.getElementById('toggleNewPwd');
    const input = document.getElementById('new_password');
    const eyeOpen = document.getElementById('eyeOpenNP');
    const eyeClosed = document.getElementById('eyeClosedNP');

    if (btn && input) {
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
  }

  // Inicializar quando o documento está pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNewPasswordToggle);
  } else {
    initNewPasswordToggle();
  }
})();
