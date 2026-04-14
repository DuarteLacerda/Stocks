<?php
require_once __DIR__ . '/../../includes/users/users-data.php';

[$usersFile, $storageError] = users_bootstrap();
$users = $storageError === null ? users_load($usersFile) : [];
$csrfToken = stocks_auth_csrf_token();
$flashMessage = null;
$currentUserId = stocks_auth_user_id();

$id = trim((string) ($_POST['id'] ?? ($_GET['id'] ?? '')));
$account = $id !== '' ? users_find_by_id($users, $id) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $storageError === null && $account !== null) {
    if (!stocks_auth_verify_csrf($_POST['csrf_token'] ?? null)) {
        $flashMessage = 'Pedido invalido. Atualiza a pagina e tenta novamente.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = users_normalize_email((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = users_normalize_role((string) ($_POST['role'] ?? 'employee'));
        $active = (string) ($_POST['active'] ?? '1') === '1';

        if ($name === '' || $username === '' || $email === '') {
            $flashMessage = 'Preenche os campos obrigatorios.';
        } elseif (!users_is_valid_email($email)) {
            $flashMessage = 'Indica um email valido.';
        } else {
            $existing = users_find_by_username($users, $username);
            $existingEmail = users_find_by_email($users, $email);
            if ($existing !== null && (string) ($existing['id'] ?? '') !== $id) {
                $flashMessage = 'Esse utilizador ja esta em uso.';
            } elseif ($existingEmail !== null && (string) ($existingEmail['id'] ?? '') !== $id) {
                $flashMessage = 'Esse email ja esta em uso.';
            } elseif ($id === $currentUserId && $role !== 'admin') {
                $flashMessage = 'Nao podes retirar perfil admin da tua propria conta.';
            } elseif ($id === $currentUserId && !$active) {
                $flashMessage = 'Nao podes desativar a tua propria conta.';
            } else {
                foreach ($users as &$item) {
                    if ((string) ($item['id'] ?? '') === $id) {
                        $item['name'] = $name;
                        $item['username'] = $username;
                        $item['email'] = $email;
                        $item['role'] = $role;
                        $item['active'] = $active;
                        if ($password !== '') {
                            if (strlen($password) < 8) {
                                $flashMessage = 'A nova palavra-pass deve ter pelo menos 8 caracteres.';
                            } else {
                                $item['password_hash'] = users_password_hash($password);
                            }
                        }
                        break;
                    }
                }
                unset($item);

                if ($flashMessage === null) {
                    if (!users_save($usersFile, $users)) {
                        $flashMessage = 'Erro ao gravar utilizadores.';
                    } else {
                        stocks_redirect('?page=users&msg=' . urlencode('Conta atualizada com sucesso.'));
                    }
                }
            }
        }
    }

    $account = users_find_by_id($users, $id);
}
?>

<div class="row g-3">
    <div class="col-12">
        <div class="metric-card dashboard-panel">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                <div>
                    <span class="dashboard-kicker">Administração</span>
                    <h1 class="dashboard-title mb-2">Editar Funcionário</h1>
                    <p class="dashboard-copy mb-0">Atualiza perfil, estado e palavra-pass da conta selecionada.</p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="?page=users">Voltar</a>
            </div>

            <?php if ($storageError !== null): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($storageError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php elseif ($account === null): ?>
                <div class="alert alert-warning" role="alert">Conta nao encontrada.</div>
            <?php endif; ?>

            <?php if ($flashMessage !== null): ?>
                <div class="alert alert-warning" role="alert"><?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($account !== null): ?>
                <form method="post" class="row g-3" data-submit-lock>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="col-md-6">
                        <label class="form-label">Nome</label>
                        <input class="form-control" name="name" required value="<?php echo htmlspecialchars((string) ($account['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Utilizador</label>
                        <input class="form-control" name="username" required value="<?php echo htmlspecialchars((string) ($account['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input class="form-control" name="email" type="email" required value="<?php echo htmlspecialchars((string) ($account['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nova palavra-pass (opcional)</label>
                        <input class="form-control" name="password" type="password" minlength="8" placeholder="Deixa vazio para manter">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Perfil</label>
                        <select class="form-select" name="role">
                            <option value="employee" <?php echo users_normalize_role((string) ($account['role'] ?? 'employee')) === 'employee' ? 'selected' : ''; ?>>Funcionario</option>
                            <option value="admin" <?php echo users_normalize_role((string) ($account['role'] ?? 'employee')) === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="active">
                            <option value="1" <?php echo ((bool) ($account['active'] ?? true)) ? 'selected' : ''; ?>>Ativa</option>
                            <option value="0" <?php echo !((bool) ($account['active'] ?? true)) ? 'selected' : ''; ?>>Inativa</option>
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