<?php
require_once __DIR__ . '/../../includes/users/users-data.php';

[$usersFile, $storageError] = users_bootstrap();
$users = $storageError === null ? users_load($usersFile) : [];
$csrfToken = stocks_auth_csrf_token();
$flashMessage = trim((string) ($_SESSION['stocks_users_flash_message'] ?? ''));
unset($_SESSION['stocks_users_flash_message']);
$currentUserId = stocks_auth_user_id();

$setUsersFlash = static function (string $message): void {
    $_SESSION['stocks_users_flash_message'] = $message;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $storageError === null) {
    if (!stocks_auth_verify_csrf($_POST['csrf_token'] ?? null)) {
        $setUsersFlash('Pedido invalido. Atualiza a pagina e tenta novamente.');
        stocks_redirect('?page=users');
    }

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'delete') {
        $id = trim((string) ($_POST['id'] ?? ''));
        if ($id === '' || $id === $currentUserId) {
            $setUsersFlash('Nao podes remover a tua propria conta.');
            stocks_redirect('?page=users');
        }

        $target = users_find_by_id($users, $id);
        $targetUsername = is_array($target) ? (string) ($target['username'] ?? '') : '';

        $beforeCount = count($users);
        $users = array_values(array_filter($users, static fn(array $item): bool => (string) ($item['id'] ?? '') !== $id));

        if (count($users) === $beforeCount) {
            $setUsersFlash('Utilizador nao encontrado.');
            stocks_redirect('?page=users');
        }

        if (!users_save($usersFile, $users)) {
            $setUsersFlash('Erro ao gravar utilizadores.');
            stocks_redirect('?page=users');
        }

        stocks_auth_audit_log('user_deleted', $targetUsername, ['target_id' => $id]);
        $setUsersFlash('Conta removida com sucesso.');
        stocks_redirect('?page=users');
    } elseif ($action === 'unlock') {
        $id = trim((string) ($_POST['id'] ?? ''));
        $account = $id !== '' ? users_find_by_id($users, $id) : null;

        if (!is_array($account)) {
            $setUsersFlash('Utilizador nao encontrado.');
            stocks_redirect('?page=users');
        }

        $username = trim((string) ($account['username'] ?? ''));
        if ($username === '') {
            $setUsersFlash('Utilizador sem identificador valido.');
            stocks_redirect('?page=users');
        }

        stocks_auth_set_manual_lock($username, false);
        stocks_auth_clear_failed_attempts($username);
        stocks_auth_audit_log('account_unlocked', $username, ['mode' => 'manual']);
        $setUsersFlash('Conta desbloqueada com sucesso.');
        stocks_redirect('?page=users');
    } elseif ($action === 'lock') {
        $id = trim((string) ($_POST['id'] ?? ''));
        if ($id === '' || $id === $currentUserId) {
            $setUsersFlash('Nao podes bloquear a tua propria conta.');
            stocks_redirect('?page=users');
        }

        $account = users_find_by_id($users, $id);
        if (!is_array($account)) {
            $setUsersFlash('Utilizador nao encontrado.');
            stocks_redirect('?page=users');
        }

        $username = trim((string) ($account['username'] ?? ''));
        if ($username === '') {
            $setUsersFlash('Utilizador sem identificador valido.');
            stocks_redirect('?page=users');
        }

        if (!stocks_auth_set_manual_lock($username, true)) {
            $setUsersFlash('Nao foi possivel bloquear a conta.');
            stocks_redirect('?page=users');
        }

        stocks_auth_audit_log('account_locked', $username, ['mode' => 'manual']);
        $setUsersFlash('Conta bloqueada sem timeout.');
        stocks_redirect('?page=users');
    }
}
?>

