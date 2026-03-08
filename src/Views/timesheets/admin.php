<?php
$basePath = $basePath ?? '';
$rows = is_array($rows ?? null) ? $rows : [];
$totals = is_array($totals ?? null) ? $totals : [];
$kpis = is_array($kpis ?? null) ? $kpis : [];
$users = is_array($users ?? null) ? $users : [];
$projects = is_array($projects ?? null) ? $projects : [];
$clients = is_array($clients ?? null) ? $clients : [];
$filters = is_array($filters ?? null) ? $filters : [];

$statusLabels = [
    'draft' => 'Borrador',
    'submitted' => 'Enviado',
    'pending_approval' => 'Pendiente aprobación',
    'approved' => 'Aprobado',
    'rejected' => 'Rechazado',
];

$statusBadgeClass = static function (string $status): string {
    return match ($status) {
        'approved' => 'ts-status-approved',
        'rejected' => 'ts-status-rejected',
        'pending_approval', 'submitted' => 'ts-status-pending',
        default => 'ts-status-draft',
    };
};

$totalHours = (float) ($totals['total_hours'] ?? 0);
$totalEntries = (int) ($totals['total_entries'] ?? 0);
$kpiApproved = (float) ($kpis['approved_hours'] ?? 0);
$kpiPending = (float) ($kpis['pending_hours'] ?? 0);
$kpiDraft = (float) ($kpis['draft_hours'] ?? 0);
$kpiTotal = (float) ($kpis['total_hours'] ?? 0);
$kpiUsers = (int) ($kpis['active_users'] ?? 0);
$kpiProjects = (int) ($kpis['active_projects'] ?? 0);

$filterUserId = (int) ($filters['user_id'] ?? 0);
$filterProjectId = (int) ($filters['project_id'] ?? 0);
$filterClientId = (int) ($filters['client_id'] ?? 0);
$filterWeek = (string) ($filters['week'] ?? '');
$filterStatus = (string) ($filters['status'] ?? '');
?>

