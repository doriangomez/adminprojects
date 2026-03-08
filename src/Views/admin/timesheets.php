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
$selectedWeek = (string) ($weekValue ?? (new DateTimeImmutable('monday this week'))->format('o-\\WW'));

$statusLabel = static function (string $status): string {
    $normalized = strtolower(trim($status));
    return match ($normalized) {
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
        'submitted', 'pending', 'pending_approval' => 'Pendiente',
        'draft' => 'Borrador',
        default => $status !== '' ? ucfirst($status) : 'Sin estado',
    };
};
?>

<section class="admin-timesheets">
    <div class="card">
        <h3>Vista administrativa de Timesheets</h3>
        <p class="muted">Consolidado para PMO y administración. No altera flujo de registro ni aprobación.</p>
        <form method="GET" action="<?= $basePath ?>/admin/timesheets" class="admin-timesheets-filters">
            <label>Usuario
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
            <label>Proyecto
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
            <label>Cliente
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
            <button type="submit" class="btn primary">Aplicar filtros</button>
        </form>
    </div>

    <div class="grid tight">
        <article class="card">
            <span class="muted">Registros</span>
            <h3><?= (int) ($totals['entries'] ?? 0) ?></h3>
        </article>
        <article class="card">
            <span class="muted">Horas registradas</span>
            <h3><?= number_format((float) ($totals['hours'] ?? 0), 2) ?>h</h3>
        </article>
        <article class="card">
            <span class="muted">Usuarios con carga</span>
            <h3><?= count($byUser) ?></h3>
        </article>
        <article class="card">
            <span class="muted">Proyectos con carga</span>
            <h3><?= count($byProject) ?></h3>
        </article>
    </div>

    <div class="section-grid wide">
        <article class="card">
            <h4>Horas por usuario</h4>
            <table>
                <thead><tr><th>Usuario</th><th>Registros</th><th>Horas</th></tr></thead>
                <tbody>
                    <?php foreach ($byUser as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($row['user_name'] ?? 'Sin usuario')) ?></td>
                            <td><?= (int) ($row['entries'] ?? 0) ?></td>
                            <td><?= number_format((float) ($row['total_hours'] ?? 0), 2) ?>h</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </article>

        <article class="card">
            <h4>Estado de horas</h4>
            <table>
                <thead><tr><th>Estado</th><th>Registros</th><th>Horas</th></tr></thead>
                <tbody>
                    <?php foreach ($statusBreakdown as $row): ?>
                        <?php $status = (string) ($row['status'] ?? ''); ?>
                        <tr>
                            <td><?= htmlspecialchars($statusLabel($status)) ?></td>
                            <td><?= (int) ($row['entries'] ?? 0) ?></td>
                            <td><?= number_format((float) ($row['total_hours'] ?? 0), 2) ?>h</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </article>
    </div>

    <div class="section-grid wide">
        <article class="card">
            <h4>Horas por proyecto</h4>
            <table>
                <thead><tr><th>Proyecto</th><th>Registros</th><th>Horas</th></tr></thead>
                <tbody>
                    <?php foreach ($byProject as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($row['project_name'] ?? 'Sin proyecto')) ?></td>
                            <td><?= (int) ($row['entries'] ?? 0) ?></td>
                            <td><?= number_format((float) ($row['total_hours'] ?? 0), 2) ?>h</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </article>

        <article class="card">
            <h4>Horas por cliente</h4>
            <table>
                <thead><tr><th>Cliente</th><th>Registros</th><th>Horas</th></tr></thead>
                <tbody>
                    <?php foreach ($byClient as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($row['client_name'] ?? 'Sin cliente')) ?></td>
                            <td><?= (int) ($row['entries'] ?? 0) ?></td>
                            <td><?= number_format((float) ($row['total_hours'] ?? 0), 2) ?>h</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </article>
    </div>

    <article class="card">
        <h4>Horas por tarea</h4>
        <table>
            <thead><tr><th>Tarea</th><th>Registros</th><th>Horas</th></tr></thead>
            <tbody>
                <?php foreach ($byTask as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($row['task_name'] ?? 'Sin tarea')) ?></td>
                        <td><?= (int) ($row['entries'] ?? 0) ?></td>
                        <td><?= number_format((float) ($row['total_hours'] ?? 0), 2) ?>h</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </article>

    <article class="card">
        <h4>Detalle semanal</h4>
        <div class="table-wrapper">
            <table>
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
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($row['user_name'] ?? 'Sin usuario')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['client_name'] ?? 'Sin cliente')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['project_name'] ?? 'Sin proyecto')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['task_name'] ?? 'Sin tarea')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['date'] ?? '')) ?></td>
                            <td><?= number_format((float) ($row['hours'] ?? 0), 2) ?>h</td>
                            <td><?= htmlspecialchars($statusLabel((string) ($row['status'] ?? ''))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<style>
    .admin-timesheets {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .admin-timesheets-filters {
        display: grid;
        grid-template-columns: repeat(6, minmax(140px, 1fr));
        gap: 10px;
        align-items: end;
    }

    .admin-timesheets-filters label {
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 13px;
    }

    @media (max-width: 1200px) {
        .admin-timesheets-filters {
            grid-template-columns: repeat(2, minmax(150px, 1fr));
        }
    }
</style>
