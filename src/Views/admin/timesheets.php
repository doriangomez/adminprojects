<?php
$basePath = $basePath ?? '';
$report = is_array($report ?? null) ? $report : [];
$totals = is_array($report['totals'] ?? null) ? $report['totals'] : ['entries' => 0, 'hours' => 0];
$rows = is_array($report['rows'] ?? null) ? $report['rows'] : [];
$byUser = is_array($report['by_user'] ?? null) ? $report['by_user'] : [];
$byProject = is_array($report['by_project'] ?? null) ? $report['by_project'] : [];
$byClient = is_array($report['by_client'] ?? null) ? $report['by_client'] : [];
$byTask = is_array($report['by_task'] ?? null) ? $report['by_task'] : [];
$statusBreakdown = is_array($report['status_breakdown'] ?? null) ? $report['status_breakdown'] : [];
$filterOptions = is_array($report['filter_options'] ?? null) ? $report['filter_options'] : [];
$users = is_array($filterOptions['users'] ?? null) ? $filterOptions['users'] : [];
$projects = is_array($filterOptions['projects'] ?? null) ? $filterOptions['projects'] : [];
$clients = is_array($filterOptions['clients'] ?? null) ? $filterOptions['clients'] : [];
$statuses = is_array($filterOptions['statuses'] ?? null) ? $filterOptions['statuses'] : [];
$filters = is_array($filters ?? null) ? $filters : [];
$weeklySummary = is_array($weeklySummary ?? null) ? $weeklySummary : [];
$selectedWeek = (string) ($weekValue ?? (new DateTimeImmutable('monday this week'))->format('o-\\WW'));
$weekStartObj = $weekStart ?? new DateTimeImmutable('monday this week');
$weekEndObj = $weekEnd ?? $weekStartObj->modify('+6 days');

$weekLabel = $weekStartObj->format('d') . '-' . $weekEndObj->format('d') . ' ' . $weekEndObj->format('M Y');

$statusLabel = static function (string $status): string {
    $normalized = strtolower(trim($status));
    return match ($normalized) {
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
        'submitted', 'pending', 'pending_approval' => 'Enviado',
        'draft' => 'Borrador',
        default => $status !== '' ? ucfirst($status) : 'Sin estado',
    };
};

$statusBadgeClass = static function (string $status): string {
    $normalized = strtolower(trim($status));
    return match ($normalized) {
        'approved' => 'at-badge-approved',
        'rejected' => 'at-badge-rejected',
        'submitted', 'pending', 'pending_approval' => 'at-badge-submitted',
        'draft' => 'at-badge-draft',
        default => 'at-badge-draft',
    };
};

$resolveWeeklyStatus = static function (string $statuses) use ($statusLabel, $statusBadgeClass): array {
    $parts = array_unique(array_filter(array_map('trim', explode(',', $statuses))));
    if (count($parts) === 1) {
        $s = $parts[0];
        return ['label' => $statusLabel($s), 'class' => $statusBadgeClass($s)];
    }
    if (in_array('approved', $parts, true) && count($parts) === 1) {
        return ['label' => 'Aprobado', 'class' => 'at-badge-approved'];
    }
    if (in_array('submitted', $parts, true) || in_array('pending_approval', $parts, true)) {
        return ['label' => 'Enviado', 'class' => 'at-badge-submitted'];
    }
    return ['label' => 'Mixto', 'class' => 'at-badge-draft'];
};

$approvedHours = 0;
$submittedHours = 0;
$draftHours = 0;
foreach ($statusBreakdown as $sb) {
    $s = strtolower(trim((string) ($sb['status'] ?? '')));
    $h = (float) ($sb['total_hours'] ?? 0);
    if ($s === 'approved') { $approvedHours += $h; }
    elseif (in_array($s, ['submitted', 'pending', 'pending_approval'], true)) { $submittedHours += $h; }
    elseif ($s === 'draft') { $draftHours += $h; }
}
?>

