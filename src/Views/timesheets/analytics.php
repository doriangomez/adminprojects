<?php
$basePath = $basePath ?? '';
$canApprove = !empty($canApprove);
$weekStart = $weekStart ?? new DateTimeImmutable('monday this week');
$weekEnd = $weekEnd ?? $weekStart->modify('+6 days');
$weekValue = $weekValue ?? $weekStart->format('o-\\WW');
$periodType = (string) ($periodType ?? 'month');
$periodStart = $periodStart ?? $weekStart->modify('first day of this month')->setTime(0, 0);
$periodEnd = $periodEnd ?? $weekStart->modify('last day of this month')->setTime(0, 0);
$projectFilter = (int) ($projectFilter ?? 0);
$projectsForFilter = is_array($projectsForFilter ?? null) ? $projectsForFilter : [];
$executiveSummary = is_array($executiveSummary ?? null) ? $executiveSummary : [];
$approvedWeeks = is_array($approvedWeeks ?? null) ? $approvedWeeks : [];
$talentBreakdown = is_array($talentBreakdown ?? null) ? $talentBreakdown : [];
$projectBreakdown = is_array($projectBreakdown ?? null) ? $projectBreakdown : [];
$activityTypeBreakdown = is_array($activityTypeBreakdown ?? null) ? $activityTypeBreakdown : [];
$phaseBreakdown = is_array($phaseBreakdown ?? null) ? $phaseBreakdown : [];
$talentSort = (string) ($talentSort ?? 'load_desc');
$monthlySummary = is_array($monthlySummary ?? null) ? $monthlySummary : [];
$weeksHistory = is_array($weeksHistory ?? null) ? $weeksHistory : [];

$statusMap = [
    'approved' => ['label' => 'Aprobada', 'class' => 'ts-approved'],
    'rejected' => ['label' => 'Rechazada', 'class' => 'ts-rejected'],
    'submitted' => ['label' => 'Enviada', 'class' => 'ts-submitted'],
    'draft' => ['label' => 'Borrador', 'class' => 'ts-draft'],
    'partial' => ['label' => 'Parcial', 'class' => 'ts-partial'],
];

$approvedPercent = (float) ($executiveSummary['approved_percent'] ?? 0);
$compliancePercent = (float) ($executiveSummary['compliance_percent'] ?? 0);
$weeksRegistered = count($approvedWeeks);
$weeksApproved = count(array_filter($approvedWeeks, static fn($w): bool => ((int) ($w['status_weight'] ?? 0)) >= 5));
$weeksPending = max(0, $weeksRegistered - $weeksApproved);
?>

