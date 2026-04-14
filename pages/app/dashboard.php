<?php
require_once __DIR__ . '/../../includes/inventory/inventory-data.php';

[$dataFile, $historyFile, $storageError] = inventory_bootstrap();
$products = $storageError === null ? inventory_load_products($dataFile) : [];

function dashboardBadgeStatus(string $status): array
{
    return match ($status) {
        'attention' => ['Atenção', 'bg-warning-subtle text-warning border border-warning-subtle'],
        'reserve' => ['Reserva', 'bg-primary-subtle text-primary border border-primary-subtle'],
        default => ['OK', 'bg-success-subtle text-success border border-success-subtle'],
    };
}

$totalProducts = count($products);
$totalUnits = 0;
$alerts = 0;
$categoryUnits = [];

foreach ($products as $product) {
    $qty = (int) ($product['quantity'] ?? 0);
    $minQty = (int) ($product['min_quantity'] ?? 0);
    $category = (string) ($product['category'] ?? 'Sem categoria');

    $totalUnits += $qty;
    if ($qty <= $minQty) {
        $alerts++;
    }

    if (!isset($categoryUnits[$category])) {
        $categoryUnits[$category] = 0;
    }
    $categoryUnits[$category] += $qty;
}

arsort($categoryUnits);

$chartLabels = array_keys($categoryUnits);
$chartData = array_values($categoryUnits);
if (count($chartLabels) === 0) {
    $chartLabels = ['Sem dados'];
    $chartData = [0];
}

$chartPalette = [
    '#5d7fb6',
    '#b0a08e',
    '#d58a73',
    '#8fb08b',
    '#d3a65a',
    '#7d93c9',
];

$categoryBreakdown = [];
$totalCategoryUnits = array_sum($chartData);
foreach ($chartLabels as $index => $label) {
    $units = (int) ($chartData[$index] ?? 0);
    $percentage = $totalCategoryUnits > 0 ? round(($units / $totalCategoryUnits) * 100) : 0;
    $categoryBreakdown[] = [
        'label' => $label,
        'units' => $units,
        'percentage' => $percentage,
        'color' => $chartPalette[$index % count($chartPalette)],
    ];
}

$compactBreakdown = array_slice($categoryBreakdown, 0, 4);
if (count($categoryBreakdown) > 4) {
    $otherUnits = array_sum(array_map(static fn(array $item): int => (int) $item['units'], array_slice($categoryBreakdown, 4)));
    $otherPercentage = $totalCategoryUnits > 0 ? round(($otherUnits / $totalCategoryUnits) * 100) : 0;
    $compactBreakdown[] = [
        'label' => 'Outras categorias',
        'units' => $otherUnits,
        'percentage' => $otherPercentage,
        'color' => '#c9bfb1',
    ];
}

$topProducts = $products;
usort($topProducts, static function (array $a, array $b): int {
    return (int) ($b['quantity'] ?? 0) <=> (int) ($a['quantity'] ?? 0);
});
$topProducts = array_slice($topProducts, 0, 5);

$topCategory = count($categoryUnits) > 0 ? array_key_first($categoryUnits) : 'Sem dados';
$topCategoryUnits = count($categoryUnits) > 0 ? (int) $categoryUnits[$topCategory] : 0;
?>

