<?php
$basePath = $basePath ?? '';
$project = $project ?? [];
$timesheetEntries = is_array($timesheetEntries ?? null) ? $timesheetEntries : [];
$projectId = (int) ($project['id'] ?? 0);
$hoursLogged = (float) ($project['timesheet_hours_logged'] ?? $project['actual_hours'] ?? 0);
$hoursEstimated = (float) ($project['hours_estimated_total'] ?? $project['planned_hours'] ?? 0);
$progress = $hoursEstimated > 0 ? min(100, round(($hoursLogged / $hoursEstimated) * 100, 1)) : 0;
?>

<section class="project-shell">
    <header class="project-header">
        <div class="project-title-block">
            <p class="eyebrow">Horas registradas</p>
            <h2><?= htmlspecialchars($project['name'] ?? '') ?></h2>
            <small class="section-muted">Registro de horas por fecha, usuario y tarea.</small>
        </div>
        <div class="project-actions">
            <a class="action-btn" href="<?= $basePath ?>/projects/<?= $projectId ?>?view=resumen">Volver al resumen</a>
        </div>
    </header>

    <?php
    $activeTab = 'horas';
    require __DIR__ . '/_tabs.php';
    ?>

    <div class="hours-summary card">
        <div class="hours-summary__bar">
            <div class="hours-summary__label">Avance</div>
            <div class="progress-track progress-track--wide" role="progressbar" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width: <?= $progress ?>%;"></div>
            </div>
            <span class="hours-summary__pct"><?= $progress ?>%</span>
        </div>
        <div class="hours-summary__values">
            <strong><?= number_format($hoursLogged, 0, ',', '.') ?>h</strong> / <?= number_format($hoursEstimated, 0, ',', '.') ?>h
        </div>
    </div>

    <article class="card">
        <h4>Detalle de horas</h4>
        <div class="table-wrapper">
            <table class="hours-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Tarea</th>
                        <th>Horas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($timesheetEntries)): ?>
                        <tr>
                            <td colspan="4" class="empty-cell">No hay horas registradas para este proyecto.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($timesheetEntries as $entry): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($entry['date'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($entry['user_name'] ?? '—')) ?></td>
                                <td><?= htmlspecialchars((string) ($entry['task_name'] ?? '—')) ?></td>
                                <td><strong><?= number_format((float) ($entry['hours'] ?? 0), 2) ?>h</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<style>
    .hours-summary { padding: 16px; display: flex; flex-direction: column; gap: 10px; }
    .hours-summary__bar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .hours-summary__label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); font-weight: 700; min-width: 60px; }
    .progress-track--wide { flex: 1; min-width: 120px; height: 10px; }
    .hours-summary__pct { font-size: 14px; font-weight: 700; color: var(--text-primary); }
    .hours-summary__values { font-size: 18px; font-weight: 800; color: var(--text-primary); }
    .hours-table { width: 100%; border-collapse: collapse; }
    .hours-table th, .hours-table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid var(--border); font-size: 13px; }
    .hours-table th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); background: color-mix(in srgb, var(--text-secondary) 12%, var(--background)); font-weight: 700; }
    .empty-cell { color: var(--text-secondary); font-style: italic; padding: 24px !important; text-align: center; }
</style>
