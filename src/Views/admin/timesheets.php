<?php
$basePath = $basePath ?? '';
$report = is_array($report ?? null) ? $report : [];
$totals = is_array($report['totals'] ?? null) ? $report['totals'] : ['entries' => 0, 'hours' => 0];
$weeklyRows = is_array($report['weekly_rows'] ?? null) ? $report['weekly_rows'] : [];
$detailRows = is_array($report['detail_rows'] ?? null) ? $report['detail_rows'] : [];
$selectedDetail = is_array($report['selected_detail'] ?? null) ? $report['selected_detail'] : [];
$filterOptions = is_array($report['filter_options'] ?? null) ? $report['filter_options'] : [];
$users = is_array($filterOptions['users'] ?? null) ? $filterOptions['users'] : [];
$projects = is_array($filterOptions['projects'] ?? null) ? $filterOptions['projects'] : [];
$clients = is_array($filterOptions['clients'] ?? null) ? $filterOptions['clients'] : [];
$statuses = is_array($filterOptions['statuses'] ?? null) ? $filterOptions['statuses'] : [];
$filters = is_array($filters ?? null) ? $filters : [];
$canApprove = !empty($canApprove);
$selectedWeek = (string) ($weekValue ?? (new DateTimeImmutable('monday this week'))->format('o-\\WW'));

$statusLabel = static function (string $status): string {
    $normalized = strtolower(trim($status));
    return match ($normalized) {
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
        'submitted', 'pending', 'pending_approval' => 'Enviado',
        'partial' => 'Mixto',
        default => 'Borrador',
    };
};

$statusClass = static function (string $status): string {
    $normalized = strtolower(trim($status));
    return match ($normalized) {
        'approved' => 'status-approved',
        'rejected' => 'status-rejected',
        'submitted', 'pending', 'pending_approval', 'partial' => 'status-submitted',
        default => 'status-draft',
    };
};

$monthShort = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];
$formatWeekRange = static function (string $weekStart, string $weekEnd) use ($monthShort): string {
    try {
        $start = new DateTimeImmutable($weekStart);
        $end = new DateTimeImmutable($weekEnd);
        $monthLabel = $monthShort[(int) $start->format('n')] ?? $start->format('M');
        if ($start->format('n') !== $end->format('n')) {
            $monthLabel = ($monthShort[(int) $start->format('n')] ?? $start->format('M')) . ' - ' . ($monthShort[(int) $end->format('n')] ?? $end->format('M'));
        }
        return $start->format('d') . '-' . $end->format('d') . ' ' . $monthLabel;
    } catch (Throwable $e) {
        return $weekStart . ' - ' . $weekEnd;
    }
};

$buildQuery = static function (array $overrides) use ($filters): string {
    $query = [
        'user_id' => (int) ($filters['user_id'] ?? 0),
        'project_id' => (int) ($filters['project_id'] ?? 0),
        'client_id' => (int) ($filters['client_id'] ?? 0),
        'status' => (string) ($filters['status'] ?? ''),
        'week' => (string) ($_GET['week'] ?? ''),
    ];
    foreach ($overrides as $key => $value) {
        $query[$key] = $value;
    }
    return http_build_query(array_filter($query, static fn($value): bool => $value !== '' && $value !== null));
};
?>