<style>
    .ts-admin-hero {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        padding: 18px 20px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow: 0 12px 30px color-mix(in srgb, var(--text-primary) 8%, var(--background));
    }

    .ts-admin-hero h1 { margin: 0 0 4px; font-size: 24px; color: var(--text-primary); }
    .ts-admin-hero p { margin: 0; color: var(--text-secondary); font-weight: 500; }

    .ts-admin-tabs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .ts-tab {
        padding: 8px 14px;
        border: 1px solid var(--border);
        border-radius: 999px;
        text-decoration: none;
        color: var(--text-secondary);
        font-size: 13px;
        font-weight: 600;
        transition: background 0.15s;
    }

    .ts-tab.active, .ts-tab:hover {
        background: color-mix(in srgb, var(--primary) 16%, var(--surface));
        border-color: color-mix(in srgb, var(--primary) 40%, var(--border));
        color: var(--text-primary);
    }

    .ts-admin-kpis {
        display: grid;
        grid-template-columns: repeat(6, minmax(120px, 1fr));
        gap: 10px;
    }

    .ts-kpi-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 14px 16px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .ts-kpi-card .kpi-label {
        font-size: 12px;
        color: var(--text-secondary);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .ts-kpi-card .kpi-value {
        font-size: 22px;
        font-weight: 800;
        color: var(--text-primary);
        line-height: 1.2;
    }

    .ts-admin-filters {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 14px 16px;
    }

    .ts-admin-filters form {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: flex-end;
    }

    .ts-admin-filters label {
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        min-width: 140px;
    }

    .ts-admin-filters select,
    .ts-admin-filters input {
        height: 36px;
        border-radius: 8px;
        border: 1px solid var(--border);
        padding: 0 10px;
        background: var(--background);
        color: var(--text-primary);
        font-size: 13px;
    }

    .ts-admin-filters .filter-actions { display: flex; gap: 8px; align-self: flex-end; }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 13px;
        text-decoration: none;
        border: 1px solid var(--border);
        cursor: pointer;
        transition: background 0.15s;
        background: var(--surface);
        color: var(--text-primary);
    }

    .btn.primary {
        background: var(--primary);
        border-color: var(--primary);
        color: var(--text-primary);
    }

    .btn.ghost {
        background: color-mix(in srgb, var(--text-secondary) 14%, var(--background));
    }

    .ts-admin-table-wrap {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        overflow: hidden;
    }

    .ts-admin-table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 16px;
        border-bottom: 1px solid var(--border);
    }

    .ts-admin-table-header h3 {
        margin: 0;
        font-size: 16px;
        color: var(--text-primary);
    }

    .ts-summary-badge {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        background: color-mix(in srgb, var(--text-secondary) 12%, var(--background));
        padding: 4px 10px;
        border-radius: 999px;
    }

    .ts-admin-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .ts-admin-table th {
        text-align: left;
        padding: 10px 14px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-secondary);
        background: color-mix(in srgb, var(--text-secondary) 8%, var(--background));
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }

    .ts-admin-table td {
        padding: 10px 14px;
        border-bottom: 1px solid color-mix(in srgb, var(--border) 50%, transparent);
        color: var(--text-primary);
        vertical-align: top;
    }

    .ts-admin-table tbody tr:last-child td { border-bottom: none; }

    .ts-admin-table tbody tr:hover td {
        background: color-mix(in srgb, var(--primary) 5%, var(--background));
    }

    .ts-hours-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 54px;
        height: 28px;
        border-radius: 8px;
        font-weight: 800;
        font-size: 13px;
        background: color-mix(in srgb, var(--primary) 14%, var(--background));
        color: var(--primary);
        border: 1px solid color-mix(in srgb, var(--primary) 30%, var(--border));
    }

    .ts-status-approved { background: color-mix(in srgb, var(--success) 16%, var(--background)); color: var(--success); border: 1px solid color-mix(in srgb, var(--success) 35%, var(--border)); padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .ts-status-rejected { background: color-mix(in srgb, var(--danger) 16%, var(--background)); color: var(--danger); border: 1px solid color-mix(in srgb, var(--danger) 35%, var(--border)); padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .ts-status-pending { background: color-mix(in srgb, var(--warning) 16%, var(--background)); color: var(--warning); border: 1px solid color-mix(in srgb, var(--warning) 35%, var(--border)); padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .ts-status-draft { background: color-mix(in srgb, var(--text-secondary) 14%, var(--background)); color: var(--text-secondary); border: 1px solid var(--border); padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; white-space: nowrap; }

    .ts-user-name { font-weight: 700; }
    .ts-project-name { font-weight: 600; font-size: 13px; }
    .ts-client-name { font-size: 12px; color: var(--text-secondary); }
    .ts-task-name { font-size: 12px; color: var(--text-secondary); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ts-date-cell { font-weight: 600; white-space: nowrap; }

    .ts-empty-state {
        padding: 40px;
        text-align: center;
        color: var(--text-secondary);
        font-weight: 600;
    }

    .ts-totals-row td {
        font-weight: 800;
        background: color-mix(in srgb, var(--text-secondary) 8%, var(--background));
        border-top: 2px solid var(--border);
    }

    @media (max-width: 1100px) {
        .ts-admin-kpis { grid-template-columns: repeat(3, minmax(120px, 1fr)); }
        .ts-admin-filters form { flex-direction: column; }
    }
</style>

<div class="ts-admin-hero">
    <div>
        <p style="margin:0; color: var(--text-secondary); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; font-size: 12px;">PMO · Control</p>
        <h1>Vista Administrativa de Timesheets</h1>
        <p>Horas registradas por usuario, proyecto, cliente y tarea</p>
    </div>
    <div class="ts-admin-tabs">
        <a class="ts-tab" href="<?= $basePath ?>/timesheets">Registro de horas</a>
        <a class="ts-tab" href="<?= $basePath ?>/approvals">Aprobación</a>
        <a class="ts-tab" href="<?= $basePath ?>/timesheets/analytics">Analítica gerencial</a>
        <a class="ts-tab active" href="<?= $basePath ?>/admin/timesheets">Vista PMO</a>
    </div>
</div>

<div class="ts-admin-kpis">
    <div class="ts-kpi-card">
        <span class="kpi-label">Total horas (histórico)</span>
        <span class="kpi-value"><?= number_format($kpiTotal, 1, ',', '.') ?>h</span>
    </div>
    <div class="ts-kpi-card" style="border-color: color-mix(in srgb, var(--success) 40%, var(--border));">
        <span class="kpi-label">Horas aprobadas</span>
        <span class="kpi-value" style="color: var(--success);"><?= number_format($kpiApproved, 1, ',', '.') ?>h</span>
    </div>
    <div class="ts-kpi-card" style="border-color: color-mix(in srgb, var(--warning) 40%, var(--border));">
        <span class="kpi-label">Pendientes aprobación</span>
        <span class="kpi-value" style="color: var(--warning);"><?= number_format($kpiPending, 1, ',', '.') ?>h</span>
    </div>
    <div class="ts-kpi-card">
        <span class="kpi-label">Borradores</span>
        <span class="kpi-value"><?= number_format($kpiDraft, 1, ',', '.') ?>h</span>
    </div>
    <div class="ts-kpi-card">
        <span class="kpi-label">Usuarios activos</span>
        <span class="kpi-value"><?= $kpiUsers ?></span>
    </div>
    <div class="ts-kpi-card">
        <span class="kpi-label">Proyectos con horas</span>
        <span class="kpi-value"><?= $kpiProjects ?></span>
    </div>
</div>

<div class="ts-admin-filters">
    <form method="GET" action="<?= $basePath ?>/admin/timesheets">
        <label>
            Usuario
            <select name="user_id">
                <option value="">Todos</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= $filterUserId === (int) $u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($u['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Proyecto
            <select name="project_id">
                <option value="">Todos</option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= (int) ($p['project_id'] ?? 0) ?>" <?= $filterProjectId === (int) ($p['project_id'] ?? 0) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($p['project'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Cliente
            <select name="client_id">
                <option value="">Todos</option>
                <?php foreach ($clients as $cl): ?>
                    <option value="<?= (int) $cl['id'] ?>" <?= $filterClientId === (int) $cl['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($cl['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Semana
            <input type="week" name="week" value="<?= htmlspecialchars($filterWeek) ?>" placeholder="e.g. 2026-W10">
        </label>
        <label>
            Estado
            <select name="status">
                <option value="">Todos</option>
                <?php foreach ($statusLabels as $code => $label): ?>
                    <option value="<?= $code ?>" <?= $filterStatus === $code ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="filter-actions">
            <button type="submit" class="btn primary">Aplicar</button>
            <a href="<?= $basePath ?>/admin/timesheets" class="btn ghost">Limpiar</a>
        </div>
    </form>
</div>

<div class="ts-admin-table-wrap">
    <div class="ts-admin-table-header">
        <h3>Registro detallado de horas</h3>
        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if ($totalEntries > 0): ?>
                <span class="ts-summary-badge"><?= $totalEntries ?> registros · <?= number_format($totalHours, 1, ',', '.') ?>h total</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <div class="ts-empty-state">
            <p>No se encontraron registros con los filtros aplicados.</p>
            <p style="font-size: 13px;">Prueba ajustando los filtros o limpiando la búsqueda.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="ts-admin-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Cliente</th>
                        <th>Proyecto</th>
                        <th>Tarea</th>
                        <th>Fecha</th>
                        <th style="text-align:right;">Horas</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                            $rowDate = $row['date'] ?? '';
                            $rowDateFormatted = '';
                            if ($rowDate !== '') {
                                $ts = strtotime((string) $rowDate);
                                if ($ts) {
                                    $rowDateFormatted = date('d/m/Y', $ts);
                                }
                            }
                            $rowStatus = (string) ($row['status'] ?? '');
                            $rowHours = (float) ($row['hours'] ?? 0);
                        ?>
                        <tr>
                            <td><span class="ts-user-name"><?= htmlspecialchars((string) ($row['user_name'] ?? '—')) ?></span></td>
                            <td><span class="ts-client-name"><?= htmlspecialchars((string) ($row['client_name'] ?? '—')) ?></span></td>
                            <td>
                                <?php if (!empty($row['project_id']) && !empty($row['project_name'])): ?>
                                    <a class="ts-project-name" href="<?= $basePath ?>/projects/<?= (int) $row['project_id'] ?>" style="text-decoration:none; color:inherit;">
                                        <?= htmlspecialchars((string) $row['project_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="ts-project-name" style="color: var(--text-secondary);">Sin proyecto</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="ts-task-name" title="<?= htmlspecialchars((string) ($row['task_title'] ?? '')) ?>">
                                    <?= htmlspecialchars((string) ($row['task_title'] ?? '—')) ?>
                                </span>
                            </td>
                            <td class="ts-date-cell"><?= htmlspecialchars($rowDateFormatted) ?></td>
                            <td style="text-align:right;">
                                <span class="ts-hours-pill"><?= number_format($rowHours, 1) ?>h</span>
                            </td>
                            <td>
                                <span class="<?= $statusBadgeClass($rowStatus) ?>">
                                    <?= htmlspecialchars($statusLabels[$rowStatus] ?? $rowStatus) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($totalEntries > 0): ?>
                    <tfoot>
                        <tr class="ts-totals-row">
                            <td colspan="5"><strong>Total (<?= $totalEntries ?> registros)</strong></td>
                            <td style="text-align:right;"><span class="ts-hours-pill"><?= number_format($totalHours, 1) ?>h</span></td>
                            <td></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    <?php endif; ?>
</div>
