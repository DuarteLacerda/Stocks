<?php
$resetToken = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$resetError = '';
$resetSuccess = '';
$tokenData = $resetToken !== '' ? stocks_auth_validate_password_reset_token($resetToken) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset-password') {
    $newPassword = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['password_confirm'] ?? '');

    if (!stocks_auth_verify_csrf($_POST['csrf_token'] ?? null)) {
        $resetError = 'Pedido invalido. Atualiza a pagina e tenta novamente.';
    } elseif ($resetToken === '' || !is_array($tokenData)) {
        $resetError = 'Link de redefinicao invalido ou expirado.';
    } elseif (strlen($newPassword) < 8) {
        $resetError = 'A palavra-pass deve ter pelo menos 8 caracteres.';
    } elseif (!hash_equals($newPassword, $confirmPassword)) {
        $resetError = 'A confirmacao de palavra-pass nao corresponde.';
    } elseif (!stocks_auth_reset_password_with_token($resetToken, $newPassword)) {
        $resetError = 'Nao foi possivel concluir a redefinicao. Pede um novo link.';
    } else {
        $resetSuccess = 'Palavra-pass atualizada com sucesso. Ja podes iniciar sessao.';
        $tokenData = null;
    }
}
?>

<div class="login-page">
    <div class="login-card">
        <div class="login-brand mb-4">
            <img class="login-logo" src="assets/logo.svg" alt="Logotipo">
            <div>
                <span class="dashboard-kicker">Seguranca</span>
                <h1 class="login-title mb-0">Redefinir palavra-pass</h1>
            </div>
        </div>

        <?php if ($resetError !== ''): ?>
            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($resetError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($resetSuccess !== ''): ?>
            <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($resetSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
            <a class="btn btn-primary w-100" href="?page=login">Voltar a iniciar sessao</a>
        <?php elseif (is_array($tokenData)): ?>
            <div class="alert alert-info" role="alert">
                Link valido para o utilizador <strong><?php echo htmlspecialchars((string) ($tokenData['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>.
            </div>

            <form method="post" action="?page=reset-password" class="login-form" data-submit-lock>
                <input type="hidden" name="action" value="reset-password">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($resetToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(stocks_auth_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                <div class="mb-3">
                    <label for="password" class="form-label">Nova palavra-pass</label>
                    <input id="password" name="password" type="password" class="form-control" minlength="8" required autofocus>
                </div>

                <div class="mb-3">
                    <label for="password_confirm" class="form-label">Confirmar nova palavra-pass</label>
                    <input id="password_confirm" name="password_confirm" type="password" class="form-control" minlength="8" required>
                </div>

                <button type="submit" class="btn btn-primary w-100" data-loading-text="A atualizar...">Atualizar palavra-pass</button>
            </form>
        <?php else: ?>
            <div class="alert alert-warning" role="alert">Link de redefinicao invalido ou expirado.</div>
            <a class="btn btn-outline-secondary w-100" href="?page=login">Voltar a iniciar sessao</a>
        <?php endif; ?>
    </div>
</div>