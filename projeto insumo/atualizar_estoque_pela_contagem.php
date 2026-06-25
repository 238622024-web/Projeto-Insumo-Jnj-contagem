<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
  require_once __DIR__ . '/vendor/autoload.php';
}

$pdo = getPDO();
ensureContagemTrackingSchema($pdo);

$current = currentUser() ?: [];
$adminId = (int)($current['id'] ?? 0);
$adminNome = trim((string)($current['nome'] ?? 'Administrador'));
$errors = [];
$successMessage = '';
$previewToken = trim((string)($_GET['import_token'] ?? ''));
$previewData = null;

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = (string)$_SESSION['csrf_token'];

function normalizeImportHeader(string $value): string {
  $value = trim($value);
  if ($value === '') {
    return '';
  }

  $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
  if ($normalized === false || $normalized === null) {
    $normalized = $value;
  }

  $normalized = strtolower($normalized);
  $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized) ?? $normalized;
  return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
}

function parseImportNumber($value): ?float {
  if ($value === null) {
    return null;
  }

  if (is_int($value) || is_float($value)) {
    return (float)$value;
  }

  $text = trim((string)$value);
  if ($text === '') {
    return null;
  }

  $text = str_replace([' ', "\xc2\xa0"], '', $text);
  if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $text)) {
    $text = str_replace('.', '', $text);
    $text = str_replace(',', '.', $text);
  } else {
    $text = str_replace(',', '.', $text);
  }

  if (!is_numeric($text)) {
    return null;
  }

  return (float)$text;
}

function parseImportDate($value): ?string {
  if ($value === null) {
    return null;
  }

  if ($value instanceof DateTimeInterface) {
    return $value->format('Y-m-d');
  }

  if (is_int($value) || is_float($value)) {
    if (class_exists('PhpOffice\\PhpSpreadsheet\\Shared\\Date')) {
      try {
        return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$value)->format('Y-m-d');
      } catch (Throwable $e) {
        // fallback abaixo
      }
    }
  }

  $text = trim((string)$value);
  if ($text === '') {
    return null;
  }

  $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'];
  foreach ($formats as $format) {
    $date = DateTime::createFromFormat($format, $text);
    if ($date instanceof DateTime) {
      return $date->format('Y-m-d');
    }
  }

  $timestamp = strtotime($text);
  return $timestamp ? date('Y-m-d', $timestamp) : null;
}

