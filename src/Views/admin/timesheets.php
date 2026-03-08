<?php
$entries = is_array($entries ?? null) ? $entries : [];
$summaryByUser = is_array($summaryByUser ?? null) ? $summaryByUser : [];
$summaryByProject = is_array($summaryByProject ?? null) ? $summaryByProject : [];
$summaryByClient = is_array($summaryByClient ?? null) ? $summaryByClient : [];
$globalStats = is_array($globalStats ?? null) ? $globalStats : [];
$allUsers = is_array($allUsers ?? null) ? $allUsers : [];
$allProjects = is_array($allProjects ?? null) ? $allProjects : [];
$allClients = is_array($allClients ?? null) ? $allClients : [];
$filters = is_array($filters ?? null) ? $filters : [];
$weekValue = $weekValue ?? '';
$viewTab = $viewTab ?? 'entries';
$basePath = $basePath ?? '';

$statusLabels = [
    'draft' => 'Borrador',
    'pending' => 'Pendiente',
    'submitted' => 'Enviado',
    'pending_approval' => 'Por aprobar',
    'approved' => 'Aprobado',
    'rejected' => 'Rechazado',
];

$statusBadgeClass = static function (string $status): string {
    return match ($status) {
        'approved' => 'badge success',
        'rejected' => 'badge danger',
        'pending', 'submitted', 'pending_approval' => 'badge warning',
        default => 'badge neutral',
    };
};

$tabUrl = static function (string $tab) use ($basePath, $filters, $weekValue): string {
    $params = ['tab' => $tab];
    if ($weekValue !== '') {
        $params['week'] = $weekValue;
    }
    if (!empty($filters['user_id'])) {
        $params['user_id'] = $filters['user_id'];
    }
    if (!empty($filters['project_id'])) {
        $params['project_id'] = $filters['project_id'];
    }
    if (!empty($filters['client_id'])) {
        $params['client_id'] = $filters['client_id'];
    }
    if (!empty($filters['status'])) {
        $params['status'] = $filters['status'];
    }
    return $basePath . '/admin/timesheets?' . http_build_query($params);
};

?>

<style>
    .admin-ts-hero {
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
    .admin-ts-hero h1 { margin: 0 0 4px; font-size: 26px; color: var(--text-primary); }
    .admin-ts-hero p { margin: 0; color: var(--text-secondary); font-weight: 500; }
    .admin-ts-kpis {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 12px;
    }
    .admin-ts-kpi {
        padding: 14px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: var(--surface);
        box-shadow: 0 10px 24px color-mix(in srgb, var(--text-primary) 6%, var(--background));
    }
    .admin-ts-kpi .label { color: var(--text-secondary); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin: 0; }
    .admin-ts-kpi .value { margin: 4px 0 0; font-size: 22px; color: var(--text-primary); font-weight: 800; }
    .admin-ts-tabs {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        padding: 4px;
        background: color-mix(in srgb, var(--text-secondary) 12%, var(--background));
        border-radius: 12px;
        width: fit-content;
    }
    .admin-ts-tabs a {
        padding: 8px 16px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 700;
        font-size: 13px;
        color: var(--text-secondary);
        border: 1px solid transparent;
        transition: all 0.15s ease;
    }
    .admin-ts-tabs a.active {
        background: var(--surface);
        color: var(--text-primary);
        border-color: var(--border);
        box-shadow: 0 4px 12px color-mix(in srgb, var(--text-primary) 10%, var(--background));
    }
    .admin-ts-tabs a:hover:not(.active) {
        color: var(--text-primary);
        background: color-mix(in srgb, var(--surface) 50%, var(--background));
    }
    .admin-ts-filter {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 14px 16px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
    }
    .admin-ts-filter .filter-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        align-items: end;
    }
    .admin-ts-table {
        width: 100%;
        table-layout: auto;
        border-collapse: collapse;
        background: var(--surface);
        border-radius: 14px;
        overflow: hidden;
        border: 1px solid var(--border);
    }
    .admin-ts-table th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-secondary);
        background: color-mix(in srgb, var(--text-secondary) 14%, var(--background));
        font-weight: 700;
        white-space: nowrap;
        padding: 10px 10px;
        text-align: left;
    }
    .admin-ts-table td {
        padding: 10px 10px;
        border-bottom: 1px solid var(--border);
        font-size: 13px;
        vertical-align: middle;
    }
    .admin-ts-table tbody tr:last-child td { border-bottom: none; }
    .admin-ts-table tbody tr:hover { background: color-mix(in srgb, var(--text-secondary) 8%, var(--background)); }
    .progress-mini { width: 80px; height: 5px; background: color-mix(in srgb, var(--text-secondary) 20%, var(--background)); border-radius: 999px; overflow: hidden; display: inline-block; vertical-align: middle; margin-right: 6px; }
    .progress-mini-bar { height: 100%; border-radius: 999px; }
    .progress-mini-bar.green { background: var(--success); }
    .progress-mini-bar.yellow { background: var(--warning); }
    .progress-mini-bar.red { background: var(--danger); }
    .empty-state { padding: 18px; border-radius: 14px; background: color-mix(in srgb, var(--text-secondary) 12%, var(--background)); border: 1px solid var(--border); color: var(--text-secondary); font-weight: 600; text-align: center; }
    @media (max-width: 960px) {
        .admin-ts-hero { flex-direction: column; }
        .admin-ts-filter .filter-row { grid-template-columns: 1fr; }
    }