<section class="ts-module">
    <nav class="ts-view-tabs">
        <a href="<?= $basePath ?>/timesheets?week=<?= urlencode($weekValue) ?>" class="ts-tab">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            Registro de horas
        </a>
        <?php if ($canApprove): ?>
        <a href="<?= $basePath ?>/timesheets/approval?week=<?= urlencode($weekValue) ?>" class="ts-tab">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3 5 6v5c0 5.3 3.4 8.8 7 10 3.6-1.2 7-4.7 7-10V6z"/><path d="m9 12 2 2 4-4"/></svg>
            Aprobación
        </a>
        <?php endif; ?>
        <a href="<?= $basePath ?>/timesheets/analytics?week=<?= urlencode($weekValue) ?>" class="ts-tab active">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h16"/><rect x="6" y="12" width="3" height="6" rx="1"/><rect x="11" y="8" width="3" height="10" rx="1"/><rect x="16" y="5" width="3" height="13" rx="1"/></svg>
            Analítica
        </a>
    </nav>

    <header class="ts-analytics-header">
        <h3>Analítica gerencial</h3>
        <form method="GET" action="<?= $basePath ?>/timesheets/analytics" class="ts-analytics-filters">
            <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
            <div class="ts-field-inline">
                <label>Periodo</label>
                <select name="period" id="ts-period-select">
                    <option value="month" <?= $periodType === 'month' ? 'selected' : '' ?>>Mes</option>
                    <option value="custom" <?= $periodType === 'custom' ? 'selected' : '' ?>>Personalizado</option>
                </select>
            </div>
            <div class="ts-field-inline">
                <label>Desde</label>
                <input type="date" name="range_start" value="<?= htmlspecialchars($periodStart->format('Y-m-d')) ?>" <?= $periodType !== 'custom' ? 'disabled' : '' ?> data-range-input>
            </div>
            <div class="ts-field-inline">
                <label>Hasta</label>
                <input type="date" name="range_end" value="<?= htmlspecialchars($periodEnd->format('Y-m-d')) ?>" <?= $periodType !== 'custom' ? 'disabled' : '' ?> data-range-input>
            </div>
            <div class="ts-field-inline">
                <label>Proyecto</label>
                <select name="project_id">
                    <option value="0">Todos</option>
                    <?php foreach ($projectsForFilter as $project): ?>
                    <option value="<?= (int) ($project['project_id'] ?? 0) ?>" <?= $projectFilter === (int) ($project['project_id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($project['project'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ts-field-inline">
                <label>Orden</label>
                <select name="talent_sort">
                    <option value="load_desc" <?= $talentSort === 'load_desc' ? 'selected' : '' ?>>Mayor carga</option>
                    <option value="compliance_asc" <?= $talentSort === 'compliance_asc' ? 'selected' : '' ?>>Menor cumplimiento</option>
                </select>
            </div>
            <button type="submit" class="ts-btn ts-btn-primary ts-btn-sm">Aplicar</button>
        </form>
    </header>

    <div class="ts-kpi-grid">
        <article class="ts-kpi-card">
            <div class="ts-kpi-value"><?= round((float) ($executiveSummary['total'] ?? 0), 1) ?>h</div>
            <div class="ts-kpi-label">Horas registradas</div>
        </article>
        <article class="ts-kpi-card ts-kpi-success">
            <div class="ts-kpi-value"><?= round((float) ($executiveSummary['approved'] ?? 0), 1) ?>h</div>
            <div class="ts-kpi-label">Aprobadas (<?= round($approvedPercent, 0) ?>%)</div>
        </article>
        <article class="ts-kpi-card ts-kpi-danger">
            <div class="ts-kpi-value"><?= round((float) ($executiveSummary['rejected'] ?? 0), 1) ?>h</div>
            <div class="ts-kpi-label">Rechazadas</div>
        </article>
        <article class="ts-kpi-card">
            <div class="ts-kpi-value"><?= round((float) ($executiveSummary['draft'] ?? 0), 1) ?>h</div>
            <div class="ts-kpi-label">Borrador</div>
        </article>
        <article class="ts-kpi-card ts-kpi-warning">
            <div class="ts-kpi-value"><?= round((float) ($executiveSummary['pending'] ?? 0), 1) ?>h</div>
            <div class="ts-kpi-label">Pendientes</div>
        </article>
        <article class="ts-kpi-card <?= $compliancePercent >= 80 ? 'ts-kpi-success' : ($compliancePercent >= 50 ? 'ts-kpi-warning' : 'ts-kpi-danger') ?>">
            <div class="ts-kpi-value"><?= round($compliancePercent, 0) ?>%</div>
            <div class="ts-kpi-label">Cumplimiento (Cap: <?= round((float) ($executiveSummary['capacity'] ?? 0), 0) ?>h)</div>
        </article>
    </div>

    <div class="ts-analytics-grid">
        <section class="ts-analytics-panel">
            <h3>Resumen de semanas</h3>
            <p class="ts-summary-line">
                <strong>Registradas:</strong> <?= $weeksRegistered ?>
                · <strong>Aprobadas:</strong> <?= $weeksApproved ?>
                · <strong>Pendientes:</strong> <?= $weeksPending ?>
            </p>
            <?php if (!empty($approvedWeeks)): ?>
            <div class="table-wrap">
                <table class="clean-table">
                    <thead><tr><th>Semana</th><th>Rango</th><th>Horas</th><th>Estado</th><th>Aprobación</th><th>Aprobador</th></tr></thead>
                    <tbody>
                    <?php foreach ($approvedWeeks as $week):
                        $start = new DateTimeImmutable((string) ($week['week_start'] ?? 'now'));
                        $end = new DateTimeImmutable((string) ($week['week_end'] ?? 'now'));
                        $weight = (int) ($week['status_weight'] ?? 0);
                        $state = $weight >= 5 ? 'approved' : ($weight >= 4 ? 'rejected' : 'submitted');
                        $meta = $statusMap[$state] ?? $statusMap['draft'];
                    ?>
                        <tr>
                            <td><a href="<?= $basePath ?>/timesheets?week=<?= urlencode($start->format('o-\\WW')) ?>">S<?= htmlspecialchars($start->format('W')) ?></a></td>
                            <td><?= htmlspecialchars($start->format('d/m')) ?> – <?= htmlspecialchars($end->format('d/m')) ?></td>
                            <td><?= round((float) ($week['total_hours'] ?? 0), 1) ?>h</td>
                            <td><span class="ts-status-badge <?= $meta['class'] ?>"><?= htmlspecialchars($meta['label']) ?></span></td>
                            <td><?= htmlspecialchars((string) ($week['approved_at'] ?? '—')) ?></td>
                            <td><?= htmlspecialchars((string) ($week['approver_name'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <section class="ts-analytics-panel">
            <h3>Resumen del mes</h3>
            <div class="ts-month-grid">
                <div class="ts-month-item"><span>Total horas</span><strong><?= round((float) ($monthlySummary['month_total'] ?? 0), 1) ?>h</strong></div>
                <div class="ts-month-item"><span>Aprobadas</span><strong><?= round((float) ($monthlySummary['approved'] ?? 0), 1) ?>h</strong></div>
                <div class="ts-month-item"><span>Rechazadas</span><strong><?= round((float) ($monthlySummary['rejected'] ?? 0), 1) ?>h</strong></div>
                <div class="ts-month-item"><span>Borrador</span><strong><?= round((float) ($monthlySummary['draft'] ?? 0), 1) ?>h</strong></div>
                <div class="ts-month-item"><span>Capacidad</span><strong><?= round((float) ($monthlySummary['capacity'] ?? 0), 0) ?>h</strong></div>
                <div class="ts-month-item"><span>Cumplimiento</span><strong><?= (float) ($monthlySummary['compliance'] ?? 0) ?>%</strong></div>
            </div>
        </section>
    </div>

    <?php if (!empty($talentBreakdown)): ?>
    <section class="ts-analytics-panel">
        <h3>Desglose por talento</h3>
        <div class="table-wrap">
            <table class="clean-table">
                <thead><tr><th>Talento</th><th>Total</th><th>Aprobadas</th><th>Rechazadas</th><th>Borrador</th><th>Cumplimiento</th><th>Última enviada</th><th>Última aprobada</th></tr></thead>
                <tbody>
                <?php foreach ($talentBreakdown as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($row['talent_name'] ?? '')) ?></td>
                        <td><?= round((float) ($row['total_hours'] ?? 0), 1) ?>h</td>
                        <td><?= round((float) ($row['approved_hours'] ?? 0), 1) ?>h</td>
                        <td><?= round((float) ($row['rejected_hours'] ?? 0), 1) ?>h</td>
                        <td><?= round((float) ($row['draft_hours'] ?? 0), 1) ?>h</td>
                        <td><?= round((float) ($row['compliance_percent'] ?? 0), 0) ?>%</td>
                        <td><?= htmlspecialchars((string) ($row['last_week_submitted'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['last_week_approved'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <div class="ts-analytics-grid">
        <?php if (!empty($projectBreakdown)): ?>
        <section class="ts-analytics-panel">
            <h3>Horas por proyecto</h3>
            <div class="ts-breakdown-list">
                <?php foreach (array_slice($projectBreakdown, 0, 10) as $row): ?>
                <div class="ts-breakdown-item">
                    <span class="ts-breakdown-name"><?= htmlspecialchars((string) ($row['project'] ?? '')) ?></span>
                    <span class="ts-breakdown-value"><?= round((float) ($row['total_hours'] ?? 0), 1) ?>h</span>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($activityTypeBreakdown)): ?>
        <section class="ts-analytics-panel">
            <h3>Distribución por tipo de actividad</h3>
            <div class="ts-breakdown-list">
                <?php foreach (array_slice($activityTypeBreakdown, 0, 8) as $row): ?>
                <div class="ts-breakdown-item">
                    <span class="ts-breakdown-name"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) ($row['activity_type'] ?? 'sin_clasificar')))) ?></span>
                    <span class="ts-breakdown-value"><?= round((float) ($row['total_hours'] ?? 0), 1) ?>h <small>(<?= round((float) ($row['percent'] ?? 0), 0) ?>%)</small></span>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <?php if (!empty($phaseBreakdown)): ?>
    <section class="ts-analytics-panel">
        <h3>Horas por fase</h3>
        <div class="ts-breakdown-list">
            <?php foreach (array_slice($phaseBreakdown, 0, 8) as $row): ?>
            <div class="ts-breakdown-item">
                <span class="ts-breakdown-name"><?= htmlspecialchars((string) ($row['phase_name'] ?? 'sin_fase')) ?> / <?= htmlspecialchars((string) ($row['subphase_name'] ?? 'sin_subfase')) ?></span>
                <span class="ts-breakdown-value"><?= round((float) ($row['total_hours'] ?? 0), 1) ?>h</span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($weeksHistory)): ?>
    <section class="ts-analytics-panel">
        <h3>Historial de semanas</h3>
        <div class="ts-weeks-history">
            <?php foreach (array_slice($weeksHistory, 0, 12) as $week):
                $start = new DateTimeImmutable((string) ($week['week_start'] ?? 'now'));
                $end = new DateTimeImmutable((string) ($week['week_end'] ?? 'now'));
                $sw = (int) ($week['status_weight'] ?? 2);
                $status = $sw >= 5 ? 'approved' : ($sw >= 4 ? 'rejected' : ($sw >= 3 ? 'submitted' : 'draft'));
                $meta = $statusMap[$status] ?? $statusMap['draft'];
            ?>
            <a class="ts-week-history-card" href="<?= $basePath ?>/timesheets?week=<?= urlencode($start->format('o-\\WW')) ?>">
                <div class="ts-whc-header">
                    <strong>S<?= htmlspecialchars($start->format('W')) ?></strong>
                    <span class="ts-status-badge <?= $meta['class'] ?>"><?= htmlspecialchars($meta['label']) ?></span>
                </div>
                <div class="ts-whc-range"><?= htmlspecialchars($start->format('d/m')) ?> – <?= htmlspecialchars($end->format('d/m')) ?></div>
                <div class="ts-whc-hours"><?= round((float) ($week['total_hours'] ?? 0), 1) ?>h</div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <div style="text-align:right;margin-top:8px">
        <button class="ts-btn ts-btn-outline ts-btn-sm" onclick="window.print()">Exportar informe</button>
    </div>
</section>

<style>
.ts-module{display:flex;flex-direction:column;gap:0}
.ts-view-tabs{display:flex;gap:2px;border-bottom:2px solid var(--border);margin-bottom:16px}
.ts-tab{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;text-decoration:none;color:var(--text-secondary);font-weight:600;font-size:14px;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .2s}
.ts-tab:hover{color:var(--text-primary);border-color:color-mix(in srgb,var(--primary) 40%,transparent)}
.ts-tab.active{color:var(--primary);border-color:var(--primary);font-weight:700}
.ts-tab svg{opacity:.7}.ts-tab.active svg{opacity:1}

.ts-analytics-header{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px 18px;margin-bottom:16px}
.ts-analytics-header h3{margin:0 0 12px;font-size:18px;font-weight:800}
.ts-analytics-filters{display:flex;gap:10px;align-items:end;flex-wrap:wrap}
.ts-field-inline{display:flex;flex-direction:column;gap:3px}
.ts-field-inline label{font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.03em}
.ts-field-inline input,.ts-field-inline select{padding:6px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--surface);color:var(--text-primary)}
.ts-btn{padding:8px 14px;border-radius:10px;border:1px solid var(--border);cursor:pointer;font-weight:600;font-size:13px;background:var(--surface);color:var(--text-primary);transition:all .15s;white-space:nowrap}
.ts-btn:hover{transform:translateY(-1px)}
.ts-btn-primary{background:var(--primary);color:#fff;border-color:var(--primary)}
.ts-btn-outline{background:transparent;border-color:var(--border)}.ts-btn-outline:hover{border-color:var(--primary);color:var(--primary)}
.ts-btn-sm{font-size:12px;padding:6px 10px}
.ts-status-badge{padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;display:inline-block}
.ts-approved{background:#dcfce7;color:#166534}.ts-rejected{background:#fee2e2;color:#991b1b}.ts-submitted{background:#fef3c7;color:#92400e}.ts-draft{background:#f1f5f9;color:#475569}.ts-partial{background:#dbeafe;color:#1e40af}

.ts-kpi-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin-bottom:16px}
.ts-kpi-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px;display:flex;flex-direction:column;gap:4px}
.ts-kpi-card.ts-kpi-success{border-top:3px solid #16a34a}.ts-kpi-card.ts-kpi-danger{border-top:3px solid #dc2626}.ts-kpi-card.ts-kpi-warning{border-top:3px solid #eab308}
.ts-kpi-value{font-size:22px;font-weight:800;color:var(--text-primary)}
.ts-kpi-label{font-size:11px;color:var(--text-secondary);font-weight:600;text-transform:uppercase;letter-spacing:.03em}

.ts-analytics-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.ts-analytics-panel{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:16px}
.ts-analytics-panel h3{margin:0 0 14px;font-size:16px;font-weight:800}
.ts-summary-line{font-size:13px;color:var(--text-secondary);margin:0 0 12px}
.ts-month-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.ts-month-item{display:flex;flex-direction:column;gap:2px;padding:10px;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--surface) 96%,var(--background) 4%)}
.ts-month-item span{font-size:11px;color:var(--text-secondary);font-weight:600;text-transform:uppercase}
.ts-month-item strong{font-size:18px;color:var(--text-primary)}

.ts-breakdown-list{display:flex;flex-direction:column;gap:6px}
.ts-breakdown-item{display:flex;justify-content:space-between;align-items:center;padding:8px 10px;border-radius:8px;background:color-mix(in srgb,var(--surface) 95%,var(--background) 5%);border:1px solid color-mix(in srgb,var(--border) 50%,transparent)}
.ts-breakdown-name{font-size:13px;font-weight:600;color:var(--text-primary)}
.ts-breakdown-value{font-size:13px;font-weight:700;color:var(--primary)}
.ts-breakdown-value small{color:var(--text-secondary);font-weight:500}

.ts-weeks-history{display:flex;gap:8px;overflow-x:auto;padding-bottom:6px}
.ts-week-history-card{min-width:120px;padding:10px;border:1px solid var(--border);border-radius:10px;text-decoration:none;color:inherit;display:flex;flex-direction:column;gap:4px;transition:all .15s;background:var(--surface)}
.ts-week-history-card:hover{border-color:var(--primary);box-shadow:0 4px 12px color-mix(in srgb,var(--primary) 15%,transparent)}
.ts-whc-header{display:flex;justify-content:space-between;align-items:center;gap:6px}
.ts-whc-range{font-size:11px;color:var(--text-secondary)}
.ts-whc-hours{font-size:16px;font-weight:800;color:var(--text-primary)}

@media(max-width:1024px){.ts-kpi-grid{grid-template-columns:repeat(3,1fr)}.ts-analytics-grid{grid-template-columns:1fr}}
@media(max-width:768px){.ts-kpi-grid{grid-template-columns:repeat(2,1fr)}.ts-month-grid{grid-template-columns:repeat(2,1fr)}.ts-analytics-filters{flex-direction:column}}
</style>

<script>
(() => {
    const periodSelect = document.getElementById('ts-period-select');
    const rangeInputs = document.querySelectorAll('[data-range-input]');
    periodSelect?.addEventListener('change', () => {
        const custom = periodSelect.value === 'custom';
        rangeInputs.forEach(el => { el.disabled = !custom; });
    });
})();
</script>
