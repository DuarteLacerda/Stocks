<div class="topbar d-flex align-items-center justify-content-between px-4">
    <?php
    $topbarAccount = stocks_auth_current_user();
    $topbarAvatarPath = users_avatar_url($topbarAccount);
    $topbarDisplayName = stocks_auth_username();
    $topbarInitial = strtoupper(substr(trim($topbarDisplayName) !== '' ? trim($topbarDisplayName) : 'U', 0, 1));
    ?>

    <button class="btn sidebar-toggle" type="button" aria-label="Alternar menu" aria-controls="sidebar">
        <span class="material-symbols-outlined">menu</span>
    </button>

    <div class="topbar-search-wrap w-50">
        <input id="globalSearch" class="form-control search w-100" type="search" list="quickNavOptions" placeholder="Procurar no... (Ctrl+K)" aria-label="Pesquisa rapida e navegacao" autocomplete="off" spellcheck="false">
        <datalist id="quickNavOptions">
            <option value="Painel"></option>
            <option value="Inventario"></option>
            <option value="Criar Produto"></option>
            <option value="Historico"></option>
            <option value="Definicoes"></option>
            <option value="Perfil"></option>
            <?php if (stocks_auth_is_admin()): ?>
                <option value="Funcionarios"></option>
                <option value="Criar Funcionario"></option>
                <option value="Auditoria"></option>
            <?php endif; ?>
        </datalist>
        <p id="globalSearchNotice" class="search-notice" hidden></p>
    </div>
    <div class="topbar-actions d-flex align-items-center gap-2">
        <a href="?page=profile" class="topbar-profile-btn" aria-label="Abrir perfil" title="Perfil">
            <?php if ($topbarAvatarPath !== ''): ?>
                <img
                    src="<?php echo htmlspecialchars($topbarAvatarPath, ENT_QUOTES, 'UTF-8'); ?>"
                    class="topbar-profile-avatar"
                    alt="Foto de perfil de <?php echo htmlspecialchars($topbarDisplayName, ENT_QUOTES, 'UTF-8'); ?>">
            <?php else: ?>
                <span class="topbar-profile-fallback" aria-hidden="true"><?php echo htmlspecialchars($topbarInitial, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </a>
        <div class="fw-bold d-none d-md-block"><?php echo htmlspecialchars($topbarDisplayName, ENT_QUOTES, 'UTF-8'); ?></div>
        <form method="post" action="?page=<?php echo urlencode($currentPage); ?>" class="m-0">
            <input type="hidden" name="action" value="logout">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($authCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
                <span class="material-symbols-outlined" aria-hidden="true">logout</span>
                <span>Sair</span>
            </button>
        </form>
    </div>
</div>