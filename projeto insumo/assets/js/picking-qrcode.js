/**
 * PICKING QR CODE - JAVASCRIPT
 * Leitura por camera e preenchimento da busca do produto
 */

(function () {
  'use strict';

  const PICKING_RECENTS_STORAGE_KEY = 'insumo.picking.recentScans';

  function readRecentScans() {
    try {
      const raw = window.localStorage.getItem(PICKING_RECENTS_STORAGE_KEY);
      if (!raw) return [];

      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      return [];
    }
  }

  function writeRecentScans(items) {
    try {
      window.localStorage.setItem(PICKING_RECENTS_STORAGE_KEY, JSON.stringify(items.slice(0, 6)));
    } catch (error) {
      // Ignora ambientes sem acesso ao storage.
    }
  }

  function normalizeRecentScan(item) {
    if (!item) return null;

    const scanCode = String(item.scan_code || item.code || '').trim();
    const productName = String(item.product_name || item.nome || '').trim();

    if (!scanCode) return null;

    return {
      scan_code: scanCode,
      product_name: productName,
      product_quantity: Number(item.product_quantity || item.quantidade || 0),
      product_unit: String(item.product_unit || item.unidade || 'UN').trim() || 'UN',
      product_position: String(item.product_position || item.posicao || '').trim(),
      recorded_at: String(item.recorded_at || new Date().toISOString())
    };
  }

  function registerRecentScan(item) {
    const normalized = normalizeRecentScan(item);
    if (!normalized) return;

    const current = readRecentScans().filter(function (existing) {
      return String(existing && existing.scan_code ? existing.scan_code : '') !== normalized.scan_code;
    });

    current.unshift(normalized);
    writeRecentScans(current);
  }

  function renderRecentScans() {
    const emptyEl = document.getElementById('picking-recent-empty');
    const wrapEl = document.getElementById('picking-recent-wrap');
    const tbodyEl = document.getElementById('picking-recent-tbody');

    if (!emptyEl || !wrapEl || !tbodyEl) return;

    const items = readRecentScans();
    tbodyEl.innerHTML = '';

    if (!items.length) {
      emptyEl.classList.remove('d-none');
      wrapEl.classList.add('d-none');
      return;
    }

    items.forEach(function (item) {
      const row = document.createElement('tr');

      const codeCell = document.createElement('td');
      codeCell.setAttribute('data-label', 'Código');
      codeCell.textContent = item.scan_code || '-';

      const productCell = document.createElement('td');
      productCell.setAttribute('data-label', 'Produto');
      productCell.textContent = item.product_name || 'QR lido sem nome';

      const qtyCell = document.createElement('td');
      qtyCell.setAttribute('data-label', 'Saldo');
      const quantityValue = Number(item.product_quantity || 0);
      qtyCell.textContent = quantityValue > 0 ? quantityValue.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' ' + (item.product_unit || 'UN') : '-';

      const positionCell = document.createElement('td');
      positionCell.setAttribute('data-label', 'Localização');
      positionCell.textContent = item.product_position || '-';

      const dateCell = document.createElement('td');
      dateCell.setAttribute('data-label', 'Lido em');
      const dateValue = item.recorded_at ? new Date(item.recorded_at) : null;
      dateCell.textContent = dateValue && !Number.isNaN(dateValue.getTime()) ? dateValue.toLocaleString('pt-BR') : '-';

      row.appendChild(codeCell);
      row.appendChild(productCell);
      row.appendChild(qtyCell);
      row.appendChild(positionCell);
      row.appendChild(dateCell);
      tbodyEl.appendChild(row);
    });

    emptyEl.classList.add('d-none');
    wrapEl.classList.remove('d-none');
  }

  function playBeep() {
    if (!window.AudioContext && !window.webkitAudioContext) return;

    try {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      const audioCtx = new Ctx();

      if (audioCtx.state === 'suspended' && typeof audioCtx.resume === 'function') {
        audioCtx.resume().catch(function () {});
      }

      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      const now = audioCtx.currentTime;

      osc.type = 'triangle';
      osc.frequency.setValueAtTime(880, now);
      osc.frequency.exponentialRampToValueAtTime(1320, now + 0.07);
      osc.connect(gain);
      gain.connect(audioCtx.destination);

      gain.gain.setValueAtTime(0.001, now);
      gain.gain.exponentialRampToValueAtTime(0.08, now + 0.01);
      gain.gain.exponentialRampToValueAtTime(0.001, now + 0.16);

      osc.start(now);
      osc.stop(now + 0.18);
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
    const statusEl = document.getElementById('picking-camera-status');

    if (!lookupForm || !codeInput || !startBtn || !stopBtn || !readerEl) return;

    let html5QrCode = null;
    let scanRunning = false;
    let scanLocked = false;

    function submitLookupForm() {
      if (typeof lookupForm.requestSubmit === 'function') {
        lookupForm.requestSubmit();
        return;
      }

      lookupForm.submit();
    }

    function showCameraError(message) {
      if (statusEl) {
        statusEl.textContent = message;
        statusEl.classList.remove('d-none');
        statusEl.classList.remove('alert-warning', 'alert-success');
        statusEl.classList.add('alert-danger');
      }

      alert(message);
    }

    function showCameraInfo(message) {
      if (!statusEl) return;

      statusEl.textContent = message;
      statusEl.classList.remove('d-none', 'alert-danger', 'alert-success');
      statusEl.classList.add('alert-warning');
    }

    function clearCameraStatus() {
      if (!statusEl) return;

      statusEl.textContent = '';
      statusEl.classList.add('d-none');
      statusEl.classList.remove('alert-danger', 'alert-success', 'alert-warning');
    }

    function stopScan() {
      if (!scanRunning || !html5QrCode) {
        scanLocked = false;
        readerEl.style.display = 'none';
        startBtn.style.display = 'inline-flex';
        stopBtn.style.display = 'none';
        return Promise.resolve();
      }

      return html5QrCode.stop().catch(function () {}).then(function () {
        return html5QrCode.clear().catch(function () {});
      }).finally(function () {
        scanRunning = false;
        scanLocked = false;
        html5QrCode = null;
        readerEl.style.display = 'none';
        startBtn.style.display = 'inline-flex';
        stopBtn.style.display = 'none';
      });
    }

    function onScanSuccess(decodedText) {
      if (scanLocked) return;
      scanLocked = true;

      const value = (decodedText || '').trim();
      if (!value) return;

      codeInput.value = value;
      registerRecentScan({
        scan_code: value,
        recorded_at: new Date().toISOString()
      });
      renderRecentScans();
      playBeep();
      stopScan().finally(function () {
        submitLookupForm();
      });
    }

    async function startScan() {
      if (scanRunning) return;

      if (typeof window.Html5Qrcode === 'undefined') {
        showCameraError('Leitor de camera indisponivel no momento. Verifique se o arquivo html5-qrcode foi carregado na hospedagem.');
        return;
      }

      if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
        showCameraError('A camera exige acesso por HTTPS na hospedagem. Abra esta pagina em uma conexao segura.');
        return;
      }

      if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
        showCameraError('Este navegador nao permite acesso a camera.');
        return;
      }

      try {
        showCameraInfo('Abrindo a camera... aguarde a permissao do navegador.');

        const permissionStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
        if (permissionStream && typeof permissionStream.getTracks === 'function') {
          permissionStream.getTracks().forEach(function (track) {
            try {
              track.stop();
            } catch (stopError) {}
          });
        }

        readerEl.style.display = 'block';
        startBtn.style.display = 'none';
        stopBtn.style.display = 'inline-flex';

        html5QrCode = new window.Html5Qrcode('reader-picking');
        const cameraConfig = {
          fps: 8,
          qrbox: { width: 240, height: 180 },
          useBarCodeDetectorIfSupported: false
        };

        let cameraStarted = false;

        if (typeof window.Html5Qrcode.getCameras === 'function') {
          try {
            const cameras = await window.Html5Qrcode.getCameras();
            if (Array.isArray(cameras) && cameras.length > 0) {
              const preferredCamera = cameras.find(function (camera) {
                const label = String(camera && camera.label ? camera.label : '').toLowerCase();
                return label.indexOf('back') !== -1 || label.indexOf('rear') !== -1 || label.indexOf('traseira') !== -1;
              }) || cameras[0];

              if (preferredCamera && preferredCamera.id) {
                await html5QrCode.start(preferredCamera.id, cameraConfig, onScanSuccess, function (errorMessage) {
                  if (errorMessage) {
                    console.debug('Leitura em andamento:', errorMessage);
                  }
                });
                cameraStarted = true;
                showCameraInfo('Camera iniciada. Aponte para o QR Code do produto.');
              }
            }
          } catch (cameraListError) {
            console.warn('Nao foi possivel listar cameras:', cameraListError);
          }
        }

        if (!cameraStarted) {
          await html5QrCode.start(
            { facingMode: { ideal: 'environment' } },
            cameraConfig,
            onScanSuccess,
            function (errorMessage) {
              if (errorMessage) {
                console.debug('Leitura em andamento:', errorMessage);
              }
            }
          );
          showCameraInfo('Camera iniciada. Aponte para o QR Code do produto.');
        }

        scanRunning = true;
      } catch (error) {
        console.error('Erro ao iniciar scanner do picking:', error);
        var errorName = error && error.name ? String(error.name) : '';
        if (errorName === 'NotAllowedError' || errorName === 'PermissionDeniedError') {
          showCameraError('A camera foi bloqueada pelo navegador. Libere a permissao para este site e tente novamente.');
        } else if (errorName === 'NotFoundError' || errorName === 'DevicesNotFoundError') {
          showCameraError('Nenhuma camera foi encontrada neste dispositivo.');
        } else if (errorName === 'NotReadableError' || errorName === 'TrackStartError') {
          showCameraError('A camera esta em uso por outro aplicativo ou nao pode ser iniciada agora.');
        } else if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
          showCameraError('A camera exige acesso por HTTPS na hospedagem. Abra esta pagina em uma conexao segura.');
        } else {
          showCameraError('Nao foi possivel iniciar a camera para leitura. Verifique permissao e disponibilidade da camera.');
        }
        stopScan();
      }
    }

    startBtn.addEventListener('click', startScan);
    stopBtn.addEventListener('click', function () {
      stopScan();
    });

    if (window.__pickingRecentScan) {
      registerRecentScan(window.__pickingRecentScan);
    }

    renderRecentScans();

    clearCameraStatus();

    window.PickingQrRecent = {
      registerScan: registerRecentScan,
      renderRecentScans: renderRecentScans
    };

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