<div class="row g-3">
    <div class="col-12">
        <div class="metric-card dashboard-panel">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                <div>
                    <span class="dashboard-kicker">Administração</span>
                    <h1 class="dashboard-title mb-2">Gestão de Funcionários</h1>
                    <p class="dashboard-copy mb-0">Cria, edita e remove contas com controlo por perfil.</p>
                </div>
                <a class="btn btn-primary btn-sm" href="?page=user-create">Novo Funcionário</a>
            </div>

            <?php if ($storageError !== null): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($storageError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($flashMessage !== ''): ?>
                <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Utilizador</th>
                            <th>Email</th>
                            <th>Perfil</th>
                            <th>Estado</th>
                            <th>Bloqueio</th>
                            <th>Criado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) === 0): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Sem contas registadas.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($users as $account): ?>
                            <?php
                            $id = (string) ($account['id'] ?? '');
                            $role = users_normalize_role((string) ($account['role'] ?? 'employee'));
                            $isActive = (bool) ($account['active'] ?? true);
                            $username = trim((string) ($account['username'] ?? ''));
                            $isManualLocked = $username !== '' ? stocks_auth_is_manually_locked($username) : false;
                            $lockSeconds = $username !== '' ? stocks_auth_lockout_remaining($username) : 0;
                            $isLocked = $isManualLocked || $lockSeconds > 0;
                            $createdAt = (string) ($account['created_at'] ?? '');
                            $createdLabel = $createdAt !== '' ? date('d/m/Y H:i', strtotime($createdAt)) : '-';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) ($account['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($account['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($account['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?php echo htmlspecialchars(users_role_label($role), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td>
                                    <?php if ($isActive): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Ativa</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inativa</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span
                                        class="badge <?php echo $isLocked ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-success-subtle text-success border border-success-subtle'; ?>"
                                        data-lock-badge
                                        data-lock-seconds="<?php echo (int) $lockSeconds; ?>"
                                        data-lock-mode="<?php echo $isManualLocked ? 'manual' : ($lockSeconds > 0 ? 'timed' : 'free'); ?>">
                                        <?php if ($isManualLocked): ?>
                                            Bloqueada (manual)
                                        <?php elseif ($lockSeconds > 0): ?>
                                            Bloqueada (<?php echo (int) $lockSeconds; ?>s)
                                        <?php else: ?>
                                            Desbloqueada
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($createdLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <a class="btn btn-sm btn-link text-decoration-none p-0 d-inline-flex align-items-center inventory-edit-toggle" href="?page=user-edit&id=<?php echo urlencode($id); ?>" title="Editar" aria-label="Editar">
                                        <span class="material-symbols-outlined">edit</span>
                                    </a>

                                    <?php if ($id !== $currentUserId): ?>
                                        <form method="post" class="mt-2 d-inline-block <?php echo $isLocked ? '' : 'd-none'; ?>" data-submit-lock data-unlock-form>
                                            <input type="hidden" name="action" value="unlock">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <button class="btn btn-sm btn-link text-warning p-0 d-inline-flex align-items-center unlock-btn" type="submit" data-loading-text="A desbloquear..." title="Desbloquear" aria-label="Desbloquear">
                                                <span class="material-symbols-outlined">lock_open</span>
                                            </button>
                                        </form>

                                        <form method="post" class="mt-2 d-inline-block <?php echo $isLocked ? 'd-none' : ''; ?>" data-submit-lock data-block-form>
                                            <input type="hidden" name="action" value="lock">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <button class="btn btn-sm btn-link text-danger p-0 d-inline-flex align-items-center lock-btn" type="submit" data-loading-text="A bloquear..." title="Bloquear" aria-label="Bloquear">
                                                <span class="material-symbols-outlined">lock</span>
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                    <?php if ($id !== $currentUserId): ?>
                                        <form method="post" class="mt-2 d-inline-block" data-submit-lock>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <button class="btn btn-sm btn-link text-danger p-0 d-inline-flex align-items-center inventory-delete-btn" type="submit" data-loading-text="A apagar..." title="Apagar" aria-label="Apagar">
                                                <span class="material-symbols-outlined">delete</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        var badges = document.querySelectorAll('[data-lock-badge]');
        if (!badges.length) {
            return;
        }

        function renderBadge(badge, seconds) {
            var row = badge.closest('tr');
            var unlockForm = row ? row.querySelector('[data-unlock-form]') : null;
            var blockForm = row ? row.querySelector('[data-block-form]') : null;
            var mode = badge.getAttribute('data-lock-mode') || 'free';

            if (mode === 'manual') {
                badge.textContent = 'Bloqueada (manual)';
                badge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle';
                if (unlockForm) {
                    unlockForm.classList.remove('d-none');
                    unlockForm.style.display = 'inline-block';
                }
                if (blockForm) {
                    blockForm.classList.add('d-none');
                }
                return;
            }

            if (seconds > 0) {
                badge.textContent = 'Bloqueada (' + seconds + 's)';
                badge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle';
                if (unlockForm) {
                    unlockForm.classList.remove('d-none');
                    unlockForm.style.display = 'inline-block';
                }
                if (blockForm) {
                    blockForm.classList.add('d-none');
                }
                return;
            }

            badge.textContent = 'Desbloqueada';
            badge.className = 'badge bg-success-subtle text-success border border-success-subtle';
            badge.setAttribute('data-lock-mode', 'free');
            if (unlockForm) {
                unlockForm.classList.add('d-none');
            }
            if (blockForm) {
                blockForm.classList.remove('d-none');
                blockForm.style.display = 'inline-block';
            }
        }

        function tick() {
            for (var i = 0; i < badges.length; i++) {
                var badge = badges[i];
                var mode = badge.getAttribute('data-lock-mode') || 'free';
                var seconds = parseInt(badge.getAttribute('data-lock-seconds') || '0', 10);
                if (isNaN(seconds) || seconds < 0) {
                    seconds = 0;
                }

                if (mode !== 'manual' && seconds > 0) {
                    seconds -= 1;
                    badge.setAttribute('data-lock-seconds', String(seconds));
                }

                renderBadge(badge, seconds);
            }
        }

        tick();
        window.setInterval(tick, 1000);
    })();
</script>