<section class="at-shell">
    <header class="at-hero">
        <div class="at-hero-text">
            <p class="at-eyebrow">Administración</p>
            <h1>Control de Horas</h1>
            <p class="at-subtitle">Vista consolidada para PMO y administración. Semana: <strong><?= htmlspecialchars($weekLabel) ?></strong></p>
        </div>
    </header>

    <form method="GET" action="<?= $basePath ?>/admin/timesheets" class="at-filters">
        <label class="at-filter">
            <span>Usuario</span>
            <select name="user_id">
                <option value="0">Todos</option>
                <?php foreach ($users as $userOption): ?>
                    <?php $userId = (int) ($userOption['user_id'] ?? 0); ?>
                    <option value="<?= $userId ?>" <?= $userId === (int) ($filters['user_id'] ?? 0) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($userOption['user_name'] ?? 'Usuario')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="at-filter">
            <span>Proyecto</span>
            <select name="project_id">
                <option value="0">Todos</option>
                <?php foreach ($projects as $projectOption): ?>
                    <?php $projectId = (int) ($projectOption['project_id'] ?? 0); ?>
                    <option value="<?= $projectId ?>" <?= $projectId === (int) ($filters['project_id'] ?? 0) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($projectOption['project_name'] ?? 'Proyecto')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="at-filter">
            <span>Cliente</span>
            <select name="client_id">
                <option value="0">Todos</option>
                <?php foreach ($clients as $clientOption): ?>
                    <?php $clientId = (int) ($clientOption['client_id'] ?? 0); ?>
                    <option value="<?= $clientId ?>" <?= $clientId === (int) ($filters['client_id'] ?? 0) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($clientOption['client_name'] ?? 'Cliente')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="at-filter">
            <span>Semana</span>
            <input type="week" name="week" value="<?= htmlspecialchars($selectedWeek) ?>">
        </label>
        <label class="at-filter">
            <span>Estado</span>
            <select name="status">
                <option value="">Todos</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= htmlspecialchars($status) ?>" <?= strtolower((string) ($filters['status'] ?? '')) === strtolower((string) $status) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($statusLabel((string) $status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="at-filter-actions">
            <button type="submit" class="at-btn at-btn-primary">Aplicar filtros</button>
            <a href="<?= $basePath ?>/admin/timesheets" class="at-btn at-btn-ghost">Limpiar</a>
        </div>
    </form>

    <section class="at-kpis">
        <article class="at-kpi">
            <div class="at-kpi-icon at-kpi-icon-total">⏱️</div>
            <div>
                <span class="at-kpi-label">Total horas</span>
                <strong class="at-kpi-value"><?= number_format((float) ($totals['hours'] ?? 0), 1) ?>h</strong>
            </div>
        </article>
        <article class="at-kpi">
            <div class="at-kpi-icon at-kpi-icon-approved">✅</div>
            <div>
                <span class="at-kpi-label">Aprobadas</span>
                <strong class="at-kpi-value"><?= number_format($approvedHours, 1) ?>h</strong>
            </div>
        </article>
        <article class="at-kpi">
            <div class="at-kpi-icon at-kpi-icon-submitted">📤</div>
            <div>
                <span class="at-kpi-label">Enviadas</span>
                <strong class="at-kpi-value"><?= number_format($submittedHours, 1) ?>h</strong>
            </div>
        </article>
        <article class="at-kpi">
            <div class="at-kpi-icon at-kpi-icon-draft">📝</div>
            <div>
                <span class="at-kpi-label">Borrador</span>
                <strong class="at-kpi-value"><?= number_format($draftHours, 1) ?>h</strong>
            </div>
        </article>
        <article class="at-kpi">
            <div class="at-kpi-icon at-kpi-icon-users">👥</div>
            <div>
                <span class="at-kpi-label">Usuarios</span>
                <strong class="at-kpi-value"><?= count($byUser) ?></strong>
            </div>
        </article>
        <article class="at-kpi">
            <div class="at-kpi-icon at-kpi-icon-projects">📊</div>
            <div>
                <span class="at-kpi-label">Proyectos</span>
                <strong class="at-kpi-value"><?= count($byProject) ?></strong>
            </div>
        </article>
    </section>

    <article class="at-card">
        <div class="at-card-header">
            <div>
                <h3>Control operativo semanal</h3>
                <p class="at-muted">Resumen agrupado por usuario y proyecto para la semana seleccionada.</p>
            </div>
        </div>
        <?php if (empty($weeklySummary)): ?>
            <div class="at-empty">No hay registros de horas para esta semana con los filtros seleccionados.</div>
        <?php else: ?>
            <div class="at-table-wrapper">
                <table class="at-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Cliente</th>
                            <th>Proyecto</th>
                            <th>Semana</th>
                            <th>Horas</th>
                            <th>Estado</th>
                            <th class="at-actions-col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($weeklySummary as $entry): ?>
                            <?php
                            $statusInfo = $resolveWeeklyStatus((string) ($entry['statuses'] ?? 'draft'));
                            $entryHours = (float) ($entry['total_hours'] ?? 0);
                            $entryUserId = (int) ($entry['user_id'] ?? 0);
                            $entryProjectId = (int) ($entry['project_id'] ?? 0);
                            ?>
                            <tr>
                                <td>
                                    <div class="at-user-cell">
                                        <span class="at-user-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr((string) ($entry['user_name'] ?? '?'), 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
                                        <strong><?= htmlspecialchars((string) ($entry['user_name'] ?? 'Sin usuario')) ?></strong>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars((string) ($entry['client_name'] ?? 'Sin cliente')) ?></td>
                                <td><strong><?= htmlspecialchars((string) ($entry['project_name'] ?? 'Sin proyecto')) ?></strong></td>
                                <td class="at-week-cell"><?= htmlspecialchars($weekLabel) ?></td>
                                <td>
                                    <strong class="at-hours-value"><?= number_format($entryHours, 1) ?>h</strong>
                                </td>
                                <td>
                                    <span class="at-badge <?= $statusInfo['class'] ?>"><?= htmlspecialchars($statusInfo['label']) ?></span>
                                </td>
                                <td class="at-actions-col">
                                    <details class="at-menu-details">
                                        <summary class="at-menu-trigger" aria-label="Acciones">⋯</summary>
                                        <div class="at-menu-list">
                                            <a href="<?= $basePath ?>/admin/timesheets?user_id=<?= $entryUserId ?>&project_id=<?= $entryProjectId ?>&week=<?= urlencode($selectedWeek) ?>#detalle-semanal" class="at-menu-item">Ver detalle</a>
                                            <?php if (in_array(strtolower(trim((string) ($entry['statuses'] ?? ''))), ['submitted', 'pending_approval'], true)): ?>
                                                <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="at-menu-form">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="user_id" value="<?= $entryUserId ?>">
                                                    <input type="hidden" name="project_id" value="<?= $entryProjectId ?>">
                                                    <input type="hidden" name="week" value="<?= htmlspecialchars($selectedWeek) ?>">
                                                    <button type="submit" class="at-menu-item at-menu-approve">Aprobar</button>
                                                </form>
                                                <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="at-menu-form">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="user_id" value="<?= $entryUserId ?>">
                                                    <input type="hidden" name="project_id" value="<?= $entryProjectId ?>">
                                                    <input type="hidden" name="week" value="<?= htmlspecialchars($selectedWeek) ?>">
                                                    <button type="submit" class="at-menu-item at-menu-reject">Rechazar</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>

    <div class="at-section-grid">
        <article class="at-card">
            <h4>Horas por usuario</h4>
            <?php if (empty($byUser)): ?>
                <p class="at-muted">Sin datos.</p>
            <?php else: ?>
                <table class="at-table at-table-compact">
                    <thead><tr><th>Usuario</th><th>Registros</th><th>Horas</th></tr></thead>
                    <tbody>
                        <?php foreach ($byUser as $row): ?>
                            <tr>
                                <td>
                                    <div class="at-user-cell">
                                        <span class="at-user-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr((string) ($row['user_name'] ?? '?'), 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
                                        <?= htmlspecialchars((string) ($row['user_name'] ?? 'Sin usuario')) ?>
                                    </div>
                                </td>
                                <td><?= (int) ($row['entries'] ?? 0) ?></td>
                                <td><strong><?= number_format((float) ($row['total_hours'] ?? 0), 1) ?>h</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </article>

        <article class="at-card">
            <h4>Estado de horas</h4>
            <?php if (empty($statusBreakdown)): ?>
                <p class="at-muted">Sin datos.</p>
            <?php else: ?>
                <table class="at-table at-table-compact">
                    <thead><tr><th>Estado</th><th>Registros</th><th>Horas</th></tr></thead>
                    <tbody>
                        <?php foreach ($statusBreakdown as $row): ?>
                            <?php $status = (string) ($row['status'] ?? ''); ?>
                            <tr>
                                <td><span class="at-badge <?= $statusBadgeClass($status) ?>"><?= htmlspecialchars($statusLabel($status)) ?></span></td>
                                <td><?= (int) ($row['entries'] ?? 0) ?></td>
                                <td><strong><?= number_format((float) ($row['total_hours'] ?? 0), 1) ?>h</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </article>
    </div>

    <div class="at-section-grid">
        <article class="at-card">
            <h4>Horas por proyecto</h4>
            <?php if (empty($byProject)): ?>
                <p class="at-muted">Sin datos.</p>
            <?php else: ?>
                <table class="at-table at-table-compact">
                    <thead><tr><th>Proyecto</th><th>Registros</th><th>Horas</th></tr></thead>
                    <tbody>
                        <?php foreach ($byProject as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars((string) ($row['project_name'] ?? 'Sin proyecto')) ?></strong></td>
                                <td><?= (int) ($row['entries'] ?? 0) ?></td>
                                <td><strong><?= number_format((float) ($row['total_hours'] ?? 0), 1) ?>h</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </article>

        <article class="at-card">
            <h4>Horas por cliente</h4>
            <?php if (empty($byClient)): ?>
                <p class="at-muted">Sin datos.</p>
            <?php else: ?>
                <table class="at-table at-table-compact">
                    <thead><tr><th>Cliente</th><th>Registros</th><th>Horas</th></tr></thead>
                    <tbody>
                        <?php foreach ($byClient as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($row['client_name'] ?? 'Sin cliente')) ?></td>
                                <td><?= (int) ($row['entries'] ?? 0) ?></td>
                                <td><strong><?= number_format((float) ($row['total_hours'] ?? 0), 1) ?>h</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </article>
    </div>

    <article class="at-card" id="detalle-semanal">
        <div class="at-card-header">
            <div>
                <h4>Detalle semanal</h4>
                <p class="at-muted">Registros individuales de horas para la semana seleccionada.</p>
            </div>
        </div>
        <?php if (empty($rows)): ?>
            <div class="at-empty">No hay registros de detalle.</div>
        <?php else: ?>
            <div class="at-table-wrapper">
                <table class="at-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Cliente</th>
                            <th>Proyecto</th>
                            <th>Tarea</th>
                            <th>Horas</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $dateRaw = (string) ($row['date'] ?? '');
                            $dateFormatted = $dateRaw;
                            if ($dateRaw !== '') {
                                $ts = strtotime($dateRaw);
                                if ($ts) {
                                    $dateFormatted = date('d M', $ts);
                                }
                            }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($dateFormatted) ?></td>
                                <td><?= htmlspecialchars((string) ($row['user_name'] ?? 'Sin usuario')) ?></td>
                                <td><?= htmlspecialchars((string) ($row['client_name'] ?? 'Sin cliente')) ?></td>
                                <td><?= htmlspecialchars((string) ($row['project_name'] ?? 'Sin proyecto')) ?></td>
                                <td><?= htmlspecialchars((string) ($row['task_name'] ?? 'Sin tarea')) ?></td>
                                <td><strong><?= number_format((float) ($row['hours'] ?? 0), 1) ?>h</strong></td>
                                <td><span class="at-badge <?= $statusBadgeClass((string) ($row['status'] ?? '')) ?>"><?= htmlspecialchars($statusLabel((string) ($row['status'] ?? ''))) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
</section>

<style>
    .at-shell {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .at-hero {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        padding: 20px 24px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow: 0 12px 30px color-mix(in srgb, var(--text-primary) 8%, var(--background));
    }

    .at-eyebrow {
        margin: 0;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-secondary);
        font-weight: 800;
    }

    .at-hero h1 {
        margin: 4px 0 0;
        font-size: 26px;
        color: var(--text-primary);
    }

    .at-subtitle {
        margin: 4px 0 0;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .at-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: flex-end;
        padding: 16px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
    }

    .at-filter {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex: 1;
        min-width: 140px;
    }

    .at-filter span {
        font-size: 12px;
        font-weight: 700;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .at-filter select,
    .at-filter input {
        padding: 9px 12px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: var(--background);
        color: var(--text-primary);
        font-size: 14px;
    }

    .at-filter-actions {
        display: flex;
        gap: 8px;
        align-items: flex-end;
    }

    .at-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 16px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 14px;
        text-decoration: none;
        border: 1px solid var(--border);
        cursor: pointer;
        transition: transform 0.12s ease, box-shadow 0.12s ease;
    }

    .at-btn:hover { transform: translateY(-1px); }

    .at-btn-primary {
        background: var(--primary);
        color: var(--text-primary);
        border-color: var(--primary);
        box-shadow: 0 8px 20px color-mix(in srgb, var(--primary) 30%, var(--background));
    }

    .at-btn-ghost {
        background: color-mix(in srgb, var(--text-secondary) 14%, var(--background));
        color: var(--text-primary);
        border-color: color-mix(in srgb, var(--text-secondary) 25%, var(--background));
    }

    .at-kpis {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 12px;
    }

    .at-kpi {
        padding: 14px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: var(--surface);
        display: flex;
        gap: 12px;
        align-items: center;
        box-shadow: 0 8px 20px color-mix(in srgb, var(--text-primary) 6%, var(--background));
    }

    .at-kpi-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .at-kpi-icon-total { background: color-mix(in srgb, var(--primary) 14%, var(--background)); }
    .at-kpi-icon-approved { background: color-mix(in srgb, var(--success) 14%, var(--background)); }
    .at-kpi-icon-submitted { background: color-mix(in srgb, #3b82f6 14%, var(--background)); }
    .at-kpi-icon-draft { background: color-mix(in srgb, var(--warning) 14%, var(--background)); }
    .at-kpi-icon-users { background: color-mix(in srgb, #8b5cf6 14%, var(--background)); }
    .at-kpi-icon-projects { background: color-mix(in srgb, #06b6d4 14%, var(--background)); }

    .at-kpi-label {
        display: block;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-secondary);
        font-weight: 700;
    }

    .at-kpi-value {
        display: block;
        font-size: 22px;
        color: var(--text-primary);
        font-weight: 800;
    }

    .at-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 18px;
        box-shadow: 0 8px 20px color-mix(in srgb, var(--text-primary) 6%, var(--background));
    }

    .at-card h3,
    .at-card h4 {
        margin: 0 0 4px;
        color: var(--text-primary);
    }

    .at-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 14px;
    }

    .at-muted {
        color: var(--text-secondary);
        font-size: 13px;
        margin: 0;
    }

    .at-table-wrapper {
        overflow-x: auto;
    }

    .at-table {
        width: 100%;
        border-collapse: collapse;
    }

    .at-table th,
    .at-table td {
        padding: 11px 12px;
        border-bottom: 1px solid var(--border);
        text-align: left;
        vertical-align: middle;
        font-size: 13px;
    }

    .at-table th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-secondary);
        background: color-mix(in srgb, var(--text-secondary) 10%, var(--background));
        font-weight: 700;
        white-space: nowrap;
    }

    .at-table tbody tr:last-child td { border-bottom: none; }
    .at-table tbody tr:hover { background: color-mix(in srgb, var(--text-secondary) 8%, var(--background)); }

    .at-table-compact th,
    .at-table-compact td {
        padding: 8px 10px;
    }

    .at-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 12px;
        border: 1px solid transparent;
    }

    .at-badge-approved {
        background: color-mix(in srgb, var(--success) 16%, var(--background));
        color: var(--success);
        border-color: color-mix(in srgb, var(--success) 35%, var(--background));
    }

    .at-badge-submitted {
        background: color-mix(in srgb, #3b82f6 16%, var(--background));
        color: #3b82f6;
        border-color: color-mix(in srgb, #3b82f6 35%, var(--background));
    }

    .at-badge-rejected {
        background: color-mix(in srgb, var(--danger) 16%, var(--background));
        color: var(--danger);
        border-color: color-mix(in srgb, var(--danger) 35%, var(--background));
    }

    .at-badge-draft {
        background: color-mix(in srgb, var(--warning) 16%, var(--background));
        color: color-mix(in srgb, var(--warning) 75%, var(--text-primary));
        border-color: color-mix(in srgb, var(--warning) 35%, var(--background));
    }

    .at-user-cell {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .at-user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--primary) 18%, var(--background));
        color: var(--primary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 13px;
        flex-shrink: 0;
    }

    .at-hours-value {
        font-size: 15px;
        color: var(--text-primary);
    }

    .at-week-cell {
        color: var(--text-secondary);
        font-size: 12px;
        white-space: nowrap;
    }

    .at-actions-col {
        text-align: right;
        width: 1%;
        white-space: nowrap;
    }

    .at-menu-details { display: inline-flex; position: relative; }
    .at-menu-details summary::-webkit-details-marker { display: none; }
    .at-menu-details summary { list-style: none; }

    .at-menu-trigger {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--text-primary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 16px;
    }

    .at-menu-trigger:hover {
        background: color-mix(in srgb, var(--primary) 12%, var(--background));
        color: var(--primary);
    }

    .at-menu-list {
        position: absolute;
        right: 0;
        top: 36px;
        min-width: 160px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 14px 28px color-mix(in srgb, var(--text-primary) 16%, var(--background));
        padding: 6px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        z-index: 10;
    }

    .at-menu-item {
        text-decoration: none;
        color: var(--text-primary);
        padding: 8px 12px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        display: block;
        border: none;
        background: none;
        cursor: pointer;
        text-align: left;
        width: 100%;
    }

    .at-menu-item:hover {
        background: color-mix(in srgb, var(--primary) 12%, var(--background));
        color: var(--primary);
    }

    .at-menu-approve:hover {
        background: color-mix(in srgb, var(--success) 14%, var(--background));
        color: var(--success);
    }

    .at-menu-reject:hover {
        background: color-mix(in srgb, var(--danger) 14%, var(--background));
        color: var(--danger);
    }

    .at-menu-form {
        margin: 0;
        padding: 0;
    }

    .at-section-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 14px;
    }

    .at-empty {
        padding: 18px;
        border-radius: 12px;
        background: color-mix(in srgb, var(--text-secondary) 10%, var(--background));
        border: 1px solid var(--border);
        color: var(--text-secondary);
        font-weight: 600;
        text-align: center;
    }

    @media (max-width: 960px) {
        .at-filters { flex-direction: column; }
        .at-filter { min-width: 100%; }
        .at-kpis { grid-template-columns: repeat(2, 1fr); }
    }
</style>