<section class="admin-timesheets">
    <article class="card">
        <h3>Control operativo de horas</h3>
        <p class="muted">Vista administrativa simple para PMO y administradores. El flujo de registro y aprobación se mantiene intacto.</p>
        <form method="GET" action="<?= $basePath ?>/admin/timesheets" class="admin-timesheets-filters">
            <label>Usuario
                <select name="user_id">
                    <option value="0">Todos</option>
                    <?php foreach ($users as $option): ?>
                        <?php $optionId = (int) ($option['user_id'] ?? 0); ?>
                        <option value="<?= $optionId ?>" <?= $optionId === (int) ($filters['user_id'] ?? 0) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($option['user_name'] ?? 'Usuario')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Proyecto
                <select name="project_id">
                    <option value="0">Todos</option>
                    <?php foreach ($projects as $option): ?>
                        <?php $optionId = (int) ($option['project_id'] ?? 0); ?>
                        <option value="<?= $optionId ?>" <?= $optionId === (int) ($filters['project_id'] ?? 0) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($option['project_name'] ?? 'Proyecto')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Cliente
                <select name="client_id">
                    <option value="0">Todos</option>
                    <?php foreach ($clients as $option): ?>
                        <?php $optionId = (int) ($option['client_id'] ?? 0); ?>
                        <option value="<?= $optionId ?>" <?= $optionId === (int) ($filters['client_id'] ?? 0) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($option['client_name'] ?? 'Cliente')) ?>
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
                        <option value="<?= htmlspecialchars((string) $status) ?>" <?= strtolower((string) ($filters['status'] ?? '')) === strtolower((string) $status) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statusLabel((string) $status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="filter-actions">
                <button type="submit" class="btn primary">Aplicar filtros</button>
                <a class="btn" href="<?= $basePath ?>/admin/timesheets">Limpiar</a>
            </div>
        </form>
    </article>

    <article class="card">
        <div class="summary-row">
            <span class="pill neutral">Filas semanales: <strong><?= count($weeklyRows) ?></strong></span>
            <span class="pill neutral">Registros: <strong><?= (int) ($totals['entries'] ?? 0) ?></strong></span>
            <span class="pill neutral">Horas: <strong><?= number_format((float) ($totals['hours'] ?? 0), 2) ?>h</strong></span>
        </div>
        <div class="table-wrapper">
            <table>
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
                    <?php if ($weeklyRows === []): ?>
                        <tr><td colspan="7" class="muted">Sin resultados para los filtros actuales.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($weeklyRows as $row): ?>
                        <?php
                        $rowUserId = (int) ($row['user_id'] ?? 0);
                        $rowProjectId = (int) ($row['project_id'] ?? 0);
                        $rowWeekStart = (string) ($row['week_start'] ?? '');
                        $rowWeekEnd = (string) ($row['week_end'] ?? '');
                        $rowStatus = (string) ($row['status'] ?? 'draft');
                        $isSelectedDetail = (int) ($selectedDetail['user_id'] ?? 0) === $rowUserId
                            && (int) ($selectedDetail['project_id'] ?? -1) === $rowProjectId
                            && (string) ($selectedDetail['week_start'] ?? '') === $rowWeekStart;
                        ?>
                        <tr class="<?= $isSelectedDetail ? 'is-selected-row' : '' ?>">
                            <td><?= htmlspecialchars((string) ($row['user_name'] ?? 'Sin usuario')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['client_name'] ?? 'Sin cliente')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['project_name'] ?? 'Sin proyecto')) ?></td>
                            <td><?= htmlspecialchars($formatWeekRange($rowWeekStart, $rowWeekEnd)) ?></td>
                            <td><strong><?= number_format((float) ($row['total_hours'] ?? 0), 2) ?>h</strong></td>
                            <td><span class="status-pill <?= $statusClass($rowStatus) ?>"><?= htmlspecialchars($statusLabel($rowStatus)) ?></span></td>
                            <td>
                                <div class="table-actions">
                                    <a class="btn sm" href="<?= $basePath ?>/admin/timesheets?<?= htmlspecialchars($buildQuery([
                                        'detail_user_id' => $rowUserId,
                                        'detail_project_id' => $rowProjectId,
                                        'detail_week_start' => $rowWeekStart,
                                    ])) ?>">Ver detalle</a>
                                    <?php if ($canApprove && in_array(strtolower($rowStatus), ['submitted', 'partial'], true)): ?>
                                        <form method="POST" action="<?= $basePath ?>/timesheets/approve-week" class="inline-form">
                                            <input type="hidden" name="week_start" value="<?= htmlspecialchars($rowWeekStart) ?>">
                                            <input type="hidden" name="target_user_id" value="<?= $rowUserId ?>">
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="btn sm">Aprobar</button>
                                        </form>
                                        <form method="POST" action="<?= $basePath ?>/timesheets/approve-week" class="inline-form reject-week-form">
                                            <input type="hidden" name="week_start" value="<?= htmlspecialchars($rowWeekStart) ?>">
                                            <input type="hidden" name="target_user_id" value="<?= $rowUserId ?>">
                                            <input type="hidden" name="status" value="rejected">
                                            <input type="hidden" name="comment" value="">
                                            <button type="submit" class="btn sm danger">Rechazar</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>

    <?php if ($selectedDetail !== []): ?>
        <article class="card">
            <h4>Detalle de horas por semana</h4>
            <p class="muted">Semana <?= htmlspecialchars($formatWeekRange((string) ($selectedDetail['week_start'] ?? ''), (string) ($selectedDetail['week_end'] ?? ''))) ?></p>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Tarea</th>
                            <th>Horas</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($detailRows === []): ?>
                            <tr><td colspan="5" class="muted">No hay registros de detalle para esta selección.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($detailRows as $detail): ?>
                            <?php $detailStatus = (string) ($detail['status'] ?? 'draft'); ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($detail['date'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($detail['user_name'] ?? 'Sin usuario')) ?></td>
                                <td><?= htmlspecialchars((string) ($detail['task_name'] ?? 'Sin tarea')) ?></td>
                                <td><?= number_format((float) ($detail['hours'] ?? 0), 2) ?></td>
                                <td><span class="status-pill <?= $statusClass($detailStatus) ?>"><?= htmlspecialchars($statusLabel($detailStatus)) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>
    <?php endif; ?>
</section>

<style>
    .admin-timesheets { display: flex; flex-direction: column; gap: 14px; }
    .admin-timesheets-filters { display: grid; grid-template-columns: repeat(6, minmax(140px, 1fr)); gap: 10px; align-items: end; }
    .admin-timesheets-filters label { display: flex; flex-direction: column; gap: 4px; font-size: 13px; }
    .filter-actions { display: flex; gap: 8px; align-items: center; }
    .summary-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
    .table-actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .inline-form { margin: 0; }
    .status-pill { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; border: 1px solid var(--border); }
    .status-approved { background: color-mix(in srgb, var(--success) 22%, var(--surface)); color: var(--text-primary); }
    .status-rejected { background: color-mix(in srgb, var(--danger) 20%, var(--surface)); color: var(--text-primary); }
    .status-submitted { background: color-mix(in srgb, var(--info) 20%, var(--surface)); color: var(--text-primary); }
    .status-draft { background: color-mix(in srgb, var(--neutral) 20%, var(--surface)); color: var(--text-primary); }
    .btn.danger { border-color: color-mix(in srgb, var(--danger) 35%, var(--border)); color: var(--danger); background: color-mix(in srgb, var(--danger) 10%, var(--surface)); }
    .is-selected-row { background: color-mix(in srgb, var(--primary) 10%, var(--surface)); }
    @media (max-width: 1200px) {
        .admin-timesheets-filters { grid-template-columns: repeat(2, minmax(150px, 1fr)); }
        .filter-actions { grid-column: 1 / -1; }
    }
</style>

<script>
    document.querySelectorAll('.reject-week-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const input = form.querySelector('input[name="comment"]');
            const reason = window.prompt('Motivo de rechazo:');
            if (!reason || !input) {
                event.preventDefault();
                return;
            }
            input.value = reason;
        });
    });
</script>
