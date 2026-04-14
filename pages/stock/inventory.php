<?php
require_once __DIR__ . '/../../includes/inventory/inventory-data.php';

[$dataFile, $historyFile, $storageError] = inventory_bootstrap();
$products = $storageError === null ? inventory_load_products($dataFile) : [];
$csrfToken = inventory_csrf_token();

$redirectParams = ['page' => 'inventory'];
foreach (['q', 'category', 'status', 'location', 'per_page', 'p'] as $param) {
    $value = trim((string) ($_GET[$param] ?? ''));
    if ($value !== '') {
        $redirectParams[$param] = $value;
    }
}
$inventoryBaseRedirect = '?' . http_build_query($redirectParams);

function inventory_redirect_with_message(string $baseUrl, string $message): string
{
    $separator = strpos($baseUrl, '?') !== false ? '&' : '?';
    return $baseUrl . $separator . 'msg=' . urlencode($message);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $storageError === null) {
    if (!inventory_verify_csrf($_POST['csrf_token'] ?? null)) {
        stocks_redirect(inventory_redirect_with_message($inventoryBaseRedirect, 'Pedido inválido. Atualiza a página e tenta novamente.'));
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = trim((string) ($_POST['id'] ?? ''));
        $deletedProduct = inventory_find_product($products, $id);
        $products = array_values(array_filter($products, static fn($item) => ($item['id'] ?? '') !== $id));

        if (!inventory_save_products($dataFile, $products)) {
            stocks_redirect(inventory_redirect_with_message($inventoryBaseRedirect, 'Erro ao gravar os dados.'));
        }

        if (is_array($deletedProduct) && inventory_history_enabled()) {
            inventory_add_history_event($historyFile, [
                'id' => 'h' . str_replace('.', '', uniqid('', true)),
                'action' => 'delete',
                'product_id' => $id,
                'product_name' => (string) ($deletedProduct['name'] ?? ''),
                'summary' => 'Produto removido do inventário.',
                'meta' => [
                    'Categoria' => (string) ($deletedProduct['category'] ?? ''),
                    'Local' => (string) ($deletedProduct['location'] ?? ''),
                    'Quantidade' => (string) ($deletedProduct['quantity'] ?? ''),
                ],
                'timestamp' => date('c'),
            ]);
        }

        stocks_redirect(inventory_redirect_with_message($inventoryBaseRedirect, 'Produto removido com sucesso.'));
    }
}

$totalUnits = 0;
$lowStockCount = 0;
$restockUnits = 0;

foreach ($products as $product) {
    $qty = (int) ($product['quantity'] ?? 0);
    $minQty = (int) ($product['min_quantity'] ?? 0);
    $totalUnits += $qty;
    if ($qty <= $minQty) {
        $lowStockCount++;
    }
    if ($qty < $minQty) {
        $restockUnits += ($minQty - $qty);
    }
}

$flashMessage = trim((string) ($_GET['msg'] ?? ''));

$searchQuery = trim((string) ($_GET['q'] ?? ''));
$selectedCategory = trim((string) ($_GET['category'] ?? ''));
$selectedStatus = trim((string) ($_GET['status'] ?? ''));
$selectedLocation = trim((string) ($_GET['location'] ?? ''));
$allowedStatuses = ['ok', 'attention', 'reserve'];
if (!in_array($selectedStatus, $allowedStatuses, true)) {
    $selectedStatus = '';
}

$categories = [];
$locations = [];
foreach ($products as $product) {
    $categoryValue = trim((string) ($product['category'] ?? ''));
    $locationValue = trim((string) ($product['location'] ?? ''));
    if ($categoryValue !== '') {
        $categories[$categoryValue] = true;
    }
    if ($locationValue !== '') {
        $locations[$locationValue] = true;
    }
}

$categories = array_keys($categories);
$locations = array_keys($locations);
sort($categories, SORT_NATURAL | SORT_FLAG_CASE);
sort($locations, SORT_NATURAL | SORT_FLAG_CASE);

$filteredProducts = array_values(array_filter($products, static function (array $product) use ($searchQuery, $selectedCategory, $selectedStatus, $selectedLocation): bool {
    $name = (string) ($product['name'] ?? '');
    $category = (string) ($product['category'] ?? '');
    $location = (string) ($product['location'] ?? '');
    $status = (string) ($product['status'] ?? '');
    $quantity = (string) ($product['quantity'] ?? '');
    $minimum = (string) ($product['min_quantity'] ?? '');

    if ($selectedCategory !== '' && strcasecmp($selectedCategory, $category) !== 0) {
        return false;
    }

    if ($selectedStatus !== '' && strcasecmp($selectedStatus, $status) !== 0) {
        return false;
    }

    if ($selectedLocation !== '' && strcasecmp($selectedLocation, $location) !== 0) {
        return false;
    }

    if ($searchQuery !== '') {
        $haystack = trim($name . ' ' . $category . ' ' . $location . ' ' . $status . ' ' . $quantity . ' ' . $minimum);
        if (stripos($haystack, $searchQuery) === false) {
            return false;
        }
    }

    return true;
}));

