<?php
require_once __DIR__ . '/../../includes/inventory/inventory-data.php';

[$dataFile, $historyFile, $storageError] = inventory_bootstrap();
$products = $storageError === null ? inventory_load_products($dataFile) : [];
$flashMessage = null;
$csrfToken = inventory_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $storageError === null) {
    if (!inventory_verify_csrf($_POST['csrf_token'] ?? null)) {
        $flashMessage = 'Pedido inválido. Atualiza a página e tenta novamente.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $quantity = max(0, (int) ($_POST['quantity'] ?? 0));
        $minQuantity = max(0, (int) ($_POST['min_quantity'] ?? 0));
        $status = inventory_normalize_status((string) ($_POST['status'] ?? 'ok'));

        if ($name === '' || $category === '' || $location === '') {
            $flashMessage = 'Preenche todos os campos obrigatórios.';
        } else {
            $productId = 'p' . str_replace('.', '', uniqid('', true));
            $products[] = [
                'id' => $productId,
                'name' => $name,
                'category' => $category,
                'location' => $location,
                'quantity' => $quantity,
                'min_quantity' => $minQuantity,
                'status' => $status,
            ];

            if (!inventory_save_products($dataFile, $products)) {
                $flashMessage = 'Erro ao gravar os dados.';
            } else {
                if (inventory_history_enabled()) {
                    inventory_add_history_event($historyFile, [
                        'id' => 'h' . str_replace('.', '', uniqid('', true)),
                        'action' => 'create',
                        'product_id' => $productId,
                        'product_name' => $name,
                        'summary' => 'Novo produto adicionado ao inventário.',
                        'meta' => [
                            'Categoria' => $category,
                            'Local' => $location,
                            'Quantidade' => (string) $quantity,
                            'Mínimo' => (string) $minQuantity,
                            'Estado' => $status,
                        ],
                        'timestamp' => date('c'),
                    ]);
                }

                stocks_redirect('?page=inventory&msg=' . urlencode('Produto criado com sucesso.'));
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
                    <h1 class="dashboard-title mb-2">Criar Produto</h1>
                    <p class="dashboard-copy mb-0">Regista um novo item com campos claros e uma estrutura direta.</p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="?page=inventory">Voltar</a>
            </div>

            <?php if ($storageError !== null): ?>
                <div class="alert alert-danger mb-0" role="alert"><?php echo htmlspecialchars($storageError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($flashMessage !== null): ?>
                <div class="alert alert-warning mb-3" role="alert"><?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" class="row g-3" data-submit-lock>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="col-md-4">
                    <label class="form-label">Nome</label>
                    <input class="form-control" name="name" required placeholder="Ex. Laptop HP">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Categoria</label>
                    <input class="form-control" name="category" required placeholder="Ex. Informática">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Local</label>
                    <input class="form-control" name="location" required placeholder="Ex. Armazém A">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Quantidade</label>
                    <input class="form-control" name="quantity" type="number" min="0" required value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mínimo</label>
                    <input class="form-control" name="min_quantity" type="number" min="0" required value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="status">
                        <option value="ok">OK</option>
                        <option value="attention">Atenção</option>
                        <option value="reserve">Reserva</option>
                    </select>
                </div>
                <div class="col-md-3 d-grid align-items-end">
                    <label class="form-label invisible">Ações</label>
                    <button class="btn btn-primary" type="submit" data-loading-text="A criar...">Criar Produto</button>
                </div>
            </form>
        </div>
    </div>
</div>