<?php
require_once __DIR__ . '/../../includes/inventory/inventory-data.php';

[$dataFile, $historyFile, $storageError] = inventory_bootstrap();
$products = $storageError === null ? inventory_load_products($dataFile) : [];
$history = $storageError === null ? inventory_load_history($historyFile) : [];

$lowStock = [];
$statusCounts = ['ok' => 0, 'attention' => 0, 'reserve' => 0];
$historyCounts = ['create' => 0, 'update' => 0, 'delete' => 0];

foreach ($products as $product) {
    $qty = (int) ($product['quantity'] ?? 0);
    $minQty = (int) ($product['min_quantity'] ?? 0);
    $status = (string) ($product['status'] ?? 'ok');

    if ($qty < $minQty) {
        $product['deficit'] = $minQty - $qty;
        $lowStock[] = $product;
    }

    if (!isset($statusCounts[$status])) {
        $statusCounts[$status] = 0;
    }
    $statusCounts[$status]++;
}

foreach ($history as $entry) {
    $action = (string) ($entry['action'] ?? '');
    if (isset($historyCounts[$action])) {
        $historyCounts[$action]++;
    }
}

usort($lowStock, static fn($a, $b) => (int) ($b['deficit'] ?? 0) <=> (int) ($a['deficit'] ?? 0));
$lowStock = array_slice($lowStock, 0, 5);
$history = array_slice($history, 0, 8);

$totalProducts = max(1, count($products));
$okPercent = (int) round(($statusCounts['ok'] / $totalProducts) * 100);
$attentionPercent = (int) round(($statusCounts['attention'] / $totalProducts) * 100);
$reservePercent = (int) round(($statusCounts['reserve'] / $totalProducts) * 100);
?>

<div class="row g-3">
    <div class="col-12">
        <div class="metric-card dashboard-panel">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div>
                    <span class="dashboard-kicker">Histórico</span>
                    <h1 class="dashboard-title mb-2">Atividade recente do inventário.</h1>
                    <p class="dashboard-copy mb-0">Registo cronológico das alterações feitas aos produtos em stock.</p>
                </div>
                <span class="dashboard-badge"><?php echo count($history); ?> eventos</span>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="metric-card dashboard-panel h-100">
            <h6 class="section-title mb-3">Resumo</h6>
            <div class="dashboard-history-stats">
                <div class="dashboard-history-stat">
                    <span>Criações</span>
                    <strong><?php echo number_format($historyCounts['create'], 0, ',', ' '); ?></strong>
                </div>
                <div class="dashboard-history-stat">
                    <span>Edições</span>
                    <strong><?php echo number_format($historyCounts['update'], 0, ',', ' '); ?></strong>
                </div>
                <div class="dashboard-history-stat">
                    <span>Apagados</span>
                    <strong><?php echo number_format($historyCounts['delete'], 0, ',', ' '); ?></strong>
                </div>
            </div>

            <hr>

            <div class="mb-3">
                <div class="d-flex justify-content-between small mb-1"><span>OK</span><strong><?php echo $okPercent; ?>%</strong></div>
                <div class="progress" style="height:8px;">
                    <div class="progress-bar bg-success" style="width: <?php echo $okPercent; ?>%;"></div>
                </div>
            </div>
            <div class="mb-3">
                <div class="d-flex justify-content-between small mb-1"><span>Atenção</span><strong><?php echo $attentionPercent; ?>%</strong></div>
                <div class="progress" style="height:8px;">
                    <div class="progress-bar bg-warning" style="width: <?php echo $attentionPercent; ?>%;"></div>
                </div>
            </div>
            <div class="mb-3">
                <div class="d-flex justify-content-between small mb-1"><span>Reserva</span><strong><?php echo $reservePercent; ?>%</strong></div>
                <div class="progress" style="height:8px;">
                    <div class="progress-bar" style="width: <?php echo $reservePercent; ?>%; background: var(--bs-primary);"></div>
                </div>
            </div>

            <hr>

            <div class="small text-muted">Produtos abaixo do mínimo</div>
            <div class="h5 mb-0"><?php echo count($lowStock); ?></div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="metric-card dashboard-panel h-100">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                <h6 class="section-title mb-0">Linha temporal</h6>
                <span class="small text-muted">Mais recente primeiro</span>
            </div>

            <div class="history-timeline">
                <?php if ($storageError !== null): ?>
                    <div class="alert alert-danger mb-0" role="alert"><?php echo htmlspecialchars($storageError, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php elseif (count($history) === 0): ?>
                    <div class="history-empty">
                        <strong>Sem registos ainda.</strong>
                        <div class="small text-muted">As ações de criar, editar e apagar vão aparecer aqui.</div>
                    </div>
                <?php endif; ?>

                <?php foreach ($history as $entry): ?>
                    <?php
                    $action = (string) ($entry['action'] ?? '');
                    $label = inventory_history_label($action);
                    $icon = inventory_history_icon($action);
                    $timestamp = (string) ($entry['timestamp'] ?? '');
                    $dateLabel = $timestamp !== '' ? date('d/m/Y H:i', strtotime($timestamp)) : 'Sem data';
                    $meta = is_array($entry['meta'] ?? null) ? $entry['meta'] : [];
                    ?>
                    <article class="history-entry">
                        <div class="history-icon history-<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="material-symbols-outlined"><?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="history-body">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <strong><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if (($entry['product_name'] ?? '') !== ''): ?>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle"><?php echo htmlspecialchars((string) $entry['product_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <span class="small text-muted"><?php echo htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="small text-muted mb-2">
                                <?php echo htmlspecialchars((string) ($entry['summary'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            </div>

                            <?php if (count($meta) > 0): ?>
                                <div class="history-meta">
                                    <?php foreach ($meta as $key => $value): ?>
                                        <span class="history-meta-item">
                                            <span><?php echo htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'); ?>:</span>
                                            <strong><?php echo htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?></strong>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="metric-card dashboard-panel">
            <h6 class="section-title mb-3">Produtos com Maior Falta</h6>
            <div class="dashboard-alerts">
                <?php if (count($lowStock) === 0): ?>
                    <div class="dashboard-alert">
                        <div><strong>Sem faltas abaixo do mínimo.</strong></div>
                    </div>
                <?php endif; ?>

                <?php foreach ($lowStock as $item): ?>
                    <div class="dashboard-alert">
                        <span class="dashboard-alert-dot" style="background: var(--bs-warning);"></span>
                        <div>
                            <strong><?php echo htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <div class="small text-muted">
                                Falta: <?php echo (int) ($item['deficit'] ?? 0); ?> |
                                Atual: <?php echo (int) ($item['quantity'] ?? 0); ?> /
                                Min: <?php echo (int) ($item['min_quantity'] ?? 0); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>