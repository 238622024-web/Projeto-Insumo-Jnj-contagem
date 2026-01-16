/**
 * CADASTRO INSUMO PAGE - JAVASCRIPT
 * Gerencia a lógica de cálculo automático de data de validade
 */

(function() {
  'use strict';

  /**
   * Calcular data de validade (+2 anos) a partir da data de entrada
   */
  function initDateValidityCalculator() {
    const entrada = document.querySelector('input[name="data_entrada"]');
    const validade = document.querySelector('input[name="validade"]');

    if (!entrada || !validade) return;

    /**
     * Converter data para formato Y-m-d
     */
    function toYmd(d) {
      const pad = (n) => String(n).padStart(2, '0');
      return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }

    /**
     * Adicionar 2 anos a uma data ISO (Y-m-d)
     */
    function addTwoYears(iso) {
      const d = new Date(iso);
      if (Number.isNaN(d.getTime())) return null;
      d.setFullYear(d.getFullYear() + 2);
      return toYmd(d);
    }

    // Listener para mudanças na data de entrada
    entrada.addEventListener('change', function() {
      if (!this.value) return;
      const v = addTwoYears(this.value);
      if (v) validade.value = v;
    });
  }

  // Inicializar quando o documento está pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDateValidityCalculator);
  } else {
    initDateValidityCalculator();
  }
})();
