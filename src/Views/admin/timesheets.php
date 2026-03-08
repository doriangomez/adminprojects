<?php
$basePath = $basePath ?? '';
$report = is_array($report ?? null) ? $report : [];
$totals = is_array($report['totals'] ?? null) ? $report['totals'] : ['entries' => 0, 'hours' => 0];
$rows = is_array($report['rows'] ?? null) ? $report['rows'] : [];
$byUser = is_array($report['by_user'] ?? null) ? $report['by_user'] : [];
$byProject = is_array($report['by_project'] ?? null) ? $report['by_project'] : [];
$byClient = is_array($report['by_client'] ?? null) ? $report['by_client'] : [];
$statusBreakdown = is_array($report['status_breakdown'] ?? null) ? $report['status_breakdown'] : [];
$filterOptions = is_array($report['filter_options'] ?? null) ? $report['filter_options'] : [];
$users = is_array($filterOptions['users'] ?? null) ? $filterOptions['users'] : [];
$projects = is_array($filterOptions['projects'] ?? null) ? $filterOptions['projects'] : [];
$clients = is_array($filterOptions['clients'] ?? null) ? $filterOptions['clients'] : [];
$statuses = is_array($filterOptions['statuses'] ?? null) ? $filterOptions['statuses'] : [];
$filters = is_array($filters ?? null) ? $filters : [];
$selectedWeek = (string) ($weekValue ?? (new DateTimeImmutable('monday this week'))->format('o-\\WW'));
$weekStartDate = (string) ($filters['week_start'] ?? '');
$canApprove = !empty($canApprove);

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
    return match (strtolower(trim($status))) {
        'approved' => 'ts-status approved',
        'rejected' => 'ts-status rejected',
        'submitted', 'pending', 'pending_approval' => 'ts-status submitted',
        'draft' => 'ts-status draft',
        default => 'ts-status draft',
    };
};

// Build week-level rows grouped by user (approval is per user+week)
$userWeekRows = [];
foreach ($rows as $row) {
    $userId = (int) ($row['user_id'] ?? 0);
    if (!isset($userWeekRows[$userId])) {
        $userWeekRows[$userId] = [
            'user_id'     => $userId,
            'user_name'   => (string) ($row['user_name'] ?? 'Sin usuario'),
            'total_hours' => 0.0,
            'projects'    => [],
            'clients'     => [],
            'statuses'    => [],
        ];
    }
    $userWeekRows[$userId]['total_hours'] += (float) ($row['hours'] ?? 0);
    $userWeekRows[$userId]['statuses'][] = strtolower(trim((string) ($row['status'] ?? '')));
    $projId = (int) ($row['project_id'] ?? 0);
    if ($projId > 0 && !isset($userWeekRows[$userId]['projects'][$projId])) {
        $userWeekRows[$userId]['projects'][$projId] = (string) ($row['project_name'] ?? 'Sin proyecto');
    }
    $clientName = (string) ($row['client_name'] ?? '');
    if ($clientName !== '' && !in_array($clientName, $userWeekRows[$userId]['clients'], true)) {
        $userWeekRows[$userId]['clients'][] = $clientName;
    }
}

// Compute dominant status per user
$dominantStatus = static function (array $statuses): string {
    if (in_array('submitted', $statuses, true) || in_array('pending_approval', $statuses, true) || in_array('pending', $statuses, true)) {
        return 'submitted';
    }
    if (in_array('rejected', $statuses, true)) {
        return 'rejected';
    }
    if (in_array('approved', $statuses, true) && !in_array('draft', $statuses, true)) {
        return 'approved';
    }
    return 'draft';
};

foreach ($userWeekRows as &$uRow) {
    $uRow['dominant_status'] = $dominantStatus($uRow['statuses']);
    $uRow['total_hours'] = round($uRow['total_hours'], 2);
}
unset($uRow);

usort($userWeekRows, static function (array $a, array $b): int {
    $order = ['submitted' => 0, 'rejected' => 1, 'draft' => 2, 'approved' => 3];
    $diff = ($order[$a['dominant_status']] ?? 9) <=> ($order[$b['dominant_status']] ?? 9);
    return $diff !== 0 ? $diff : strcmp((string) ($a['user_name'] ?? ''), (string) ($b['user_name'] ?? ''));
});

