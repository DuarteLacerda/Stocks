<?php
require_once __DIR__ . '/../../includes/inventory/inventory-data.php';

[$dataFile, $historyFile, $storageError] = inventory_bootstrap();
$products = $storageError === null ? inventory_load_products($dataFile) : [];
$flashMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !inventory_verify_csrf($_POST['csrf_token'] ?? null)) {
    $flashMessage = 'Pedido inválido. Atualiza a página e tenta novamente.';
}

$id = trim((string) ($_POST['id'] ?? ($_GET['id'] ?? '')));
$product = $id !== '' ? inventory_find_product($products, $id) : null;
$csrfToken = inventory_csrf_token();
$postAction = (string) ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $storageError === null && $postAction === 'update') {
    if ($flashMessage !== null) {
        // Keep the error from CSRF validation.
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $quantity = max(0, (int) ($_POST['quantity'] ?? 0));
        $minQuantity = max(0, (int) ($_POST['min_quantity'] ?? 0));
        $status = inventory_normalize_status((string) ($_POST['status'] ?? 'ok'));

        if ($product === null) {
            $flashMessage = 'Produto não encontrado.';
        } elseif ($name === '' || $category === '' || $location === '') {
            $flashMessage = 'Preenche todos os campos obrigatórios.';
        } else {
            $before = $product;
            foreach ($products as &$currentProduct) {
                if (($currentProduct['id'] ?? '') === $id) {
                    $currentProduct['name'] = $name;
                    $currentProduct['category'] = $category;
                    $currentProduct['location'] = $location;
                    $currentProduct['quantity'] = $quantity;
                    $currentProduct['min_quantity'] = $minQuantity;
                    $currentProduct['status'] = $status;
                    break;
                }
            }
            unset($currentProduct);

            if (!inventory_save_products($dataFile, $products)) {
                $flashMessage = 'Erro ao gravar os dados.';
            } else {
                if (inventory_history_enabled()) {
                    inventory_add_history_event($historyFile, [
                        'id' => 'h' . str_replace('.', '', uniqid('', true)),
                        'action' => 'update',
                        'product_id' => $id,
                        'product_name' => $name,
                        'summary' => 'Dados do produto atualizados.',
                        'meta' => [
                            'Nome anterior' => (string) ($before['name'] ?? ''),
                            'Quantidade anterior' => (string) ($before['quantity'] ?? ''),
                            'Quantidade nova' => (string) $quantity,
                            'Estado anterior' => (string) ($before['status'] ?? ''),
                            'Estado novo' => $status,
                        ],
                        'timestamp' => date('c'),
                    ]);
                }

                stocks_redirect('?page=inventory&msg=' . urlencode('Produto atualizado com sucesso.'));
            }
        }
    }
}
?>

<div class="row g-3">
    <div class="col-12">
        <div class="metric-card dashboard-panel">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                <div>
                    <span class="dashboard-kicker">Inventário</span>
                    <h1 class="dashboard-title mb-2">Editar Produto</h1>
                    <p class="dashboard-copy mb-0">Ajusta os dados do item selecionado com contexto explícito em cada campo.</p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="?page=inventory">Voltar</a>
            </div>

            <?php if ($storageError !== null): ?>
                <div class="alert alert-danger mb-0" role="alert"><?php echo htmlspecialchars($storageError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php elseif ($product === null): ?>
                <div class="alert alert-warning mb-0" role="alert">Produto não encontrado.</div>
            <?php endif; ?>

            <?php if ($flashMessage !== null): ?>
                <div class="alert alert-warning mb-3" role="alert"><?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($product !== null): ?>
                <form method="post" class="row g-3" data-submit-lock>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="col-md-4">
                        <label class="form-label">Nome</label>
                        <input class="form-control" name="name" required value="<?php echo htmlspecialchars((string) ($product['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Categoria</label>
                        <input class="form-control" name="category" required value="<?php echo htmlspecialchars((string) ($product['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Local</label>
                        <input class="form-control" name="location" required value="<?php echo htmlspecialchars((string) ($product['location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Quantidade</label>
                        <input class="form-control" name="quantity" type="number" min="0" required value="<?php echo (int) ($product['quantity'] ?? 0); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mínimo</label>
                        <input class="form-control" name="min_quantity" type="number" min="0" required value="<?php echo (int) ($product['min_quantity'] ?? 0); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="status">
                            <option value="ok" <?php echo (($product['status'] ?? '') === 'ok') ? 'selected' : ''; ?>>OK</option>
                            <option value="attention" <?php echo (($product['status'] ?? '') === 'attention') ? 'selected' : ''; ?>>Atenção</option>
                            <option value="reserve" <?php echo (($product['status'] ?? '') === 'reserve') ? 'selected' : ''; ?>>Reserva</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-grid align-items-end">
                        <label class="form-label invisible">Ações</label>
                        <button class="btn btn-primary" type="submit" data-loading-text="A guardar...">Guardar alterações</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>