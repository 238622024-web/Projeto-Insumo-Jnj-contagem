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
  let pageLength = 6; // valor padrão compacto
  if (window.innerWidth <= 768) {
    pageLength = 5;
  }

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
        const $bodyRows = $table.find('tbody tr');

        // If table has no thead or no header cells, skip initialization
        if (headerCount === 0) return;

        // Avoid re-initializing already enhanced tables
        if ($.fn.DataTable.isDataTable($table)) return;

        // Validate every body row against header column count
        let hasInvalidRow = false;
        $bodyRows.each(function() {
          const $row = $(this);
          const $cells = $row.children('td, th');
          const cellCount = $cells.length;

          if (cellCount === 0) return;

          // Placeholder row (single cell with colspan) is not valid for DataTables body.
          // Remove it and let DataTables render its own empty state.
          if (cellCount === 1) {
            const colspan = parseInt($cells.first().attr('colspan') || '0', 10);
            if (colspan > 1) {
              $row.remove();
              return;
            }
          }

          if (cellCount !== headerCount) {
            hasInvalidRow = true;
          }
        });

        if (hasInvalidRow) {
          console.warn('DataTables skipped due to column count mismatch in tbody for table', $table);
          return;
        }

        // Build a neutral columns definition to avoid column-count issues
        const columnsDef = [];
        for (let i = 0; i < headerCount; i++) {
          columnsDef.push({});
        }

        $table.DataTable({
          pageLength: pageLength,
          lengthMenu: [5, 6, 10, 15, 25],
          columns: columnsDef,
          columnDefs: [{ orderable: false, targets: -1 }],
          autoWidth: false,
          scrollX: true,
          language: {
            url: 'assets/vendor/datatables/i18n/pt-BR.json',
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
