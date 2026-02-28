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

  function initBarcodeScanner() {
    const form = document.getElementById('form-cadastro-insumo');
    const barcodeInput = document.getElementById('codigo_barra');
    const startBtn = document.getElementById('btn-start-scan');
    const stopBtn = document.getElementById('btn-stop-scan');
    const readerEl = document.getElementById('reader');
    const nameInput = document.querySelector('input[name="nome"]');

    if (!form || !barcodeInput) return;

    barcodeInput.addEventListener('keydown', function(event) {
      if (event.key !== 'Enter') return;
      event.preventDefault();

      if (form.checkValidity()) {
        form.submit();
        return;
      }

      if (nameInput && !nameInput.value.trim()) {
        nameInput.focus();
      }
    });

    if (!startBtn || !stopBtn || !readerEl) return;

    let html5QrCode = null;
    let scanRunning = false;

    function onScanSuccess(decodedText) {
      if (!decodedText) return;

      barcodeInput.value = decodedText.trim();
      barcodeInput.dispatchEvent(new Event('change', { bubbles: true }));

      stopScan();

      if (form.checkValidity()) {
        form.submit();
      } else if (nameInput && !nameInput.value.trim()) {
        nameInput.focus();
      }
    }

    async function startScan() {
      if (scanRunning) return;

      if (typeof window.Html5Qrcode === 'undefined') {
        alert('Leitor de câmera indisponível no momento.');
        return;
      }

      try {
        readerEl.style.display = 'block';
        startBtn.style.display = 'none';
        stopBtn.style.display = 'inline-block';

        html5QrCode = new window.Html5Qrcode('reader');
        await html5QrCode.start(
          { facingMode: 'environment' },
          { fps: 10, qrbox: { width: 250, height: 120 } },
          onScanSuccess,
          () => {}
        );
        scanRunning = true;
      } catch (error) {
        console.error('Erro ao iniciar scanner:', error);
        alert('Não foi possível iniciar a câmera para escaneamento.');
        stopScan();
      }
    }

    async function stopScan() {
      if (!scanRunning || !html5QrCode) {
        readerEl.style.display = 'none';
        startBtn.style.display = 'inline-block';
        stopBtn.style.display = 'none';
        return;
      }

      try {
        await html5QrCode.stop();
        await html5QrCode.clear();
      } catch (error) {
        console.warn('Falha ao parar scanner:', error);
      }

      scanRunning = false;
      html5QrCode = null;
      readerEl.style.display = 'none';
      startBtn.style.display = 'inline-block';
      stopBtn.style.display = 'none';
    }

    startBtn.addEventListener('click', startScan);
    stopBtn.addEventListener('click', stopScan);

    window.addEventListener('beforeunload', () => {
      stopScan();
    });
  }

  // Inicializar quando o documento está pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      initDateValidityCalculator();
      initBarcodeScanner();
    });
  } else {
    initDateValidityCalculator();
    initBarcodeScanner();
  }
})();
