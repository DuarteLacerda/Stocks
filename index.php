<?php
require_once __DIR__ . '/includes/auth/auth.php';

if (ob_get_level() === 0) {
    ob_start();
}

if (!function_exists('stocks_redirect')) {
    function stocks_redirect(string $url): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, 303);
            exit;
        }

        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        echo '<script>window.location.href=' . json_encode($url, JSON_UNESCAPED_UNICODE) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . $safeUrl . '"></noscript>';
        exit;
    }
}

stocks_auth_ensure_session();

$allowedPages = [
    'login' => [
        'file' => __DIR__ . '/pages/auth/login.php',
        'label' => 'Entrar',
    ],
    'reset-password' => [
        'file' => __DIR__ . '/pages/auth/reset-password.php',
        'label' => 'Redefinir Palavra-pass',
    ],
    'forgot-password' => [
        'file' => __DIR__ . '/pages/auth/forgot-password.php',
        'label' => 'Recuperar Palavra-pass',
    ],
    'dashboard' => [
        'file' => __DIR__ . '/pages/app/dashboard.php',
        'label' => 'Painel',
    ],
    'inventory' => [
        'file' => __DIR__ . '/pages/stock/inventory.php',
        'label' => 'Inventario',
    ],
    'inventory-create' => [
        'file' => __DIR__ . '/pages/stock/inventory-create.php',
        'label' => 'Criar Produto',
    ],
    'inventory-edit' => [
        'file' => __DIR__ . '/pages/stock/inventory-edit.php',
        'label' => 'Editar Produto',
    ],
    'history' => [
        'file' => __DIR__ . '/pages/stock/history.php',
        'label' => 'Historico',
    ],
    'settings' => [
        'file' => __DIR__ . '/pages/app/settings.php',
        'label' => 'Definicoes',
    ],
    'profile' => [
        'file' => __DIR__ . '/pages/app/profile.php',
        'label' => 'Perfil',
    ],
    'users' => [
        'file' => __DIR__ . '/pages/admin/users.php',
        'label' => 'Funcionarios',
    ],
    'user-create' => [
        'file' => __DIR__ . '/pages/admin/user-create.php',
        'label' => 'Criar Funcionario',
    ],
    'user-edit' => [
        'file' => __DIR__ . '/pages/admin/user-edit.php',
        'label' => 'Editar Funcionario',
    ],
    'audit' => [
        'file' => __DIR__ . '/pages/admin/audit.php',
        'label' => 'Auditoria',
    ],
];

$adminOnlyPages = ['users', 'user-create', 'user-edit', 'audit'];
$publicPages = ['login', 'reset-password', 'forgot-password'];

$currentPage = $_GET['page'] ?? 'dashboard';
if (!array_key_exists($currentPage, $allowedPages)) {
    $currentPage = 'dashboard';
}

$isAuthenticated = stocks_auth_is_authenticated();

if ($isAuthenticated && stocks_auth_enforce_idle_timeout()) {
    header('Location: ?page=login&expired=1');
    exit;
}

$isAuthenticated = stocks_auth_is_authenticated();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout' && $isAuthenticated) {
    if (stocks_auth_verify_csrf($_POST['csrf_token'] ?? null)) {
        stocks_auth_logout();
        header('Location: ?page=login');
        exit;
    }
}

if (!$isAuthenticated && !in_array($currentPage, $publicPages, true)) {
    header('Location: ?page=login');
    exit;
}

if (in_array($currentPage, $adminOnlyPages, true) && !stocks_auth_is_admin()) {
    header('Location: ?page=dashboard');
    exit;
}

$loginError = '';
$loginInfo = '';
$loginStatus = '';
$hadLoginAttempt = false;
$loginLastIdentifier = trim((string) ($_SESSION['stocks_auth_last_login_identifier'] ?? ''));
$loginLockoutSeconds = 0;

if ($currentPage === 'login') {
    $flashError = trim((string) ($_SESSION['stocks_login_flash_error'] ?? ''));
    $flashInfo = trim((string) ($_SESSION['stocks_login_flash_info'] ?? ''));
    $flashStatus = trim((string) ($_SESSION['stocks_login_flash_status'] ?? ''));

    if ($flashError !== '') {
        $loginError = $flashError;
    }
    if ($flashInfo !== '') {
        $loginInfo = $flashInfo;
    }
    if ($flashStatus !== '') {
        $loginStatus = $flashStatus;
    }

    unset(
        $_SESSION['stocks_login_flash_error'],
        $_SESSION['stocks_login_flash_info'],
        $_SESSION['stocks_login_flash_status']
    );
}

