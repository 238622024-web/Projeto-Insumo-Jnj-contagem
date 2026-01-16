/**
 * SETTINGS PAGE - JAVASCRIPT
 * Gerencia o logout do usuário
 */

document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  const logoutBtn = document.getElementById('logout-btn');

  if (logoutBtn) {
    logoutBtn.addEventListener('click', () => {
      // Redirect to the login page
      window.location.href = 'index.html';
    });
  }
});
