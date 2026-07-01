<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

$pdo = getPDO();
ensureUserAuthSchema();
ensureContagemTrackingSchema($pdo);

$id = (int)($_GET['id'] ?? 0);
$item = null;

if ($id > 0) {
  try {
    $stmt = $pdo->prepare('SELECT id, nome, posicao, lote, codigo_barra, quantidade, unidade, data_entrada, validade FROM insumos_jnj WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $item = $stmt->fetch() ?: null;
  } catch (Throwable $e) {
    flash('error', 'Não foi possível abrir o QR deste material. Verifique se a base de dados da hospedagem está atualizada.');
    header('Location: produtos.php');
    exit;
  }
}

if (!$item) {
  flash('error', 'Produto não encontrado para imprimir o QR.');
  header('Location: estoque_atual.php');
  exit;
}

$payload = 'item:' . (int)$item['id'];
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&margin=10&data=' . rawurlencode($payload);
$validadeText = '-';
if (!empty($item['validade'])) {
  $validadeText = date('d/m/Y', strtotime((string)$item['validade']));
}

$dataEntradaText = '-';
if (!empty($item['data_entrada'])) {
  $dataEntradaText = date('d/m/Y', strtotime((string)$item['data_entrada']));
}

?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Imprimir QR - <?= h((string)$item['nome']) ?></title>
  <style>
    :root {
      color-scheme: light;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: Arial, Helvetica, sans-serif;
      background: #f4f6f8;
      color: #111827;
    }

    .page {
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 24px;
    }

    .label {
      width: min(1050px, 100%);
      min-height: 620px;
      background: #fff;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
      padding: 18px 20px 16px;
      display: grid;
      grid-template-rows: auto 1fr auto;
      gap: 14px;
    }

    .name-card {
      text-align: center;
      padding: 34px 8px 0;
    }

    .name-value {
      font-size: 126px;
      line-height: 0.8;
      font-weight: 900;
      letter-spacing: -0.1em;
      text-transform: uppercase;
      color: #111827;
      word-break: break-word;
      margin: 0 auto;
    }

    .content-grid {
      display: grid;
      grid-template-columns: minmax(320px, 0.9fr) minmax(320px, 1.1fr);
      gap: 14px;
      align-items: stretch;
      min-height: 420px;
      padding-top: 8px;
    }

    .qr-area {
      display: flex;
      align-items: end;
      justify-content: flex-start;
      padding: 0 0 8px 0;
      min-height: 100%;
    }

    .qr-card {
      display: grid;
      place-items: center;
      width: min(360px, 100%);
    }

    .qr-card img {
      width: min(300px, 100%);
      height: auto;
      display: block;
    }

    .details-area {
      display: grid;
      gap: 10px;
      align-content: start;
      min-height: 100%;
      padding-top: 0;
      padding-bottom: 8px;
      align-self: stretch;
    }

    .details-area::before {
      content: '';
      display: block;
      height: 126px;
    }

    .detail-box {
      border: 1px solid #e5e7eb;
      border-radius: 4px;
      padding: 12px 14px;
      background: #fff;
    }

    .detail-label {
      display: block;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #374151;
      margin-bottom: 5px;
    }

    .detail-value {
      font-size: 16px;
      line-height: 1.15;
      font-weight: 800;
      color: #111827;
      word-break: break-word;
    }

    .actions {
      margin-top: 20px;
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }

    .btn {
      appearance: none;
      border: 0;
      border-radius: 12px;
      padding: 12px 18px;
      font: inherit;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: #111827;
      color: #fff;
    }

    .btn.secondary {
      background: #e5e7eb;
      color: #111827;
    }

    @media print {
      @page {
        size: A4 landscape;
        margin: 0;
      }

      html,
      body {
        margin: 0 !important;
        padding: 0 !important;
        background: #fff;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      .page {
        width: 297mm;
        height: 210mm;
        padding: 0;
      }

      .label {
        width: 297mm;
        height: 210mm;
        box-shadow: none;
        border: 0;
        border-radius: 0;
        padding: 0;
        margin: 0;
      }

      .actions {
        display: none;
      }
    }

    @media (max-width: 768px) {
      .label {
        min-height: 520px;
        padding: 14px;
      }

      .name-value {
        font-size: 78px;
      }

      .content-grid {
        grid-template-columns: 1fr;
        min-height: auto;
        padding-top: 0;
      }

      .qr-area {
        justify-content: center;
      }

      .details-area {
        padding-top: 0;
        padding-bottom: 0;
      }

      .details-area::before {
        display: none;
      }

      .qr-card img {
        width: min(240px, 100%);
      }

      .detail-value {
        font-size: 15px;
      }
    }
  </style>
</head>
<body>
  <div class="page">
    <div class="label">
      <div class="name-card">
        <div class="name-value"><?= h((string)$item['nome']) ?></div>
      </div>

      <div class="content-grid">
        <div class="qr-area">
          <div class="qr-card">
            <img src="<?= h($qrUrl) ?>" alt="QR Code do produto">
          </div>
        </div>

        <div class="details-area">
          <div class="detail-box">
            <span class="detail-label">Data do recebimento</span>
            <div class="detail-value"><?= h($dataEntradaText) ?></div>
          </div>
          <div class="detail-box">
            <span class="detail-label">Data de validade</span>
            <div class="detail-value"><?= h($validadeText) ?></div>
          </div>
          <div class="detail-box">
            <span class="detail-label">Lote</span>
            <div class="detail-value"><?= h((string)($item['lote'] ?? '-')) ?></div>
          </div>
          <div class="detail-box">
            <span class="detail-label">Posição</span>
            <div class="detail-value"><?= h((string)($item['posicao'] ?? '-')) ?></div>
          </div>
        </div>
      </div>

      <div class="actions">
        <button class="btn" type="button" onclick="window.print()">Imprimir agora</button>
        <a class="btn secondary" href="estoque_atual.php">Voltar ao estoque</a>
      </div>
    </div>
  </div>
  <script>
    window.addEventListener('load', () => window.print());
  </script>
</body>
</html>