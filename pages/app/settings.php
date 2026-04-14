<?php
require_once __DIR__ . '/../../includes/inventory/inventory-data.php';

[$dataFile, $historyFile, $storageError] = inventory_bootstrap();
$products = $storageError === null ? inventory_load_products($dataFile) : [];
$history = $storageError === null ? inventory_load_history($historyFile) : [];

$totalProducts = count($products);
$totalUnits = 0;
$lowStockCount = 0;
foreach ($products as $product) {
    $qty = (int) ($product['quantity'] ?? 0);
    $minQty = (int) ($product['min_quantity'] ?? 0);
    $totalUnits += $qty;
    if ($qty < $minQty) {
        $lowStockCount++;
    }
}

$lastUpdate = file_exists($dataFile) ? date('d/m/Y H:i', (int) filemtime($dataFile)) : 'Sem registo';
$historyUpdate = file_exists($historyFile) ? date('d/m/Y H:i', (int) filemtime($historyFile)) : 'Sem registo';
$fileSizeKb = file_exists($dataFile) ? round(filesize($dataFile) / 1024, 2) : 0;
$historyCount = count($history);
?>

<div class="row g-3">
    <div class="col-12">
        <div class="metric-card dashboard-panel">
            <span class="dashboard-kicker">Definições</span>
            <h1 class="dashboard-title mb-2">Estado do sistema.</h1>
            <p class="dashboard-copy mb-0">Resumo rápido da estrutura que suporta o inventário, o histórico e o fluxo de manutenção.</p>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="metric-card dashboard-panel">
            <h6 class="section-title mb-3">Sistema</h6>
            <div class="dashboard-history-stats">
                <div class="dashboard-history-stat">
                    <span>Interface</span>
                    <strong>Tema claro/escuro</strong>
                </div>
                <div class="dashboard-history-stat">
                    <span>Dados</span>
                    <strong>JSON local</strong>
                </div>
                <div class="dashboard-history-stat">
                    <span>Fluxos</span>
                    <strong>Criar, editar e apagar + historico</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="metric-card dashboard-panel">
            <h6 class="section-title mb-3">Dados</h6>
            <div class="dashboard-history-stats">
                <div class="dashboard-history-stat">
                    <span>Produtos</span>
                    <strong><?php echo number_format($totalProducts, 0, ',', ' '); ?></strong>
                </div>
                <div class="dashboard-history-stat">
                    <span>Unidades</span>
                    <strong><?php echo number_format($totalUnits, 0, ',', ' '); ?></strong>
                </div>
                <div class="dashboard-history-stat">
                    <span>Abaixo do mínimo</span>
                    <strong><?php echo number_format($lowStockCount, 0, ',', ' '); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="metric-card dashboard-panel">
            <h6 class="section-title mb-3">Ficheiros</h6>
            <div class="dashboard-alerts">
                <div class="dashboard-alert">
                    <div>
                        <strong><?php echo htmlspecialchars($lastUpdate, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <div class="small text-muted">products.json · <?php echo number_format($fileSizeKb, 2, ',', ' '); ?> KB</div>
                    </div>
                </div>
                <div class="dashboard-alert">
                    <div>
                        <strong><?php echo htmlspecialchars($historyUpdate, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <div class="small text-muted">history.json · <?php echo number_format($historyCount, 0, ',', ' '); ?> eventos</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="metric-card dashboard-panel">
            <h6 class="section-title mb-3">Atalhos</h6>
            <div class="dashboard-alerts">
                <a class="dashboard-alert text-decoration-none" href="?page=inventory">
                    <div><strong>Abrir inventário</strong>
                        <div class="small text-muted">Gerir produtos, editar e apagar registos.</div>
                    </div>
                </a>
                <a class="dashboard-alert text-decoration-none" href="?page=inventory-create">
                    <div><strong>Criar Produto</strong>
                        <div class="small text-muted">Adicionar um novo item ao stock.</div>
                    </div>
                </a>
                <a class="dashboard-alert text-decoration-none" href="?page=history">
                    <div><strong>Ver histórico</strong>
                        <div class="small text-muted">Consultar ações recentes e alterações.</div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="metric-card dashboard-panel">
            <h6 class="section-title mb-3">Última atividade</h6>
            <div class="dashboard-alerts">
                <?php if (count($history) === 0): ?>
                    <div class="dashboard-alert">
                        <div><strong>Sem registos no histórico.</strong>
                            <div class="small text-muted">As ações recentes aparecem aqui assim que forem criadas.</div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php $latest = $history[0]; ?>
                    <div class="dashboard-alert">
                        <div>
                            <strong><?php echo htmlspecialchars(inventory_history_label((string) ($latest['action'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <div class="small text-muted">
                                <?php echo htmlspecialchars((string) ($latest['product_name'] ?? 'Sem produto'), ENT_QUOTES, 'UTF-8'); ?> ·
                                <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($latest['timestamp'] ?? 'now'))), ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>