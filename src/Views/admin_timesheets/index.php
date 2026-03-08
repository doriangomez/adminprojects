<?php
$basePath = $basePath ?? '';
$entries = is_array($entries ?? null) ? $entries : [];
$filters = is_array($filters ?? null) ? $filters : [];
$clients = is_array($clients ?? null) ? $clients : [];
$projects = is_array($projects ?? null) ? $projects : [];
$users = is_array($users ?? null) ? $users : [];

$statusLabels = [
    'draft' => 'Borrador',
    'submitted' => 'Enviado',
    'pending' => 'Pendiente',
    'pending_approval' => 'Pendiente aprobación',
    'approved' => 'Aprobado',
    'rejected' => 'Rechazado',
];

$queryParams = array_filter([
    'user_id' => $filters['user_id'] ?? null,
    'project_id' => $filters['project_id'] ?? null,
    'client_id' => $filters['client_id'] ?? null,
    'week_start' => $filters['week_start'] ?? null,
    'status' => $filters['status'] ?? null,
], static fn ($v) => $v !== null && $v !== '');
$queryString = http_build_query($queryParams);
?>

<style>
.admin-timesheets-hero {
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
.admin-timesheets-hero h1 { margin: 0 0 4px; font-size: 26px; color: var(--text-primary); }
.admin-timesheets-hero p { margin: 0; color: var(--text-secondary); font-weight: 500; }
.admin-timesheets-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding: 14px 16px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    margin-top: 12px;
}
.admin-timesheets-filter label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; font-weight: 600; }
.admin-timesheets-filter select,
.admin-timesheets-filter input { padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border); min-width: 140px; }
.admin-timesheets-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--surface);
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid var(--border);
    margin-top: 16px;
}
.admin-timesheets-table th,
.admin-timesheets-table td { padding: 11px 12px; border-bottom: 1px solid var(--border); text-align: left; font-size: 13px; }
.admin-timesheets-table th {
    background: color-mix(in srgb, var(--text-secondary) 14%, var(--background));
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-secondary);
    font-weight: 700;
}
.admin-timesheets-table tbody tr:last-child td { border-bottom: none; }
.admin-timesheets-table tbody tr:hover { background: color-mix(in srgb, var(--text-secondary) 8%, var(--background)); }
.badge-status { padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.badge-draft { background: color-mix(in srgb, var(--text-secondary) 20%, var(--background)); }
.badge-submitted, .badge-pending, .badge-pending_approval { background: color-mix(in srgb, var(--warning) 20%, var(--background)); color: var(--warning); }
.badge-approved { background: color-mix(in srgb, var(--success) 20%, var(--background)); color: var(--success); }
.badge-rejected { background: color-mix(in srgb, var(--danger) 20%, var(--background)); color: var(--danger); }
.btn-apply { padding: 8px 14px; border-radius: 8px; background: var(--primary); color: var(--text-primary); border: none; font-weight: 700; cursor: pointer; }
.btn-apply:hover { opacity: 0.9; }
.empty-state { padding: 24px; text-align: center; color: var(--text-secondary); font-weight: 600; }
</style>

<div class="admin-timesheets-hero">
    <div>
        <p class="eyebrow" style="margin:0; color: var(--text-secondary); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">PMO / Administración</p>
        <h1>Timesheets · Vista administrativa</h1>
        <p>Horas por usuario, proyecto, cliente y tarea</p>
    </div>
    <a href="<?= $basePath ?>/timesheets" class="button secondary">Ir a registro de horas</a>
</div>

<form method="GET" action="<?= $basePath ?>/admin/timesheets" class="admin-timesheets-filter">
    <label>
        Usuario
        <select name="user_id">
            <option value="">Todos</option>
            <?php foreach ($users as $u): ?>
                <option value="<?= (int) ($u['id'] ?? 0) ?>" <?= ($filters['user_id'] ?? null) === (int) ($u['id'] ?? 0) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) ($u['name'] ?? '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Cliente
        <select name="client_id">
            <option value="">Todos</option>
            <?php foreach ($clients as $c): ?>
                <option value="<?= (int) ($c['id'] ?? 0) ?>" <?= ($filters['client_id'] ?? null) === (int) ($c['id'] ?? 0) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) ($c['name'] ?? '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Proyecto
        <select name="project_id">
            <option value="">Todos</option>
            <?php foreach ($projects as $p): ?>
                <option value="<?= (int) ($p['id'] ?? 0) ?>" <?= ($filters['project_id'] ?? null) === (int) ($p['id'] ?? 0) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) ($p['name'] ?? '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Semana (lunes)
        <input type="date" name="week_start" value="<?= htmlspecialchars((string) ($filters['week_start'] ?? '')) ?>" placeholder="YYYY-MM-DD">
    </label>
    <label>
        Estado
        <select name="status">
            <option value="">Todos</option>
            <?php foreach ($statusLabels as $code => $label): ?>
                <option value="<?= htmlspecialchars($code) ?>" <?= ($filters['status'] ?? '') === $code ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label style="align-self: flex-end;">
        <button type="submit" class="btn-apply">Aplicar</button>
    </label>
</form>

<?php if (empty($entries)): ?>
    <div class="empty-state">No hay registros de timesheets con los filtros aplicados.</div>
<?php else: ?>
    <table class="admin-timesheets-table" aria-label="Listado de timesheets">
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
            <?php foreach ($entries as $row): ?>
                <?php
                    $status = (string) ($row['status'] ?? 'draft');
                    $statusLabel = $statusLabels[$status] ?? $status;
                    $badgeClass = 'badge-' . str_replace(' ', '_', $status);
                ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($row['usuario'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['cliente'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['proyecto'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['tarea'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['date'] ?? '')) ?></td>
                    <td><?= number_format((float) ($row['hours'] ?? 0), 2, ',', '.') ?>h</td>
                    <td><span class="badge-status <?= $badgeClass ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