// Format week range label
$weekLabel = $selectedWeek;
if ($weekStartDate !== '') {
    $start = DateTimeImmutable::createFromFormat('Y-m-d', $weekStartDate);
    if ($start) {
        $end = $start->modify('+4 days');
        $weekLabel = $start->format('d') . '–' . $end->format('d M');
    }
}
?>

<style>
    .ats-page {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .ats-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        padding: 18px 20px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow: 0 12px 28px color-mix(in srgb, var(--text-primary) 7%, var(--background));
    }

    .ats-header h1 {
        margin: 0 0 4px;
        font-size: 24px;
        color: var(--text-primary);
    }

    .ats-header p {
        margin: 0;
        color: var(--text-secondary);
        font-size: 14px;
    }

    .ats-filter-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 14px 16px;
    }

    .ats-filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(148px, 1fr));
        gap: 10px;
        align-items: end;
    }

    .ats-filter-grid label {
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
    }

    .ats-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
    }

    .ats-kpi {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 14px 16px;
        display: flex;
        gap: 12px;
        align-items: center;
        box-shadow: 0 8px 20px color-mix(in srgb, var(--text-primary) 6%, var(--background));
    }

    .ats-kpi-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: color-mix(in srgb, var(--primary) 14%, var(--background));
        color: var(--primary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .ats-kpi p { margin: 0; }
    .ats-kpi .kpi-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-secondary); }
    .ats-kpi .kpi-value { font-size: 22px; font-weight: 800; color: var(--text-primary); }

    .ats-table-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 24px color-mix(in srgb, var(--text-primary) 6%, var(--background));
    }

    .ats-table-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 18px;
        border-bottom: 1px solid var(--border);
    }

    .ats-table-card-header h3 { margin: 0; font-size: 15px; }
    .ats-table-card-header p { margin: 4px 0 0; font-size: 13px; color: var(--text-secondary); }

    .ats-table {
        width: 100%;
        border-collapse: collapse;
    }

    .ats-table th {
        padding: 10px 14px;
        text-align: left;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-secondary);
        background: color-mix(in srgb, var(--text-secondary) 12%, var(--background));
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }

    .ats-table td {
        padding: 12px 14px;
        font-size: 13px;
        border-bottom: 1px solid color-mix(in srgb, var(--border) 60%, transparent);
        vertical-align: middle;
    }

    .ats-table tbody tr:last-child td { border-bottom: none; }
    .ats-table tbody tr:hover { background: color-mix(in srgb, var(--text-secondary) 6%, var(--background)); }

    .ts-user-name { font-weight: 700; color: var(--text-primary); margin: 0; }
    .ts-user-meta { color: var(--text-secondary); font-size: 12px; margin: 2px 0 0; }

    .ts-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid transparent;
    }

    .ts-status.approved   { background: color-mix(in srgb, var(--success) 16%, var(--background)); color: var(--success); border-color: color-mix(in srgb, var(--success) 35%, var(--background)); }
    .ts-status.submitted  { background: color-mix(in srgb, var(--warning) 18%, var(--background)); color: color-mix(in srgb, var(--warning) 80%, var(--text-primary)); border-color: color-mix(in srgb, var(--warning) 35%, var(--background)); }
    .ts-status.rejected   { background: color-mix(in srgb, var(--danger) 16%, var(--background)); color: var(--danger); border-color: color-mix(in srgb, var(--danger) 35%, var(--background)); }
    .ts-status.draft      { background: color-mix(in srgb, var(--text-secondary) 14%, var(--background)); color: var(--text-secondary); border-color: color-mix(in srgb, var(--text-secondary) 28%, var(--background)); }

    .ts-hours { font-size: 16px; font-weight: 800; color: var(--text-primary); }

    .ts-actions { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }

    .ts-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid var(--border);
        cursor: pointer;
        text-decoration: none;
        background: var(--surface);
        color: var(--text-primary);
        transition: background 0.15s, transform 0.15s;
    }

    .ts-btn:hover { transform: translateY(-1px); }
    .ts-btn.approve { background: color-mix(in srgb, var(--success) 14%, var(--background)); color: var(--success); border-color: color-mix(in srgb, var(--success) 35%, var(--background)); }
    .ts-btn.reject  { background: color-mix(in srgb, var(--danger) 12%, var(--background)); color: var(--danger); border-color: color-mix(in srgb, var(--danger) 30%, var(--background)); }
    .ts-btn.view    { background: color-mix(in srgb, var(--primary) 12%, var(--background)); color: var(--primary); border-color: color-mix(in srgb, var(--primary) 30%, var(--background)); }

    .ts-week-chip {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        background: color-mix(in srgb, var(--text-secondary) 12%, var(--background));
        color: var(--text-primary);
    }

    .ats-detail-section details > summary {
        cursor: pointer;
        list-style: none;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 18px;
        font-weight: 700;
        font-size: 14px;
        color: var(--text-secondary);
        border-top: 1px solid var(--border);
        user-select: none;
    }

    .ats-detail-section details > summary::-webkit-details-marker { display: none; }
    .ats-detail-section details[open] > summary { color: var(--text-primary); }

    .ats-reject-modal {
        position: fixed;
        inset: 0;
        z-index: 60;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .ats-reject-modal.is-open { display: flex; }

    .ats-reject-modal__backdrop {
        position: absolute;
        inset: 0;
        background: color-mix(in srgb, var(--text-primary) 45%, transparent);
    }

    .ats-reject-modal__panel {
        position: relative;
        z-index: 1;
        background: var(--surface);
        border-radius: 16px;
        padding: 20px;
        width: min(480px, 90vw);
        display: flex;
        flex-direction: column;
        gap: 14px;
        box-shadow: 0 24px 48px color-mix(in srgb, var(--text-primary) 24%, transparent);
    }

    .ats-reject-modal__header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .ats-reject-modal__header h4 { margin: 0; }

    .ats-reject-modal__field {
        display: flex;
        flex-direction: column;
        gap: 6px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
    }

    .ats-reject-modal__field textarea {
        border-radius: 10px;
        border: 1px solid var(--border);
        padding: 10px;
        font-size: 13px;
        resize: vertical;
        color: var(--text-primary);
        background: var(--background);
    }

    .ats-reject-modal__actions { display: flex; justify-content: flex-end; gap: 8px; }

    .ats-summary-row { display: flex; flex-wrap: wrap; gap: 12px; }
    .ats-mini-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .ats-mini-table th { padding: 8px 12px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); background: color-mix(in srgb, var(--text-secondary) 10%, var(--background)); }
    .ats-mini-table td { padding: 8px 12px; border-bottom: 1px solid color-mix(in srgb, var(--border) 50%, transparent); }
    .ats-mini-table tbody tr:last-child td { border-bottom: none; }

    .ats-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
    }

    .ats-summary-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
    }

    .ats-summary-card h5 {
        margin: 0;
        padding: 10px 14px;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-secondary);
        border-bottom: 1px solid var(--border);
        font-weight: 700;
    }

    .empty-ts {
        padding: 24px;
        text-align: center;
        color: var(--text-secondary);
        font-weight: 600;
    }

    @media (max-width: 900px) {
        .ats-filter-grid { grid-template-columns: repeat(2, 1fr); }
        .ats-table th:nth-child(3),
        .ats-table td:nth-child(3) { display: none; }
    }
