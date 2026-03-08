<?php
$basePath = $basePath ?? '';
$report = is_array($report ?? null) ? $report : [];
$totals = is_array($report['totals'] ?? null) ? $report['totals'] : ['entries' => 0, 'hours' => 0];
$aggregatedRows = is_array($report['aggregated_rows'] ?? null) ? $report['aggregated_rows'] : [];
$rows = is_array($report['rows'] ?? null) ? $report['rows'] : [];
$filterOptions = is_array($report['filter_options'] ?? null) ? $report['filter_options'] : [];
$users = is_array($filterOptions['users'] ?? null) ? $filterOptions['users'] : [];
$projects = is_array($filterOptions['projects'] ?? null) ? $filterOptions['projects'] : [];
$clients = is_array($filterOptions['clients'] ?? null) ? $filterOptions['clients'] : [];
$statuses = is_array($filterOptions['statuses'] ?? null) ? $filterOptions['statuses'] : [];
$filters = is_array($filters ?? null) ? $filters : [];
$selectedWeek = (string) ($weekValue ?? (new DateTimeImmutable('monday this week'))->format('o-\\WW'));
$canApprove = (bool) ($canApprove ?? false);

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

$statusClass = static function (string $status): string {
    $n = strtolower(trim($status));
    return match ($n) {
        'approved' => 'status-approved',
        'rejected' => 'status-rejected',
        'submitted', 'pending', 'pending_approval' => 'status-submitted',
        default => 'status-draft',
    };
};
?>

