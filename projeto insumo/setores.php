<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireAdmin();

$pdo = getPDO();
ensureInsumoRequestsSchema($pdo);

$sectors = [
  ['name' => 'Outbound', 'code' => 'outbound', 'icon' => 'fa-truck-arrow-right', 'description' => 'Fluxo de saída e expedição de materiais.'],
  ['name' => 'Inbound', 'code' => 'inbound', 'icon' => 'fa-truck-arrow-left', 'description' => 'Recebimento e entrada de insumos.'],
  ['name' => 'Adequaçao', 'code' => 'adequacao', 'icon' => 'fa-pen-ruler', 'description' => 'Ajustes operacionais e correções de rotina.'],
  ['name' => 'AdequaçãoAdm', 'code' => 'adequacaoadm', 'icon' => 'fa-clipboard-check', 'description' => 'Ajustes administrativos do fluxo interno.'],
  ['name' => 'DPS/VLM', 'code' => 'dpsvlm', 'icon' => 'fa-vial', 'description' => 'Operação associada ao núcleo DPS/VLM.'],
  ['name' => 'KIT-DPS', 'code' => 'kitdps', 'icon' => 'fa-boxes-stacked', 'description' => 'Separação e controle de kits DPS.'],
  ['name' => 'Faturamento', 'code' => 'faturamento', 'icon' => 'fa-file-invoice-dollar', 'description' => 'Rotina ligada à conferência e faturamento.'],
  ['name' => 'qualidade', 'code' => 'qualidade', 'icon' => 'fa-circle-check', 'description' => 'Controle de qualidade e validação de processos.'],
  ['name' => 'INVENTÁRIO', 'code' => 'inventario', 'icon' => 'fa-warehouse', 'description' => 'Contagem física e controle de estoque.'],
  ['name' => 'EXPORTAÇÃO REVERSA', 'code' => 'exportacao-reversa', 'icon' => 'fa-rotate-left', 'description' => 'Fluxo de retorno e exportação reversa.'],
  ['name' => 'JOHSON E JOHSON', 'code' => 'johnson-johnson', 'icon' => 'fa-industry', 'description' => 'Área dedicada ao atendimento da unidade Johnson e Johnson.'],
];

$countsStmt = $pdo->query(
  "SELECT TRIM(setor) AS setor_nome, COUNT(*) AS total_requests
   FROM insumo_requests
   WHERE setor IS NOT NULL AND TRIM(setor) <> ''
   GROUP BY TRIM(setor)
   ORDER BY total_requests DESC, setor_nome ASC"
);
$sectorUsageRows = $countsStmt->fetchAll() ?: [];
$sectorUsage = [];
foreach ($sectorUsageRows as $row) {
  $sectorUsage[mb_strtolower(trim((string)($row['setor_nome'] ?? '')))] = (int)($row['total_requests'] ?? 0);
}

$summaryStmt = $pdo->query(
  "SELECT
      COUNT(*) AS total_requests,
      SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_requests,
      SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_requests,
      SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_requests
   FROM insumo_requests"
);
$summary = $summaryStmt->fetch() ?: [];

$recentStmt = $pdo->query(
  "SELECT id, setor, user_nome, user_email, status, requested_at
   FROM insumo_requests
   ORDER BY requested_at DESC, id DESC
   LIMIT 8"
);
$recentRequests = $recentStmt->fetchAll() ?: [];

include __DIR__ . '/includes/header.php';
?>