</style>

<div class="ats-page">

    <!-- Header -->
    <div class="ats-header">
        <div>
            <p style="margin:0 0 4px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-secondary);">Administración</p>
            <h1>Control de Timesheets</h1>
            <p>Vista operativa para PMO y administración. No altera el flujo de registro ni aprobación.</p>
        </div>
        <?php if ($weekStartDate !== ''): ?>
            <span class="ts-week-chip">Semana: <?= htmlspecialchars($weekLabel) ?></span>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="ats-filter-card">
        <form method="GET" action="<?= $basePath ?>/admin/timesheets" class="ats-filter-grid">
            <label>Usuario
                <select name="user_id">
                    <option value="0">Todos</option>
                    <?php foreach ($users as $userOption): ?>
                        <?php $uid = (int) ($userOption['user_id'] ?? 0); ?>
                        <option value="<?= $uid ?>" <?= $uid === (int) ($filters['user_id'] ?? 0) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($userOption['user_name'] ?? 'Usuario')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Proyecto
                <select name="project_id">
                    <option value="0">Todos</option>
                    <?php foreach ($projects as $projectOption): ?>
                        <?php $pid = (int) ($projectOption['project_id'] ?? 0); ?>
                        <option value="<?= $pid ?>" <?= $pid === (int) ($filters['project_id'] ?? 0) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($projectOption['project_name'] ?? 'Proyecto')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Cliente
                <select name="client_id">
                    <option value="0">Todos</option>
                    <?php foreach ($clients as $clientOption): ?>
                        <?php $cid = (int) ($clientOption['client_id'] ?? 0); ?>
                        <option value="<?= $cid ?>" <?= $cid === (int) ($filters['client_id'] ?? 0) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($clientOption['client_name'] ?? 'Cliente')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Semana
                <input type="week" name="week" value="<?= htmlspecialchars($selectedWeek) ?>">
            </label>
            <label>Estado
                <select name="status">
                    <option value="">Todos</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= strtolower((string) ($filters['status'] ?? '')) === strtolower((string) $status) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statusLabel((string) $status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div style="display:flex; gap:8px; align-items:flex-end;">
                <button type="submit" class="btn primary" style="flex:1;">Aplicar</button>
                <a href="<?= $basePath ?>/admin/timesheets" class="btn" style="flex-shrink:0;">Limpiar</a>
            </div>
        </form>
    </div>

    <!-- KPIs -->
    <div class="ats-kpi-grid">
        <div class="ats-kpi">
            <div class="ats-kpi-icon">👥</div>
            <div>
                <p class="kpi-label">Usuarios</p>
                <p class="kpi-value"><?= count($byUser) ?></p>
            </div>
        </div>
        <div class="ats-kpi">
            <div class="ats-kpi-icon">⏱</div>
            <div>
                <p class="kpi-label">Horas registradas</p>
                <p class="kpi-value"><?= number_format((float) ($totals['hours'] ?? 0), 1) ?>h</p>
            </div>
        </div>
        <div class="ats-kpi">
            <div class="ats-kpi-icon">📁</div>
            <div>
                <p class="kpi-label">Proyectos con carga</p>
                <p class="kpi-value"><?= count($byProject) ?></p>
            </div>
        </div>
        <div class="ats-kpi">
            <div class="ats-kpi-icon">📋</div>
            <div>
                <p class="kpi-label">Registros totales</p>
                <p class="kpi-value"><?= (int) ($totals['entries'] ?? 0) ?></p>
            </div>
        </div>
    </div>

    <!-- Main operational table -->
    <div class="ats-table-card">
        <div class="ats-table-card-header">
            <div>
                <h3>Control operativo por usuario</h3>
                <p>Una fila por talento en la semana seleccionada · <?= htmlspecialchars($weekLabel) ?></p>
            </div>
        </div>

        <?php if (empty($userWeekRows)): ?>
            <p class="empty-ts">No hay registros para los filtros seleccionados.</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="ats-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Cliente · Proyecto</th>
                            <th>Semana</th>
                            <th>Horas</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userWeekRows as $uRow): ?>
                            <?php
                                $projectList = array_values($uRow['projects']);
                                $clientList = $uRow['clients'];
                                $primaryProject = count($projectList) === 1
                                    ? $projectList[0]
                                    : (count($projectList) > 1 ? $projectList[0] . ' +' . (count($projectList) - 1) . ' más' : 'Sin proyecto');
                                $primaryClient = count($clientList) >= 1 ? $clientList[0] : 'Sin cliente';
                                $ds = $uRow['dominant_status'];
                                $tsWeekUrl = $basePath . '/timesheets?week=' . urlencode($selectedWeek);
                            ?>
                            <tr>
                                <td>
                                    <p class="ts-user-name"><?= htmlspecialchars($uRow['user_name']) ?></p>
                                </td>
                                <td>
                                    <p style="margin:0; font-weight:600; color:var(--text-primary);"><?= htmlspecialchars($primaryClient) ?></p>
                                    <p class="ts-user-meta"><?= htmlspecialchars($primaryProject) ?></p>
                                </td>
                                <td>
                                    <span class="ts-week-chip"><?= htmlspecialchars($weekLabel) ?></span>
                                </td>
                                <td>
                                    <span class="ts-hours"><?= number_format($uRow['total_hours'], 1) ?>h</span>
                                </td>
                                <td>
                                    <span class="<?= $statusBadgeClass($ds) ?>">
                                        <?php
                                        $dot = match ($ds) {
                                            'approved' => '✓',
                                            'rejected' => '✕',
                                            'submitted' => '→',
                                            default    => '○',
                                        };
                                        echo $dot . ' ' . htmlspecialchars($statusLabel($ds));
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="ts-actions">
                                        <a class="ts-btn view" href="<?= htmlspecialchars($tsWeekUrl) ?>">
                                            Ver semana
                                        </a>
                                        <?php if ($canApprove && in_array($ds, ['submitted', 'draft'], true)): ?>
                                            <form method="POST" action="<?= $basePath ?>/timesheets/approve-week" style="display:contents;">
                                                <input type="hidden" name="week_start" value="<?= htmlspecialchars($weekStartDate) ?>">
                                                <input type="hidden" name="target_user_id" value="<?= (int) $uRow['user_id'] ?>">
                                                <input type="hidden" name="status" value="approved">
                                                <button type="submit" class="ts-btn approve" onclick="return confirm('¿Aprobar todos los registros de <?= htmlspecialchars(addslashes($uRow['user_name'])) ?> en esta semana?');">
                                                    ✓ Aprobar
                                                </button>
                                            </form>
                                            <button
                                                class="ts-btn reject"
                                                type="button"
                                                data-open-reject
                                                data-user-id="<?= (int) $uRow['user_id'] ?>"
                                                data-user-name="<?= htmlspecialchars($uRow['user_name']) ?>"
                                                data-week-start="<?= htmlspecialchars($weekStartDate) ?>">
                                                ✕ Rechazar
                                            </button>
                                        <?php elseif ($canApprove && $ds === 'approved'): ?>
                                            <span style="font-size:12px; color:var(--success); font-weight:700;">Semana aprobada</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Detail breakdown: per-project rows -->
        <?php if (!empty($rows)): ?>
            <div class="ats-detail-section">
                <details>
                    <summary>
                        <span>▶</span>
                        Ver detalle completo por entrada (<?= count($rows) ?> registros)
                    </summary>
                    <div style="overflow-x:auto; padding: 0 0 12px;">
                        <table class="ats-table">
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
                                    <tr>
                                        <td style="white-space:nowrap;"><?= htmlspecialchars((string) ($row['date'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars((string) ($row['user_name'] ?? 'Sin usuario')) ?></td>
                                        <td><?= htmlspecialchars((string) ($row['client_name'] ?? 'Sin cliente')) ?></td>
                                        <td><?= htmlspecialchars((string) ($row['project_name'] ?? 'Sin proyecto')) ?></td>
                                        <td><?= htmlspecialchars((string) ($row['task_name'] ?? 'Sin tarea')) ?></td>
                                        <td><?= number_format((float) ($row['hours'] ?? 0), 2) ?>h</td>
                                        <td><span class="<?= $statusBadgeClass((string) ($row['status'] ?? '')) ?>"><?= htmlspecialchars($statusLabel((string) ($row['status'] ?? ''))) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        <?php endif; ?>
    </div>

    <!-- Summary panels -->
    <?php if (!empty($byProject) || !empty($byClient) || !empty($statusBreakdown)): ?>
        <div class="ats-summary-grid">
            <?php if (!empty($byProject)): ?>
                <div class="ats-summary-card">
                    <h5>Horas por proyecto</h5>
                    <table class="ats-mini-table">
                        <thead><tr><th>Proyecto</th><th>Horas</th></tr></thead>
                        <tbody>
                            <?php foreach ($byProject as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($row['project_name'] ?? 'Sin proyecto')) ?></td>
                                    <td><strong><?= number_format((float) ($row['total_hours'] ?? 0), 1) ?>h</strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php if (!empty($byClient)): ?>
                <div class="ats-summary-card">
                    <h5>Horas por cliente</h5>
                    <table class="ats-mini-table">
                        <thead><tr><th>Cliente</th><th>Horas</th></tr></thead>
                        <tbody>
                            <?php foreach ($byClient as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($row['client_name'] ?? 'Sin cliente')) ?></td>
                                    <td><strong><?= number_format((float) ($row['total_hours'] ?? 0), 1) ?>h</strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php if (!empty($statusBreakdown)): ?>
                <div class="ats-summary-card">
                    <h5>Estado de horas</h5>
                    <table class="ats-mini-table">
                        <thead><tr><th>Estado</th><th>Horas</th></tr></thead>
                        <tbody>
                            <?php foreach ($statusBreakdown as $row): ?>
                                <?php $st = (string) ($row['status'] ?? ''); ?>
                                <tr>
                                    <td><span class="<?= $statusBadgeClass($st) ?>"><?= htmlspecialchars($statusLabel($st)) ?></span></td>
                                    <td><strong><?= number_format((float) ($row['total_hours'] ?? 0), 1) ?>h</strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<!-- Reject modal -->
<?php if ($canApprove): ?>
<div class="ats-reject-modal" id="reject-modal" aria-hidden="true">
    <div class="ats-reject-modal__backdrop" data-close-reject></div>
    <div class="ats-reject-modal__panel" role="dialog" aria-modal="true" aria-labelledby="reject-modal-title">
        <div class="ats-reject-modal__header">
            <h4 id="reject-modal-title">Rechazar semana</h4>
            <button type="button" class="icon-btn" data-close-reject aria-label="Cerrar">✕</button>
        </div>
        <p id="reject-modal-desc" style="margin:0; color:var(--text-secondary); font-size:13px;"></p>
        <form method="POST" action="<?= $basePath ?>/timesheets/approve-week">
            <input type="hidden" name="status" value="rejected">
            <input type="hidden" name="week_start" id="reject-week-start" value="">
            <input type="hidden" name="target_user_id" id="reject-user-id" value="">
            <div class="ats-reject-modal__field" style="margin-top:10px;">
                <label for="reject-comment">Motivo del rechazo <span style="color:var(--danger);">*</span></label>
                <textarea id="reject-comment" name="comment" rows="4" placeholder="Explica por qué se rechaza esta semana..." required></textarea>
            </div>
            <div class="ats-reject-modal__actions" style="margin-top:12px;">
                <button type="button" class="btn" data-close-reject>Cancelar</button>
                <button type="submit" class="btn danger">Rechazar semana</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('reject-modal');
    const desc = document.getElementById('reject-modal-desc');
    const weekInput = document.getElementById('reject-week-start');
    const userInput = document.getElementById('reject-user-id');

    document.querySelectorAll('[data-open-reject]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const userId = btn.getAttribute('data-user-id');
            const userName = btn.getAttribute('data-user-name');
            const weekStart = btn.getAttribute('data-week-start');
            if (userInput) userInput.value = userId || '';
            if (weekInput) weekInput.value = weekStart || '';
            if (desc) desc.textContent = 'Usuario: ' + userName;
            if (modal) {
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            }
        });
    });

    document.querySelectorAll('[data-close-reject]').forEach(function (el) {
        el.addEventListener('click', function () {
            if (modal) {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
            }
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }
    });

    document.querySelectorAll('.ats-detail-section details > summary').forEach(function (s) {
        s.addEventListener('toggle', function () {}, true);
    });
})();
</script>
<?php endif; ?>