if ($currentPage === 'login' && $loginLastIdentifier !== '') {
    $loginLockoutSeconds = stocks_auth_lockout_remaining($loginLastIdentifier);
    $loginAttemptsLeft = stocks_auth_attempts_remaining($loginLastIdentifier);
    if (stocks_auth_is_manually_locked($loginLastIdentifier)) {
        $loginStatus = 'A conta "' . $loginLastIdentifier . '" está bloqueada manualmente pelo administrador.';
    } elseif ($loginLockoutSeconds > 0) {
        $loginStatus = 'O utilizador "' . $loginLastIdentifier . '" está bloqueado por ' . $loginLockoutSeconds . ' segundos.';
    } elseif ($loginAttemptsLeft > 0 && $loginAttemptsLeft < stocks_auth_config()['max_attempts']) {
        $loginStatus = 'Ainda restam ' . $loginAttemptsLeft . ' tentativas para "' . $loginLastIdentifier . '" antes de bloqueio temporário.';
    }
}

if ($currentPage === 'login' && (string) ($_GET['expired'] ?? '') === '1') {
    $loginInfo = 'Sessão terminada por inatividade. Inicia sessão novamente.';
}

if ($currentPage === 'login' && stocks_auth_is_local_request() && (string) ($_GET['dev_access'] ?? '') === '1') {
    if (stocks_auth_force_local_admin_login('admin', 'admin123')) {
        stocks_redirect('?page=dashboard');
    }
    $loginError = 'Recuperação local indisponível. Tenta novamente sem cache.';
}

if ($currentPage === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $hadLoginAttempt = true;
    $postedUsername = (string) ($_POST['username'] ?? '');
    $postedPassword = (string) ($_POST['password'] ?? '');
    $loginLastIdentifier = trim($postedUsername);
    $_SESSION['stocks_auth_last_login_identifier'] = $loginLastIdentifier;
    $loginLockoutSeconds = stocks_auth_lockout_remaining($postedUsername);

    $csrfValid = stocks_auth_verify_csrf($_POST['csrf_token'] ?? null);
    if (!$csrfValid && stocks_auth_is_local_request()) {
        // Local recovery mode: continue login attempt even if CSRF token is stale.
        $csrfValid = true;
        $loginInfo = 'Token de sessão expirado; validação CSRF ignorada apenas em ambiente local.';
    }

    if (!$csrfValid) {
        $loginError = 'Pedido inválido. Atualiza a página e tenta novamente.';
    } elseif (stocks_auth_force_local_admin_login($postedUsername, $postedPassword)) {
        stocks_redirect('?page=dashboard');
    } elseif ($loginLockoutSeconds > 0) {
        $loginError = 'Muitas tentativas falhadas. Tenta novamente em ' . $loginLockoutSeconds . ' segundos.';
    } elseif (!stocks_auth_login($postedUsername, $postedPassword)) {
        if (stocks_auth_is_manually_locked($postedUsername)) {
            $loginError = 'Conta bloqueada pelo administrador. Contacta um administrador para desbloquear.';
        } else {
            $remaining = stocks_auth_lockout_remaining($postedUsername);
            if ($remaining > 0) {
                $loginError = 'Muitas tentativas falhadas. Tenta novamente em ' . $remaining . ' segundos.';
            } else {
                $attemptsLeft = stocks_auth_attempts_remaining($postedUsername);
                if ($attemptsLeft <= 0) {
                    $loginError = 'Credenciais inválidas. Nova tentativa pode bloquear temporariamente a conta.';
                } else {
                    $loginError = 'Credenciais inválidas. Restam ' . $attemptsLeft . ' tentativas antes de bloqueio temporário.';
                }
            }
        }
    } else {
        stocks_redirect('?page=dashboard');
    }

    $loginLockoutSeconds = stocks_auth_lockout_remaining($postedUsername);
    if ($loginError === '' && $loginLockoutSeconds > 0) {
        $loginStatus = 'O utilizador "' . $loginLastIdentifier . '" está bloqueado por ' . $loginLockoutSeconds . ' segundos.';
    }
}

if ($currentPage === 'login' && $hadLoginAttempt && $loginError === '' && $loginInfo === '' && !stocks_auth_is_authenticated()) {
    $loginError = 'Não foi possível iniciar sessão. Verifica os dados e tenta novamente.';
}

