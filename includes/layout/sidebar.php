<div class="sidebar" id="sidebar">
    <div class="logo">
        <div class="logo-mark" aria-hidden="true">
            <img class="logo-symbol" src="assets/logo.svg" alt="" aria-hidden="true" />
        </div>
        <div class="logo-text">
            <strong>Controlo Stock</strong><br>
            <small>Painel Tecnico</small>
        </div>

        <button class="btn sidebar-close d-md-none" type="button" aria-label="Fechar menu">
            <span class="material-symbols-outlined">close</span>
        </button>
    </div>

    <a href="?page=dashboard" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" aria-current="<?php echo $currentPage === 'dashboard' ? 'page' : 'false'; ?>">
        <span class="material-symbols-outlined">dashboard</span>
        Painel
    </a>
    <a href="?page=inventory" class="<?php echo $currentPage === 'inventory' ? 'active' : ''; ?>" aria-current="<?php echo $currentPage === 'inventory' ? 'page' : 'false'; ?>">
        <span class="material-symbols-outlined">inventory_2</span>
        Inventario
    </a>
    <a href="?page=history" class="<?php echo $currentPage === 'history' ? 'active' : ''; ?>" aria-current="<?php echo $currentPage === 'history' ? 'page' : 'false'; ?>">
        <span class="material-symbols-outlined">history</span>
        Historico
    </a>
    <a href="?page=settings" class="<?php echo $currentPage === 'settings' ? 'active' : ''; ?>" aria-current="<?php echo $currentPage === 'settings' ? 'page' : 'false'; ?>">
        <span class="material-symbols-outlined">settings</span>
        Definicoes
    </a>
    <?php if (stocks_auth_is_admin()): ?>
        <a href="?page=users" class="<?php echo $currentPage === 'users' || $currentPage === 'user-create' || $currentPage === 'user-edit' ? 'active' : ''; ?>" aria-current="<?php echo $currentPage === 'users' || $currentPage === 'user-create' || $currentPage === 'user-edit' ? 'page' : 'false'; ?>">
            <span class="material-symbols-outlined">groups</span>
            Funcionarios
        </a>

        <a href="?page=audit" class="<?php echo $currentPage === 'audit' ? 'active' : ''; ?>" aria-current="<?php echo $currentPage === 'audit' ? 'page' : 'false'; ?>">
            <span class="material-symbols-outlined">shield_lock</span>
            Auditoria
        </a>
    <?php endif; ?>

    <div class="mt-auto">
        <div class="sidebar-theme-toggle" title="Mudar tema">
            <label class="switch" aria-label="Alternar tema claro/escuro">
                <input id="themeToggle" type="checkbox" role="switch" aria-label="Alternar tema claro/escuro">
                <span class="slider" aria-hidden="true"></span>
            </label>
        </div>

        <a class="new-item-btn w-100" href="?page=inventory-create" aria-label="Novo item">
            <span class="material-symbols-outlined new-item-icon" aria-hidden="true">add</span>
            <span class="new-item-label">Novo Produto</span>
        </a>

        <?php if (stocks_auth_is_admin()): ?>
            <a class="new-item-btn w-100" href="?page=user-create" aria-label="Novo funcionario">
                <span class="material-symbols-outlined new-item-icon" aria-hidden="true">person_add</span>
                <span class="new-item-label">Novo Funcionario</span>
            </a>
        <?php endif; ?>
    </div>
</div>