<?php

$resetRequestError = '';
$resetRequestInfo = '';
$resetEmailPrefill = trim((string) ($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'request-reset') {
    $postedEmail = users_normalize_email((string) ($_POST['email'] ?? ''));
    $resetEmailPrefill = $postedEmail;

    if (!stocks_auth_verify_csrf($_POST['csrf_token'] ?? null)) {
        $resetRequestError = 'Pedido invalido. Atualiza a pagina e tenta novamente.';
    } elseif ($postedEmail === '') {
        $resetRequestError = 'Indica o email associado a conta.';
    } elseif (!users_is_valid_email($postedEmail)) {
        $resetRequestError = 'Indica um email valido.';
    } elseif (stocks_auth_password_reset_is_rate_limited($postedEmail)) {
        $resetRequestInfo = 'Muitos pedidos em pouco tempo. Tenta novamente em alguns minutos.';
        stocks_auth_audit_log('password_reset_request_rate_limited', $postedEmail, []);
    } else {
        stocks_auth_password_reset_register_request($postedEmail);
        $tokenData = stocks_auth_create_password_reset_token($postedEmail, 1800);
        if (is_array($tokenData) && isset($tokenData['token'])) {
            stocks_auth_audit_log('password_reset_redirected', (string) ($tokenData['username'] ?? ''), ['email' => $postedEmail]);
            stocks_redirect('?page=reset-password&token=' . urlencode((string) $tokenData['token']));
        } else {
            stocks_auth_audit_log('password_reset_requested_unknown_email', $postedEmail, []);
            $resetRequestError = 'Nao foi possivel iniciar a redefinicao para este email.';
        }
    }
}
?>

<div class="login-page">
    <div class="login-card">
        <div class="login-brand mb-4">
            <img class="login-logo" src="assets/logo.svg" alt="Logotipo">
            <div>
                <span class="dashboard-kicker">Recuperacao</span>
                <h1 class="login-title mb-0">Esqueci-me da palavra-pass</h1>
            </div>
        </div>

        <?php if ($resetRequestError !== ''): ?>
            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($resetRequestError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($resetRequestInfo !== ''): ?>
            <div class="alert alert-info" role="alert"><?php echo htmlspecialchars($resetRequestInfo, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <section class="login-section login-section-muted">
            <h2 class="login-section-title">
                <span class="material-symbols-outlined" aria-hidden="true">key</span>
                Recuperar acesso
            </h2>
            <p class="login-section-copy">Insere o email da conta e seras encaminhado para redefinir a palavra-pass.</p>

            <form method="post" action="?page=forgot-password" class="login-form" data-submit-lock>
                <input type="hidden" name="action" value="request-reset">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(stocks_auth_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                <div class="mb-3">
                    <label for="reset-email" class="form-label">Email</label>
                    <input id="reset-email" name="email" type="email" class="form-control" autocomplete="email" value="<?php echo htmlspecialchars($resetEmailPrefill, ENT_QUOTES, 'UTF-8'); ?>" required autofocus>
                </div>

                <button type="submit" class="btn btn-outline-secondary w-100" data-loading-text="A recuperar...">Recuperar palavra-pass</button>
            </form>
        </section>

        <div class="login-divider"></div>

        <section class="login-section">
            <h2 class="login-section-title">
                <span class="material-symbols-outlined" aria-hidden="true">info</span>
                Como funciona
            </h2>
            <p class="login-section-copy mb-0">Depois de validares o pedido, poderas definir uma nova palavra-pass e voltar a iniciar sessao no painel.</p>
        </section>

        <a class="login-reset-link" href="?page=login">Voltar a iniciar sessao</a>
    </div>
</div>