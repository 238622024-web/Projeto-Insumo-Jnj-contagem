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

  function scorePassword(value) {
    let score = 0;
    const password = value || '';

    if (password.length >= 8) score += 1;
    if (password.length >= 12) score += 1;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score += 1;
    if (/\d/.test(password)) score += 1;
    if (/[^A-Za-z0-9]/.test(password)) score += 1;

    return Math.min(score, 4);
  }

  function setupPasswordStrength() {
    const input = document.getElementById('new_password');
    const confirmInput = document.getElementById('confirm_password');
    const root = document.querySelector('[data-password-strength]');
    if (!input || !root) return;

    const bar = root.querySelector('[data-password-strength-bar]');
    const label = root.querySelector('[data-password-strength-label]');
    const hint = root.querySelector('[data-password-strength-hint]');

    function render() {
      const value = input.value || '';
      const score = scorePassword(value);
      const levels = [
        { text: 'Muito fraca', className: 'is-weak', width: '15%' },
        { text: 'Fraca', className: 'is-weak', width: '35%' },
        { text: 'Boa', className: 'is-medium', width: '62%' },
        { text: 'Forte', className: 'is-strong', width: '86%' },
        { text: 'Muito forte', className: 'is-strong', width: '100%' },
      ];

      const level = value ? levels[score] : { text: 'Digite uma senha', className: 'is-empty', width: '0%' };
      root.className = 'password-strength ' + level.className;

      if (bar) {
        bar.style.width = level.width;
      }
      if (label) {
        label.textContent = level.text;
      }
      if (hint) {
        if (!value) {
          hint.textContent = 'Use 8+ caracteres, letras maiúsculas, números e símbolos.';
        } else if (confirmInput && confirmInput.value && confirmInput.value !== value) {
          hint.textContent = 'As senhas ainda não conferem.';
        } else if (score >= 4) {
          hint.textContent = 'Senha excelente.';
        } else if (score >= 2) {
          hint.textContent = 'Inclua mais variedade para fortalecer a senha.';
        } else {
          hint.textContent = 'Adicione letras maiúsculas, números e símbolos.';
        }
      }
    }

    input.addEventListener('input', render);
    if (confirmInput) {
      confirmInput.addEventListener('input', render);
    }
    render();
  }

  function centerCropImage(file, targetSize) {
    return new Promise(function(resolve, reject) {
      const reader = new FileReader();
      reader.onerror = function() {
        reject(new Error('Não foi possível ler a imagem.'));
      };
      reader.onload = function() {
        const image = new Image();
        image.onerror = function() {
          reject(new Error('Não foi possível carregar a imagem.'));
        };
        image.onload = function() {
          const size = Math.min(image.width, image.height);
          const offsetX = Math.floor((image.width - size) / 2);
          const offsetY = Math.floor((image.height - size) / 2);
          const canvas = document.createElement('canvas');
          canvas.width = targetSize;
          canvas.height = targetSize;
          const context = canvas.getContext('2d');
          if (!context) {
            reject(new Error('Não foi possível preparar o corte da imagem.'));
            return;
          }

          context.drawImage(image, offsetX, offsetY, size, size, 0, 0, targetSize, targetSize);
          canvas.toBlob(function(blob) {
            if (!blob) {
              reject(new Error('Não foi possível gerar o avatar cortado.'));
              return;
            }
            const extension = file.type === 'image/png' ? 'png' : file.type === 'image/webp' ? 'webp' : 'jpg';
            const croppedFile = new File([blob], file.name.replace(/\.[^.]+$/, '') + '-avatar.' + extension, { type: blob.type || file.type || 'image/jpeg' });
            resolve(croppedFile);
          }, file.type === 'image/png' ? 'image/png' : 'image/jpeg', 0.92);
        };
        image.src = reader.result;
      };
      reader.readAsDataURL(file);
    });
  }

  function setupAvatarPreview() {
    const input = document.getElementById('avatarInput');
    const preview = document.querySelector('[data-avatar-preview]');
    const removeCheckbox = document.getElementById('removeAvatar');
    const removeButton = document.querySelector('[data-remove-avatar-button]');
    if (!input || !preview) return;

    function setPreviewFromFile(file) {
      const objectUrl = URL.createObjectURL(file);
      preview.classList.remove('avatar-preview-fallback');
      if (preview.tagName === 'IMG') {
        preview.src = objectUrl;
      } else {
        preview.innerHTML = '<img src="' + objectUrl + '" alt="Pré-visualização do avatar" class="avatar-preview-image" />';
      }
    }

    async function handleChange() {
      const file = input.files && input.files[0];
      if (!file) return;

      if (!file.type || !file.type.startsWith('image/')) {
        input.value = '';
        return;
      }

      try {
        const croppedFile = await centerCropImage(file, 512);
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(croppedFile);
        input.files = dataTransfer.files;
        setPreviewFromFile(croppedFile);
        if (removeCheckbox) {
          removeCheckbox.checked = false;
        }
      } catch (error) {
        input.value = '';
      }
    }

    input.addEventListener('change', handleChange);

    if (removeCheckbox) {
      removeCheckbox.addEventListener('change', function() {
        if (this.checked) {
          input.value = '';
        }
      });
    }

    if (removeButton) {
      removeButton.addEventListener('click', function() {
        if (removeCheckbox) {
          removeCheckbox.checked = true;
          input.value = '';
          input.dispatchEvent(new Event('change'));
        }
      });
    }

    if (!preview.tagName || preview.tagName.toLowerCase() !== 'img') {
      preview.classList.add('avatar-preview-fallback');
    }
  }

  // Inicializar quando o documento está pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProfileToggles);
    document.addEventListener('DOMContentLoaded', setupPasswordStrength);
    document.addEventListener('DOMContentLoaded', setupAvatarPreview);
  } else {
    initProfileToggles();
    setupPasswordStrength();
    setupAvatarPreview();
  }
})();
