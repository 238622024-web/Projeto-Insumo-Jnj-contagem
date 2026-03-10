/**
 * CONTAGEM PAGE - JAVASCRIPT
 * Suporte ao scanner fisico e scanner por camera na tela de contagem
 */

(function () {
  'use strict';

  function initInputFocus() {
    const input = document.getElementById('scan-input');
    if (!input) return;

    input.focus();

    setInterval(function () {
      if (document.hidden) return;
      if (document.activeElement !== input) {
        input.focus();
      }
    }, 1500);
  }

  function initAlertBeeps() {
    const successAlert = document.querySelector('.alert.alert-success');
    const errorAlert = document.querySelector('.alert.alert-danger');
    if (!successAlert && !errorAlert) return;

    if (!window.AudioContext && !window.webkitAudioContext) return;

    const isSuccess = !!successAlert;
    const sourceAlert = successAlert || errorAlert;
    const message = ((sourceAlert && sourceAlert.textContent) || '').toLowerCase();
    if (message.indexOf('contagem') === -1) return;

    try {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      const audioCtx = new Ctx();

      const beep = function (frequency, startOffset, duration, peak) {
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        const now = audioCtx.currentTime + startOffset;

        osc.type = 'sine';
        osc.frequency.value = frequency;

        osc.connect(gain);
        gain.connect(audioCtx.destination);

        gain.gain.setValueAtTime(0.001, now);
        gain.gain.exponentialRampToValueAtTime(peak, now + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.001, now + duration);

        osc.start(now);
        osc.stop(now + duration + 0.02);
      };

      const playPattern = function () {
        if (isSuccess) {
          // Sucesso: beep unico curto
          beep(880, 0, 0.16, 0.06);
          return;
        }

        // Erro: beep duplo curto
        beep(520, 0, 0.12, 0.07);
        beep(520, 0.16, 0.12, 0.07);
      };

      if (audioCtx.state === 'suspended') {
        audioCtx.resume().then(playPattern).catch(function () {});
      } else {
        playPattern();
      }
    } catch (error) {
      console.warn('Nao foi possivel tocar beep de alerta:', error);
    }
  }

  function initCameraScanner() {
    const form = document.querySelector('form[method="post"]');
    const barcodeInput = document.getElementById('scan-input');
    const feedbackEl = document.getElementById('scan-feedback');
    const feedbackTextEl = document.getElementById('scan-feedback-text');
    const startBtn = document.getElementById('btn-start-scan-contagem');
    const stopBtn = document.getElementById('btn-stop-scan-contagem');
    const readerEl = document.getElementById('reader-contagem');

    if (!form || !barcodeInput || !startBtn || !stopBtn || !readerEl) return;

    barcodeInput.addEventListener('keydown', function (event) {
      if (event.key !== 'Enter') return;

      event.preventDefault();

      const value = (barcodeInput.value || '').trim();
      if (!value) return;

      if (feedbackEl && feedbackTextEl) {
        feedbackTextEl.textContent = 'Codigo lido: ' + value;
        feedbackEl.style.display = 'block';
      }

      form.submit();
    });

    let html5QrCode = null;
    let scanRunning = false;

    function onScanSuccess(decodedText) {
      if (!decodedText) return;

      barcodeInput.value = decodedText.trim();
      barcodeInput.dispatchEvent(new Event('change', { bubbles: true }));

      if (feedbackEl && feedbackTextEl) {
        feedbackTextEl.textContent = 'Codigo lido: ' + decodedText.trim();
        feedbackEl.style.display = 'block';
      }

      stopScan();

      setTimeout(function () {
        form.submit();
      }, 450);
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
        stopBtn.style.display = 'inline-block';

        html5QrCode = new window.Html5Qrcode('reader-contagem');
        await html5QrCode.start(
          { facingMode: 'environment' },
          { fps: 10, qrbox: { width: 250, height: 120 } },
          onScanSuccess,
          function () {}
        );

        scanRunning = true;
      } catch (error) {
        console.error('Erro ao iniciar scanner na contagem:', error);
        alert('Nao foi possivel iniciar a camera para escaneamento.');
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
        console.warn('Falha ao parar scanner da contagem:', error);
      }

      scanRunning = false;
      html5QrCode = null;
      readerEl.style.display = 'none';
      startBtn.style.display = 'inline-block';
      stopBtn.style.display = 'none';
    }

    startBtn.addEventListener('click', startScan);
    stopBtn.addEventListener('click', stopScan);

    window.addEventListener('beforeunload', function () {
      stopScan();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initAlertBeeps();
      initInputFocus();
      initCameraScanner();
    });
  } else {
    initAlertBeeps();
    initInputFocus();
    initCameraScanner();
  }
})();
