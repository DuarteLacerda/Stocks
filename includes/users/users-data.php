<?php

function users_use_file_locks(): bool
{
    $value = getenv('STOCKS_USE_FILE_LOCKS');
    if (!is_string($value) || $value === '') {
        return false;
    }

    $normalized = strtolower(trim($value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function users_write_json_file(string $filePath, array $payload): bool
{
    $flags = users_use_file_locks() ? LOCK_EX : 0;
    return file_put_contents($filePath, json_encode($payload, JSON_UNESCAPED_UNICODE), $flags) !== false;
}

function users_password_cost(): int
{
    $cost = (int) (getenv('STOCKS_APP_PASSWORD_COST') ?: 4);
    return max(4, min(12, $cost));
}

function users_password_hash(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => users_password_cost()]);
}

function users_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function users_is_valid_email(string $email): bool
{
    $normalized = users_normalize_email($email);
    return $normalized !== '' && filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false;
}

function users_data_file_path(): string
{
    return dirname(__DIR__, 2) . '/data/users.json';
}

function users_avatar_relative_dir(): string
{
    return 'uploads/avatars';
}

function users_avatar_storage_dir(): string
{
    return dirname(__DIR__, 2) . '/' . users_avatar_relative_dir();
}

function users_normalize_avatar_path(string $avatarPath): string
{
    $normalized = str_replace('\\', '/', trim($avatarPath));
    if ($normalized === '') {
        return '';
    }

    $prefix = users_avatar_relative_dir() . '/';
    if (strpos($normalized, $prefix) !== 0) {
        return '';
    }

    $fileName = basename($normalized);
    if ($fileName === '' || $fileName === '.' || $fileName === '..') {
        return '';
    }

    return $prefix . $fileName;
}

function users_avatar_url(?array $user): string
{
    if (!is_array($user)) {
        return '';
    }

    return users_normalize_avatar_path((string) ($user['avatar_path'] ?? ''));
}

function users_bootstrap(): array
{
    $dataDir = dirname(__DIR__, 2) . '/data';
    $usersFile = users_data_file_path();
    $storageError = null;

    if (!is_dir($dataDir) && !mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
        $storageError = 'Nao foi possivel criar o diretorio de dados de utilizadores.';
    }

    if ($storageError === null && !file_exists($usersFile)) {
        $adminUser = getenv('STOCKS_APP_USER');
        $adminEmail = getenv('STOCKS_APP_EMAIL');
        $adminPass = getenv('STOCKS_APP_PASS');
        $adminPassHash = getenv('STOCKS_APP_PASS_HASH');

        $resolvedUser = is_string($adminUser) && trim($adminUser) !== '' ? trim($adminUser) : 'admin';
        $resolvedEmail = is_string($adminEmail) && trim($adminEmail) !== '' ? users_normalize_email($adminEmail) : 'admin@local.test';
        $resolvedPass = is_string($adminPass) && $adminPass !== '' ? $adminPass : 'admin123';
        $resolvedHash = is_string($adminPassHash) && $adminPassHash !== ''
            ? $adminPassHash
            : users_password_hash($resolvedPass);

        $seedUsers = [[
            'id' => 'u' . str_replace('.', '', uniqid('', true)),
            'username' => $resolvedUser,
            'email' => $resolvedEmail,
            'name' => 'Administrador',
            'role' => 'admin',
            'password_hash' => $resolvedHash,
            'active' => true,
            'created_at' => date('c'),
        ]];

        if (!users_write_json_file($usersFile, $seedUsers)) {
            $storageError = 'Nao foi possivel criar o ficheiro de utilizadores.';
        }
    }

    return [$usersFile, $storageError];
}

function users_load(string $usersFile): array
{
    if (!file_exists($usersFile)) {
        return [];
    }

    $content = file_get_contents($usersFile);
    if ($content === false || trim($content) === '') {
        return [];
    }

    $decoded = json_decode($content, true);
    if (!is_array($decoded)) {
        return [];
    }

    foreach ($decoded as &$user) {
        if (!is_array($user)) {
            continue;
        }

        $email = (string) ($user['email'] ?? '');
        if ($email === '') {
            $username = (string) ($user['username'] ?? '');
            if (users_is_valid_email($username)) {
                $user['email'] = users_normalize_email($username);
            }
            continue;
        }

        $user['email'] = users_normalize_email($email);
    }
    unset($user);

    return $decoded;
}

function users_save(string $usersFile, array $users): bool
{
    return users_write_json_file($usersFile, array_values($users));
}

function users_normalize_role(string $role): string
{
    $normalized = strtolower(trim($role));
    return in_array($normalized, ['admin', 'employee'], true) ? $normalized : 'employee';
}

function users_find_by_id(array $users, string $id): ?array
{
    foreach ($users as $user) {
        if (($user['id'] ?? '') === $id) {
            return $user;
        }
    }

    return null;
}

function users_find_by_username(array $users, string $username): ?array
{
    $needle = strtolower(trim($username));
    foreach ($users as $user) {
        $candidate = strtolower(trim((string) ($user['username'] ?? '')));
        if ($candidate !== '' && $candidate === $needle) {
            return $user;
        }
    }

    return null;
}

function users_find_by_email(array $users, string $email): ?array
{
    $needle = users_normalize_email($email);
    if ($needle === '') {
        return null;
    }

    foreach ($users as $user) {
        $candidate = users_normalize_email((string) ($user['email'] ?? ''));
        if ($candidate !== '' && $candidate === $needle) {
            return $user;
        }
    }

    return null;
}

function users_find_by_login_identifier(array $users, string $identifier): ?array
{
    $needle = strtolower(trim($identifier));
    if ($needle === '') {
        return null;
    }

    foreach ($users as $user) {
        $username = strtolower(trim((string) ($user['username'] ?? '')));
        $name = strtolower(trim((string) ($user['name'] ?? '')));
        if (($username !== '' && $username === $needle) || ($name !== '' && $name === $needle)) {
            return $user;
        }
    }

    return null;
}

function users_role_label(string $role): string
{
    return $role === 'admin' ? 'Administrador' : 'Funcionario';
}
