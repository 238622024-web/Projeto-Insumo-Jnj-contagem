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
    $stmt = $pdo->prepare('SELECT id, nome, posicao, lote, codigo_barra, quantidade, unidade, validade FROM insumos_jnj WHERE id = ? LIMIT 1');
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
      width: min(860px, 100%);
      background: #fff;
      border: 1px solid #d1d5db;
      border-radius: 20px;
      box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
      padding: 24px;
    }

    .label-grid {
      display: grid;
      grid-template-columns: 320px 1fr;
      gap: 24px;
      align-items: center;
    }

    .qr-box {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 18px;
      padding: 16px;
      text-align: center;
    }

    .qr-box img {
      width: 100%;
      max-width: 320px;
      height: auto;
      display: block;
      margin: 0 auto 12px;
    }

    .payload {
      font-size: 14px;
      color: #6b7280;
      word-break: break-all;
    }

    .title {
      margin: 0 0 12px;
      font-size: 30px;
      line-height: 1.1;
    }

    .meta {
      display: grid;
      gap: 10px;
      font-size: 16px;
    }

    .meta strong {
      display: inline-block;
      min-width: 140px;
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
      body {
        background: #fff;
      }

      .page {
        padding: 0;
      }

      .label {
        width: 100%;
        box-shadow: none;
        border: 0;
        border-radius: 0;
        padding: 0;
      }

      .actions {
        display: none;
      }
    }

    @media (max-width: 768px) {
      .label-grid {
        grid-template-columns: 1fr;
      }

      .title {
        font-size: 24px;
      }
    }
  </style>
</head>
<body>
  <div class="page">
    <div class="label">
      <div class="label-grid">
        <div class="qr-box">
          <img src="<?= h($qrUrl) ?>" alt="QR Code do produto">
          <div class="payload"><?= h($payload) ?></div>
        </div>
        <div>
          <h1 class="title"><?= h((string)$item['nome']) ?></h1>
          <div class="meta">
            <div><strong>ID:</strong> <?= (int)$item['id'] ?></div>
            <div><strong>Posição:</strong> <?= h((string)($item['posicao'] ?? '-')) ?></div>
            <div><strong>Lote:</strong> <?= h((string)($item['lote'] ?? '-')) ?></div>
            <div><strong>Código de barras:</strong> <?= h((string)($item['codigo_barra'] ?? '-')) ?></div>
            <div><strong>Saldo atual:</strong> <?= h(number_format((float)($item['quantidade'] ?? 0), 0, ',', '.')) ?> <?= h((string)($item['unidade'] ?? 'UN')) ?></div>
            <div><strong>Validade:</strong> <?= h($validadeText) ?></div>
          </div>
          <div class="actions">
            <button class="btn" type="button" onclick="window.print()">Imprimir agora</button>
            <a class="btn secondary" href="estoque_atual.php">Voltar ao estoque</a>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script>
    window.addEventListener('load', () => window.print());
  </script>
</body>
</html>