/**
 * LOGIN PAGE - JAVASCRIPT
 * Gerencia a funcionalidade de toggle de senha e campos preenchidos
 */

(function() {
  'use strict';

  /**
   * Toggle de visibilidade da senha
   */
  function initPasswordToggle() {
    const toggleBtn = document.getElementById('togglePassword');
    const pwd = document.getElementById('password');
    const eyeOpen = document.getElementById('eyeOpen');
    const eyeClosed = document.getElementById('eyeClosed');

    if (toggleBtn && pwd) {
      toggleBtn.addEventListener('click', function() {
        const newType = pwd.type === 'password' ? 'text' : 'password';
        pwd.type = newType;

        const isVisible = newType === 'text';
        if (eyeOpen && eyeClosed) {
          eyeOpen.classList.toggle('d-none', !isVisible);
          eyeClosed.classList.toggle('d-none', isVisible);
        }

        const label = isVisible ? 'Ocultar senha' : 'Mostrar senha';
        toggleBtn.setAttribute('aria-label', label);
        toggleBtn.setAttribute('title', label);
        toggleBtn.setAttribute('aria-pressed', String(isVisible));
      });
    }
  }

  /**
   * Toggle de classe .filled em inputs para labels flutuarem
   */
  function initFilledState() {
    function updateFilled(el) {
      const wrapper = el.closest('.mb-3');
      if (!wrapper) return;

      if (el.value && el.value.trim() !== '') {
        wrapper.classList.add('filled');
      } else {
        wrapper.classList.remove('filled');
      }
    }

    document.addEventListener('DOMContentLoaded', function() {
      const inputs = document.querySelectorAll('.mb-3 input, .mb-3 textarea');
      inputs.forEach(function(inp) {
        // Initialize
        updateFilled(inp);
        // Update on input/change
        inp.addEventListener('input', function() {
          updateFilled(inp);
        });
        inp.addEventListener('change', function() {
          updateFilled(inp);
        });
      });
    });
  }

  // Inicializar quando o documento está pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      initPasswordToggle();
      initFilledState();
    });
  } else {
    initPasswordToggle();
    initFilledState();
  }
})();
