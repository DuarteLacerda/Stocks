<?php
$auditEntries = stocks_auth_audit_load();
usort($auditEntries, static function (array $a, array $b): int {
    $left = strtotime((string) ($a['timestamp'] ?? '')) ?: 0;
    $right = strtotime((string) ($b['timestamp'] ?? '')) ?: 0;
    return $right <=> $left;
});

$eventFilter = trim((string) ($_GET['event'] ?? ''));
$actorFilter = trim((string) ($_GET['actor'] ?? ''));
$searchFilter = trim((string) ($_GET['q'] ?? ''));
$perPageOptions = [10, 25, 50];
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 10;
}

$availableEvents = [];
$availableActors = [];
foreach ($auditEntries as $entry) {
    $event = trim((string) ($entry['event'] ?? ''));
    $actor = trim((string) ($entry['actor'] ?? ''));
    if ($event !== '') {
        $availableEvents[$event] = true;
    }
    if ($actor !== '') {
        $availableActors[$actor] = true;
    }
}
$availableEvents = array_keys($availableEvents);
$availableActors = array_keys($availableActors);
sort($availableEvents);
sort($availableActors);

$auditEntries = array_values(array_filter($auditEntries, static function (array $entry) use ($eventFilter, $actorFilter, $searchFilter): bool {
    $event = trim((string) ($entry['event'] ?? ''));
    $actor = trim((string) ($entry['actor'] ?? ''));
    $username = trim((string) ($entry['username'] ?? ''));
    $ip = trim((string) ($entry['ip'] ?? ''));
    $meta = is_array($entry['meta'] ?? null) ? $entry['meta'] : [];

    if ($eventFilter !== '' && $event !== $eventFilter) {
        return false;
    }

    if ($actorFilter !== '' && $actor !== $actorFilter) {
        return false;
    }

    if ($searchFilter !== '') {
        $haystack = strtolower(implode(' ', [
            $event,
            $actor,
            $username,
            $ip,
            json_encode($meta, JSON_UNESCAPED_UNICODE) ?: '',
        ]));

        if (strpos($haystack, strtolower($searchFilter)) === false) {
            return false;
        }
    }

    return true;
}));

$totalEvents = count($auditEntries);
$failedLogins = 0;
$successfulLogins = 0;
$securityActions = 0;
foreach ($auditEntries as $entry) {
    $event = (string) ($entry['event'] ?? '');
    if ($event === 'login_failed') {
        $failedLogins++;
    }
    if (strpos($event, 'login_success') === 0) {
        $successfulLogins++;
    }
    if (strpos($event, 'password_reset') === 0 || strpos($event, 'account_') === 0 || strpos($event, 'user_') === 0) {
        $securityActions++;
    }
}

$lastEventAt = $totalEvents > 0
    ? date('d/m/Y H:i:s', strtotime((string) ($auditEntries[0]['timestamp'] ?? '')) ?: time())
    : '-';

$totalPages = max(1, (int) ceil($totalEvents / $perPage));
$currentAuditPage = (int) ($_GET['p'] ?? 1);
if ($currentAuditPage < 1) {
    $currentAuditPage = 1;
}
if ($currentAuditPage > $totalPages) {
    $currentAuditPage = $totalPages;
}

$offset = ($currentAuditPage - 1) * $perPage;
$pagedEntries = array_slice($auditEntries, $offset, $perPage);
$firstItem = $totalEvents > 0 ? $offset + 1 : 0;
$lastItem = $totalEvents > 0 ? min($offset + $perPage, $totalEvents) : 0;

$buildAuditUrl = static function (array $overrides = []) use ($searchFilter, $eventFilter, $actorFilter, $perPage, $currentAuditPage): string {
    $params = [
        'page' => 'audit',
        'q' => $searchFilter,
        'event' => $eventFilter,
        'actor' => $actorFilter,
        'per_page' => $perPage,
        'p' => $currentAuditPage,
    ];

    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }

    $params = array_filter($params, static function ($value): bool {
        if (is_int($value)) {
            return true;
        }

        return trim((string) $value) !== '';
    });

    return '?' . http_build_query($params);
};

$eventBadgeClass = static function (string $event): string {
    if ($event === 'login_failed') {
        return 'bg-danger-subtle text-danger border border-danger-subtle';
    }
    if (strpos($event, 'login_success') === 0 || $event === 'logout') {
        return 'bg-success-subtle text-success border border-success-subtle';
    }
    if (strpos($event, 'password_reset') === 0) {
        return 'bg-warning-subtle text-warning border border-warning-subtle';
    }
    if (strpos($event, 'account_') === 0 || strpos($event, 'user_') === 0) {
        return 'bg-primary-subtle text-primary border border-primary-subtle';
    }

    return 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle';
};
?>