<section class="admin-timesheets">
    <header class="admin-timesheets-header">
        <div>
            <h2>Control de Timesheets</h2>
            <p class="admin-timesheets-subtitle">Vista operativa para PMO y administradores. El talento sigue usando el timesheet actual sin cambios.</p>
        </div>
    </header>

    <form method="GET" action="<?= $basePath ?>/admin/timesheets" class="admin-timesheets-filters card">
        <div class="filters-row">
            <label>
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
            <label>
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
            <label>
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
            <label>
                <span>Semana</span>
                <input type="week" name="week" value="<?= htmlspecialchars($selectedWeek) ?>">
            </label>
            <label>
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
            <div class="filters-actions">
                <button type="submit" class="btn primary">Aplicar</button>
            </div>
        </div>
    </form>

    <div class="admin-timesheets-table-card card">
        <div class="table-toolbar">
            <div class="table-summary">
                <strong><?= count($aggregatedRows) ?></strong> registro(s) · <strong><?= number_format((float) ($totals['hours'] ?? 0), 1) ?>h</strong> total
            </div>
        </div>
        <div class="table-wrapper">
            <table class="admin-timesheets-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Cliente</th>
                        <th>Proyecto</th>
                        <th>Semana</th>
                        <th>Horas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($aggregatedRows)): ?>
                        <tr>
                            <td colspan="7" class="empty-cell">No hay registros con estos filtros.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($aggregatedRows as $row): ?>
                            <?php
                            $weekStart = (string) ($row['week_start'] ?? '');
                            $targetUserId = (int) ($row['user_id'] ?? 0);
                            $status = (string) ($row['status'] ?? 'draft');
                            $detailRows = array_filter($rows, static function ($r) use ($row) {
                                $d = trim((string) ($r['date'] ?? ''));
                                if ($d === '') return false;
                                try {
                                    $ws = (new DateTimeImmutable($d))->modify('monday this week')->format('Y-m-d');
                                } catch (\Throwable $e) {
                                    return false;
                                }
                                return $ws === ($row['week_start'] ?? '')
                                    && (int) ($r['user_id'] ?? 0) === (int) ($row['user_id'] ?? 0)
                                    && (int) ($r['project_id'] ?? 0) === (int) ($row['project_id'] ?? 0);
                            });
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars((string) ($row['user_name'] ?? '—')) ?></strong></td>
                                <td><?= htmlspecialchars((string) ($row['client_name'] ?? '—')) ?></td>
                                <td><?= htmlspecialchars((string) ($row['project_name'] ?? '—')) ?></td>
                                <td><?= htmlspecialchars((string) ($row['week_label'] ?? $weekStart)) ?></td>
                                <td><strong><?= number_format((float) ($row['total_hours'] ?? 0), 1) ?>h</strong></td>
                                <td>
                                    <span class="badge <?= $statusClass($status) ?>"><?= htmlspecialchars($statusLabel($status)) ?></span>
                                </td>
                                <td class="actions-cell">
                                    <div class="actions-inline">
                                        <details class="row-actions">
                                            <summary class="action-trigger">Ver detalle</summary>
                                            <div class="detail-panel">
                                                <?php if (!empty($detailRows)): ?>
                                                    <table class="detail-table">
                                                        <thead><tr><th>Fecha</th><th>Tarea</th><th>Horas</th><th>Estado</th></tr></thead>
                                                        <tbody>
                                                            <?php foreach ($detailRows as $dr): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars((string) ($dr['date'] ?? '')) ?></td>
                                                                    <td><?= htmlspecialchars((string) ($dr['task_name'] ?? '—')) ?></td>
                                                                    <td><?= number_format((float) ($dr['hours'] ?? 0), 2) ?>h</td>
                                                                    <td><span class="badge <?= $statusClass((string) ($dr['status'] ?? '')) ?>"><?= htmlspecialchars($statusLabel((string) ($dr['status'] ?? ''))) ?></span></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                <?php else: ?>
                                                    <p class="section-muted">Sin detalle de registros.</p>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                        <?php if ($canApprove && in_array($status, ['submitted', 'pending', 'pending_approval'], true)): ?>
                                            <form method="POST" action="<?= $basePath ?>/timesheets/approve-week" class="inline-form">
                                                <input type="hidden" name="week_start" value="<?= htmlspecialchars($weekStart) ?>">
                                                <input type="hidden" name="target_user_id" value="<?= $targetUserId ?>">
                                                <input type="hidden" name="status" value="approved">
                                                <button type="submit" class="btn btn-sm success">Aprobar</button>
                                            </form>
                                            <form method="POST" action="<?= $basePath ?>/timesheets/approve-week" class="inline-form reject-form" data-reject-form>
                                                <input type="hidden" name="week_start" value="<?= htmlspecialchars($weekStart) ?>">
                                                <input type="hidden" name="target_user_id" value="<?= $targetUserId ?>">
                                                <input type="hidden" name="status" value="rejected">
                                                <input type="hidden" name="comment" value="" data-comment-input>
                                                <button type="submit" class="btn btn-sm danger">Rechazar</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<style>
    .admin-timesheets { display: flex; flex-direction: column; gap: 16px; }
    .admin-timesheets-header h2 { margin: 0 0 4px; font-size: 22px; color: var(--text-primary); }
    .admin-timesheets-subtitle { margin: 0; color: var(--text-secondary); font-size: 13px; }
    .admin-timesheets-filters.card { padding: 14px 18px; }
    .admin-timesheets-filters .filters-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        align-items: end;
    }
    .admin-timesheets-filters label { display: flex; flex-direction: column; gap: 4px; font-size: 13px; }
    .admin-timesheets-filters label span { font-weight: 600; color: var(--text-secondary); }
    .admin-timesheets-filters select, .admin-timesheets-filters input[type="week"] {
        padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--surface);
    }
    .filters-actions { display: flex; align-items: flex-end; }
    .admin-timesheets-table-card { padding: 0; overflow: hidden; }
    .table-toolbar { padding: 12px 16px; border-bottom: 1px solid var(--border); background: color-mix(in srgb, var(--text-secondary) 8%, var(--background)); }
    .table-summary { font-size: 13px; color: var(--text-secondary); }
    .table-summary strong { color: var(--text-primary); }
    .admin-timesheets-table { width: 100%; border-collapse: collapse; }
    .admin-timesheets-table th, .admin-timesheets-table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid var(--border); font-size: 13px; }
    .admin-timesheets-table th {
        font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);
        background: color-mix(in srgb, var(--text-secondary) 12%, var(--background)); font-weight: 700;
    }
    .admin-timesheets-table tbody tr:hover { background: color-mix(in srgb, var(--text-secondary) 6%, var(--background)); }
    .empty-cell { color: var(--text-secondary); font-style: italic; padding: 24px !important; text-align: center; }
    .badge { display: inline-flex; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
    .badge.status-approved { background: color-mix(in srgb, var(--success) 20%, var(--background)); color: var(--success); }
    .badge.status-rejected { background: color-mix(in srgb, var(--danger) 20%, var(--background)); color: var(--danger); }
    .badge.status-submitted { background: color-mix(in srgb, var(--warning) 20%, var(--background)); color: var(--warning); }
    .badge.status-draft { background: color-mix(in srgb, var(--text-secondary) 18%, var(--background)); color: var(--text-secondary); }
    .actions-cell { white-space: nowrap; }
    .row-actions { display: inline-block; }
    .action-trigger { cursor: pointer; padding: 6px 10px; border-radius: 8px; font-weight: 600; font-size: 12px; color: var(--primary); list-style: none; }
    .action-trigger::-webkit-details-marker { display: none; }
    .action-trigger:hover { background: color-mix(in srgb, var(--primary) 12%, var(--background)); }
    .detail-panel { padding: 14px; margin-top: 8px; border: 1px solid var(--border); border-radius: 12px; background: color-mix(in srgb, var(--text-secondary) 6%, var(--background)); }
    .detail-table { width: 100%; font-size: 12px; margin-bottom: 12px; }
    .detail-table th, .detail-table td { padding: 6px 8px; }
    .approval-actions { display: flex; gap: 8px; margin-top: 10px; }
    .inline-form { display: inline; }
    .btn { padding: 8px 14px; border-radius: 8px; font-weight: 600; cursor: pointer; border: 1px solid var(--border); }
    .btn.primary { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
    .btn.btn-sm { padding: 6px 10px; font-size: 12px; }
    .btn.success { background: color-mix(in srgb, var(--success) 25%, var(--background)); color: var(--success); border-color: var(--success); }
    .btn.danger { background: color-mix(in srgb, var(--danger) 15%, var(--background)); color: var(--danger); border-color: var(--danger); }
    .actions-inline { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    @media (max-width: 900px) {
        .admin-timesheets-filters .filters-row { grid-template-columns: repeat(2, 1fr); }
    }
</style>

<script>
document.querySelectorAll('[data-reject-form]').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        var input = form.querySelector('[data-comment-input]');
        if (!input || input.value.trim() !== '') return;
        e.preventDefault();
        var comment = prompt('Comentario de rechazo (requerido):');
        if (!comment || comment.trim() === '') return;
        input.value = comment.trim();
        form.submit();
    });
});
</script>