function readContagemImportFile(string $filePath, string $originalName): array {
  if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
    throw new RuntimeException('A biblioteca para leitura de planilhas não está disponível.');
  }

  $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
  $sheet = $spreadsheet->getActiveSheet();
  $rows = $sheet->toArray(null, true, true, true);

  if (empty($rows)) {
    throw new RuntimeException('A planilha enviada está vazia.');
  }

  $headerRow = array_shift($rows);
  $headerMap = [];
  foreach ($headerRow as $column => $value) {
    $normalized = normalizeImportHeader((string)$value);
    if ($normalized !== '') {
      $headerMap[$normalized] = $column;
    }
  }

  $requiredHeaders = [
    'data de contagem',
    'unidade',
    'id',
    'nome',
    'posicao',
    'lote',
    'quantidade',
    'data entrada',
    'validade',
    'observacoes',
  ];

  $missingHeaders = [];
  foreach ($requiredHeaders as $requiredHeader) {
    if (!isset($headerMap[$requiredHeader])) {
      $missingHeaders[] = $requiredHeader;
    }
  }

  if (!empty($missingHeaders)) {
    throw new RuntimeException('Cabeçalhos obrigatórios não encontrados na planilha: ' . implode(', ', $missingHeaders) . '.');
  }

  $parsedRows = [];
  $warnings = [];
  $rowNumber = 2;
  foreach ($rows as $row) {
    $values = [];
    foreach ($headerMap as $normalizedHeader => $columnLetter) {
      $values[$normalizedHeader] = trim((string)($row[$columnLetter] ?? ''));
    }

    $allBlank = true;
    foreach ($values as $value) {
      if ($value !== '') {
        $allBlank = false;
        break;
      }
    }

    if ($allBlank) {
      $rowNumber++;
      continue;
    }

    $id = (int)preg_replace('/\D+/', '', (string)($values['id'] ?? ''));
    $quantity = parseImportNumber($values['quantidade'] ?? null);
    $countDate = parseImportDate($values['data de contagem'] ?? null) ?: date('Y-m-d');
    $unit = strtoupper(trim((string)($values['unidade'] ?? 'UN')));
    $name = trim((string)($values['nome'] ?? ''));
    $position = trim((string)($values['posicao'] ?? ''));
    $lot = trim((string)($values['lote'] ?? ''));
    $entryDate = parseImportDate($values['data entrada'] ?? null);
    $validity = parseImportDate($values['validade'] ?? null);
    $notes = trim((string)($values['observacoes'] ?? ''));

    $rowErrors = [];
    if ($id <= 0) {
      $rowErrors[] = 'ID inválido';
    }
    if ($quantity === null) {
      $rowErrors[] = 'Quantidade inválida';
    }
    if ($name === '') {
      $rowErrors[] = 'Nome vazio';
    }
    if ($position === '') {
      $rowErrors[] = 'Posição vazia';
    }

    if (!empty($rowErrors)) {
      $warnings[] = 'Linha ' . $rowNumber . ': ' . implode(', ', $rowErrors) . '.';
      $rowNumber++;
      continue;
    }

    $parsedRows[] = [
      'row_number' => $rowNumber,
      'id' => $id,
      'name' => $name,
      'unit' => $unit !== '' ? $unit : 'UN',
      'position' => $position,
      'lot' => $lot,
      'quantity' => (int)round((float)$quantity),
      'quantity_raw' => (float)$quantity,
      'count_date' => $countDate,
      'entry_date' => $entryDate,
      'validity' => $validity,
      'notes' => $notes,
      'source_file' => $originalName,
    ];

    $rowNumber++;
  }

  if (empty($parsedRows)) {
    throw new RuntimeException('Nenhuma linha válida foi encontrada na planilha.');
  }

  $ids = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['id'], $parsedRows)));
  $existingById = [];
  if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $GLOBALS['pdo']->prepare('SELECT id, nome, quantidade, unidade, data_contagem FROM insumos_jnj WHERE id IN (' . $placeholders . ')');
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() ?: [] as $item) {
      $existingById[(int)$item['id']] = $item;
    }
  }

  foreach ($parsedRows as &$parsedRow) {
    $existing = $existingById[(int)$parsedRow['id']] ?? null;
    $parsedRow['exists'] = $existing !== null;
    $parsedRow['current_name'] = (string)($existing['nome'] ?? '');
    $parsedRow['current_quantity'] = isset($existing['quantidade']) ? (int)$existing['quantidade'] : null;
    $parsedRow['current_unit'] = (string)($existing['unidade'] ?? '');
    $parsedRow['name_matches'] = $existing === null ? null : (mb_strtolower(trim((string)$existing['nome'])) === mb_strtolower($parsedRow['name']));
    $parsedRow['will_update'] = $parsedRow['exists'];

    if ($existing !== null && !$parsedRow['name_matches']) {
      $warnings[] = 'Linha ' . $parsedRow['row_number'] . ': nome da planilha difere do cadastro atual (ID ' . $parsedRow['id'] . ').';
    }
  }
  unset($parsedRow);

  return [
    'file_name' => $originalName,
    'parsed_rows' => $parsedRows,
    'warnings' => $warnings,
    'total_rows' => count($parsedRows),
    'existing_rows' => count(array_filter($parsedRows, static fn(array $row): bool => !empty($row['exists']))),
    'missing_rows' => count(array_filter($parsedRows, static fn(array $row): bool => empty($row['exists']))),
  ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'prepare_import') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
      $errors[] = 'Sessão inválida. Atualize a página e tente novamente.';
    } elseif (empty($_FILES['arquivo_contagem']['name']) || !is_uploaded_file($_FILES['arquivo_contagem']['tmp_name'])) {
      $errors[] = 'Envie um arquivo Excel ou CSV para continuar.';
    } elseif (!empty($_FILES['arquivo_contagem']['error'])) {
      $errors[] = 'Não foi possível processar o arquivo enviado.';
    } else {
      try {
        $fileName = (string)$_FILES['arquivo_contagem']['name'];
        $tmpPath = (string)$_FILES['arquivo_contagem']['tmp_name'];
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'csv'], true)) {
          throw new RuntimeException('Use um arquivo .xlsx ou .csv exportado do sistema.');
        }

        $importPreview = readContagemImportFile($tmpPath, $fileName);
        $previewToken = bin2hex(random_bytes(16));
        $_SESSION['stock_count_import_previews'] = $_SESSION['stock_count_import_previews'] ?? [];
        $_SESSION['stock_count_import_previews'][$previewToken] = [
          'created_at' => time(),
          'data' => $importPreview,
        ];

        flash('success', 'Arquivo carregado. Revise a prévia e clique em aplicar para atualizar o estoque.');
        header('Location: atualizar_estoque_pela_contagem.php?import_token=' . rawurlencode($previewToken));
        exit;
      } catch (Throwable $e) {
        $errors[] = $e->getMessage();
      }
    }
  }

  if ($action === 'apply_import') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    $importToken = trim((string)($_POST['import_token'] ?? ''));
    if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
      $errors[] = 'Sessão inválida. Atualize a página e tente novamente.';
    } elseif ($importToken === '' || empty($_SESSION['stock_count_import_previews'][$importToken]['data'])) {
      $errors[] = 'A prévia da importação expirou. Envie o arquivo novamente.';
    } else {
      $previewData = $_SESSION['stock_count_import_previews'][$importToken]['data'];
      $rowsToUpdate = array_values(array_filter($previewData['parsed_rows'], static fn(array $row): bool => !empty($row['exists'])));
      $missingRows = array_values(array_filter($previewData['parsed_rows'], static fn(array $row): bool => empty($row['exists'])));

      if (empty($rowsToUpdate)) {
        $errors[] = 'Nenhuma linha válida encontrada para aplicar.';
      } else {
        $updated = 0;
        $skipped = 0;
        $pdo->beginTransaction();
        try {
          $stmt = $pdo->prepare(
            'UPDATE insumos_jnj
             SET quantidade = ?, unidade = ?, data_contagem = ?, contagem_por_id = ?, contagem_por_nome = ?, contagem_em = ?
             WHERE id = ?'
          );
          $now = date('Y-m-d H:i:s');
          foreach ($rowsToUpdate as $row) {
            $stmt->execute([
              (int)$row['quantity'],
              mb_substr((string)$row['unit'], 0, 30),
              $row['count_date'],
              $adminId > 0 ? $adminId : null,
              mb_substr($adminNome, 0, 150),
              $now,
              (int)$row['id'],
            ]);
            if ($stmt->rowCount() > 0) {
              $updated += 1;
            }
          }
          $pdo->commit();

          $skipped = count($missingRows);
          logUserActivity(
            $adminId,
            'stock_count_import',
            'Estoque atualizado pela contagem',
            'Atualizados: ' . $updated . '. Ignorados: ' . $skipped . '. Arquivo: ' . (string)($previewData['file_name'] ?? 'planilha importada') . '.'
          );

          unset($_SESSION['stock_count_import_previews'][$importToken]);
          flash('success', 'Estoque atualizado pela contagem. Atualizados: ' . $updated . '. Ignorados: ' . $skipped . '.');
          header('Location: estoque_atual.php');
          exit;
        } catch (Throwable $e) {
          if ($pdo->inTransaction()) {
            $pdo->rollBack();
          }
          $errors[] = 'Não foi possível aplicar a contagem: ' . $e->getMessage();
        }
      }
    }
  }

  if ($action === 'cancel_import') {
    $importToken = trim((string)($_POST['import_token'] ?? ''));
    if ($importToken !== '' && !empty($_SESSION['stock_count_import_previews'][$importToken])) {
      unset($_SESSION['stock_count_import_previews'][$importToken]);
    }
    flash('success', 'Prévia de importação cancelada.');
    header('Location: atualizar_estoque_pela_contagem.php');
    exit;
  }
}