<div class="row g-3">
    <div class="col-12">
        <div class="metric-card dashboard-panel">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                <div>
                    <span class="dashboard-kicker">Seguranca</span>
                    <h1 class="dashboard-title mb-2">Auditoria de Acessos</h1>
                    <p class="dashboard-copy mb-0">Eventos de sessao, bloqueios, desbloqueios e alteracoes sensiveis.</p>
                </div>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?php echo (int) $totalEvents; ?> eventos</span>
            </div>

            <form method="get" class="row g-2 align-items-end inventory-filter-form mb-2" data-submit-lock>
                <input type="hidden" name="page" value="audit">
                <div class="col-sm-6 col-lg-3">
                    <label class="inventory-filter-label" for="audit-q">Pesquisa</label>
                    <input id="audit-q" name="q" type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($searchFilter, ENT_QUOTES, 'UTF-8'); ?>" placeholder="evento, utilizador, ip, meta...">
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label class="inventory-filter-label" for="audit-event">Evento</label>
                    <select id="audit-event" name="event" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($availableEvents as $eventOption): ?>
                            <option value="<?php echo htmlspecialchars($eventOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $eventFilter === $eventOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($eventOption, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label class="inventory-filter-label" for="audit-actor">Ator</label>
                    <select id="audit-actor" name="actor" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($availableActors as $actorOption): ?>
                            <option value="<?php echo htmlspecialchars($actorOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $actorFilter === $actorOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($actorOption, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label class="inventory-filter-label" for="audit-per-page">Por página</label>
                    <select id="audit-per-page" name="per_page" class="form-select form-select-sm">
                        <?php foreach ($perPageOptions as $option): ?>
                            <option value="<?php echo (int) $option; ?>" <?php echo $perPage === $option ? 'selected' : ''; ?>><?php echo (int) $option; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-sm-12 col-lg-1 d-flex gap-2 justify-content-start justify-content-lg-end">
                    <button type="submit" class="btn btn-sm btn-outline-secondary" data-loading-text="A filtrar...">Aplicar</button>
                </div>
            </form>

            <div class="inventory-filter-meta mb-3">
                <span>
                    <?php if ($totalEvents > 0): ?>
                        A mostrar <?php echo (int) $firstItem; ?>-<?php echo (int) $lastItem; ?> de <?php echo (int) $totalEvents; ?> resultados
                    <?php else: ?>
                        Sem eventos para mostrar
                    <?php endif; ?>
                </span>
                <?php if ($searchFilter !== '' || $eventFilter !== '' || $actorFilter !== '' || $perPage !== 10): ?>
                    <a href="?page=audit" class="inventory-clear-link">Limpar filtros</a>
                <?php endif; ?>
            </div>

            <div class="audit-stats-grid mb-3">
                <div class="audit-stat-card">
                    <span>Total filtrado</span>
                    <strong><?php echo number_format($totalEvents, 0, ',', ' '); ?></strong>
                </div>
                <div class="audit-stat-card">
                    <span>Sessoes com sucesso</span>
                    <strong><?php echo number_format($successfulLogins, 0, ',', ' '); ?></strong>
                </div>
                <div class="audit-stat-card">
                    <span>Falhas de sessao</span>
                    <strong><?php echo number_format($failedLogins, 0, ',', ' '); ?></strong>
                </div>
                <div class="audit-stat-card">
                    <span>Acoes de seguranca</span>
                    <strong><?php echo number_format($securityActions, 0, ',', ' '); ?></strong>
                </div>
                <div class="audit-stat-card">
                    <span>Ultimo evento</span>
                    <strong><?php echo htmlspecialchars($lastEventAt, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-custom mb-0 audit-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Evento</th>
                            <th>Utilizador</th>
                            <th>Ator</th>
                            <th>Origem</th>
                            <th>Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($pagedEntries) === 0): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Sem eventos para os filtros aplicados.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($pagedEntries as $entry): ?>
                            <?php
                            $timestamp = (string) ($entry['timestamp'] ?? '');
                            $dateLabel = $timestamp !== '' ? date('d/m/Y H:i:s', strtotime($timestamp)) : '-';
                            $event = (string) ($entry['event'] ?? '-');
                            $username = (string) ($entry['username'] ?? '');
                            $actor = (string) ($entry['actor'] ?? '');
                            $ip = trim((string) ($entry['ip'] ?? ''));
                            $meta = is_array($entry['meta'] ?? null) ? $entry['meta'] : [];
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <span class="badge <?php echo $eventBadgeClass($event); ?> audit-event-badge">
                                        <?php echo htmlspecialchars($event, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($username !== '' ? $username : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($actor !== '' ? $actor : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if ($ip !== ''): ?>
                                        <span class="audit-ip"><?php echo htmlspecialchars($ip, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (count($meta) > 0): ?>
                                        <div class="audit-meta-list">
                                            <?php foreach ($meta as $k => $v): ?>
                                                <span class="audit-meta-item"><strong><?php echo htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="inventory-pagination-wrap mt-3" aria-label="Paginação da auditoria">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?php echo $currentAuditPage <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars($buildAuditUrl(['p' => max(1, $currentAuditPage - 1)]), ENT_QUOTES, 'UTF-8'); ?>">Anterior</a>
                        </li>
                        <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                            <li class="page-item <?php echo $pageNumber === $currentAuditPage ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars($buildAuditUrl(['p' => $pageNumber]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $pageNumber; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $currentAuditPage >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars($buildAuditUrl(['p' => min($totalPages, $currentAuditPage + 1)]), ENT_QUOTES, 'UTF-8'); ?>">Seguinte</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>