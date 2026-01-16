/**
 * DASHBOARD - JAVASCRIPT
 * Gerencia a funcionalidade do dashboard
 */

document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  const logoutBtn = document.getElementById('logout-btn');
  const totalInsumos = document.getElementById('total-insumos');
  const insumosTableBody = document.getElementById('insumos-table-body');

  // Recuperar insumos do Local Storage
  const insumos = JSON.parse(localStorage.getItem('insumos')) || [];

  /**
   * Renderizar o dashboard com dados dos insumos
   */
  const renderDashboard = () => {
    // Atualizar total de insumos
    if (totalInsumos) {
      totalInsumos.textContent = insumos.length;
    }

    // Atualizar tabela de insumos
    if (insumosTableBody) {
      insumosTableBody.innerHTML = '';
      insumos.forEach(insumo => {
        const row = document.createElement('tr');
        row.className = 'bg-white border-b hover:bg-gray-50';
        row.innerHTML = `
          <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">${escapeHtml(insumo.id)}</td>
          <td class="px-6 py-4">${escapeHtml(insumo.name)}</td>
          <td class="px-6 py-4 text-center">${escapeHtml(insumo.quantity)}</td>
          <td class="px-6 py-4 text-center">${escapeHtml(insumo.arrivalDate)}</td>
        `;
        insumosTableBody.appendChild(row);
      });
    }
  };

  /**
   * Escape HTML para evitar XSS
   */
  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
  }

  // Configurar logout
  if (logoutBtn) {
    logoutBtn.addEventListener('click', () => {
      window.location.href = 'index.html';
    });
  }

  // Renderizar dashboard
  renderDashboard();
});
