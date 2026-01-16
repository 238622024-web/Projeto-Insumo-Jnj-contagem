/**
 * HEADER & FOOTER - JAVASCRIPT
 * Funcionalidades gerais do header e footer
 */

(function() {
  'use strict';

  /**
   * Obter o valor padrão de itens por página do PHP
   * Este valor precisa ser inserido dinamicamente pelo PHP
   */
  let pageLength = 25; // valor padrão

  /**
   * Inicializar DataTables para todas as tabelas
   */
  function initDataTables() {
    if (typeof jQuery === 'undefined' || !jQuery.fn.DataTable) {
      console.warn('DataTables not loaded');
      return;
    }

    jQuery(document).ready(function($) {
      $('table.table').each(function() {
        const $table = $(this);
        const headerCount = $table.find('thead th').length;
        const firstBodyRow = $table.find('tbody tr:first');
        const firstRowCount = firstBodyRow.length ? firstBodyRow.find('td').length : 0;

        // If table has no thead or no header cells, skip initialization
        if (headerCount === 0) return;

        // If the first row is an "empty message" with a colspan equal to header count, allow init
        if (firstRowCount > 0 && firstRowCount !== headerCount) {
          // Try to detect a single placeholder row using colspan
          const $firstTd = firstBodyRow.find('td').first();
          const colspan = $firstTd.attr('colspan');
          if (!colspan) {
            console.warn('DataTables skipped due to column count mismatch (thead:', headerCount, 'td:', firstRowCount, ') for table', $table);
            return;
          }

          // If colspan exists but is incorrect, fix it to match headerCount
          if (parseInt(colspan, 10) !== headerCount) {
            console.info('Adjusting colspan from', colspan, 'to', headerCount, 'for table', $table);
            $firstTd.attr('colspan', headerCount);
          }
        }

        // Build a neutral columns definition to avoid column-count issues
        const columnsDef = [];
        for (let i = 0; i < headerCount; i++) {
          columnsDef.push({});
        }

        $table.DataTable({
          pageLength: pageLength,
          lengthMenu: [10, 25, 50, 100],
          columns: columnsDef,
          columnDefs: [{ orderable: false, targets: -1 }],
          language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json',
            searchPlaceholder: 'Pesquisar...'
          }
        });
      });
    });
  }

  /**
   * Remover alertas após alguns segundos
   */
  function initAutoCloseAlerts() {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
      setTimeout(function() {
        const bsAlert = new (window.bootstrap && window.bootstrap.Alert ? window.bootstrap.Alert : function() {})(alert);
        if (bsAlert && typeof bsAlert.close === 'function') {
          bsAlert.close();
        }
      }, 5000);
    });
  }

  /**
   * Inicializar funcionalidades
   */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      initDataTables();
      initAutoCloseAlerts();
    });
  } else {
    initDataTables();
    initAutoCloseAlerts();
  }
})();