$perPageOptions = [10, 25, 50];
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 10;
}

$totalFiltered = count($filteredProducts);
$totalPages = max(1, (int) ceil($totalFiltered / $perPage));
$currentListPage = (int) ($_GET['p'] ?? 1);
if ($currentListPage < 1) {
    $currentListPage = 1;
}
if ($currentListPage > $totalPages) {
    $currentListPage = $totalPages;
}

$offset = ($currentListPage - 1) * $perPage;
$visibleProducts = array_slice($filteredProducts, $offset, $perPage);
$rangeStart = $totalFiltered === 0 ? 0 : ($offset + 1);
$rangeEnd = $totalFiltered === 0 ? 0 : ($offset + count($visibleProducts));

$baseParams = [
    'page' => 'inventory',
    'q' => $searchQuery,
    'category' => $selectedCategory,
    'status' => $selectedStatus,
    'location' => $selectedLocation,
    'per_page' => (string) $perPage,
];

$buildInventoryUrl = static function (array $overrides = []) use ($baseParams): string {
    $params = array_merge($baseParams, $overrides);
    $params = array_filter($params, static fn($value): bool => $value !== '');
    return '?' . http_build_query($params);
};
?>

<div class="row g-3">
    <div class="col-12">
        <div class="metric-card dashboard-panel">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                <div>
                    <span class="dashboard-kicker">Inventário</span>
                    <h1 class="dashboard-title mb-2">Gestão de Produtos em Stock</h1>
                </div>
                <span class="dashboard-badge"><?php echo $totalFiltered; ?> de <?php echo count($products); ?> produtos</span>
            </div>

            <?php if ($storageError !== null): ?>
                <div class="alert alert-danger mb-0" role="alert"><?php echo htmlspecialchars($storageError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($flashMessage !== ''): ?>
                <div class="alert alert-success mb-3" role="alert"><?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="metric-card stat-card">
                        <div class="metric-title">Unidades em stock</div>
                        <div class="metric-value"><?php echo number_format($totalUnits, 0, ',', ' '); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-card stat-card">
                        <div class="metric-title">Reposição necessária</div>
                        <div class="metric-value"><?php echo number_format($restockUnits, 0, ',', ' '); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-card stat-card metric-highlight">
                        <div class="metric-title text-danger">Abaixo do mínimo</div>
                        <div class="metric-value text-danger"><?php echo number_format($lowStockCount, 0, ',', ' '); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="metric-card dashboard-panel">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <h6 class="section-title mb-0">Produtos</h6>
                <a class="btn btn-primary btn-sm" href="?page=inventory-create">Novo Produto</a>
            </div>

            <form method="get" action="" class="row g-2 align-items-end inventory-filter-form">
                <input type="hidden" name="page" value="inventory">
                <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="col-sm-6 col-lg-3">
                    <label for="filterCategory" class="form-label inventory-filter-label">Categoria</label>
                    <select id="filterCategory" name="category" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedCategory === $category ? 'selected' : ''; ?>><?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label for="filterStatus" class="form-label inventory-filter-label">Estado</label>
                    <select id="filterStatus" name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="ok" <?php echo $selectedStatus === 'ok' ? 'selected' : ''; ?>>OK</option>
                        <option value="attention" <?php echo $selectedStatus === 'attention' ? 'selected' : ''; ?>>Atenção</option>
                        <option value="reserve" <?php echo $selectedStatus === 'reserve' ? 'selected' : ''; ?>>Reserva</option>
                    </select>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label for="filterLocation" class="form-label inventory-filter-label">Local</label>
                    <select id="filterLocation" name="location" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?php echo htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedLocation === $location ? 'selected' : ''; ?>><?php echo htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label for="filterPerPage" class="form-label inventory-filter-label">Por página</label>
                    <select id="filterPerPage" name="per_page" class="form-select form-select-sm">
                        <?php foreach ($perPageOptions as $option): ?>
                            <option value="<?php echo $option; ?>" <?php echo $perPage === $option ? 'selected' : ''; ?>><?php echo $option; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-sm-12 col-lg-1 d-flex gap-2 justify-content-start justify-content-lg-end">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Aplicar</button>
                </div>
            </form>

            <div class="inventory-filter-meta mt-2">
                <span>A mostrar <?php echo $rangeStart; ?>-<?php echo $rangeEnd; ?> de <?php echo $totalFiltered; ?> resultados</span>
                <?php if ($searchQuery !== '' || $selectedCategory !== '' || $selectedStatus !== '' || $selectedLocation !== '' || $perPage !== 10): ?>
                    <a href="?page=inventory" class="inventory-clear-link">Limpar filtros</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="metric-card dashboard-panel">
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th>Local</th>
                            <th>Qtd</th>
                            <th>Mínimo</th>
                            <th>Estado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody" class="inventory-table-body">
                        <?php if ($totalFiltered === 0): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Sem produtos para os filtros selecionados.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($visibleProducts as $product): ?>
                            <?php
                            [$statusText, $statusClass] = inventory_badge_by_status((string) ($product['status'] ?? 'ok'));
                            $id = (string) ($product['id'] ?? '');
                            $searchIndex = strtolower(trim(((string) ($product['name'] ?? '')) . ' ' . ((string) ($product['category'] ?? '')) . ' ' . ((string) ($product['location'] ?? '')) . ' ' . ((string) ($product['status'] ?? '')) . ' ' . ((string) ($product['quantity'] ?? '')) . ' ' . ((string) ($product['min_quantity'] ?? ''))));
                            ?>
                            <tr data-search-row="product" data-search="<?php echo htmlspecialchars($searchIndex, ENT_QUOTES, 'UTF-8'); ?>">
                                <td><?php echo htmlspecialchars((string) ($product['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($product['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($product['location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo (int) ($product['quantity'] ?? 0); ?></td>
                                <td><?php echo (int) ($product['min_quantity'] ?? 0); ?></td>
                                <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                <td>
                                    <form method="post" action="?page=inventory-edit" class="d-inline" data-submit-lock>
                                        <input type="hidden" name="action" value="open">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                        <button class="btn btn-sm btn-link text-decoration-none p-0 d-inline-flex align-items-center gap-1 inventory-edit-toggle" type="submit" data-loading-text="A abrir...">
                                            <span class="material-symbols-outlined">edit</span>
                                            <span>Editar</span>
                                        </button>
                                    </form>
                                    <form method="post" class="mt-2 inventory-delete-form" data-submit-lock>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                        <button class="btn btn-sm btn-link text-danger p-0 d-inline-flex align-items-center gap-1 inventory-delete-btn js-delete-trigger" type="submit" aria-label="Apagar" data-product-name="<?php echo htmlspecialchars((string) ($product['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-loading-text="A apagar...">
                                            <span class="material-symbols-outlined">delete</span>
                                            <span>Apagar</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="inventory-pagination-wrap mt-3" aria-label="Paginação do inventário">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?php echo $currentListPage <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars($buildInventoryUrl(['p' => max(1, $currentListPage - 1)]), ENT_QUOTES, 'UTF-8'); ?>">Anterior</a>
                        </li>
                        <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                            <li class="page-item <?php echo $pageNumber === $currentListPage ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars($buildInventoryUrl(['p' => $pageNumber]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $pageNumber; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $currentListPage >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars($buildInventoryUrl(['p' => min($totalPages, $currentListPage + 1)]), ENT_QUOTES, 'UTF-8'); ?>">Seguinte</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="inventory-confirm-modal" id="inventoryDeleteModal" hidden>
    <div class="inventory-confirm-backdrop" data-close-delete-modal="true"></div>
    <div class="inventory-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="inventoryDeleteTitle" aria-describedby="inventoryDeleteText">
        <div class="inventory-confirm-head">
            <span class="material-symbols-outlined inventory-confirm-icon" aria-hidden="true">delete_forever</span>
            <div>
                <h2 id="inventoryDeleteTitle">Confirmar remoção</h2>
                <p id="inventoryDeleteText" class="mb-0">Esta ação vai remover o produto do inventário e não pode ser anulada.</p>
            </div>
        </div>
        <div class="inventory-confirm-product-wrap mb-4">
            <span class="inventory-confirm-product-label">Produto selecionado</span>
            <p class="inventory-confirm-product mb-0" id="inventoryDeleteProduct">-</p>
        </div>
        <div class="inventory-confirm-actions">
            <button type="button" class="btn btn-outline-secondary btn-sm inventory-confirm-cancel" id="inventoryDeleteCancel">Cancelar</button>
            <button type="button" class="btn btn-danger btn-sm inventory-confirm-delete" id="inventoryDeleteConfirm">Apagar produto</button>
        </div>
    </div>
</div>

<script>
    (() => {
        const modal = document.getElementById('inventoryDeleteModal');
        if (!modal) {
            return;
        }

        const confirmBtn = document.getElementById('inventoryDeleteConfirm');
        const cancelBtn = document.getElementById('inventoryDeleteCancel');
        const productLabel = document.getElementById('inventoryDeleteProduct');
        const triggers = document.querySelectorAll('.js-delete-trigger');
        let activeForm = null;

        const openModal = (form, productName) => {
            activeForm = form;
            productLabel.textContent = productName && productName.trim() !== '' ? productName : 'Produto selecionado';
            modal.hidden = false;
            document.body.classList.add('modal-open');
            confirmBtn.focus();
        };

        const closeModal = () => {
            modal.hidden = true;
            document.body.classList.remove('modal-open');
            activeForm = null;
        };

        triggers.forEach((trigger) => {
            trigger.addEventListener('click', (event) => {
                event.preventDefault();
                const form = trigger.closest('form');
                if (!form) {
                    return;
                }

                openModal(form, trigger.getAttribute('data-product-name') || '');
            });
        });

        confirmBtn.addEventListener('click', () => {
            if (activeForm) {
                activeForm.submit();
            }
        });

        cancelBtn.addEventListener('click', closeModal);

        modal.addEventListener('click', (event) => {
            const target = event.target;
            if (target && target.nodeType === 1 && target.dataset && target.dataset.closeDeleteModal === 'true') {
                closeModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });
    })();
</script>