<?php
require_once __DIR__ . '/../../includes/users/users-data.php';

[$usersFile, $storageError] = users_bootstrap();
$users = $storageError === null ? users_load($usersFile) : [];
$csrfToken = stocks_auth_csrf_token();
$flashMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $storageError === null) {
    if (!stocks_auth_verify_csrf($_POST['csrf_token'] ?? null)) {
        $flashMessage = 'Pedido invalido. Atualiza a pagina e tenta novamente.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = users_normalize_email((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = users_normalize_role((string) ($_POST['role'] ?? 'employee'));
        $active = (string) ($_POST['active'] ?? '1') === '1';

        if ($name === '' || $username === '' || $email === '' || $password === '') {
            $flashMessage = 'Preenche todos os campos obrigatorios.';
        } elseif (!users_is_valid_email($email)) {
            $flashMessage = 'Indica um email valido.';
        } elseif (strlen($password) < 8) {
            $flashMessage = 'A palavra-pass deve ter pelo menos 8 caracteres.';
        } elseif (users_find_by_username($users, $username) !== null) {
            $flashMessage = 'Esse utilizador ja existe.';
        } elseif (users_find_by_email($users, $email) !== null) {
            $flashMessage = 'Esse email ja esta associado a outra conta.';
        } else {
            $users[] = [
                'id' => 'u' . str_replace('.', '', uniqid('', true)),
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'password_hash' => users_password_hash($password),
                'active' => $active,
                'created_at' => date('c'),
            ];

            if (!users_save($usersFile, $users)) {
                $flashMessage = 'Erro ao gravar utilizadores.';
            } else {
                stocks_redirect('?page=users&msg=' . urlencode('Conta criada com sucesso.'));
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
                    <span class="dashboard-kicker">Administração</span>
                    <h1 class="dashboard-title mb-2">Criar Funcionário</h1>
                    <p class="dashboard-copy mb-0">Regista uma nova conta com perfil e acesso controlado.</p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="?page=users">Voltar</a>
            </div>

            <?php if ($storageError !== null): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($storageError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($flashMessage !== null): ?>
                <div class="alert alert-warning" role="alert"><?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" class="row g-3" data-submit-lock>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="col-md-6">
                    <label class="form-label">Nome</label>
                    <input class="form-control" name="name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Utilizador</label>
                    <input class="form-control" name="username" required autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input class="form-control" name="email" type="email" required autocomplete="email">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Palavra-pass</label>
                    <input class="form-control" name="password" type="password" minlength="8" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Perfil</label>
                    <select class="form-select" name="role">
                        <option value="employee">Funcionario</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="active">
                        <option value="1">Ativa</option>
                        <option value="0">Inativa</option>
                    </select>
                </div>
                <div class="col-md-3 d-grid align-items-end">
                    <label class="form-label invisible">Ações</label>
                    <button class="btn btn-primary" type="submit" data-loading-text="A criar...">Criar conta</button>
                </div>
            </form>
        </div>
    </div>
</div>