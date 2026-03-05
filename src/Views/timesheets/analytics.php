<?php
/**
 * Vista analítica gerencial de Timesheet
 * Resumen de semanas, desglose por talento, historial, métricas
 */
$basePath = $basePath ?? '';
?>
<section class="ts-analytics">
    <header class="card">
        <div class="ts-analytics-header">
            <div>
                <h2>Timesheets – Analítica</h2>
                <p class="section-muted">Resumen de semanas, desglose por talento, historial.</p>
            </div>
            <a href="<?= $basePath ?>/timesheets" class="primary-button">← Registro de horas</a>
        </div>
        <form method="GET" class="filters-grid">
            <label>Semana
                <input type="week" name="week" value="<?= htmlspecialchars($weekValue ?? '') ?>">
            </label>
            <label>Periodo
                <select name="period" id="period-select">
                    <option value="month" <?= ($periodType ?? '') === 'month' ? 'selected' : '' ?>>Mes</option>
                    <option value="custom" <?= ($periodType ?? '') === 'custom' ? 'selected' : '' ?>>Rango personalizado</option>
                </select>
            </label>
            <label>Desde
                <input type="date" name="range_start" value="<?= htmlspecialchars(($periodStart ?? null)?->format('Y-m-d') ?? '') ?>" <?= ($periodType ?? '') !== 'custom' ? 'disabled' : '' ?> data-range-input>
            </label>
            <label>Hasta
                <input type="date" name="range_end" value="<?= htmlspecialchars(($periodEnd ?? null)?->format('Y-m-d') ?? '') ?>" <?= ($periodType ?? '') !== 'custom' ? 'disabled' : '' ?> data-range-input>
            </label>
            <label>Proyecto
                <select name="project_id">
                    <option value="0">Todos</option>
                    <?php foreach ($projectsForFilter ?? [] as $project): ?>
                    <option value="<?= (int) ($project['project_id'] ?? 0) ?>" <?= ($projectFilter ?? 0) === (int) ($project['project_id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($project['project'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Orden talento
                <select name="talent_sort">
                    <option value="load_desc" <?= ($talentSort ?? '') === 'load_desc' ? 'selected' : '' ?>>Mayor carga</option>
                    <option value="compliance_asc" <?= ($talentSort ?? '') === 'compliance_asc' ? 'selected' : '' ?>>Menor cumplimiento</option>
                </select>
            </label>
            <button type="submit" class="primary-button">Aplicar</button>
        </form>
    </header>

    <section class="kpi-grid">
        <article class="card kpi"><h4>Horas registradas</h4><strong><?= round((float) ($executiveSummary['total'] ?? 0), 2) ?>h</strong></article>
        <article class="card kpi approved"><h4>Aprobadas</h4><strong><?= round((float) ($executiveSummary['approved'] ?? 0), 2) ?>h</strong><small><?= round($approvedPercent ?? 0, 2) ?>%</small></article>
        <article class="card kpi rejected"><h4>Rechazadas</h4><strong><?= round((float) ($executiveSummary['rejected'] ?? 0), 2) ?>h</strong></article>
        <article class="card kpi draft"><h4>Borrador</h4><strong><?= round((float) ($executiveSummary['draft'] ?? 0), 2) ?>h</strong></article>
        <article class="card kpi pending"><h4>Pendientes</h4><strong><?= round((float) ($executiveSummary['pending'] ?? 0), 2) ?>h</strong></article>
        <article class="card kpi <?= ($compliancePercent ?? 0) > 100 ? 'rejected' : (($compliancePercent ?? 0) >= 80 ? 'approved' : 'pending') ?>"><h4>Cumplimiento vs capacidad</h4><strong><?= round($compliancePercent ?? 0, 2) ?>%</strong><small>Capacidad: <?= round((float) ($executiveSummary['capacity'] ?? 0), 2) ?>h</small></article>
    </section>

    <section class="card">
        <h3>Resumen de semanas del periodo</h3>
        <p><strong>Semanas registradas:</strong> <?= $weeksRegistered ?? 0 ?> · <strong>Aprobadas:</strong> <?= $weeksApproved ?? 0 ?> · <strong>Pendientes:</strong> <?= $weeksPending ?? 0 ?></p>
    </section>

    <section class="card">
        <h3>Horas aprobadas por semana</h3>
        <div class="table-wrap">
            <table class="clean-table">
                <thead><tr><th>Semana</th><th>Rango</th><th>Total horas</th><th>Estado</th><th>Fecha aprobación</th><th>Aprobador</th></tr></thead>
                <tbody>
                <?php foreach ($approvedWeeks ?? [] as $week):
                    $start = new DateTimeImmutable((string) ($week['week_start'] ?? 'now'));
                    $end = new DateTimeImmutable((string) ($week['week_end'] ?? 'now'));
                    $weight = (int) ($week['status_weight'] ?? 0);
                    $state = $weight >= 5 ? 'approved' : ($weight >= 4 ? 'rejected' : 'submitted');
                    $meta = ($statusMap ?? [])[$state] ?? ['label' => 'Pendiente', 'class' => 'submitted'];
                ?>
                    <tr>
                        <td><a href="<?= $basePath ?>/timesheets?week=<?= urlencode($start->format('o-\\WW')) ?>">Semana <?= htmlspecialchars($start->format('W')) ?></a></td>
                        <td><?= htmlspecialchars($start->format('d/m/Y')) ?> - <?= htmlspecialchars($end->format('d/m/Y')) ?></td>
                        <td><?= round((float) ($week['total_hours'] ?? 0), 2) ?>h</td>
                        <td><span class="badge-state <?= $meta['class'] ?>"><?= htmlspecialchars($meta['label']) ?></span></td>
                        <td><?= htmlspecialchars((string) ($week['approved_at'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars((string) ($week['approver_name'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h3>Desglose por Talento</h3>
        <div class="table-wrap">
            <table class="clean-table">
                <thead><tr><th>Talento</th><th>Total</th><th>Aprobadas</th><th>Rechazadas</th><th>Borrador</th><th>% Cumplimiento</th><th>Última semana enviada</th><th>Última semana aprobada</th></tr></thead>
                <tbody>
                <?php foreach ($talentBreakdown ?? [] as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($row['talent_name'] ?? '')) ?></td>
                        <td><?= round((float) ($row['total_hours'] ?? 0), 2) ?>h</td>
                        <td><?= round((float) ($row['approved_hours'] ?? 0), 2) ?>h</td>
                        <td><?= round((float) ($row['rejected_hours'] ?? 0), 2) ?>h</td>
                        <td><?= round((float) ($row['draft_hours'] ?? 0), 2) ?>h</td>
                        <td><?= round((float) ($row['compliance_percent'] ?? 0), 2) ?>%</td>
                        <td><?= htmlspecialchars((string) ($row['last_week_submitted'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['last_week_approved'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h3>Historial de semanas</h3>
        <div class="weeks-row">
            <?php foreach ($weeksHistory ?? [] as $week):
                $start = new DateTimeImmutable((string) ($week['week_start'] ?? 'now'));
                $end = new DateTimeImmutable((string) ($week['week_end'] ?? 'now'));
                $statusWeight = (int) ($week['status_weight'] ?? 2);
                $status = $statusWeight >= 5 ? 'approved' : ($statusWeight >= 4 ? 'rejected' : ($statusWeight >= 3 ? 'submitted' : 'draft'));
                $meta = ($statusMap ?? [])[$status] ?? ['label' => 'Borrador', 'class' => 'draft'];
            ?>
                <a class="week-card <?= $meta['class'] ?>" href="<?= $basePath ?>/timesheets?week=<?= urlencode($start->format('o-\\WW')) ?>">
                    <strong>Semana <?= htmlspecialchars($start->format('W')) ?></strong>
                    <span><?= htmlspecialchars($start->format('d/m')) ?> - <?= htmlspecialchars($end->format('d/m')) ?></span>
                    <span><?= htmlspecialchars((string) round((float) ($week['total_hours'] ?? 0), 2)) ?>h</span>
                    <span class="badge-state <?= $meta['class'] ?>"><?= htmlspecialchars($meta['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card">
        <h3>Distribución por tipo de actividad</h3>
        <ul class="summary-list">
            <?php foreach (array_slice($activityTypeBreakdown ?? [], 0, 8) as $row): ?>
                <li><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) ($row['activity_type'] ?? 'sin_clasificar')))) ?>: <strong><?= round((float) ($row['total_hours'] ?? 0), 2) ?>h</strong> (<?= round((float) ($row['percent'] ?? 0), 2) ?>%)</li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="card">
        <h3>Horas por fase / subfase</h3>
        <ul class="summary-list">
            <?php foreach (array_slice($phaseBreakdown ?? [], 0, 8) as $row): ?>
                <li><?= htmlspecialchars((string) ($row['phase_name'] ?? 'sin_fase')) ?> / <?= htmlspecialchars((string) ($row['subphase_name'] ?? 'sin_subfase')) ?>: <strong><?= round((float) ($row['total_hours'] ?? 0), 2) ?>h</strong></li>
            <?php endforeach; ?>
        </ul>
    </section>
</section>

<style>
.ts-analytics{display:flex;flex-direction:column;gap:16px}
.ts-analytics-header{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:14px}
.filters-grid{display:grid;grid-template-columns:repeat(7,minmax(120px,1fr));gap:10px;align-items:end}
.filters-grid label{display:flex;flex-direction:column;gap:4px}
.kpi-grid{display:grid;grid-template-columns:repeat(3,minmax(160px,1fr));gap:12px}
.kpi strong{font-size:1.4rem}
.kpi.approved{border-top:4px solid #16a34a}
.kpi.rejected{border-top:4px solid #dc2626}
.kpi.draft{border-top:4px solid #6b7280}
.kpi.pending{border-top:4px solid #eab308}
.weeks-row{display:flex;gap:10px;overflow:auto;padding-bottom:6px}
.week-card{min-width:170px;display:flex;flex-direction:column;gap:4px;border:1px solid var(--border);border-radius:12px;padding:10px;text-decoration:none;color:inherit}
.week-card.approved{border-left:6px solid #16a34a}
.week-card.rejected{border-left:6px solid #dc2626}
.week-card.submitted{border-left:6px solid #eab308}
.week-card.draft{border-left:6px solid #6b7280}
.badge-state{font-size:12px;font-weight:700;padding:2px 8px;border-radius:999px}
.badge-state.approved{background:#dcfce7;color:#166534}
.badge-state.rejected{background:#fee2e2;color:#991b1b}
.badge-state.submitted{background:#fef3c7;color:#92400e}
.badge-state.draft{background:#e5e7eb;color:#374151}
.summary-list{margin:0;padding-left:18px;display:flex;flex-direction:column;gap:8px}
.table-wrap{overflow:auto}
</style>

<script>
document.getElementById('period-select')?.addEventListener('change', function() {
  const custom = this.value === 'custom';
  document.querySelectorAll('[data-range-input]').forEach(el => { el.disabled = !custom; });
});
</script>
