<?php

function inventory_history_enabled(): bool
{
    $value = getenv('STOCKS_ENABLE_HISTORY');
    if (!is_string($value) || $value === '') {
        return true;
    }

    $normalized = strtolower(trim($value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function inventory_use_file_locks(): bool
{
    $value = getenv('STOCKS_USE_FILE_LOCKS');
    if (!is_string($value) || $value === '') {
        return false;
    }

    $normalized = strtolower(trim($value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function inventory_write_json_file(string $filePath, array $payload): bool
{
    $flags = inventory_use_file_locks() ? LOCK_EX : 0;
    return file_put_contents($filePath, json_encode($payload, JSON_UNESCAPED_UNICODE), $flags) !== false;
}

function inventory_ensure_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function inventory_csrf_token(): string
{
    inventory_ensure_session();

    if (empty($_SESSION['inventory_csrf']) || !is_string($_SESSION['inventory_csrf'])) {
        $_SESSION['inventory_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['inventory_csrf'];
}

function inventory_verify_csrf(?string $token): bool
{
    inventory_ensure_session();

    $stored = $_SESSION['inventory_csrf'] ?? null;
    if (!is_string($stored) || $stored === '' || !is_string($token) || $token === '') {
        return false;
    }

    return hash_equals($stored, $token);
}

function inventory_bootstrap(): array
{
    $dataDir = dirname(__DIR__, 2) . '/data';
    $dataFile = $dataDir . '/products.json';
    $historyFile = $dataDir . '/history.json';
    $storageError = null;

    if (!is_dir($dataDir) && !mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
        $storageError = 'Não foi possível criar o diretório de dados.';
    }

    if ($storageError === null && !file_exists($dataFile)) {
        $seedProducts = [
            [
                'id' => 'p1',
                'name' => 'Dell Latitude',
                'category' => 'Informática',
                'location' => 'Sala T3',
                'quantity' => 42,
                'min_quantity' => 15,
                'status' => 'ok',
            ],
            [
                'id' => 'p2',
                'name' => 'Centrífuga',
                'category' => 'Laboratório',
                'location' => 'Química',
                'quantity' => 3,
                'min_quantity' => 5,
                'status' => 'attention',
            ],
            [
                'id' => 'p3',
                'name' => 'Projetor Epson',
                'category' => 'Áudio/Vídeo',
                'location' => 'Sala 12',
                'quantity' => 8,
                'min_quantity' => 4,
                'status' => 'reserve',
            ],
        ];

        if (!inventory_write_json_file($dataFile, $seedProducts)) {
            $storageError = 'Não foi possível criar o ficheiro de produtos.';
        }
    }

    if ($storageError === null && !file_exists($historyFile)) {
        if (!inventory_write_json_file($historyFile, [])) {
            $storageError = 'Não foi possível criar o ficheiro de histórico.';
        }
    }

    return [$dataFile, $historyFile, $storageError];
}

function inventory_load_products(string $filePath): array
{
    if (!file_exists($filePath)) {
        return [];
    }

    $content = file_get_contents($filePath);
    if ($content === false || trim($content) === '') {
        return [];
    }

    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

function inventory_save_products(string $filePath, array $products): bool
{
    return inventory_write_json_file($filePath, array_values($products));
}

function inventory_load_history(string $filePath): array
{
    if (!file_exists($filePath)) {
        return [];
    }

    $content = file_get_contents($filePath);
    if ($content === false || trim($content) === '') {
        return [];
    }

    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

function inventory_save_history(string $filePath, array $entries): bool
{
    return inventory_write_json_file($filePath, array_values($entries));
}

function inventory_add_history_event(string $filePath, array $event): bool
{
    $history = inventory_load_history($filePath);
    array_unshift($history, $event);
    return inventory_save_history($filePath, $history);
}

function inventory_normalize_status(string $value): string
{
    $allowed = ['ok', 'attention', 'reserve'];
    return in_array($value, $allowed, true) ? $value : 'ok';
}

function inventory_badge_by_status(string $status): array
{
    return match ($status) {
        'attention' => ['Atenção', 'bg-warning-subtle text-warning border border-warning-subtle'],
        'reserve' => ['Reserva', 'bg-primary-subtle text-primary border border-primary-subtle'],
        default => ['OK', 'bg-success-subtle text-success border border-success-subtle'],
    };
}

function inventory_find_product(array $products, string $id): ?array
{
    foreach ($products as $product) {
        if (($product['id'] ?? '') === $id) {
            return $product;
        }
    }

    return null;
}

function inventory_history_label(string $action): string
{
    return match ($action) {
        'create' => 'Produto criado',
        'update' => 'Produto atualizado',
        'delete' => 'Produto removido',
        default => 'Alteração',
    };
}

function inventory_history_icon(string $action): string
{
    return match ($action) {
        'create' => 'add_circle',
        'update' => 'edit',
        'delete' => 'delete',
        default => 'history',
    };
}