if ($previewToken !== '' && empty($previewData) && !empty($_SESSION['stock_count_import_previews'][$previewToken]['data'])) {
  $previewData = $_SESSION['stock_count_import_previews'][$previewToken]['data'];
}

$previewWarnings = $previewData['warnings'] ?? [];
$previewRows = $previewData['parsed_rows'] ?? [];
$previewStats = [
  'total' => $previewData['total_rows'] ?? 0,
  'existing' => $previewData['existing_rows'] ?? 0,
  'missing' => $previewData['missing_rows'] ?? 0,
];

include __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="solicitacoes-kicker">Estoque</span>
          <h1 class="display-6 fw-semibold mb-2">Atualizar estoque pela contagem</h1>
          <p class="solicitacoes-subtitle mb-0">Envie a planilha exportada, revise a prévia e aplique as quantidades diretamente nos materiais cadastrados.</p>
        </div>
        <div class="text-lg-end d-flex flex-column gap-2 align-items-lg-end">
          <span class="solicitacoes-pill"><i class="fa-solid fa-file-arrow-up"></i>Administração do estoque</span>
          <small class="text-muted d-block">Aceita arquivos .xlsx ou .csv gerados pelo exportador.</small>
        </div>
      </div>
    </div>
  </section>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm"><?= h(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <div class="row g-4 mb-4">
    <div class="col-12 col-md-4">
      <div class="metric-card h-100">
        <div class="metric-icon metric-icon-info"><i class="fa-solid fa-file-excel"></i></div>
        <div>
          <div class="metric-label">Fluxo seguro</div>
          <div class="metric-value">2 etapas</div>
          <div class="metric-help">Carregar e depois aplicar.</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="metric-card h-100">
        <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-layer-group"></i></div>
        <div>
          <div class="metric-label">Prévia carregada</div>
          <div class="metric-value"><?= h((string)($previewStats['total'] ?? 0)) ?></div>
          <div class="metric-help">Linhas prontas para atualização.</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="metric-card h-100">
        <div class="metric-icon metric-icon-success"><i class="fa-solid fa-clipboard-check"></i></div>
        <div>
          <div class="metric-label">Encontrados no estoque</div>
          <div class="metric-value"><?= h((string)($previewStats['existing'] ?? 0)) ?></div>
          <div class="metric-help">Itens que serão atualizados.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="section-card card border-0 shadow-sm mb-4">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div>
          <span class="section-badge mb-2"><i class="fa-solid fa-upload"></i>Enviar planilha</span>
          <h2 class="h5 mb-2">Escolha o arquivo exportado do sistema</h2>
          <p class="section-card-subtitle mb-0">O arquivo deve conter as colunas originais do exportador: ID, nome, posição, lote, unidade e quantidade.</p>
        </div>
        <a href="export_excel.php" class="btn btn-outline-success">
          <i class="fa-solid fa-file-excel me-1"></i>Baixar planilha base
        </a>
      </div>

      <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <input type="hidden" name="action" value="prepare_import">
        <div class="col-12 col-lg-8">
          <label class="form-label">Arquivo da contagem</label>
          <input type="file" name="arquivo_contagem" class="form-control" accept=".xlsx,.csv" required>
          <div class="form-text">Use o arquivo exportado, confira e depois aplique pela área do admin.</div>
        </div>
        <div class="col-12 col-lg-4 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-magnifying-glass-chart me-1"></i>Carregar prévia</button>
        </div>
      </form>
    </div>
  </div>

  <?php if (!empty($previewRows) && $previewData !== null): ?>
    <div class="section-card card border-0 shadow-sm mb-4">
      <div class="card-body p-4 p-lg-5">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
          <div>
            <span class="section-badge mb-2"><i class="fa-solid fa-eye"></i>Prévia da importação</span>
            <h2 class="h5 mb-2">Revise antes de aplicar</h2>
            <div class="section-card-subtitle">Arquivo: <?= h((string)($previewData['file_name'] ?? '')) ?></div>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-light text-dark border"><?= h((string)($previewStats['total'] ?? 0)) ?> linhas</span>
            <span class="badge bg-light text-dark border"><?= h((string)($previewStats['existing'] ?? 0)) ?> encontradas</span>
            <span class="badge bg-light text-dark border"><?= h((string)($previewStats['missing'] ?? 0)) ?> ignoradas</span>
          </div>
        </div>

        <?php if (!empty($previewWarnings)): ?>
          <div class="alert alert-warning">
            <strong>Atenção:</strong>
            <ul class="mb-0 mt-2">
              <?php foreach ($previewWarnings as $warning): ?>
                <li><?= h((string)$warning) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <div class="table-responsive request-table-wrap mb-4">
          <table class="table table-hover align-middle mb-0 request-table js-no-datatable">
            <thead>
              <tr>
                <th>Linha</th>
                <th>ID</th>
                <th>Nome</th>
                <th>Qtd. atual</th>
                <th>Qtd. importada</th>
                <th>Unidade</th>
                <th>Data contagem</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($previewRows as $row): ?>
                <tr>
                  <td data-label="Linha"><?= h((string)$row['row_number']) ?></td>
                  <td data-label="ID"><?= h((string)$row['id']) ?></td>
                  <td data-label="Nome">
                    <strong><?= h((string)$row['name']) ?></strong>
                    <?php if (!empty($row['current_name']) && $row['current_name'] !== $row['name']): ?>
                      <div class="text-muted small">Cadastro atual: <?= h((string)$row['current_name']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td data-label="Qtd. atual"><?= h((string)($row['current_quantity'] ?? '-')) ?></td>
                  <td data-label="Qtd. importada"><?= h(number_format((float)$row['quantity_raw'], 0, ',', '.')) ?></td>
                  <td data-label="Unidade"><?= h((string)$row['unit']) ?></td>
                  <td data-label="Data contagem"><?= h(date('d/m/Y', strtotime((string)$row['count_date']))) ?></td>
                  <td data-label="Status">
                    <?php if (!empty($row['exists'])): ?>
                      <span class="badge bg-success-subtle text-success border border-success-subtle">Pronto</span>
                    <?php else: ?>
                      <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Ignorado</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="d-flex flex-column flex-md-row gap-2 justify-content-md-end">
          <form method="post" class="m-0">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="apply_import">
            <input type="hidden" name="import_token" value="<?= h($previewToken) ?>">
            <button type="submit" class="btn btn-success">
              <i class="fa-solid fa-circle-check me-1"></i>Aplicar estoque pela contagem
            </button>
          </form>
          <form method="post" class="m-0">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="cancel_import">
            <input type="hidden" name="import_token" value="<?= h($previewToken) ?>">
            <button type="submit" class="btn btn-outline-secondary">
              <i class="fa-solid fa-xmark me-1"></i>Cancelar prévia
            </button>
          </form>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