if ($currentPage === 'login' && stocks_auth_is_authenticated()) {
    header('Location: ?page=dashboard');
    exit;
}

$currentPageFile = $allowedPages[$currentPage]['file'];
$siteName = 'Controlo Stock';
$pageTitle = $allowedPages[$currentPage]['label'] . ' | ' . $siteName;
$authCsrfToken = stocks_auth_csrf_token();
$showPageLoader = true;
$authLayoutPages = ['login', 'forgot-password', 'reset-password'];
$isAuthLayoutPage = in_array($currentPage, $authLayoutPages, true);

$renderPageSafely = static function (string $filePath): string {
    if (!is_file($filePath)) {
        $missingFile = htmlspecialchars(basename($filePath), ENT_QUOTES, 'UTF-8');
        return '<div class="alert alert-danger" role="alert"><strong>Erro de pagina:</strong> nao foi encontrado o ficheiro <code>' . $missingFile . '</code>.</div>';
    }

    ob_start();
    try {
        include $filePath;
    } catch (Throwable $exception) {
        ob_end_clean();
        $exceptionMessage = trim((string) $exception->getMessage());
        $message = $exceptionMessage !== '' ? $exceptionMessage : 'Erro interno sem detalhe adicional.';
        $fileName = htmlspecialchars(basename($filePath), ENT_QUOTES, 'UTF-8');
        return '<div class="alert alert-danger" role="alert"><strong>Erro ao carregar a pagina</strong> <code>' . $fileName . '</code>: ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    return ob_get_clean() ?: '<div class="alert alert-warning" role="alert">A pagina carregou sem conteudo renderizavel.</div>';
};
?>
<!DOCTYPE html>
<html lang="pt-PT">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#10161d">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>

    <?php if ($showPageLoader): ?>
        <script>
            document.documentElement.classList.add('page-loading');
        </script>
    <?php endif; ?>

    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('stocks.theme');
                if (savedTheme === 'dark' || savedTheme === 'light') {
                    document.documentElement.setAttribute('data-theme', savedTheme);
                    document.documentElement.setAttribute('data-bs-theme', savedTheme);
                } else {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    document.documentElement.setAttribute('data-bs-theme', 'dark');
                }
            } catch (error) {
                document.documentElement.setAttribute('data-theme', 'dark');
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            }
        })();
    </script>

    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="assets/favicon.ico">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <?php if ($showPageLoader): ?>
        <div class="page-loader" id="pageLoader" aria-hidden="true">
            <div class="page-loader-content" role="status" aria-live="polite">
                <div class="spinner" aria-hidden="true">
                    <div></div>
                    <div></div>
                    <div></div>
                    <div></div>
                    <div></div>
                    <div></div>
                </div>
            </div>
        </div>

        <script>
            (function() {
                var done = false;

                function forceHideLoader() {
                    if (done) {
                        return;
                    }

                    done = true;
                    var loader = document.getElementById('pageLoader');
                    document.documentElement.classList.remove('page-loading');

                    if (!loader) {
                        return;
                    }

                    loader.classList.add('is-hidden');
                    window.setTimeout(function() {
                        if (loader.parentNode) {
                            loader.parentNode.removeChild(loader);
                        }
                    }, 220);
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', forceHideLoader, {
                        once: true
                    });
                } else {
                    forceHideLoader();
                }

                window.addEventListener('pageshow', forceHideLoader, {
                    once: true
                });
                window.setTimeout(forceHideLoader, 1800);
            })();
        </script>
    <?php endif; ?>

    <?php if (!$isAuthLayoutPage): ?>
        <script>
            (function() {
                var mobileOpen = localStorage.getItem('stocks.sidebarOpen');
                var desktopCollapsed = localStorage.getItem('stocks.sidebarCollapsed');
                var isMobile = window.matchMedia('(max-width: 767.98px)').matches;
                if (mobileOpen !== 'false' && isMobile) {
                    document.body.classList.add('nav-open');
                }

                if (desktopCollapsed === 'true' && !isMobile) {
                    document.body.classList.add('nav-collapsed');
                }
            })();
        </script>

        <?php include __DIR__ . '/includes/layout/sidebar.php'; ?>
        <div class="nav-backdrop" aria-hidden="true"></div>

        <div class="main">
            <?php include __DIR__ . '/includes/layout/topbar.php'; ?>

            <div class="container-fluid p-4 page-content">
                <?php echo $renderPageSafely($currentPageFile); ?>
            </div>
        </div>
    <?php else: ?>
        <div class="container-fluid p-4 p-md-5 login-layout">
            <?php echo $renderPageSafely($currentPageFile); ?>
        </div>
    <?php endif; ?>

    <script>
        (function() {
            var loader = document.getElementById('pageLoader');
            var root = document.documentElement;
            var hasHidden = false;

            function hideLoader() {
                if (hasHidden) {
                    return;
                }

                hasHidden = true;
                root.classList.remove('page-loading');

                if (!loader) {
                    return;
                }

                loader.classList.add('is-hidden');
                window.setTimeout(function() {
                    if (loader && loader.parentNode) {
                        loader.parentNode.removeChild(loader);
                    }
                }, 280);
            }

            if (document.readyState === 'interactive' || document.readyState === 'complete') {
                hideLoader();
            } else {
                document.addEventListener('DOMContentLoaded', hideLoader, {
                    once: true
                });
            }

            window.addEventListener('load', hideLoader, {
                once: true
            });
            window.addEventListener('pageshow', hideLoader, {
                once: true
            });
        })();

        (function() {
            var themeToggle = document.getElementById('themeToggle');
            var themeMeta = document.querySelector('meta[name="theme-color"]');

            function currentTheme() {
                var value = document.documentElement.getAttribute('data-theme');
                return value === 'dark' ? 'dark' : 'light';
            }

            function syncThemeUi(theme) {
                document.documentElement.setAttribute('data-bs-theme', theme);

                if (themeToggle) {
                    themeToggle.checked = theme === 'light';
                }

                if (themeMeta) {
                    themeMeta.setAttribute('content', theme === 'dark' ? '#10161d' : '#f5f7f8');
                }
            }

            syncThemeUi(currentTheme());

            if (themeToggle) {
                themeToggle.addEventListener('change', function() {
                    var nextTheme = themeToggle.checked ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', nextTheme);
                    syncThemeUi(nextTheme);

                    try {
                        localStorage.setItem('stocks.theme', nextTheme);
                    } catch (error) {
                        // Ignore storage errors and keep current session theme.
                    }
                });
            }
        })();

        (function() {
            var searchInput = document.getElementById('globalSearch');
            if (!searchInput) {
                return;
            }

            var isAdmin = <?php echo stocks_auth_is_admin() ? 'true' : 'false'; ?>;

            var quickRoutes = [{
                    page: 'inventory',
                    url: '?page=inventory',
                    terms: ['inventario', 'stock', 'produtos', 'lista']
                },
                {
                    page: 'dashboard',
                    url: '?page=dashboard',
                    terms: ['painel', 'dashboard', 'inicio', 'home']
                },
                {
                    page: 'inventory-create',
                    url: '?page=inventory-create',
                    terms: ['criar produto', 'novo produto', 'adicionar produto']
                },
                {
                    page: 'history',
                    url: '?page=history',
                    terms: ['historico', 'atividade', 'eventos']
                },
                {
                    page: 'settings',
                    url: '?page=settings',
                    terms: ['definicoes', 'settings', 'configuracoes']
                },
                {
                    page: 'profile',
                    url: '?page=profile',
                    terms: ['perfil', 'conta', 'minha conta', 'dados pessoais']
                }
            ];

            if (isAdmin) {
                quickRoutes.push({
                    page: 'users',
                    url: '?page=users',
                    terms: ['funcionarios', 'utilizadores', 'contas', 'equipa']
                });
                quickRoutes.push({
                    page: 'user-create',
                    url: '?page=user-create',
                    terms: ['criar funcionario', 'novo funcionario']
                });
                quickRoutes.push({
                    page: 'audit',
                    url: '?page=audit',
                    terms: ['auditoria', 'logs', 'acessos', 'seguranca']
                });
            }

            function normalize(value) {
                return (value || '')
                    .toLowerCase()
                    .trim();
            }

            function resolveRoute(query) {
                var needle = normalize(query);
                if (!needle) {
                    return null;
                }

                for (var i = 0; i < quickRoutes.length; i++) {
                    if (quickRoutes[i].page === needle) {
                        return quickRoutes[i].url;
                    }
                }

                for (var j = 0; j < quickRoutes.length; j++) {
                    var route = quickRoutes[j];
                    for (var k = 0; k < route.terms.length; k++) {
                        var term = normalize(route.terms[k]);

                        // Avoid over-eager routing for short search strings (e.g. "de").
                        if (needle.length < 3) {
                            continue;
                        }

                        if (term === needle || term.indexOf(needle) === 0) {
                            return route.url;
                        }
                    }
                }

                return null;
            }

            function buildInventoryUrl(query) {
                var needle = (query || '').trim();
                try {
                    var current = new window.URLSearchParams(window.location.search || '');
                    var next = new window.URLSearchParams();

                    next.set('page', 'inventory');

                    var keepParams = ['category', 'status', 'location', 'per_page'];
                    for (var i = 0; i < keepParams.length; i++) {
                        var value = current.get(keepParams[i]);
                        if (value && value.trim() !== '') {
                            next.set(keepParams[i], value);
                        }
                    }

                    if (needle !== '') {
                        next.set('q', needle);
                    }

                    return '?' + next.toString();
                } catch (error) {
                    if (!needle) {
                        return '?page=inventory';
                    }

                    return '?page=inventory&q=' + encodeURIComponent(needle);
                }
            }

            var inventoryTableBody = document.getElementById('inventoryTableBody');
            var inventoryRows = inventoryTableBody ? Array.prototype.slice.call(inventoryTableBody.querySelectorAll('tr[data-search-row="product"]')) : [];
            var inventoryFilterForm = document.querySelector('form.inventory-filter-form');
            var inventoryQueryInput = inventoryFilterForm ? inventoryFilterForm.querySelector('input[name="q"]') : null;
            var searchNotice = document.getElementById('globalSearchNotice');
            var noResultsRow = null;
            var lastVisibleCount = inventoryRows.length;

            function setSearchNotice(message) {
                if (!searchNotice) {
                    return;
                }

                var text = (message || '').trim();
                if (!text) {
                    searchNotice.hidden = true;
                    searchNotice.textContent = '';
                    return;
                }

                searchNotice.textContent = text;
                searchNotice.hidden = false;
            }

            function isInventoryPage() {
                try {
                    var params = new window.URLSearchParams(window.location.search || '');
                    return (params.get('page') || '').toLowerCase() === 'inventory';
                } catch (error) {
                    return false;
                }
            }

            function ensureInventoryQueryInput() {
                if (!inventoryFilterForm) {
                    return null;
                }

                if (inventoryQueryInput) {
                    return inventoryQueryInput;
                }

                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'q';
                inventoryFilterForm.appendChild(hidden);
                inventoryQueryInput = hidden;
                return inventoryQueryInput;
            }

            function syncInventorySearchState(query, updateUrl) {
                var safeQuery = (query || '').trim();

                var queryField = ensureInventoryQueryInput();
                if (queryField) {
                    queryField.value = safeQuery;
                }

                if (!updateUrl || !isInventoryPage()) {
                    return;
                }

                try {
                    var current = new window.URLSearchParams(window.location.search || '');
                    if (safeQuery === '') {
                        current.delete('q');
                    } else {
                        current.set('q', safeQuery);
                    }
                    current.delete('p');

                    var nextUrl = window.location.pathname + '?' + current.toString() + (window.location.hash || '');
                    window.history.replaceState({}, document.title, nextUrl);
                } catch (error) {
                    // Keep current URL if URLSearchParams is unavailable.
                }
            }

            function clearInventoryFilter() {
                if (!inventoryRows.length) {
                    return;
                }

                for (var i = 0; i < inventoryRows.length; i++) {
                    inventoryRows[i].hidden = false;
                }

                if (noResultsRow && noResultsRow.parentNode) {
                    noResultsRow.parentNode.removeChild(noResultsRow);
                }

                lastVisibleCount = inventoryRows.length;
            }

            function applyInventoryFilter(query) {
                if (!inventoryRows.length) {
                    return 0;
                }

                var needle = normalize(query);
                if (!needle) {
                    clearInventoryFilter();
                    return inventoryRows.length;
                }

                var visibleCount = 0;

                for (var i = 0; i < inventoryRows.length; i++) {
                    var row = inventoryRows[i];
                    var haystack = normalize(row.getAttribute('data-search') || row.textContent || '');
                    var isMatch = haystack.indexOf(needle) !== -1;
                    row.hidden = !isMatch;
                    if (isMatch) {
                        visibleCount++;
                    }
                }

                if (visibleCount === 0) {
                    if (!noResultsRow) {
                        noResultsRow = document.createElement('tr');
                        noResultsRow.setAttribute('data-filter-empty', 'true');
                        noResultsRow.innerHTML = '<td colspan="7" class="text-center text-muted py-4"></td>';
                    }

                    var emptyCell = noResultsRow.querySelector('td');
                    if (emptyCell) {
                        emptyCell.textContent = 'Sem resultados para "' + query + '".';
                    }

                    if (!noResultsRow.parentNode) {
                        inventoryTableBody.appendChild(noResultsRow);
                    }
                } else if (noResultsRow && noResultsRow.parentNode) {
                    noResultsRow.parentNode.removeChild(noResultsRow);
                }

                lastVisibleCount = visibleCount;
                return visibleCount;
            }

            (function hydrateSearchFromQuery() {
                try {
                    var search = window.location.search || '';
                    var q = null;
                    var query = search.charAt(0) === '?' ? search.slice(1) : search;
                    if (query) {
                        var pairs = query.split('&');
                        for (var i = 0; i < pairs.length; i++) {
                            var pair = pairs[i].split('=');
                            if (decodeURIComponent((pair[0] || '').replace(/\+/g, ' ')) === 'q') {
                                q = decodeURIComponent((pair[1] || '').replace(/\+/g, ' '));
                                break;
                            }
                        }
                    }

                    if (q && !searchInput.value) {
                        searchInput.value = q;
                    }

                    syncInventorySearchState(q || '', false);

                    if (q) {
                        applyInventoryFilter(q);
                    }
                } catch (error) {
                    // Ignore malformed URLs and keep default behavior.
                }
            })();

            searchInput.addEventListener('input', function() {
                var currentQuery = searchInput.value;
                var normalizedQuery = normalize(currentQuery);
                var visibleCount = applyInventoryFilter(currentQuery);
                syncInventorySearchState(searchInput.value, true);

                if (!normalizedQuery) {
                    setSearchNotice('');
                    return;
                }

                if (isInventoryPage() && inventoryRows.length) {
                    if (visibleCount <= 0) {
                        setSearchNotice('Sem resultados nesta pagina. Pressiona Enter para pesquisar no inventario completo.');
                    } else {
                        setSearchNotice('');
                    }
                    return;
                }

                if (normalizedQuery.length >= 2 && !resolveRoute(currentQuery)) {
                    setSearchNotice('Sem atalho encontrado. Pressiona Enter para pesquisar no inventario.');
                } else {
                    setSearchNotice('');
                }
            });

            searchInput.addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    var targetUrl = resolveRoute(searchInput.value);
                    if (targetUrl) {
                        setSearchNotice('');
                        window.location.href = targetUrl;
                    } else if (isInventoryPage() && inventoryFilterForm) {
                        syncInventorySearchState(searchInput.value, true);
                        setSearchNotice('');
                        inventoryFilterForm.submit();
                    } else {
                        setSearchNotice('');
                        window.location.href = buildInventoryUrl(searchInput.value);
                    }
                }

                if (event.key === 'Escape') {
                    searchInput.value = '';
                    clearInventoryFilter();
                    syncInventorySearchState('', true);
                    setSearchNotice('');
                    searchInput.blur();
                }
            });

            document.addEventListener('keydown', function(event) {
                var trigger = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k';
                if (trigger) {
                    event.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }
            });
        })();

        (function() {
            var alerts = document.querySelectorAll('.alert');
            if (!alerts.length) {
                return;
            }

            for (var i = 0; i < alerts.length; i++) {
                var alertEl = alerts[i];
                if (alertEl.querySelector('[data-alert-close]')) {
                    continue;
                }

                alertEl.classList.add('is-dismissible');

                var closeButton = document.createElement('button');
                closeButton.type = 'button';
                closeButton.className = 'stocks-alert-close';
                closeButton.setAttribute('aria-label', 'Fechar aviso');
                closeButton.setAttribute('data-alert-close', '1');
                closeButton.textContent = 'x';

                closeButton.addEventListener('click', function(event) {
                    var button = event.currentTarget;
                    var targetAlert = button && button.parentElement ? button.parentElement : null;
                    if (!targetAlert) {
                        return;
                    }

                    targetAlert.style.display = 'none';
                });

                alertEl.appendChild(closeButton);
            }

            try {
                var ephemeralParams = ['msg', 'le', 'li', 'ls', 'expired', 'dev_access'];
                var rawSearch = window.location.search || '';
                var query = rawSearch.charAt(0) === '?' ? rawSearch.slice(1) : rawSearch;
                if (query) {
                    var pairs = query.split('&');
                    var kept = [];
                    var changed = false;

                    for (var i = 0; i < pairs.length; i++) {
                        if (!pairs[i]) {
                            continue;
                        }

                        var key = pairs[i].split('=')[0] || '';
                        key = decodeURIComponent(key.replace(/\+/g, ' '));

                        if (ephemeralParams.indexOf(key) !== -1) {
                            changed = true;
                            continue;
                        }

                        kept.push(pairs[i]);
                    }

                    if (changed) {
                        var nextUrl = window.location.pathname + (kept.length ? '?' + kept.join('&') : '') + (window.location.hash || '');
                        window.history.replaceState({}, document.title, nextUrl);
                    }
                }
            } catch (error) {
                // Keep current URL if browser URL API is unavailable.
            }
        })();

        (function() {
            document.addEventListener('submit', function(event) {
                var form = event.target;
                if (!form || form.nodeName !== 'FORM' || !form.hasAttribute('data-submit-lock')) {
                    return;
                }

                if (form.getAttribute('data-submitted') === 'true') {
                    event.preventDefault();
                    return;
                }

                form.setAttribute('data-submitted', 'true');

                var submitControls = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                for (var i = 0; i < submitControls.length; i++) {
                    var control = submitControls[i];
                    control.disabled = true;

                    if (control.nodeName === 'BUTTON') {
                        var loadingText = control.getAttribute('data-loading-text');
                        if (loadingText) {
                            control.textContent = loadingText;
                        }
                    }
                }
            }, true);
        })();

        (function() {
            var toggle = document.querySelector('.sidebar-toggle');
            if (!toggle) {
                return;
            }

            var body = document.body;
            var icon = toggle.querySelector('.material-symbols-outlined');
            var closeButton = document.querySelector('.sidebar-close');
            var backdrop = document.querySelector('.nav-backdrop');
            var sidebarLinks = document.querySelectorAll('.sidebar a');

            function isMobile() {
                return window.matchMedia('(max-width: 767.98px)').matches;
            }

            function persistMobileState() {
                localStorage.setItem('stocks.sidebarOpen', body.classList.contains('nav-open') ? 'true' : 'false');
            }

            function persistDesktopState() {
                localStorage.setItem('stocks.sidebarCollapsed', body.classList.contains('nav-collapsed') ? 'true' : 'false');
            }

            function closeNav() {
                body.classList.remove('nav-open');
                persistMobileState();
                syncToggleLabel();
            }

            function syncToggleLabel() {
                if (isMobile()) {
                    var open = body.classList.contains('nav-open');
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
                    if (icon) {
                        icon.textContent = open ? 'close' : 'menu';
                    }
                    return;
                }

                var collapsed = body.classList.contains('nav-collapsed');
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                toggle.setAttribute('aria-label', collapsed ? 'Expandir menu' : 'Recolher menu');
                if (icon) {
                    icon.textContent = collapsed ? 'menu' : 'menu_open';
                }
            }

            toggle.addEventListener('click', function() {
                if (isMobile()) {
                    body.classList.toggle('nav-open');
                    persistMobileState();
                } else {
                    body.classList.toggle('nav-collapsed');
                    persistDesktopState();
                }
                syncToggleLabel();
            });

            if (closeButton) {
                closeButton.addEventListener('click', closeNav);
            }

            if (backdrop) {
                backdrop.addEventListener('click', closeNav);
            }

            sidebarLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    if (isMobile()) {
                        closeNav();
                    }
                });
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeNav();
                }
            });

            window.addEventListener('resize', function() {
                if (isMobile()) {
                    body.classList.remove('nav-collapsed');
                    if (localStorage.getItem('stocks.sidebarOpen') !== 'false') {
                        body.classList.add('nav-open');
                    } else {
                        body.classList.remove('nav-open');
                    }
                } else {
                    body.classList.remove('nav-open');
                    if (localStorage.getItem('stocks.sidebarCollapsed') === 'true') {
                        body.classList.add('nav-collapsed');
                    } else {
                        body.classList.remove('nav-collapsed');
                    }
                }

                syncToggleLabel();
            });

            syncToggleLabel();
        })();
    </script>

</body>

</html>