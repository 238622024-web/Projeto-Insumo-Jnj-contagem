document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var data = window.dashboardData || null;
  if (!data) {
    return;
  }

  var palette = {
    red: '#E45757',
    amber: '#F2A93B',
    yellow: '#FFD166',
    green: '#2FBF71',
    blue: '#2D8CFF',
    cyan: '#23B5D3',
    axis: '#5B6777',
    grid: '#E8EDF5',
    text: '#1E293B'
  };

  var chartState = {
    validade: null,
    unidades: null,
    tendencia: null
  };

  function formatNumber(value) {
    var number = Number(value || 0);
    return number.toLocaleString('pt-BR');
  }

  function setupCanvas(canvas) {
    if (!canvas) return null;
    var rect = canvas.getBoundingClientRect();
    var dpr = Math.max(window.devicePixelRatio || 1, 1);
    canvas.width = Math.max(300, Math.floor(rect.width * dpr));
    canvas.height = Math.max(220, Math.floor(rect.height * dpr));
    var ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    return { ctx: ctx, w: Math.floor(canvas.width / dpr), h: Math.floor(canvas.height / dpr) };
  }

  function roundRect(ctx, x, y, width, height, radius) {
    var r = Math.min(radius, width / 2, height / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + width - r, y);
    ctx.quadraticCurveTo(x + width, y, x + width, y + r);
    ctx.lineTo(x + width, y + height - r);
    ctx.quadraticCurveTo(x + width, y + height, x + width - r, y + height);
    ctx.lineTo(x + r, y + height);
    ctx.quadraticCurveTo(x, y + height, x, y + height - r);
    ctx.lineTo(x, y + r);
    ctx.quadraticCurveTo(x, y, x + r, y);
    ctx.closePath();
  }

  function drawLegend(containerId, labels, values, colors) {
    var root = document.getElementById(containerId);
    if (!root) return;
    var html = '';
    for (var i = 0; i < labels.length; i += 1) {
      html += '<div class="legend-item d-flex align-items-center justify-content-between mb-1">'
        + '<div class="d-flex align-items-center">'
        + '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' + colors[i % colors.length] + ';margin-right:8px;"></span>'
        + '<span class="small text-muted">' + String(labels[i]) + '</span>'
        + '</div>'
        + '<strong class="small">' + formatNumber(values[i]) + '</strong>'
        + '</div>';
    }
    root.innerHTML = html;
  }

  function drawDoughnut(canvas, labels, values, colors) {
    var meta = setupCanvas(canvas);
    if (!meta) return;
    var ctx = meta.ctx;
    var w = meta.w;
    var h = meta.h;
    var total = values.reduce(function (acc, v) { return acc + v; }, 0);

    ctx.clearRect(0, 0, w, h);

    if (total <= 0) {
      ctx.fillStyle = palette.axis;
      ctx.font = '14px sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('Sem dados para exibir', w / 2, h / 2);
      return;
    }

    var cx = w / 2;
    var cy = h / 2;
    var radius = Math.min(w, h) * 0.34;
    var inner = radius * 0.58;
    var start = -Math.PI / 2;

    for (var i = 0; i < values.length; i += 1) {
      var val = values[i];
      var angle = (val / total) * Math.PI * 2;
      var end = start + angle;

      ctx.beginPath();
      ctx.arc(cx, cy, radius, start, end);
      ctx.arc(cx, cy, inner, end, start, true);
      ctx.closePath();
      ctx.fillStyle = colors[i % colors.length];
      ctx.fill();

      start = end;
    }

    ctx.fillStyle = palette.text;
    ctx.font = '700 18px sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(formatNumber(total), cx, cy + 2);
    ctx.fillStyle = palette.axis;
    ctx.font = '12px sans-serif';
    ctx.fillText('TOTAL', cx, cy + 20);
    drawLegend('legend-validade', labels, values, colors);
  }

  function drawBar(canvas, labels, values, color) {
    var meta = setupCanvas(canvas);
    if (!meta) return;
    var ctx = meta.ctx;
    var w = meta.w;
    var h = meta.h;
    var max = Math.max.apply(null, values.concat([1]));
    var left = 42;
    var right = 12;
    var top = 12;
    var bottom = 48;
    var plotW = w - left - right;
    var plotH = h - top - bottom;

    ctx.clearRect(0, 0, w, h);

    for (var g = 0; g <= 4; g += 1) {
      var gy = top + (plotH * g / 4);
      ctx.strokeStyle = palette.grid;
      ctx.beginPath();
      ctx.moveTo(left, gy);
      ctx.lineTo(left + plotW, gy);
      ctx.stroke();
    }

    ctx.strokeStyle = palette.axis;
    ctx.beginPath();
    ctx.moveTo(left, top);
    ctx.lineTo(left, top + plotH);
    ctx.lineTo(left + plotW, top + plotH);
    ctx.stroke();

    var step = values.length > 0 ? plotW / values.length : plotW;
    var barW = Math.max(12, step * 0.62);
    ctx.textAlign = 'center';

    for (var i = 0; i < values.length; i += 1) {
      var x = left + i * step + (step - barW) / 2;
      var bh = (values[i] / max) * (plotH - 8);
      var y = top + plotH - bh;

      var grad = ctx.createLinearGradient(0, y, 0, top + plotH);
      grad.addColorStop(0, color);
      grad.addColorStop(1, 'rgba(45, 140, 255, 0.25)');
      ctx.fillStyle = grad;
      roundRect(ctx, x, y, barW, bh, 8);
      ctx.fill();

      ctx.fillStyle = palette.text;
      ctx.font = '12px sans-serif';
      ctx.fillText(formatNumber(values[i]), x + barW / 2, y - 6);

      var label = String(labels[i] || '');
      var shortLabel = label.length > 11 ? label.slice(0, 10) + '…' : label;
      ctx.fillStyle = palette.axis;
      ctx.fillText(shortLabel, x + barW / 2, top + plotH + 16);
    }
  }

  function drawLine(canvas, labels, values, stroke, fill) {
    var meta = setupCanvas(canvas);
    if (!meta) return;
    var ctx = meta.ctx;
    var w = meta.w;
    var h = meta.h;
    var max = Math.max.apply(null, values.concat([1]));
    var left = 40;
    var right = 12;
    var top = 12;
    var bottom = 36;
    var plotW = w - left - right;
    var plotH = h - top - bottom;

    ctx.clearRect(0, 0, w, h);

    for (var g = 0; g <= 4; g += 1) {
      var gy = top + (plotH * g / 4);
      ctx.strokeStyle = palette.grid;
      ctx.beginPath();
      ctx.moveTo(left, gy);
      ctx.lineTo(left + plotW, gy);
      ctx.stroke();
    }

    if (values.length === 0) {
      ctx.fillStyle = palette.axis;
      ctx.font = '14px sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('Sem dados para exibir', w / 2, h / 2);
      return;
    }

    var step = values.length > 1 ? plotW / (values.length - 1) : 0;
    var points = [];
    for (var i = 0; i < values.length; i += 1) {
      var x = left + i * step;
      var y = top + plotH - ((values[i] / max) * (plotH - 8));
      points.push({ x: x, y: y });
    }

    ctx.beginPath();
    ctx.moveTo(points[0].x, points[0].y);
    for (i = 1; i < points.length; i += 1) {
      ctx.lineTo(points[i].x, points[i].y);
    }
    ctx.lineTo(points[points.length - 1].x, top + plotH);
    ctx.lineTo(points[0].x, top + plotH);
    ctx.closePath();
    var gradFill = ctx.createLinearGradient(0, top, 0, top + plotH);
    gradFill.addColorStop(0, fill);
    gradFill.addColorStop(1, 'rgba(35, 181, 211, 0.02)');
    ctx.fillStyle = gradFill;
    ctx.fill();

    ctx.beginPath();
    ctx.moveTo(points[0].x, points[0].y);
    for (i = 1; i < points.length; i += 1) {
      ctx.lineTo(points[i].x, points[i].y);
    }
    ctx.strokeStyle = stroke;
    ctx.lineWidth = 3;
    ctx.stroke();

    ctx.fillStyle = stroke;
    for (i = 0; i < points.length; i += 1) {
      ctx.beginPath();
      ctx.arc(points[i].x, points[i].y, 3.5, 0, Math.PI * 2);
      ctx.fill();
      ctx.beginPath();
      ctx.strokeStyle = '#ffffff';
      ctx.lineWidth = 2;
      ctx.arc(points[i].x, points[i].y, 5.5, 0, Math.PI * 2);
      ctx.stroke();

      ctx.fillStyle = palette.axis;
      ctx.font = '11px sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(String(labels[i] || ''), points[i].x, top + plotH + 16);
      ctx.fillStyle = stroke;
    }
  }

  function renderAll() {
    drawDoughnut(
      document.getElementById('chart-validade'),
      data.validade.labels || [],
      data.validade.values || [],
      [palette.red, palette.amber, palette.yellow, palette.green]
    );

    drawBar(
      document.getElementById('chart-unidades'),
      data.unidades.labels || [],
      data.unidades.values || [],
      palette.blue
    );

    drawLine(
      document.getElementById('chart-tendencia'),
      data.tendencia.labels || [],
      data.tendencia.values || [],
      palette.cyan,
      'rgba(35, 181, 211, 0.20)'
    );
  }

  function debounce(fn, wait) {
    var timeout = null;
    return function () {
      if (timeout) {
        clearTimeout(timeout);
      }
      timeout = setTimeout(fn, wait);
    };
  }

  renderAll();
  window.addEventListener('resize', debounce(renderAll, 140));
});
