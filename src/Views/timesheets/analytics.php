<?php
$basePath = $basePath ?? '';
$weekValue = (string) ($weekValue ?? (new DateTimeImmutable('monday this week'))->format('o-\\WW'));
$periodType = (string) ($periodType ?? 'month');
$periodStart = $periodStart ?? new DateTimeImmutable('first day of this month');
$periodEnd = $periodEnd ?? new DateTimeImmutable('last day of this month');
$projectFilter = (int) ($projectFilter ?? 0);
$projectsForFilter = is_array($projectsForFilter ?? null) ? $projectsForFilter : [];
$executiveSummary = is_array($executiveSummary ?? null) ? $executiveSummary : [];
$approvedWeeks = is_array($approvedWeeks ?? null) ? $approvedWeeks : [];
$talentBreakdown = is_array($talentBreakdown ?? null) ? $talentBreakdown : [];
$projectBreakdown = is_array($projectBreakdown ?? null) ? $projectBreakdown : [];
$activityTypeBreakdown = is_array($activityTypeBreakdown ?? null) ? $activityTypeBreakdown : [];
$phaseBreakdown = is_array($phaseBreakdown ?? null) ? $phaseBreakdown : [];
$talentSort = (string) ($talentSort ?? 'load_desc');
?>

<section class="timesheet-analytics">
    <div class="analytics-tabs card">
        <a class="tab" href="<?= $basePath ?>/timesheets?week=<?= urlencode($weekValue) ?>">Registro de horas</a>
        <a class="tab" href="<?= $basePath ?>/approvals">Aprobación de horas</a>
        <a class="tab active" href="<?= $basePath ?>/timesheets/analytics?week=<?= urlencode($weekValue) ?>">Analítica gerencial</a>
    </div>

    <header class="card">
        <h3>Analítica de Timesheets</h3>
        <p class="section-muted">Vista separada para métricas gerenciales y comportamiento de carga.</p>
        <form method="GET" class="analytics-filters">
            <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
            <label>Periodo
                <select name="period" id="analytics-period">
                    <option value="month" <?= $periodType === 'month' ? 'selected' : '' ?>>Mes</option>
                    <option value="custom" <?= $periodType === 'custom' ? 'selected' : '' ?>>Rango personalizado</option>
                </select>
            </label>
            <label>Desde
                <input type="date" name="range_start" value="<?= htmlspecialchars($periodStart->format('Y-m-d')) ?>" <?= $periodType !== 'custom' ? 'disabled' : '' ?> data-analytics-range>
            </label>
            <label>Hasta
                <input type="date" name="range_end" value="<?= htmlspecialchars($periodEnd->format('Y-m-d')) ?>" <?= $periodType !== 'custom' ? 'disabled' : '' ?> data-analytics-range>
            </label>
            <label>Proyecto
                <select name="project_id">
                    <option value="0">Todos</option>
                    <?php foreach ($projectsForFilter as $project): ?>
                        <option value="<?= (int) ($project['project_id'] ?? 0) ?>" <?= $projectFilter === (int) ($project['project_id'] ?? 0) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($project['project'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Orden talento
                <select name="talent_sort">
                    <option value="load_desc" <?= $talentSort === 'load_desc' ? 'selected' : '' ?>>Mayor carga</option>
                    <option value="compliance_asc" <?= $talentSort === 'compliance_asc' ? 'selected' : '' ?>>Menor cumplimiento</option>
                </select>
            </label>
            <button type="submit" class="btn primary">Aplicar</button>
        </form>
    </header>

    <section class="analytics-kpis">
        <article class="card"><span>Total horas</span><strong><?= round((float) ($executiveSummary['total'] ?? 0), 2) ?>h</strong></article>
        <article class="card"><span>Aprobadas</span><strong><?= round((float) ($executiveSummary['approved'] ?? 0), 2) ?>h</strong></article>
        <article class="card"><span>Rechazadas</span><strong><?= round((float) ($executiveSummary['rejected'] ?? 0), 2) ?>h</strong></article>
        <article class="card"><span>Pendientes</span><strong><?= round((float) ($executiveSummary['pending'] ?? 0), 2) ?>h</strong></article>
        <article class="card"><span>Cumplimiento</span><strong><?= round((float) ($executiveSummary['compliance_percent'] ?? 0), 2) ?>%</strong></article>
    </section>

    <section class="card">
        <h4>Horas por proyecto</h4>
        <table>
            <thead><tr><th>Proyecto</th><th>Horas</th></tr></thead>
            <tbody>
            <?php foreach ($projectBreakdown as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($row['project'] ?? '')) ?></td>
                    <td><?= round((float) ($row['total_hours'] ?? 0), 2) ?>h</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h4>Horas por talento</h4>
        <table>
            <thead><tr><th>Talento</th><th>Total</th><th>Aprobadas</th><th>Rechazadas</th><th>Borrador</th><th>Cumplimiento</th></tr></thead>
            <tbody>
            <?php foreach ($talentBreakdown as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($row['talent_name'] ?? '')) ?></td>
                    <td><?= round((float) ($row['total_hours'] ?? 0), 2) ?>h</td>
                    <td><?= round((float) ($row['approved_hours'] ?? 0), 2) ?>h</td>
                    <td><?= round((float) ($row['rejected_hours'] ?? 0), 2) ?>h</td>
                    <td><?= round((float) ($row['draft_hours'] ?? 0), 2) ?>h</td>
                    <td><?= round((float) ($row['compliance_percent'] ?? 0), 2) ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h4>Distribución por tipo de actividad</h4>
        <ul>
            <?php foreach ($activityTypeBreakdown as $row): ?>
                <li><?= htmlspecialchars((string) ($row['activity_type'] ?? 'sin_clasificar')) ?>: <strong><?= round((float) ($row['total_hours'] ?? 0), 2) ?>h</strong> (<?= round((float) ($row['percent'] ?? 0), 2) ?>%)</li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="card">
        <h4>Distribución por fase/subfase</h4>
        <ul>
            <?php foreach ($phaseBreakdown as $row): ?>
                <li><?= htmlspecialchars((string) ($row['phase_name'] ?? 'sin_fase')) ?> / <?= htmlspecialchars((string) ($row['subphase_name'] ?? 'sin_subfase')) ?>: <strong><?= round((float) ($row['total_hours'] ?? 0), 2) ?>h</strong></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="card">
        <h4>Historial de semanas</h4>
        <table>
            <thead><tr><th>Semana</th><th>Total</th><th>Estado</th><th>Aprobador</th></tr></thead>
            <tbody>
            <?php foreach ($approvedWeeks as $week): ?>
                <?php
                $start = new DateTimeImmutable((string) ($week['week_start'] ?? 'now'));
                $weight = (int) ($week['status_weight'] ?? 0);
                $state = $weight >= 5 ? 'Aprobada' : ($weight >= 4 ? 'Rechazada' : 'Pendiente');
                ?>
                <tr>
                    <td><a href="<?= $basePath ?>/timesheets?week=<?= urlencode($start->format('o-\\WW')) ?>">Semana <?= htmlspecialchars($start->format('W')) ?></a></td>
                    <td><?= round((float) ($week['total_hours'] ?? 0), 2) ?>h</td>
                    <td><?= $state ?></td>
                    <td><?= htmlspecialchars((string) ($week['approver_name'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</section>

<style>
.timesheet-analytics{display:flex;flex-direction:column;gap:14px}
.analytics-tabs{display:flex;gap:8px}
.tab{padding:8px 12px;border:1px solid var(--border);border-radius:999px;text-decoration:none;color:var(--text-primary)}
.tab.active{background:color-mix(in srgb,var(--primary) 18%,var(--surface));border-color:color-mix(in srgb,var(--primary) 45%,var(--border));font-weight:700}
.analytics-filters{display:grid;grid-template-columns:repeat(6,minmax(130px,1fr));gap:8px;align-items:end}
.analytics-filters label{display:flex;flex-direction:column;gap:4px;font-size:13px}
.analytics-kpis{display:grid;grid-template-columns:repeat(5,minmax(120px,1fr));gap:10px}
.analytics-kpis article{display:flex;flex-direction:column;gap:4px}
.analytics-kpis span{font-size:12px;color:var(--text-secondary)}
@media (max-width: 1100px){.analytics-filters{grid-template-columns:1fr 1fr}.analytics-kpis{grid-template-columns:repeat(2,minmax(120px,1fr))}}
</style>

<script>
(() => {
  const period = document.getElementById('analytics-period');
  const ranges = document.querySelectorAll('[data-analytics-range]');
  period?.addEventListener('change', () => {
    const custom = period.value === 'custom';
    ranges.forEach((input) => { input.disabled = !custom; });
  });
})();
</script>