</style>

<div class="admin-ts-hero">
    <div>
        <p style="margin:0; color: var(--text-secondary); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; font-size: 12px;">Administración</p>
        <h1>Timesheets PMO</h1>
        <p>Vista consolidada de horas registradas por usuario, proyecto y cliente</p>
    </div>
</div>

<section class="admin-ts-kpis">
    <div class="admin-ts-kpi">
        <p class="label">Total horas</p>
        <p class="value"><?= number_format($globalStats['total_hours'] ?? 0, 1, ',', '.') ?>h</p>
    </div>
    <div class="admin-ts-kpi">
        <p class="label">Aprobadas</p>
        <p class="value"><?= number_format($globalStats['approved_hours'] ?? 0, 1, ',', '.') ?>h</p>
    </div>
    <div class="admin-ts-kpi">
        <p class="label">Pendientes</p>
        <p class="value"><?= number_format($globalStats['pending_hours'] ?? 0, 1, ',', '.') ?>h</p>
    </div>
    <div class="admin-ts-kpi">
        <p class="label">Borrador</p>
        <p class="value"><?= number_format($globalStats['draft_hours'] ?? 0, 1, ',', '.') ?>h</p>
    </div>
    <div class="admin-ts-kpi">
        <p class="label">Usuarios</p>
        <p class="value"><?= (int) ($globalStats['total_users'] ?? 0) ?></p>
    </div>
    <div class="admin-ts-kpi">
        <p class="label">Proyectos</p>
        <p class="value"><?= (int) ($globalStats['total_projects'] ?? 0) ?></p>
    </div>
    <div class="admin-ts-kpi">
        <p class="label">Registros</p>
        <p class="value"><?= number_format($globalStats['total_entries'] ?? 0, 0, ',', '.') ?></p>
    </div>
</section>