<div class="solicitacoes-page">
  <section class="solicitacoes-hero card border-0 shadow-lg mb-4 overflow-hidden">
    <div class="card-body p-4 p-lg-5">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <div>
          <span class="solicitacoes-kicker">Administração</span>
          <h1 class="display-6 fw-semibold mb-2">Setores</h1>
          <p class="solicitacoes-subtitle mb-0">Lista oficial dos setores usados nas solicitações de insumo e no fluxo operacional do sistema.</p>
        </div>
        <div class="text-lg-end">
          <div class="solicitacoes-pill">Cadastro de referência</div>
          <small class="text-muted d-block mt-2">Os pedidos já registrados aparecem no resumo abaixo.</small>
        </div>
      </div>

      <div class="row g-3 mt-4">
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-info"><i class="fa-solid fa-building-columns"></i></div>
            <div>
              <div class="metric-label">Setores cadastrados</div>
              <div class="metric-value"><?= h(number_format(count($sectors), 0, ',', '.')) ?></div>
              <div class="metric-help">Lista oficial usada nesta página.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-success"><i class="fa-solid fa-file-signature"></i></div>
            <div>
              <div class="metric-label">Pedidos totais</div>
              <div class="metric-value"><?= h(number_format((int)($summary['total_requests'] ?? 0), 0, ',', '.')) ?></div>
              <div class="metric-help">Solicitações já gravadas no banco.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-warning"><i class="fa-solid fa-clock"></i></div>
            <div>
              <div class="metric-label">Pendentes</div>
              <div class="metric-value"><?= h(number_format((int)($summary['pending_requests'] ?? 0), 0, ',', '.')) ?></div>
              <div class="metric-help">Pedidos aguardando tratamento.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="metric-card h-100">
            <div class="metric-icon metric-icon-primary"><i class="fa-solid fa-circle-check"></i></div>
            <div>
              <div class="metric-label">Concluídos</div>
              <div class="metric-value"><?= h(number_format((int)($summary['completed_requests'] ?? 0), 0, ',', '.')) ?></div>
              <div class="metric-help">Solicitações já finalizadas.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="section-card card border-0 shadow-sm mb-4">
    <div class="card-body">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-3">
        <div>
          <span class="section-badge mb-2"><i class="fa-solid fa-list"></i>Lista oficial</span>
          <h2 class="h5 mb-2">Setores disponíveis</h2>
          <p class="section-card-subtitle mb-0">Cada setor abaixo aparece como referência para classificação e conferência dos pedidos.</p>
        </div>
      </div>

      <div class="row g-3">
        <?php foreach ($sectors as $sector): ?>
          <?php $usageKey = mb_strtolower($sector['name']); $usageCount = (int)($sectorUsage[$usageKey] ?? 0); ?>
          <div class="col-12 col-md-6 col-xl-4">
            <div class="metric-card h-100">
              <div class="d-flex align-items-start justify-content-between gap-3">
                <div class="d-flex align-items-start gap-3">
                  <div class="metric-icon metric-icon-info"><i class="fa-solid <?= h($sector['icon']) ?>"></i></div>
                  <div>
                    <h3 class="h6 mb-1"><?= h($sector['name']) ?></h3>
                    <p class="text-muted small mb-2"><?= h($sector['description']) ?></p>
                  </div>
                </div>
                <span class="badge text-bg-primary"><?= h(number_format($usageCount, 0, ',', '.')) ?></span>
              </div>
              <div class="d-flex flex-column gap-1 mt-2 small text-muted">
                <div><i class="fa-regular fa-hashtag me-2 text-primary"></i>Código: <?= h($sector['code']) ?></div>
                <div><i class="fa-regular fa-chart-bar me-2 text-primary"></i>Pedidos registrados: <?= h(number_format($usageCount, 0, ',', '.')) ?></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="section-card card border-0 shadow-sm mb-4">
    <div class="card-body">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-3">
        <div>
          <span class="section-badge mb-2"><i class="fa-solid fa-table-list"></i>Conferência</span>
          <h2 class="h5 mb-2">Pedidos recentes por setor</h2>
          <p class="section-card-subtitle mb-0">Últimos registros para validar se os setores estão sendo usados corretamente.</p>
        </div>
      </div>

      <?php if (empty($recentRequests)): ?>
        <div class="alert alert-info mb-0">Ainda não há solicitações registradas.</div>
      <?php else: ?>
        <div class="table-responsive request-table-wrap">
          <table class="table table-hover align-middle mb-0 request-table js-no-datatable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Setor</th>
                <th>Solicitante</th>
                <th>E-mail</th>
                <th>Status</th>
                <th>Solicitado em</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentRequests as $request): ?>
                <?php
                  $status = (string)($request['status'] ?? 'pending');
                  $statusBadge = $status === 'completed' ? 'bg-success' : ($status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark');
                ?>
                <tr>
                  <td><?= (int)$request['id'] ?></td>
                  <td><?= h((string)($request['setor'] ?? 'Sem setor')) ?></td>
                  <td><?= h((string)($request['user_nome'] ?? '-')) ?></td>
                  <td><?= h((string)($request['user_email'] ?? '-')) ?></td>
                  <td><span class="badge <?= h($statusBadge) ?>"><?= $status === 'completed' ? 'Concluída' : ($status === 'rejected' ? 'Rejeitada' : 'Pendente') ?></span></td>
                  <td><?= !empty($request['requested_at']) ? h(date('d/m/Y H:i', strtotime((string)$request['requested_at']))) : '-' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>