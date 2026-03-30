document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var data = window.dashboardData || null;
  if (!data) {
    return;
  }

  var palette = {
    red: '#dc3545',
    amber: '#fd7e14',
    yellow: '#ffc107',
    green: '#198754',
    blue: '#0d6efd',
    cyan: '#20c997',
    axis: '#6c757d',
    grid: '#e9ecef'
  };

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

  function drawLegend(containerId, labels, values, colors) {
    var root = document.getElementById(containerId);
    if (!root) return;
    var html = '';
    for (var i = 0; i < labels.length; i += 1) {
      html += '<div class="d-flex align-items-center mb-1">'
        + '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' + colors[i % colors.length] + ';margin-right:8px;"></span>'
        + '<span class="small text-muted">' + String(labels[i]) + ': ' + String(values[i]) + '</span>'
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

    ctx.fillStyle = '#212529';
    ctx.font = '700 18px sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(String(total), cx, cy + 6);
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

      ctx.fillStyle = color;
      ctx.fillRect(x, y, barW, bh);

      ctx.fillStyle = '#212529';
      ctx.font = '12px sans-serif';
      ctx.fillText(String(values[i]), x + barW / 2, y - 6);

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
    ctx.fillStyle = fill;
    ctx.fill();

    ctx.beginPath();
    ctx.moveTo(points[0].x, points[0].y);
    for (i = 1; i < points.length; i += 1) {
      ctx.lineTo(points[i].x, points[i].y);
    }
    ctx.strokeStyle = stroke;
    ctx.lineWidth = 2;
    ctx.stroke();

    ctx.fillStyle = stroke;
    for (i = 0; i < points.length; i += 1) {
      ctx.beginPath();
      ctx.arc(points[i].x, points[i].y, 3, 0, Math.PI * 2);
      ctx.fill();

      ctx.fillStyle = palette.axis;
      ctx.font = '11px sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(String(labels[i] || ''), points[i].x, top + plotH + 16);
      ctx.fillStyle = stroke;
    }
  }

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
    'rgba(32, 201, 151, 0.18)'
  );
});
