<div class="login-page">
    <div class="login-card">
        <?php $loginWasSubmitted = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string) ($_POST['action'] ?? '') === 'login'; ?>
        <?php $loginPrefillUsername = trim((string) ($loginLastIdentifier ?? ($_POST['username'] ?? ''))); ?>
        <?php $loginErrorText = trim((string) ($loginError ?? '')); ?>
        <?php $loginInfoText = trim((string) ($loginInfo ?? '')); ?>
        <?php $loginStatusText = trim((string) ($loginStatus ?? '')); ?>
        <?php if ($loginWasSubmitted && $loginErrorText === '' && $loginInfoText === '' && $loginStatusText === '') {
            $fallbackIdentifier = trim((string) ($_POST['username'] ?? $loginPrefillUsername));
            $fallbackLockoutSeconds = stocks_auth_lockout_remaining($fallbackIdentifier);

            if (stocks_auth_is_manually_locked($fallbackIdentifier)) {
                $loginErrorText = 'Conta bloqueada pelo administrador. Contacta um administrador para desbloquear.';
            } elseif ($fallbackLockoutSeconds > 0) {
                $loginErrorText = 'Muitas tentativas falhadas. Tenta novamente em ' . $fallbackLockoutSeconds . ' segundos.';
            } else {
                $fallbackAttemptsLeft = stocks_auth_attempts_remaining($fallbackIdentifier);
                if ($fallbackAttemptsLeft <= 0) {
                    $loginErrorText = 'Credenciais inválidas. Nova tentativa pode bloquear temporariamente a conta.';
                } else {
                    $loginErrorText = 'Credenciais inválidas. Restam ' . $fallbackAttemptsLeft . ' tentativas antes de bloqueio temporário.';
                }
            }
        } ?>
        <div class="login-brand mb-4">
            <div class="login-brand-main">
                <img class="login-logo" src="assets/logo.svg" alt="Logotipo">
                <div>
                    <span class="dashboard-kicker">Acesso seguro</span>
                    <h1 class="login-title mb-0">Iniciar sessao no painel</h1>
                </div>
            </div>
            <label class="switch login-theme-toggle" aria-label="Alternar tema claro/escuro" title="Alternar tema">
                <input id="themeToggle" type="checkbox" role="switch" aria-label="Alternar tema claro/escuro">
                <span class="slider" aria-hidden="true"></span>
            </label>
        </div>

        <?php if ($loginErrorText !== ''): ?>
            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($loginErrorText, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($loginInfoText !== ''): ?>
            <div class="alert alert-warning" role="alert"><?php echo htmlspecialchars($loginInfoText, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($loginStatusText !== ''): ?>
            <div class="alert alert-info" role="alert"><?php echo htmlspecialchars($loginStatusText, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <section class="login-section">
            <h2 class="login-section-title">
                <span class="material-symbols-outlined" aria-hidden="true">login</span>
                Iniciar sessao
            </h2>

            <form method="post" action="?page=login" class="login-form" data-submit-lock>
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(stocks_auth_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                <div class="mb-3">
                    <label for="username" class="form-label">Utilizador</label>
                    <input id="username" name="username" type="text" class="form-control" autocomplete="username" required value="<?php echo htmlspecialchars($loginPrefillUsername, ENT_QUOTES, 'UTF-8'); ?>" autofocus>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Palavra-pass</label>
                    <input id="password" name="password" type="password" class="form-control" autocomplete="current-password" required>
                </div>

                <button type="submit" class="btn btn-primary w-100" data-loading-text="A iniciar sessao...">Iniciar sessao</button>
            </form>

            <a class="login-reset-link" href="?page=forgot-password">Esqueci-me da palavra-pass</a>
        </section>

        <!-- Default credentials hint
        <p class="login-hint mt-3 mb-0">Credenciais por defeito: <strong>admin</strong> / <strong>admin123</strong>. Em produção, define <code>STOCKS_APP_USER</code> e <code>STOCKS_APP_PASS_HASH</code> (ou <code>STOCKS_APP_PASS</code>). Para ambiente local, podes afinar <code>STOCKS_APP_PASSWORD_COST</code>.</p>
        -->

        <?php if (!empty($loginLockoutSeconds)): ?>
            <p class="login-hint mt-2 mb-0">Este utilizador está temporariamente bloqueado. Podes tentar com outro utilizador.</p>
        <?php endif; ?>
    </div>
</div>