<div class="dashboard-page">
    <div class="metric-card dashboard-hero mb-4">
        <div class="row align-items-center g-4">
            <div class="col-12">
                <span class="dashboard-kicker">Painel principal</span>
                <h1 class="dashboard-title mb-3">Controlo de stock com leitura imediata.</h1>
                <p class="dashboard-copy mb-0">Resumo direto do inventário, valor e atividade.</p>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="metric-card stat-card">
                <div class="metric-title">Produtos registados</div>
                <div class="metric-value"><?php echo number_format($totalProducts, 0, ',', ' '); ?></div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="metric-card stat-card">
                <div class="metric-title">Unidades em stock</div>
                <div class="metric-value"><?php echo number_format($totalUnits, 0, ',', ' '); ?></div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="metric-card stat-card metric-highlight">
                <div class="metric-title text-danger">Alertas de reposição</div>
                <div class="metric-value text-danger"><?php echo number_format($alerts, 0, ',', ' '); ?></div>
            </div>
        </div>
    </div>

    <div class="metric-card dashboard-panel mb-4 dashboard-chart-card">
        <div class="dashboard-chart-header">
            <div>
                <h6 class="section-title mb-1">Stock por categoria</h6>
                <p class="dashboard-chart-note mb-0">Distribuição resumida das categorias com maior peso no inventário.</p>
            </div>
            <span class="dashboard-badge"><?php echo count($categoryUnits); ?> categorias</span>
        </div>

        <div class="dashboard-chart-summary mb-3">
            <div class="dashboard-chart-summary-item">
                <span class="dashboard-chart-label">Categoria principal</span>
                <strong><?php echo htmlspecialchars($topCategory, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div class="dashboard-chart-summary-item">
                <span class="dashboard-chart-label">Unidades na liderança</span>
                <strong><?php echo number_format($topCategoryUnits, 0, ',', ' '); ?></strong>
            </div>
        </div>

        <div class="dashboard-chart-main">
            <div class="row g-3 align-items-center">
                <div class="col-lg-7">
                    <div class="dashboard-chart-canvas">
                        <canvas id="weeklyActivityChart" aria-label="Gráfico de stock por categoria"></canvas>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="dashboard-mini-list">
                        <?php foreach ($compactBreakdown as $index => $item): ?>
                            <div class="dashboard-mini-item">
                                <span class="dashboard-alert-dot dashboard-category-dot" data-chart-dot-index="<?php echo (int) $index; ?>" style="background: <?php echo htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8'); ?>;"></span>
                                <div>
                                    <strong><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <div class="small text-muted"><?php echo number_format($item['units'], 0, ',', ' '); ?> unidades · <?php echo (int) $item['percentage']; ?>%</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="metric-card dashboard-panel">
        <h6 class="section-title mb-3">Produtos com Maior Stock</h6>

        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Localização</th>
                        <th>Qtd</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($topProducts) === 0): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Sem dados no inventário.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($topProducts as $product): ?>
                        <?php [$statusText, $statusClass] = dashboardBadgeStatus((string) ($product['status'] ?? 'ok')); ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($product['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($product['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($product['location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) ($product['quantity'] ?? 0); ?></td>
                            <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    (() => {
        const canvas = document.getElementById('weeklyActivityChart');
        if (!canvas || typeof window.Chart === 'undefined') {
            return;
        }

        const labels = <?php echo json_encode(array_values($chartLabels), JSON_UNESCAPED_UNICODE); ?>;
        const values = <?php echo json_encode(array_values($chartData)); ?>;

        const lightPalette = ['#5d7fb6', '#b0a08e', '#d58a73', '#8fb08b', '#d3a65a', '#7d93c9'];
        const darkPalette = ['#7fa6da', '#d2bf9b', '#e3a18f', '#98c6ad', '#e7bf79', '#9fb5eb'];
        let chartInstance = null;

        function themeValue(name, fallback) {
            const raw = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            return raw !== '' ? raw : fallback;
        }

        function paletteForTheme(theme, length) {
            const source = theme === 'dark' ? darkPalette : lightPalette;
            return Array.from({
                length
            }, (_, index) => source[index % source.length]);
        }

        function renderChart() {
            const theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            const textMain = themeValue('--text-main', theme === 'dark' ? '#e7edf5' : '#1d2733');
            const borderColor = themeValue('--border-color', theme === 'dark' ? '#2a3745' : '#e4d9cd');
            const surfaceColor = themeValue('--bg-surface', theme === 'dark' ? '#151e27' : '#fffdfb');
            const chartColors = paletteForTheme(theme, values.length);
            const compactDotColors = paletteForTheme(theme, document.querySelectorAll('.dashboard-category-dot').length);

            document.querySelectorAll('.dashboard-category-dot').forEach((dot, index) => {
                dot.style.backgroundColor = compactDotColors[index % compactDotColors.length];
            });

            if (chartInstance) {
                chartInstance.destroy();
            }

            chartInstance = new window.Chart(canvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        label: 'Unidades',
                        data: values,
                        backgroundColor: chartColors,
                        borderColor: surfaceColor,
                        borderWidth: 3,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: surfaceColor,
                            titleColor: textMain,
                            bodyColor: textMain,
                            borderColor: borderColor,
                            borderWidth: 1,
                            padding: 10
                        }
                    },
                    cutout: '70%',
                    layout: {
                        padding: 4
                    }
                }
            });
        }

        renderChart();

        const root = document.documentElement;
        const observer = new window.MutationObserver((mutationList) => {
            if (mutationList.some((mutation) => mutation.type === 'attributes' && mutation.attributeName === 'data-theme')) {
                renderChart();
            }
        });
        observer.observe(root, {
            attributes: true,
            attributeFilter: ['data-theme']
        });
    })();
</script>