<form method="GET" action="<?= $basePath ?>/admin/timesheets" class="admin-ts-filter">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($viewTab) ?>">
    <div class="filter-row">
        <label>
            Usuario
            <select name="user_id">
                <option value="">Todos</option>
                <?php foreach ($allUsers as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= ((int) ($filters['user_id'] ?? 0)) === (int) $u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['name'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Proyecto
            <select name="project_id">
                <option value="">Todos</option>
                <?php foreach ($allProjects as $proj): ?>
                    <option value="<?= (int) $proj['id'] ?>" <?= ((int) ($filters['project_id'] ?? 0)) === (int) $proj['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($proj['name'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Cliente
            <select name="client_id">
                <option value="">Todos</option>
                <?php foreach ($allClients as $cl): ?>
                    <option value="<?= (int) $cl['id'] ?>" <?= ((int) ($filters['client_id'] ?? 0)) === (int) $cl['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cl['name'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Semana
            <input type="week" name="week" value="<?= htmlspecialchars($weekValue) ?>">
        </label>
        <label>
            Estado
            <select name="status">
                <option value="">Todos</option>
                <option value="draft" <?= ($filters['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Borrador</option>
                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pendiente</option>
                <option value="submitted" <?= ($filters['status'] ?? '') === 'submitted' ? 'selected' : '' ?>>Enviado</option>
                <option value="approved" <?= ($filters['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Aprobado</option>
                <option value="rejected" <?= ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rechazado</option>
            </select>
        </label>
        <div style="display:flex; gap:8px; align-items:end;">
            <button type="submit" class="btn primary" style="white-space:nowrap;">Aplicar</button>
            <a class="btn secondary" href="<?= $basePath ?>/admin/timesheets">Limpiar</a>
        </div>
    </div>
</form>

<div class="admin-ts-tabs">
    <a href="<?= htmlspecialchars($tabUrl('entries')) ?>" class="<?= $viewTab === 'entries' ? 'active' : '' ?>">Detalle</a>
    <a href="<?= htmlspecialchars($tabUrl('by_user')) ?>" class="<?= $viewTab === 'by_user' ? 'active' : '' ?>">Por usuario</a>
    <a href="<?= htmlspecialchars($tabUrl('by_project')) ?>" class="<?= $viewTab === 'by_project' ? 'active' : '' ?>">Por proyecto</a>
    <a href="<?= htmlspecialchars($tabUrl('by_client')) ?>" class="<?= $viewTab === 'by_client' ? 'active' : '' ?>">Por cliente</a>
</div>

<?php if ($viewTab === 'entries'): ?>
    <?php if (empty($entries)): ?>
        <div class="empty-state">No hay registros de timesheets con estos filtros.</div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="admin-ts-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Cliente</th>
                        <th>Proyecto</th>
                        <th>Tarea</th>
                        <th>Fecha</th>
                        <th>Horas</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--text-primary);"><?= htmlspecialchars($entry['user_name'] ?? 'Sin usuario') ?></strong>
                            </td>
                            <td><?= htmlspecialchars($entry['client_name'] ?? 'Sin cliente') ?></td>
                            <td><?= htmlspecialchars($entry['project_name'] ?? 'Sin proyecto') ?></td>
                            <td><?= htmlspecialchars($entry['task_title'] ?? 'Sin tarea') ?></td>
                            <td style="white-space:nowrap;"><?= htmlspecialchars($entry['date'] ?? '') ?></td>
                            <td><strong><?= number_format((float) ($entry['hours'] ?? 0), 2) ?>h</strong></td>
                            <td>
                                <?php $st = (string) ($entry['status'] ?? 'draft'); ?>
                                <span class="<?= $statusBadgeClass($st) ?>"><?= htmlspecialchars($statusLabels[$st] ?? ucfirst($st)) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php elseif ($viewTab === 'by_user'): ?>
    <?php if (empty($summaryByUser)): ?>
        <div class="empty-state">No hay datos de usuarios con estos filtros.</div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="admin-ts-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Total horas</th>
                        <th>Proyectos</th>
                        <th>Registros</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summaryByUser as $row): ?>
                        <tr>
                            <td><strong style="color: var(--text-primary);"><?= htmlspecialchars($row['user_name'] ?? 'Sin usuario') ?></strong></td>
                            <td><strong><?= number_format((float) ($row['total_hours'] ?? 0), 1) ?>h</strong></td>
                            <td><?= (int) ($row['projects_count'] ?? 0) ?></td>
                            <td><?= (int) ($row['entries_count'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php elseif ($viewTab === 'by_project'): ?>
    <?php if (empty($summaryByProject)): ?>
        <div class="empty-state">No hay datos de proyectos con estos filtros.</div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="admin-ts-table">
                <thead>
                    <tr>
                        <th>Proyecto</th>
                        <th>Cliente</th>
                        <th>Horas registradas</th>
                        <th>Horas planificadas</th>
                        <th>Consumo</th>
                        <th>Usuarios</th>
                        <th>Registros</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summaryByProject as $row): ?>
                        <?php
                            $regHours = (float) ($row['total_hours'] ?? 0);
                            $planHours = (float) ($row['planned_hours'] ?? 0);
                            $consumePct = $planHours > 0 ? round(($regHours / $planHours) * 100, 1) : 0;
                            $barColor = $consumePct > 100 ? 'red' : ($consumePct > 80 ? 'yellow' : 'green');
                        ?>
                        <tr>
                            <td><strong style="color: var(--text-primary);"><?= htmlspecialchars($row['project_name'] ?? 'Sin proyecto') ?></strong></td>
                            <td><?= htmlspecialchars($row['client_name'] ?? 'Sin cliente') ?></td>
                            <td><strong><?= number_format($regHours, 1) ?>h</strong></td>
                            <td><?= $planHours > 0 ? number_format($planHours, 1) . 'h' : 'N/A' ?></td>
                            <td>
                                <?php if ($planHours > 0): ?>
                                    <span class="progress-mini">
                                        <span class="progress-mini-bar <?= $barColor ?>" style="width: <?= min(100, $consumePct) ?>%;"></span>
                                    </span>
                                    <span style="font-weight:700; color: var(--text-primary);"><?= $consumePct ?>%</span>
                                    <?php if ($consumePct > 100): ?>
                                        <span class="badge danger" style="margin-left:4px; font-size:10px;">Sobreconsumo</span>
                                    <?php elseif ($consumePct > 80): ?>
                                        <span class="badge warning" style="margin-left:4px; font-size:10px;">Alerta</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: var(--text-secondary);">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) ($row['users_count'] ?? 0) ?></td>
                            <td><?= (int) ($row['entries_count'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php elseif ($viewTab === 'by_client'): ?>
    <?php if (empty($summaryByClient)): ?>
        <div class="empty-state">No hay datos de clientes con estos filtros.</div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="admin-ts-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Total horas</th>
                        <th>Proyectos</th>
                        <th>Usuarios</th>
                        <th>Registros</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summaryByClient as $row): ?>
                        <tr>
                            <td><strong style="color: var(--text-primary);"><?= htmlspecialchars($row['client_name'] ?? 'Sin cliente') ?></strong></td>
                            <td><strong><?= number_format((float) ($row['total_hours'] ?? 0), 1) ?>h</strong></td>
                            <td><?= (int) ($row['projects_count'] ?? 0) ?></td>
                            <td><?= (int) ($row['users_count'] ?? 0) ?></td>
                            <td><?= (int) ($row['entries_count'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
