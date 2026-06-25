/**
 * PICKING QR CODE - JAVASCRIPT
 * Leitura por camera e preenchimento da busca do produto
 */

(function () {
  'use strict';

  function playBeep() {
    if (!window.AudioContext && !window.webkitAudioContext) return;

    try {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      const audioCtx = new Ctx();

      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      const now = audioCtx.currentTime;

      osc.type = 'sine';
      osc.frequency.value = 1040;
      osc.connect(gain);
      gain.connect(audioCtx.destination);

      gain.gain.setValueAtTime(0.001, now);
      gain.gain.exponentialRampToValueAtTime(0.06, now + 0.01);
      gain.gain.exponentialRampToValueAtTime(0.001, now + 0.12);

      osc.start(now);
      osc.stop(now + 0.14);
    } catch (error) {
      console.warn('Nao foi possivel tocar beep:', error);
    }
  }

  function initPickingScanner() {
    const lookupForm = document.getElementById('picking-lookup-form');
    const codeInput = document.getElementById('picking-scan-code');
    const startBtn = document.getElementById('btn-start-picking-scan');
    const stopBtn = document.getElementById('btn-stop-picking-scan');
    const readerEl = document.getElementById('reader-picking');

    if (!lookupForm || !codeInput || !startBtn || !stopBtn || !readerEl) return;

    let html5QrCode = null;
    let scanRunning = false;

    function stopScan() {
      if (!scanRunning || !html5QrCode) {
        readerEl.style.display = 'none';
        startBtn.style.display = 'inline-flex';
        stopBtn.style.display = 'none';
        return Promise.resolve();
      }

      return html5QrCode.stop().catch(function () {}).then(function () {
        return html5QrCode.clear().catch(function () {});
      }).finally(function () {
        scanRunning = false;
        html5QrCode = null;
        readerEl.style.display = 'none';
        startBtn.style.display = 'inline-flex';
        stopBtn.style.display = 'none';
      });
    }

    function onScanSuccess(decodedText) {
      const value = (decodedText || '').trim();
      if (!value) return;

      codeInput.value = value;
      playBeep();
      stopScan().finally(function () {
        lookupForm.requestSubmit();
      });
    }

    async function startScan() {
      if (scanRunning) return;

      if (typeof window.Html5Qrcode === 'undefined') {
        alert('Leitor de camera indisponivel no momento.');
        return;
      }

      try {
        readerEl.style.display = 'block';
        startBtn.style.display = 'none';
        stopBtn.style.display = 'inline-flex';

        html5QrCode = new window.Html5Qrcode('reader-picking');
        await html5QrCode.start(
          { facingMode: 'environment' },
          { fps: 10, qrbox: { width: 240, height: 180 } },
          onScanSuccess,
          function () {}
        );

        scanRunning = true;
      } catch (error) {
        console.error('Erro ao iniciar scanner do picking:', error);
        alert('Nao foi possivel iniciar a camera para leitura.');
        stopScan();
      }
    }

    startBtn.addEventListener('click', startScan);
    stopBtn.addEventListener('click', function () {
      stopScan();
    });

    window.addEventListener('beforeunload', function () {
      stopScan();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPickingScanner);
  } else {
    initPickingScanner();
  }
})();