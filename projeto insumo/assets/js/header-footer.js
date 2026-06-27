/**
 * HEADER & FOOTER - JAVASCRIPT
 * Funcionalidades gerais do header e footer
 */

(function() {
  'use strict';

  const SIDEBAR_COLLAPSED_STORAGE_KEY = 'insumo.sidebar.collapsed';
  const ADMIN_MENU_OPEN_STORAGE_KEY = 'insumo.sidebar.adminMenu.open';
  const STOCK_MENU_OPEN_STORAGE_KEY = 'insumo.sidebar.stockMenu.open';
  const REPORT_MENU_OPEN_STORAGE_KEY = 'insumo.sidebar.reportMenu.open';

  function readStoredSidebarCollapsed() {
    try {
      return window.localStorage.getItem(SIDEBAR_COLLAPSED_STORAGE_KEY) === '1';
    } catch (error) {
      return false;
    }
  }

  function writeStoredSidebarCollapsed(isCollapsed) {
    try {
      if (isCollapsed) {
        window.localStorage.setItem(SIDEBAR_COLLAPSED_STORAGE_KEY, '1');
      } else {
        window.localStorage.removeItem(SIDEBAR_COLLAPSED_STORAGE_KEY);
      }
    } catch (error) {
      // Ignora ambientes sem acesso a storage.
    }
  }

  function setSidebarCollapsed(isCollapsed) {
    document.body.classList.toggle('sidebar-collapsed', isCollapsed);
  }

  function syncDesktopSidebarFromStorage() {
    setSidebarCollapsed(readStoredSidebarCollapsed());
  }

  function isDesktopSidebarCollapsed() {
    return document.body.classList.contains('sidebar-collapsed');
  }

  function readStoredAdminMenuOpen() {
    try {
      return window.localStorage.getItem(ADMIN_MENU_OPEN_STORAGE_KEY) === '1';
    } catch (error) {
      return false;
    }
  }

  function writeStoredAdminMenuOpen(isOpen) {
    try {
      if (isOpen) {
        window.localStorage.setItem(ADMIN_MENU_OPEN_STORAGE_KEY, '1');
      } else {
        window.localStorage.removeItem(ADMIN_MENU_OPEN_STORAGE_KEY);
      }
    } catch (error) {
      // Ignora ambientes sem acesso a storage.
    }
  }

  function readStoredStockMenuOpen() {
    try {
      return window.localStorage.getItem(STOCK_MENU_OPEN_STORAGE_KEY) === '1';
    } catch (error) {
      return false;
    }
  }

  function writeStoredStockMenuOpen(isOpen) {
    try {
      if (isOpen) {
        window.localStorage.setItem(STOCK_MENU_OPEN_STORAGE_KEY, '1');
      } else {
        window.localStorage.removeItem(STOCK_MENU_OPEN_STORAGE_KEY);
      }
    } catch (error) {
      // Ignora ambientes sem acesso a storage.
    }
  }

  function readStoredReportMenuOpen() {
    try {
      return window.localStorage.getItem(REPORT_MENU_OPEN_STORAGE_KEY) === '1';
    } catch (error) {
      return false;
    }
  }

  function writeStoredReportMenuOpen(isOpen) {
    try {
      if (isOpen) {
        window.localStorage.setItem(REPORT_MENU_OPEN_STORAGE_KEY, '1');
      } else {
        window.localStorage.removeItem(REPORT_MENU_OPEN_STORAGE_KEY);
      }
    } catch (error) {
      // Ignora ambientes sem acesso a storage.
    }
  }

  function setAdminMenuOpen(isOpen) {
    const menu = document.querySelector('[data-admin-menu]');
    if (!menu) return;

    const submenu = menu.querySelector('[data-admin-submenu]');
    const toggle = menu.querySelector('[data-admin-menu-toggle]');

    menu.classList.toggle('is-open', isOpen);
    menu.dataset.adminMenuOpen = isOpen ? 'true' : 'false';

    if (submenu) {
      submenu.classList.toggle('is-open', isOpen);
    }

    if (toggle) {
      toggle.classList.toggle('is-open', isOpen);
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    writeStoredAdminMenuOpen(isOpen);
  }

  function setMobileSidebarOpen(isOpen) {
    document.body.classList.toggle('sidebar-open-mobile', isOpen);
    document.body.classList.toggle('sidebar-scroll-lock', isOpen);

    const mobileSidebarToggle = document.querySelector('#mobile-sidebar-toggle');
    if (mobileSidebarToggle && mobileSidebarToggle.checked !== isOpen) {
      mobileSidebarToggle.checked = isOpen;
    }

    const toggleButtons = document.querySelectorAll('[data-sidebar-toggle]');
    toggleButtons.forEach(function(button) {
      button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    if (backdrop) {
      backdrop.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    }
  }

  function isMobileViewport() {
    return window.innerWidth <= 991.98;
  }

  let lastSidebarToggleAt = 0;
  let lastViewportIsMobile = isMobileViewport();
  let ignoreNextClickUntil = 0;
  let desktopSidebarHovering = false;

  function handleSidebarToggle(event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();

      if (event.type === 'click' && Date.now() < ignoreNextClickUntil) {
        return;
      }

      if (event.type === 'pointerup' && event.pointerType === 'touch') {
        ignoreNextClickUntil = Date.now() + 650;
      }
    }

    const now = Date.now();
    if (now - lastSidebarToggleAt < 300) {
      return;
    }
    lastSidebarToggleAt = now;

    if (isMobileViewport()) {
      setSidebarCollapsed(false);
      setMobileSidebarOpen(!document.body.classList.contains('sidebar-open-mobile'));
      return;
    }

    const isCollapsed = !isDesktopSidebarCollapsed();
    setSidebarCollapsed(isCollapsed);
    writeStoredSidebarCollapsed(isCollapsed);
  }

  function handleMenuToggle(event, menu, setOpenCallback, submenu) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();

      if (event.type === 'click' && Date.now() < ignoreNextClickUntil) {
        return;
      }
    }

    const nextIsOpen = !menu.classList.contains('is-open');
    setOpenCallback(nextIsOpen);

    if (submenu) {
      submenu.offsetHeight;
    }
  }

  function handleMobileSidebarClose(event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }

    const mobileSidebarToggle = document.querySelector('#mobile-sidebar-toggle');
    if (mobileSidebarToggle) {
      mobileSidebarToggle.checked = false;
    }

    setAdminMenuOpen(false);
    setStockMenuOpen(false);
    setReportMenuOpen(false);
    setMobileSidebarOpen(false);
  }

  function handleSidebarDoubleClick(event) {
    if (isMobileViewport()) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const isCollapsed = !isDesktopSidebarCollapsed();
    setSidebarCollapsed(isCollapsed);
    writeStoredSidebarCollapsed(isCollapsed);
  }

  function syncSidebarStateToViewport() {
    const isMobile = isMobileViewport();
    if (isMobile === lastViewportIsMobile) {
      return;
    }

    lastViewportIsMobile = isMobile;

    if (isMobile) {
      setSidebarCollapsed(false);
      setMobileSidebarOpen(false);
      return;
    }

    syncDesktopSidebarFromStorage();
    setMobileSidebarOpen(false);
  }

  function enableDesktopSidebarHover(sidebar) {
    sidebar.addEventListener('mouseenter', function() {
      if (isMobileViewport()) return;
      if (!readStoredSidebarCollapsed()) return;

      desktopSidebarHovering = true;
      setSidebarCollapsed(false);
    });

    sidebar.addEventListener('mouseleave', function() {
      if (isMobileViewport()) return;
      if (!readStoredSidebarCollapsed()) return;

      desktopSidebarHovering = false;
      setSidebarCollapsed(true);
    });
  }

  function initSidebarToggle() {
    if (!document.body.classList.contains('app-shell')) return;

    const sidebar = document.querySelector('[data-sidebar]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const mobileSidebarToggle = document.querySelector('#mobile-sidebar-toggle');
    const toggleButtons = document.querySelectorAll('[data-sidebar-toggle]');
    if (!sidebar || !toggleButtons.length) return;

    if (isMobileViewport()) {
      setSidebarCollapsed(false);
      setMobileSidebarOpen(false);
    } else {
      syncDesktopSidebarFromStorage();
      setMobileSidebarOpen(false);
    }

    enableDesktopSidebarHover(sidebar);

    toggleButtons.forEach(function(button) {
      button.addEventListener('pointerup', function(event) {
        if (event.pointerType === 'touch') {
          handleSidebarToggle(event);
        }
      }, { passive: false });
      button.addEventListener('click', function(event) {
        if (isMobileViewport()) {
          handleSidebarToggle(event);
        }
      });
      button.addEventListener('dblclick', handleSidebarDoubleClick);
    });

    if (mobileSidebarToggle) {
      mobileSidebarToggle.addEventListener('change', function() {
        setSidebarCollapsed(false);
        setMobileSidebarOpen(this.checked);
      });
    }

    sidebar.addEventListener('click', function(event) {
      const clickedLink = event.target.closest('a.sidebar-link, a.sidebar-submenu-link');
      if (!clickedLink) return;

      if (isMobileViewport()) {
        setMobileSidebarOpen(false);
        setAdminMenuOpen(false);
        setStockMenuOpen(false);
        setReportMenuOpen(false);
      }
    });

    const closeButton = sidebar.querySelector('[data-sidebar-close-mobile]');
    if (closeButton) {
      closeButton.addEventListener('click', function() {
        if (isMobileViewport()) {
          handleMobileSidebarClose();
        }
      });

      closeButton.addEventListener('pointerup', function(event) {
        if (event.pointerType === 'touch' && isMobileViewport()) {
          handleMobileSidebarClose(event);
        }
      }, { passive: false });

      closeButton.addEventListener('touchend', function(event) {
        if (isMobileViewport()) {
          handleMobileSidebarClose(event);
        }
      }, { passive: false });
    }

    if (backdrop) {
      backdrop.addEventListener('click', function() {
        if (isMobileViewport()) {
          setMobileSidebarOpen(false);
        }
      });
    }

    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape' && document.body.classList.contains('sidebar-open-mobile')) {
        setMobileSidebarOpen(false);
      }
    });

    window.addEventListener('resize', function() {
      syncSidebarStateToViewport();
    });
  }

  function initAdminMenuToggle() {
    const menu = document.querySelector('[data-admin-menu]');
    if (!menu) return;

    const toggle = menu.querySelector('[data-admin-menu-toggle]');
    if (!toggle) return;

    const submenu = menu.querySelector('[data-admin-submenu]');
    const initialIsOpen = menu.dataset.adminMenuOpen === 'true' || readStoredAdminMenuOpen();
    setAdminMenuOpen(initialIsOpen);

    toggle.addEventListener('click', function(event) {
      handleMenuToggle(event, menu, setAdminMenuOpen, submenu);
    });
  }

  function setStockMenuOpen(isOpen) {
    const menu = document.querySelector('[data-stock-menu]');
    if (!menu) return;

    const submenu = menu.querySelector('[data-stock-submenu]');
    const toggle = menu.querySelector('[data-stock-menu-toggle]');

    menu.classList.toggle('is-open', isOpen);
    menu.dataset.stockMenuOpen = isOpen ? 'true' : 'false';

    if (submenu) {
      submenu.classList.toggle('is-open', isOpen);
    }

    if (toggle) {
      toggle.classList.toggle('is-open', isOpen);
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    writeStoredStockMenuOpen(isOpen);
  }

  function initStockMenuToggle() {
    const menu = document.querySelector('[data-stock-menu]');
    if (!menu) return;

    const toggle = menu.querySelector('[data-stock-menu-toggle]');
    if (!toggle) return;

    const submenu = menu.querySelector('[data-stock-submenu]');
    const initialIsOpen = menu.dataset.stockMenuOpen === 'true' || readStoredStockMenuOpen();
    setStockMenuOpen(initialIsOpen);

    toggle.addEventListener('click', function(event) {
      handleMenuToggle(event, menu, setStockMenuOpen, submenu);
    });
  }

  function setReportMenuOpen(isOpen) {
    const menu = document.querySelector('[data-report-menu]');
    if (!menu) return;

    const submenu = menu.querySelector('[data-report-submenu]');
    const toggle = menu.querySelector('[data-report-menu-toggle]');

    menu.classList.toggle('is-open', isOpen);
    menu.dataset.reportMenuOpen = isOpen ? 'true' : 'false';

    if (submenu) {
      submenu.classList.toggle('is-open', isOpen);
    }

    if (toggle) {
      toggle.classList.toggle('is-open', isOpen);
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    writeStoredReportMenuOpen(isOpen);
  }

  function initReportMenuToggle() {
    const menu = document.querySelector('[data-report-menu]');
    if (!menu) return;

    const toggle = menu.querySelector('[data-report-menu-toggle]');
    if (!toggle) return;

    const submenu = menu.querySelector('[data-report-submenu]');
    const initialIsOpen = menu.dataset.reportMenuOpen === 'true' || readStoredReportMenuOpen();
    setReportMenuOpen(initialIsOpen);

    toggle.addEventListener('click', function(event) {
      handleMenuToggle(event, menu, setReportMenuOpen, submenu);
    });
  }

  function normalizeText(value) {
    return (value || '')
      .toString()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/[^a-z0-9\s]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function levenshteinDistance(a, b) {
    if (a === b) return 0;
    if (!a.length) return b.length;
    if (!b.length) return a.length;

    const prev = [];
    const curr = [];

    for (let j = 0; j <= b.length; j++) {
      prev[j] = j;
    }

    for (let i = 1; i <= a.length; i++) {
      curr[0] = i;
      for (let j = 1; j <= b.length; j++) {
        const cost = a[i - 1] === b[j - 1] ? 0 : 1;
        curr[j] = Math.min(
          curr[j - 1] + 1,
          prev[j] + 1,
          prev[j - 1] + cost
        );
      }

      for (let j = 0; j <= b.length; j++) {
        prev[j] = curr[j];
      }
    }

    return prev[b.length];
  }

  function tokenMatches(token, rowText, rowWords) {
    if (!token) return true;
    if (rowText.indexOf(token) !== -1) return true;

    if (token.length <= 2) {
      return false;
    }

    const maxDistance = token.length >= 6 ? 2 : 1;

    for (let i = 0; i < rowWords.length; i++) {
      const word = rowWords[i];
      if (!word) continue;
      if (Math.abs(word.length - token.length) > maxDistance) continue;
      if (word[0] !== token[0]) continue;

      if (levenshteinDistance(token, word) <= maxDistance) {
        return true;
      }
    }

    return false;
  }

  function installAdvancedSearch($) {
    if (window.__insumoAdvancedSearchInstalled) return;

    $.fn.dataTable.ext.search.push(function(settings, searchData) {
      if (!settings || !settings.nTable || !settings.nTable.classList || !settings.nTable.classList.contains('js-materials-search')) {
        return true;
      }

      const rawSearch = (settings && settings.oPreviousSearch && settings.oPreviousSearch.sSearch) || '';
      const query = normalizeText(rawSearch);
      if (!query) return true;

      const rowText = normalizeText((searchData || []).join(' '));
      if (!rowText) return false;

      const tokens = query.split(' ').filter(Boolean);
      if (!tokens.length) return true;

      const rowWords = rowText.split(' ').filter(Boolean);
      for (let i = 0; i < tokens.length; i++) {
        if (!tokenMatches(tokens[i], rowText, rowWords)) {
          return false;
        }
      }

      return true;
    });

    window.__insumoAdvancedSearchInstalled = true;
  }

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
      installAdvancedSearch($);

      $('table.table').each(function() {
        const $table = $(this);
        if ($table.hasClass('js-no-datatable')) return;
        const isMaterialsTable = $table.hasClass('js-materials-search');
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

        const options = {
          pageLength: pageLength,
          lengthMenu: [5, 6, 10, 15, 25],
          search: {
            smart: false,
            regex: false,
            caseInsensitive: true
          },
          columns: columnsDef,
          columnDefs: [{ orderable: false, targets: -1 }],
          autoWidth: false,
          scrollX: true,
          language: {
            url: 'assets/vendor/datatables/i18n/pt-BR.json',
            searchPlaceholder: 'Pesquisar...'
          }
        };

        if (isMaterialsTable) {
          options.pageLength = 6;
          options.lengthMenu = [6, 12, 24, 48];
          options.dom = "<'materials-table-top d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3'<'materials-top-left'l><'materials-top-right'f>>" +
            "t" +
            "<'materials-table-bottom d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3'<'materials-bottom-left'i><'materials-bottom-right'p>>";
          options.language.lengthMenu = 'Exibir _MENU_ resultados por pagina';
          options.language.search = 'Pesquisar';
          options.language.info = 'Mostrando _START_ a _END_ de _TOTAL_ materiais';
          options.language.zeroRecords = 'Nenhum material encontrado';
          options.language.emptyTable = 'Nenhum material cadastrado';
          options.initComplete = function() {
            const wrapper = this.api().table().container();
            if (wrapper && wrapper.classList) {
              wrapper.classList.add('materials-dt-wrapper');
            }
          };
        }

        $table.DataTable(options);
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
      initSidebarToggle();
      initAdminMenuToggle();
      initStockMenuToggle();
      initReportMenuToggle();
      initDataTables();
      initAutoCloseAlerts();
    });
  } else {
    initSidebarToggle();
    initAdminMenuToggle();
    initStockMenuToggle();
    initReportMenuToggle();
    initDataTables();
    initAutoCloseAlerts();
  }
})();
