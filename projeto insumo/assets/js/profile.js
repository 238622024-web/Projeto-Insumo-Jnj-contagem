/**
 * PROFILE PAGE - JAVASCRIPT
 * Gerencia o toggle de visibilidade de senha na página de perfil
 */

(function() {
  'use strict';

  function setupToggle(btnId, inputId, openId, closedId) {
    const btn = document.getElementById(btnId);
    const input = document.getElementById(inputId);
    if (!btn || !input) return;

    const eyeOpen = openId ? document.getElementById(openId) : null;
    const eyeClosed = closedId ? document.getElementById(closedId) : null;

    btn.addEventListener('click', function() {
      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';

      if (eyeOpen && eyeClosed) {
        eyeOpen.classList.toggle('d-none', !showing);
        eyeClosed.classList.toggle('d-none', showing);
      } else {
        const icon = btn.querySelector('i');
        if (icon) {
          icon.classList.toggle('fa-eye', showing);
          icon.classList.toggle('fa-eye-slash', !showing);
        }
      }

      btn.setAttribute('aria-label', showing ? 'Mostrar senha' : 'Ocultar senha');
    });
  }

  function initProfileToggles() {
    setupToggle('toggleCurrentPwd', 'current_password');
    setupToggle('toggleNewPwd', 'new_password', 'eyeOpenNP', 'eyeClosedNP');
    setupToggle('toggleConfirmPwd', 'confirm_password');
  }

  // Inicializar quando o documento está pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProfileToggles);
  } else {
    initProfileToggles();
  }
})();
